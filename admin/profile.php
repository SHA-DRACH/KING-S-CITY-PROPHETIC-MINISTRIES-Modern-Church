<?php
/** The signed-in user's own profile. Role, departments and permissions are read-only here. */
require __DIR__ . '/partials/init.php';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'phone' => post('phone') ?: null, 'title' => post('title') ?: null];
    $errors = [];
    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $k => $l) {
        if ($data[$k] === '' || mb_strlen($data[$k]) > 80) {
            $errors[$k] = "$l is required.";
        }
    }
    try {
        if ($avatar = Upload::fromField('avatar', 'image', 'profiles', 'profile')) {
            $data['avatar'] = $avatar;
        }
    } catch (UploadException $e) {
        $errors['avatar'] = $e->getMessage();
    }
    if ($errors) {
        json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
    }
    DB::update('users', $data, 'id = ?', [$me['id']]);
    if (isset($data['avatar'])) {
        Upload::delete($me['avatar']);
    }
    log_activity('update', 'profile', 'Updated own profile');
    json_response(['ok' => true, 'message' => 'Profile updated.', 'reload' => true]);
}

$perms = is_super_admin() ? ['All permissions (Super Administrator)'] : array_column(DB::all(
    'SELECT label FROM permissions WHERE name IN (' . implode(',', array_fill(0, max(1, count(user_permission_names((int) $me['id']))), '?')) . ') ORDER BY module, id',
    array_keys(user_permission_names((int) $me['id'])) ?: ['']
), 'label');
$depts = DB::all('SELECT d.name, du.is_head FROM department_users du JOIN departments d ON d.id = du.department_id WHERE du.user_id = ?', [$me['id']]);

admin_header('My Profile', 'profile');
?>
<div class="page-head"><div><h1 class="page-title"><i class="fa-solid fa-id-badge"></i> My Profile</h1></div>
<a class="btn btn-light" href="<?= e(url('auth/change-password.php')) ?>"><i class="fa-solid fa-lock"></i> Change Password</a></div>
<div class="row g-4">
  <div class="col-lg-7">
    <form class="panel" data-ajax method="post" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
      <div class="d-flex align-items-center gap-3 mb-4">
        <?php if ($me['avatar']): ?><img src="<?= e(media_url($me['avatar'])) ?>" alt="" class="avatar-lg"><?php else: ?><span class="avatar-lg"><?= e(initials($me['full_name'])) ?></span><?php endif; ?>
        <div><h2 class="h5 mb-0"><?= e($me['full_name']) ?></h2><div class="text-muted small"><?= e($me['email']) ?></div><span class="role-pill mt-1"><?= e($me['role_name']) ?></span></div>
      </div>
      <div class="row g-3">
        <?= form_field('first_name', ['label' => 'First Name', 'required' => true, 'col' => 6], $me['first_name']) ?>
        <?= form_field('last_name', ['label' => 'Last Name', 'required' => true, 'col' => 6], $me['last_name']) ?>
        <?= form_field('title', ['label' => 'Display Title', 'col' => 6], $me['title']) ?>
        <?= form_field('phone', ['label' => 'Phone', 'type' => 'tel', 'col' => 6], $me['phone']) ?>
        <?= form_field('avatar', ['label' => 'Profile Photo', 'type' => 'file', 'upload' => 'image']) ?>
      </div>
      <p class="small text-muted mt-3"><i class="fa-solid fa-circle-info"></i> Your email, role and permissions can only be changed by an administrator.</p>
      <div class="text-end"><button class="btn btn-gold" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> Save Profile</button></div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="panel mb-4"><h2 class="panel-title">Account</h2>
      <dl class="info-list">
        <dt>Role</dt><dd><?= e($me['role_name']) ?></dd>
        <dt>Departments</dt><dd><?= $depts ? e(implode(', ', array_map(fn($d) => $d['name'] . ($d['is_head'] ? ' (Head)' : ''), $depts))) : '—' ?></dd>
        <dt>Last Login</dt><dd><?= e($me['last_login_at'] ? format_date($me['last_login_at'], 'M j, Y g:i A') . ' from ' . $me['last_login_ip'] : '—') ?></dd>
        <dt>Member Since</dt><dd><?= e(format_date($me['created_at'])) ?></dd>
      </dl></div>
    <div class="panel"><h2 class="panel-title">What You Can Do</h2>
      <ul class="perm-list"><?php foreach ($perms as $label): ?><li><i class="fa-solid fa-check"></i> <?= e($label) ?></li><?php endforeach; ?></ul></div>
  </div>
</div>
<?php
admin_footer();
