<?php
/**
 * Reusable helpers: config, URLs, escaping, settings, CSRF, flash/toast
 * messages, responses, formatting, rate limiting, activity logging.
 */

// ---------------------------------------------------------------------
//  Config & URLs
// ---------------------------------------------------------------------
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['__config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Base URL of the app, e.g. "http://localhost/kings-city" (no trailing slash). */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = rtrim((string) config('app.base_url', ''), '/');
    if ($configured !== '') {
        return $base = $configured;
    }
    if (PHP_SAPI === 'cli') {
        return $base = '';
    }
    $scheme = is_https() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $path = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
    return $base = $scheme . '://' . $host . rtrim($path, '/');
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** Asset URL with cache-busting version; serves .min files in production when present. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    if (!config('app.debug') && preg_match('/\.(css|js)$/', $path)) {
        $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
        if (is_file(APP_ROOT . '/assets/' . $min)) {
            $path = $min;
        }
    }
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . $path) . '?v=' . $v;
}

/** URL for a stored media path (uploads/…, assets/… or an absolute URL), with a branded fallback. */
function media_url(?string $path, string $fallback = 'assets/images/placeholders/worship.svg'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return url($fallback);
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url($path);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function current_path(): string
{
    return basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php') ?: 'index.php';
}

function canonical_url(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $host = preg_replace('#^(https?://[^/]+).*$#', '$1', base_url());
    $query = [];
    foreach (['slug', 'page', 'category'] as $keep) {
        if (!empty($_GET[$keep]) && is_scalar($_GET[$keep])) {
            $query[$keep] = $_GET[$keep];
        }
    }
    return $host . $path . ($query ? '?' . http_build_query($query) : '');
}

function redirect(string $to): never
{
    // Absolute URLs and host-relative paths ("/kings-city/…") pass through untouched.
    if (!preg_match('#^(https?://|/)#', $to)) {
        $to = url($to);
    }
    header('Location: ' . $to);
    exit;
}

/** Only allow redirects back into this application. */
function safe_next(?string $next, string $default = 'admin/dashboard.php'): string
{
    $next = (string) $next;
    if ($next !== '' && str_starts_with($next, '/') && !str_starts_with($next, '//') && !str_contains($next, "\n")) {
        return $next;
    }
    return url($default);
}

// ---------------------------------------------------------------------
//  Escaping & input
// ---------------------------------------------------------------------
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape and keep line breaks. */
function nl2br_e(?string $text): string
{
    return nl2br(e($text ?? ''));
}

/** Plain text → escaped paragraphs. */
function paragraphs(?string $text): string
{
    $parts = preg_split("/\R{2,}/", trim(str_replace('\n', "\n", (string) $text)));
    return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', array_filter($parts, fn($p) => trim($p) !== '')));
}

function input(string $key, mixed $default = ''): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function query_int(string $key, int $default = 0): int
{
    return filter_var($_GET[$key] ?? $default, FILTER_VALIDATE_INT) ?: $default;
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function valid_url(string $url): bool
{
    return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url);
}

/**
 * Allow a small, safe subset of HTML for admin-edited page content.
 * Strips scripts, event handlers, styles and unsafe URLs.
 */
function sanitize_html(?string $html): string
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a', 'hr'];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) {
        return e(strip_tags($html));
    }
    $walk = function (DOMNode $node) use (&$walk, $allowed, $doc) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    $keep = $tag === 'a' && $name === 'href' && preg_match('#^(https?:|mailto:|tel:|/|\#)#i', trim($attr->value));
                    if (!$keep) {
                        $child->removeAttribute($attr->name);
                    }
                }
                if ($tag === 'a') {
                    $child->setAttribute('rel', 'noopener');
                }
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

// ---------------------------------------------------------------------
//  Settings (loaded once per request from MySQL)
// ---------------------------------------------------------------------
function settings(): array
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = array_column(DB::all('SELECT setting_key, setting_value FROM church_settings'), 'setting_value', 'setting_key');
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $v = settings()[$key] ?? null;
    return ($v === null || $v === '') ? $default : (string) $v;
}

function social_links(): array
{
    static $cache = null;
    return $cache ??= DB::all('SELECT * FROM social_links WHERE is_active = 1 ORDER BY sort_order, id');
}

function service_times(): array
{
    static $cache = null;
    return $cache ??= DB::all('SELECT * FROM service_times WHERE is_active = 1 ORDER BY sort_order, id');
}

function church_address_line(): string
{
    return implode(', ', array_filter([setting('address'), setting('city'), setting('country')]));
}

// ---------------------------------------------------------------------
//  Session, CSRF, flash messages
// ---------------------------------------------------------------------
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(config('security.session_name', 'KCPM_SESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || empty($_SESSION['_csrf']) || !hash_equals($_SESSION['_csrf'], $token)) {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], 403);
        }
        render_error_page(403, 'Session Expired', 'Your session expired or the form was already submitted. Please go back, refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function set_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old(string $key, string $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
    return isset($old[$key]) && is_string($old[$key]) ? $old[$key] : $default;
}

// ---------------------------------------------------------------------
//  Responses
// ---------------------------------------------------------------------
function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_response(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Unified result for form handlers: JSON (toast) for AJAX requests,
 * otherwise a flash message + redirect.
 */
function respond(bool $ok, string $message, ?string $redirect = null, array $extra = []): never
{
    if (is_ajax()) {
        json_response(array_merge(['ok' => $ok, 'message' => $message, 'redirect' => $redirect ? url($redirect) : null], $extra), $ok ? 200 : 422);
    }
    flash($ok ? 'success' : 'error', $message);
    redirect($extra['redirect'] ?? $redirect ?? ($_SERVER['HTTP_REFERER'] ?? url()));
}

function render_error_page(int $code, string $title, string $message): never
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    $home = function_exists('url') ? url() : '/';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#071B35;color:#fff;font-family:system-ui,sans-serif;text-align:center;padding:24px}'
        . '.c{max-width:520px}.n{font-size:72px;font-weight:800;color:#F4C542;margin:0}h1{margin:.2em 0}p{color:#c8d3e6;line-height:1.6}a{display:inline-block;margin-top:16px;background:#F4C542;color:#071B35;padding:12px 26px;border-radius:40px;font-weight:700;text-decoration:none}</style></head>'
        . '<body><div class="c"><p class="n">' . $code . '</p><h1>' . e($title) . '</h1><p>' . e($message) . '</p><a href="' . e($home) . '">Return Home</a></div></body></html>';
    exit;
}

function abort(int $code = 404, string $message = 'The page you are looking for could not be found.'): never
{
    $titles = [403 => 'Access Denied', 404 => 'Page Not Found', 405 => 'Method Not Allowed'];
    if (is_ajax()) {
        json_response(['ok' => false, 'message' => $message], $code);
    }
    render_error_page($code, $titles[$code] ?? 'Error', $message);
}

function send_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header_remove('X-Powered-By');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---------------------------------------------------------------------
//  Formatting
// ---------------------------------------------------------------------
function slugify(string $text): string
{
    $text = str_replace(["'", '’'], '', $text); // "King's" → "kings"
    $text = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text), '-'));
    return $text !== '' ? $text : bin2hex(random_bytes(4));
}

function unique_slug(string $table, string $source, ?int $ignoreId = null): string
{
    $base = substr(slugify($source), 0, 180);
    $slug = $base;
    $i = 2;
    while (DB::value("SELECT COUNT(*) FROM `$table` WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : ''), $ignoreId ? [$slug, $ignoreId] : [$slug])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function format_date(?string $date, string $format = 'M j, Y'): string
{
    return $date ? date($format, strtotime($date)) : '';
}

function format_time(?string $time): string
{
    return $time ? date('g:i A', strtotime($time)) : '';
}

function time_range(?string $start, ?string $end): string
{
    return trim(format_time($start) . ($end ? ' – ' . format_time($end) : ''));
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    return match (true) {
        $diff < 60      => 'just now',
        $diff < 3600    => floor($diff / 60) . ' min ago',
        $diff < 86400   => floor($diff / 3600) . ' hours ago',
        $diff < 604800  => floor($diff / 86400) . ' days ago',
        default         => format_date($datetime),
    };
}

function excerpt(?string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . '…' : $text;
}

function money(float|string $amount, string $currency = 'USD'): string
{
    $symbol = ['USD' => '$', 'LRD' => 'L$'][$currency] ?? $currency . ' ';
    return $symbol . number_format((float) $amount, 2);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr(end($parts) ?: '', 0, 1));
}

/** Convert a YouTube / Facebook URL into an embeddable player URL. */
function video_embed_url(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|live/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
    }
    if (str_contains($url, 'facebook.com')) {
        return 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($url) . '&show_text=false';
    }
    return null;
}

function youtube_thumb(?string $url): ?string
{
    if ($url && preg_match('~(?:v=|embed/|live/|shorts/|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
        return 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg';
    }
    return null;
}

// ---------------------------------------------------------------------
//  Pagination
// ---------------------------------------------------------------------
function paginate(int $total, int $perPage, ?int $page = null): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, $page ?? query_int('page', 1)));
    return ['page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function pagination_links(array $p, string $class = ''): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $params = $_GET;
    $link = function (int $n) use ($params) {
        $params['page'] = $n;
        return '?' . http_build_query($params);
    };
    $html = '<nav aria-label="Pagination" class="' . e($class) . '"><ul class="pagination">';
    $html .= '<li class="page-item' . ($p['page'] <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(max(1, $p['page'] - 1))) . '" aria-label="Previous">&lsaquo;</a></li>';
    for ($i = max(1, $p['page'] - 2); $i <= min($p['pages'], $p['page'] + 2); $i++) {
        $html .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="' . e($link($i)) . '"' . ($i === $p['page'] ? ' aria-current="page"' : '') . '>' . $i . '</a></li>';
    }
    $html .= '<li class="page-item' . ($p['page'] >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(min($p['pages'], $p['page'] + 1))) . '" aria-label="Next">&rsaquo;</a></li>';
    return $html . '</ul></nav>';
}

// ---------------------------------------------------------------------
//  Security helpers: IP, rate limiting, activity log
// ---------------------------------------------------------------------
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** True when $key has reached $max hits within the last $minutes. */
function rate_limited(string $key, int $max, int $minutes): bool
{
    $count = (int) DB::value(
        'SELECT COUNT(*) FROM rate_limits WHERE rl_key = ? AND created_at > (NOW() - INTERVAL ? MINUTE)',
        [$key, $minutes]
    );
    return $count >= $max;
}

function rate_hit(string $key): void
{
    DB::insert('rate_limits', ['rl_key' => substr($key, 0, 190)]);
    if (random_int(1, 50) === 1) {
        DB::query('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function rate_clear(string $key): void
{
    DB::delete('rate_limits', 'rl_key = ?', [$key]);
}

/** Record an administrative action for accountability. */
function log_activity(string $action, string $module, string $description = '', ?int $userId = null): void
{
    try {
        DB::insert('activity_logs', [
            'user_id'     => $userId ?? (current_user()['id'] ?? null),
            'action'      => substr($action, 0, 60),
            'module'      => substr($module, 0, 60),
            'description' => mb_substr($description, 0, 500),
            'ip_address'  => client_ip(),
            'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('[KCPM] activity log failed: ' . $e->getMessage());
    }
}

/** Basic spam trap for public forms: hidden "website" field must stay empty. */
function honeypot_field(): string
{
    return '<div class="hp-field" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
}

function honeypot_tripped(): bool
{
    return !empty($_POST['website']);
}
