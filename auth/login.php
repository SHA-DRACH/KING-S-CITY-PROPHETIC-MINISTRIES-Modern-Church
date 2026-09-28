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

auth_layout_start('Sign In', 'Welcome back', 'Sign in to the church management dashboard.');
?>
<form method="post" action="<?= e(url('admin/login.php')) ?>" data-once novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e((string) ($_GET['next'] ?? '')) ?>">
  <div class="mb-3">
    <label class="form-label" for="email">Email address</label>
    <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" autofocus>
  </div>
  <?= password_input('password', 'Password') ?>
  <div class="d-flex justify-content-end mb-4"><a class="small" href="<?= e(url('auth/forgot-password.php')) ?>">Forgot password?</a></div>
  <button type="submit" class="btn btn-gold btn-lg w-100"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
</form>
<p class="small text-muted mt-4 mb-0"><i class="fa-solid fa-shield-halved"></i> Protected area. Repeated failed attempts are temporarily blocked and every sign-in is logged.</p>
<?php
auth_layout_end();
