<?php
define('ADMIN_AREA', true);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/layout.php';

$devLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(post('email'));
    $key = 'reset:' . client_ip();
    if (rate_limited($key, 5, 60)) {
        flash('error', 'Too many reset requests. Please try again later.');
        redirect('auth/forgot-password.php');
    }
    rate_hit($key);

    $user = valid_email($email) ? DB::one("SELECT id, first_name, email FROM users WHERE email = ? AND status = 'active'", [$email]) : null;
    if ($user) {
        $token = create_password_reset((int) $user['id']);
        $link = url('auth/reset-password.php?token=' . $token);
        $sent = send_mail($user['email'], 'Reset your password',
            "Hello {$user['first_name']},\n\nUse the link below to choose a new password. It expires in " . config('security.reset_token_minutes') . " minutes.\n\n$link\n\nIf you did not request this, you can ignore this email.\n\n" . setting('church_name'));
        log_activity('password_reset_request', 'auth', 'Password reset requested', (int) $user['id']);
        // Local development without a mail server: show the link on screen.
        if (!$sent && config('app.debug')) {
            $devLink = $link;
        }
    }
    // Same message whether or not the account exists (no account enumeration).
    flash('success', 'If an account exists for that email, a reset link has been sent.');
}

auth_layout_start('Forgot Password', 'Forgot your password?', 'Enter your email and we will send you a secure reset link.');
if ($devLink): ?>
  <div class="alert alert-warning small"><strong>Development mode:</strong> no mail server is configured, so here is the reset link:<br><a href="<?= e($devLink) ?>" class="text-break">Reset password</a></div>
<?php endif; ?>
<form method="post" data-once novalidate>
  <?= csrf_field() ?>
  <?= auth_input('email', 'Email address', 'fa-envelope', 'email', 'email', '', true) ?>
  <button type="submit" class="auth-btn mt-2"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Reset Link</button>
</form>
<p class="auth-row justify-content-center mt-3 mb-0"><a href="<?= e(url('admin/login.php')) ?>">Back to sign in</a></p>
<?php
auth_layout_end();
