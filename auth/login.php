<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/layout.php';

if (current_user()) {
    redirect('admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post('email');
    [$ok, $message] = auth_attempt($email, (string) ($_POST['password'] ?? ''));
    if ($ok) {
        flash('success', $message);
        redirect(safe_next($_POST['next'] ?? ''));
    }
    flash('error', $message);
    set_old(['email' => $email]);
    redirect('admin/login.php' . (!empty($_POST['next']) ? '?next=' . rawurlencode((string) $_POST['next']) : ''));
}

auth_layout_start('Sign In', 'Staff Sign In', 'Welcome back. Sign in to manage the ministry.');
?>
<form method="post" action="<?= e(url('admin/login.php')) ?>" data-once novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e((string) ($_GET['next'] ?? '')) ?>">
  <?= auth_input('email', 'Email address', 'fa-envelope', 'email', 'username', old('email'), true) ?>
  <?= password_input('password', 'Password') ?>
  <div class="auth-row"><a href="<?= e(url('auth/forgot-password.php')) ?>">Forgot password?</a></div>
  <button type="submit" class="auth-btn"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> Sign In</button>
</form>
<?php
auth_layout_end();
