<?php
/**
 * Authentication: login with throttling, session hardening, logout,
 * password resets and page guards.
 */

/** The signed-in user (with role info), or null. Validated once per request. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid) {
        return null;
    }

    // Idle timeout
    $timeout = max(5, (int) setting('session_timeout_minutes', '30')) * 60;
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout) {
        auth_logout(false);
        flash('info', 'You were signed out after a period of inactivity.');
        return null;
    }
    // Bind the session to the browser that created it
    if (($_SESSION['ua_hash'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
        auth_logout(false);
        return null;
    }

    $row = DB::one(
        'SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.level AS role_level, r.is_super
           FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
        [$uid]
    );
    if (!$row || $row['status'] !== 'active') {
        auth_logout(false);
        return null;
    }
    unset($row['password_hash']);
    $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
    $_SESSION['last_activity'] = time();
    return $user = $row;
}

function user_id(): ?int
{
    return current_user()['id'] ?? null;
}

/**
 * Attempt a login. Returns [bool success, string message].
 * Failed attempts are throttled per email and per IP.
 */
function auth_attempt(string $email, string $password): array
{
    $email = strtolower(trim($email));
    $max = max(3, (int) setting('max_login_attempts', '5'));
    $window = (int) config('security.login_window_min', 15);
    $keyEmail = 'login:' . $email;
    $keyIp = 'login-ip:' . client_ip();

    if (rate_limited($keyEmail, $max, $window) || rate_limited($keyIp, $max * 3, $window)) {
        return [false, "Too many failed attempts. Please wait $window minutes and try again."];
    }

    $user = DB::one('SELECT * FROM users WHERE email = ?', [$email]);
    // Always run password_verify to keep response timing uniform.
    $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $valid = password_verify($password, $hash);

    if (!$user || !$valid) {
        rate_hit($keyEmail);
        rate_hit($keyIp);
        log_activity('login_failed', 'auth', 'Failed login for ' . $email, $user['id'] ?? null);
        return [false, 'Invalid email or password.'];
    }
    if ($user['status'] !== 'active') {
        return [false, 'This account has been disabled. Please contact the church administrator.'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ua_hash'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    unset($_SESSION['_csrf']);

    DB::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()], 'id = ?', [$user['id']]);
    rate_clear($keyEmail);
    log_activity('login', 'auth', $user['first_name'] . ' ' . $user['last_name'] . ' signed in', (int) $user['id']);
    return [true, 'Welcome back, ' . $user['first_name'] . '!'];
}

function auth_logout(bool $log = true): void
{
    if ($log && !empty($_SESSION['uid'])) {
        log_activity('logout', 'auth', 'User signed out', (int) $_SESSION['uid']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        session_start();
    }
}

/** Guard for every protected page. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Please sign in again.'], 401);
        }
        redirect('admin/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    // Force a password change for new / reset accounts.
    if ((int) $user['must_change_password'] === 1 && !defined('ALLOW_PASSWORD_CHANGE_PAGE')) {
        redirect('auth/change-password.php');
    }
    return $user;
}

function password_policy_error(string $password, string $confirm): ?string
{
    $min = (int) config('security.password_min_length', 8);
    if (strlen($password) < $min) {
        return "Password must be at least $min characters.";
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain both letters and numbers.';
    }
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }
    return null;
}

/** Create a single-use reset token (stored hashed). Returns the plain token. */
function create_password_reset(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    DB::delete('password_resets', 'user_id = ? AND used_at IS NULL', [$userId]);
    DB::insert('password_resets', [
        'user_id'    => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + 60 * (int) config('security.reset_token_minutes', 60)),
    ]);
    return $token;
}

function find_password_reset(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return DB::one(
        'SELECT pr.*, u.email, u.first_name FROM password_resets pr JOIN users u ON u.id = pr.user_id
          WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.status = "active"',
        [hash('sha256', $token)]
    );
}

function send_mail(string $to, string $subject, string $body): bool
{
    $from = config('mail.from_email');
    $headers = [
        'From: ' . config('mail.from_name') . " <$from>",
        'Content-Type: text/plain; charset=UTF-8',
    ];
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}
