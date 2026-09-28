<?php
define('ADMIN_AREA', true);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/layout.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$reset = find_password_reset($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset) {
    $password = (string) ($_POST['password'] ?? '');
    if ($err = password_policy_error($password, (string) ($_POST['password_confirm'] ?? ''))) {
        flash('error', $err);
        redirect('auth/reset-password.php?token=' . rawurlencode($token));
    }
    DB::transaction(function () use ($reset, $password) {
        DB::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => 0], 'id = ?', [$reset['user_id']]);
        DB::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = ?', [$reset['id']]);
    });
    rate_clear('login:' . $reset['email']);
    log_activity('password_reset', 'auth', 'Password reset via email link', (int) $reset['user_id']);
    flash('success', 'Your password has been reset. Please sign in.');
    redirect('admin/login.php');
}

auth_layout_start('Reset Password', 'Choose a new password', $reset ? 'Hello ' . $reset['first_name'] . ', enter your new password below.' : '');
if (!$reset): ?>
  <div class="alert alert-danger">This reset link is invalid or has expired.</div>
  <a class="auth-btn" href="<?= e(url('auth/forgot-password.php')) ?>">Request a new link</a>
<?php else: ?>
<form method="post" data-once novalidate>
  <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
  <?= password_input('password', 'New password', 'new-password') ?>
  <?= password_input('password_confirm', 'Confirm new password', 'new-password') ?>
  <p class="small text-muted">At least <?= (int) config('security.password_min_length') ?> characters, including letters and numbers.</p>
  <button type="submit" class="auth-btn">Reset Password</button>
</form>
<?php endif;
auth_layout_end();
