<?php
define('ADMIN_AREA', true);
define('ALLOW_PASSWORD_CHANGE_PAGE', true);
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/layout.php';

$user = require_login();
$forced = (int) $user['must_change_password'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hash = (string) DB::value('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    $new = (string) ($_POST['password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
        flash('error', 'Your current password is incorrect.');
    } elseif ($err = password_policy_error($new, (string) ($_POST['password_confirm'] ?? ''))) {
        flash('error', $err);
    } elseif (password_verify($new, $hash)) {
        flash('error', 'Choose a password different from your current one.');
    } else {
        DB::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0], 'id = ?', [$user['id']]);
        session_regenerate_id(true);
        log_activity('password_change', 'auth', 'Changed own password');
        flash('success', 'Password changed successfully.');
        redirect('admin/dashboard.php');
    }
    redirect('auth/change-password.php');
}

auth_layout_start('Change Password', $forced ? 'Set your own password' : 'Change password',
    $forced ? 'For your security, please replace the temporary password before continuing.' : 'Update the password for ' . $user['email'] . '.');
?>
<form method="post" data-once novalidate>
  <?= csrf_field() ?>
  <?= password_input('current_password', $forced ? 'Temporary password' : 'Current password') ?>
  <?= password_input('password', 'New password', 'new-password') ?>
  <?= password_input('password_confirm', 'Confirm new password', 'new-password') ?>
  <p class="small text-muted">At least <?= (int) config('security.password_min_length') ?> characters, including letters and numbers.</p>
  <button type="submit" class="auth-btn"><i class="fa-solid fa-lock" aria-hidden="true"></i> Save Password</button>
</form>
<?php if (!$forced): ?><p class="auth-row justify-content-center mt-3 mb-0"><a href="<?= e(url('admin/dashboard.php')) ?>">Back to dashboard</a></p>
<?php else: ?><form method="post" action="<?= e(url('auth/logout.php')) ?>" class="text-center mt-4"><?= csrf_field() ?><button class="btn btn-link btn-sm text-muted">Sign out</button></form><?php endif;
auth_layout_end();
