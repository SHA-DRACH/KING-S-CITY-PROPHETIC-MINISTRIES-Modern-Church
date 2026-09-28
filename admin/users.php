<?php
/**
 * User management: accounts, roles, departments and direct permission overrides.
 *
 * Anti-escalation rules (all enforced here, server-side):
 *  - you can only manage users whose role is BELOW your own level (Super Admin excepted)
 *  - you can only assign roles below your own level; nobody but a Super Admin assigns Super Admin
 *  - you can only grant permissions you hold yourself, never critical ones (Super Admin excepted)
 *  - nobody edits their own role/permissions here (use Profile)
 */
require __DIR__ . '/partials/init.php';
require_permission('users.view');

$action = $_POST['action'] ?? $_GET['action'] ?? 'index';

function load_manageable_user(int $id): array
{
    $u = User::withRole($id);
    if (!$u) {
        abort(404, 'User not found.');
    }
    if (!can_manage_user($u)) {
        abort(403, 'You cannot manage this account (same or higher privilege level).');
    }
    return $u;
}

// ---------------------------------------------------------------------
//  POST actions
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'save':
            $id = (int) ($_POST['id'] ?? 0);
            $target = $id ? load_manageable_user($id) : null;
            require_permission($target ? 'users.edit' : 'users.create');

            $data = [
                'first_name' => post('first_name'),
                'last_name'  => post('last_name'),
                'email'      => strtolower(post('email')),
                'phone'      => post('phone') ?: null,
                'title'      => post('title') ?: null,
                'role_id'    => (int) post('role_id'),
                'status'     => post('status') === 'disabled' ? 'disabled' : 'active',
            ];
            $errors = [];
            foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $k => $label) {
                if ($data[$k] === '' || mb_strlen($data[$k]) > 80) {
                    $errors[$k] = "$label is required (max 80 characters).";
                }
            }
            if (!valid_email($data['email'])) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif (DB::value('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$data['email'], $id])) {
                $errors['email'] = 'Another account already uses this email.';
            }
            if (!role_assignable($data['role_id'])) {
                $errors['role_id'] = 'You are not allowed to assign this role.';
            }
            $password = (string) ($_POST['password'] ?? '');
            if (!$target || $password !== '') {
                if ($password === '' && !$target) {
                    $errors['password'] = 'Set an initial password.';
                } elseif ($err = password_policy_error($password, (string) ($_POST['password_confirm'] ?? ''))) {
                    $errors['password'] = $err;
                }
            }
            if ($errors) {
                json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
            }

            $id = DB::transaction(function () use ($target, $data, $password, $id) {
                if ($password !== '') {
                    $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $data['must_change_password'] = 1; // the user chooses their own password at first sign-in
                }
                if ($target) {
                    DB::update('users', $data, 'id = ?', [$id]);
                } else {
                    $data['created_by'] = user_id();
                    $id = DB::insert('users', $data);
                }
                if (can('departments.assign')) {
                    User::syncDepartments($id, (array) ($_POST['departments'] ?? []), !empty($_POST['is_head']));
                }
                if (can('permissions.assign')) {
                    sync_user_permissions($id, $data['role_id'], (array) ($_POST['permissions'] ?? []));
                }
                return $id;
            });

            $name = $data['first_name'] . ' ' . $data['last_name'];
            log_activity($target ? 'update' : 'create', 'users', ($target ? 'Updated user ' : 'Created user ') . $name . ' (' . DB::value('SELECT name FROM roles WHERE id = ?', [$data['role_id']]) . ')');
            if ($target && $target['role_id'] != $data['role_id']) {
                log_activity('role_change', 'users', "Changed role of $name from {$target['role_name']}");
            }
            respond(true, $target ? 'User updated successfully.' : 'Account created. The user must change the password at first sign-in.', 'admin/users.php');

        case 'status':
            require_permission('users.edit');
            $u = load_manageable_user((int) post('id'));
            $new = $u['status'] === 'active' ? 'disabled' : 'active';
            DB::update('users', ['status' => $new], 'id = ?', [$u['id']]);
            log_activity($new === 'active' ? 'enable' : 'disable', 'users', ucfirst($new === 'active' ? 'enabled' : 'disabled') . " account of {$u['first_name']} {$u['last_name']}");
            json_response(['ok' => true, 'message' => 'Account ' . ($new === 'active' ? 'enabled.' : 'disabled.')]);

        case 'reset':
            require_permission('users.edit');
            $u = load_manageable_user((int) post('id'));
            $temp = 'Kc-' . substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(9))), 0, 8) . random_int(10, 99);
            DB::update('users', ['password_hash' => password_hash($temp, PASSWORD_DEFAULT), 'must_change_password' => 1], 'id = ?', [$u['id']]);
            log_activity('password_reset', 'users', "Reset password for {$u['first_name']} {$u['last_name']}");
            // The temporary password is shown ONCE to the admin and never stored in plain text.
            json_response(['ok' => true, 'message' => 'Password reset.', 'temp_password' => $temp, 'user' => $u['first_name'] . ' ' . $u['last_name']]);

        case 'delete':
            require_permission('users.delete');
            $u = load_manageable_user((int) post('id'));
            DB::delete('users', 'id = ?', [$u['id']]);
            log_activity('delete', 'users', "Deleted user {$u['first_name']} {$u['last_name']} ({$u['email']})");
            json_response(['ok' => true, 'message' => 'User deleted.']);
    }
    abort(404);
}

// ---------------------------------------------------------------------
//  Create / edit form
// ---------------------------------------------------------------------
if ($action === 'new' || $action === 'edit') {
    $target = $action === 'edit' ? load_manageable_user(query_int('id')) : null;
    require_permission($target ? 'users.edit' : 'users.create');

    $roles = assignable_roles();
    $roleId = (int) ($target['role_id'] ?? ($roles[count($roles) - 1]['id'] ?? 0));
    $rolePermMap = [];
    foreach ($roles as $r) {
        $rolePermMap[$r['id']] = $r['is_super'] ? 'all' : role_permission_ids((int) $r['id']);
    }
    $effective = $target ? array_keys(user_permission_names((int) $target['id'])) : [];
    $effectiveIds = $target
        ? ($target['is_super'] ? array_column(DB::all('SELECT id FROM permissions'), 'id') : array_column(DB::all('SELECT id FROM permissions WHERE name IN (' . (implode(',', array_fill(0, max(1, count($effective)), '?'))) . ')', $effective ?: ['']), 'id'))
        : role_permission_ids($roleId);
    $effectiveIds = array_map('intval', $effectiveIds);
    $overrides = $target ? array_column(DB::all('SELECT permission_id, granted FROM user_permissions WHERE user_id = ?', [$target['id']]), 'granted', 'permission_id') : [];
    $grantable = array_flip(grantable_permission_ids());
    $userDepts = $target ? User::departmentIds((int) $target['id']) : [];
    $isHead = $target ? (bool) DB::value('SELECT MAX(is_head) FROM department_users WHERE user_id = ?', [$target['id']]) : false;
    $allDepts = DB::all('SELECT id, name FROM departments ORDER BY sort_order, name');

    admin_header($target ? 'Edit User' : 'Add User', 'users');
    ?>
    <div class="page-head">
      <div><h1 class="page-title"><i class="fa-solid fa-user-pen"></i> <?= $target ? 'Edit ' . e($target['first_name'] . ' ' . $target['last_name']) : 'Create User Account' ?></h1>
      <p class="page-sub">User → Role → Department → Role Permissions → Direct Permissions</p></div>
      <a class="btn btn-light" href="<?= e(url('admin/users.php')) ?>"><i class="fa-solid fa-arrow-left"></i> All Users</a>
    </div>

    <form data-ajax method="post" action="<?= e(url('admin/users.php')) ?>" class="row g-4" autocomplete="off" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($target['id'] ?? 0) ?>">
      <div class="col-xl-5">
        <div class="panel">
          <h2 class="panel-title">Account Details</h2>
          <div class="row g-3">
            <?= form_field('first_name', ['label' => 'First Name', 'required' => true, 'max' => 80, 'col' => 6], $target['first_name'] ?? '') ?>
            <?= form_field('last_name', ['label' => 'Last Name', 'required' => true, 'max' => 80, 'col' => 6], $target['last_name'] ?? '') ?>
            <?= form_field('email', ['label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 190], $target['email'] ?? '') ?>
            <?= form_field('phone', ['label' => 'Phone', 'type' => 'tel', 'max' => 40, 'col' => 6], $target['phone'] ?? '') ?>
            <?= form_field('title', ['label' => 'Display Title', 'max' => 120, 'col' => 6, 'placeholder' => 'e.g. Youth Ministry Head'], $target['title'] ?? '') ?>
            <?= form_field('role_id', ['label' => 'Role', 'type' => 'select', 'required' => true, 'options_resolved' => array_column($roles, 'name', 'id'), 'col' => 6, 'help' => 'Selecting a role loads its default permissions below.'], $roleId) ?>
            <?= form_field('status', ['label' => 'Status', 'type' => 'select', 'required' => true, 'options_resolved' => ['active' => 'Active', 'disabled' => 'Disabled'], 'col' => 6], $target['status'] ?? 'active') ?>
          </div>
          <h2 class="panel-title mt-4"><?= $target ? 'Set New Password (optional)' : 'Initial Password' ?></h2>
          <div class="row g-3">
            <?= form_field('password', ['label' => 'Password', 'type' => 'password', 'required' => !$target, 'col' => 6, 'help' => 'Min 8 characters, letters and numbers.']) ?>
            <?= form_field('password_confirm', ['label' => 'Confirm Password', 'type' => 'password', 'required' => !$target, 'col' => 6]) ?>
            <div class="col-12"><button type="button" class="btn btn-sm btn-light" data-generate-password><i class="fa-solid fa-wand-magic-sparkles"></i> Generate strong password</button>
            <span class="small text-muted ms-2" data-generated></span></div>
          </div>
          <p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-shield-halved"></i> Passwords are hashed and never displayed. The user must change it at first sign-in.</p>
        </div>

        <div class="panel mt-4">
          <h2 class="panel-title">Departments</h2>
          <?php if (can('departments.assign')): ?>
            <div class="check-grid">
              <?php foreach ($allDepts as $d): ?>
                <label class="check-pill"><input type="checkbox" name="departments[]" value="<?= (int) $d['id'] ?>" <?= in_array((int) $d['id'], $userDepts, true) ? 'checked' : '' ?>> <?= e($d['name']) ?></label>
              <?php endforeach; ?>
            </div>
            <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="is_head" value="1" id="is_head" <?= $isHead ? 'checked' : '' ?>><label class="form-check-label" for="is_head">This user is the <strong>head</strong> of the selected department(s)</label></div>
          <?php else: ?>
            <p class="text-muted small mb-0"><i class="fa-solid fa-lock"></i> You do not have permission to assign departments.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-xl-7">
        <div class="panel">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div><h2 class="panel-title mb-1">Permissions</h2>
            <p class="small text-muted">Ticked = the user can do it. <span class="legend role">Role default</span> <span class="legend grant">Added for this user</span> <span class="legend revoke">Removed from role</span> <span class="legend locked"><i class="fa-solid fa-lock"></i> Outside your authority</span></p></div>
            <?php if (can('permissions.assign')): ?><button type="button" class="btn btn-sm btn-light" data-reset-role-perms><i class="fa-solid fa-rotate-left"></i> Reset to role defaults</button><?php endif; ?>
          </div>
          <?php if (!can('permissions.assign')): ?><div class="alert alert-light border small"><i class="fa-solid fa-lock"></i> You can view but not change permissions. Only the role defaults will apply.</div><?php endif; ?>
          <div class="perm-matrix" data-role-perms='<?= e(json_encode($rolePermMap)) ?>'>
            <?php foreach (permissions_by_module() as $module => $perms): ?>
              <fieldset class="perm-group">
                <legend><?= e($module) ?></legend>
                <?php foreach ($perms as $p):
                    $pid = (int) $p['id'];
                    $checked = in_array($pid, $effectiveIds, true);
                    $locked = !isset($grantable[$pid]) || !can('permissions.assign');
                    $state = isset($overrides[$pid]) ? ($overrides[$pid] ? 'grant' : 'revoke') : 'role'; ?>
                  <label class="perm-item state-<?= $state ?><?= $locked ? ' locked' : '' ?>" title="<?= e($p['name']) ?>">
                    <input type="checkbox" name="permissions[]" value="<?= $pid ?>" <?= $checked ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                    <span><?= e($p['label']) ?><?php if ($p['is_critical']): ?> <i class="fa-solid fa-shield-halved text-danger" title="Critical — Super Administrator only"></i><?php endif; ?></span>
                    <?php if ($locked): ?><i class="fa-solid fa-lock lock"></i><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </fieldset>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="sticky-actions">
          <a class="btn btn-light" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
          <button class="btn btn-gold btn-lg" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> <?= $target ? 'Save Changes' : 'Create Account' ?></button>
        </div>
      </div>
    </form>
    <?php
    admin_footer();
    exit;
}

// ---------------------------------------------------------------------
//  List
// ---------------------------------------------------------------------
$where = ['1'];
$params = [];
if ($q = trim((string) ($_GET['q'] ?? ''))) {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($rid = query_int('role')) {
    $where[] = 'u.role_id = ?';
    $params[] = $rid;
}
if (in_array($_GET['status'] ?? '', ['active', 'disabled'], true)) {
    $where[] = 'u.status = ?';
    $params[] = $_GET['status'];
}
$whereSql = implode(' AND ', $where);
$p = paginate((int) DB::value("SELECT COUNT(*) FROM users u WHERE $whereSql", $params), 15);
$users = DB::all("SELECT u.*, r.name AS role_name, r.level AS role_level, r.is_super,
        (SELECT GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ', ') FROM department_users du JOIN departments d ON d.id = du.department_id WHERE du.user_id = u.id) AS departments,
        (SELECT COUNT(*) FROM user_permissions up WHERE up.user_id = u.id) AS overrides
    FROM users u JOIN roles r ON r.id = u.role_id WHERE $whereSql ORDER BY r.level DESC, u.first_name LIMIT {$p['per_page']} OFFSET {$p['offset']}", $params);

admin_header('Users', 'users');
?>
<div class="tab-bar mb-3">
  <a class="tab-link active" href="<?= e(url('admin/users.php')) ?>">All Users</a>
  <?php if (can('users.create')): ?><a class="tab-link" href="<?= e(url('admin/users.php?action=new')) ?>">Add User</a><?php endif; ?>
  <?php if (can('roles.view')): ?><a class="tab-link" href="<?= e(url('admin/roles.php')) ?>">Roles</a><?php endif; ?>
  <?php if (can('permissions.view')): ?><a class="tab-link" href="<?= e(url('admin/permissions.php')) ?>">Permissions</a><?php endif; ?>
  <?php if (can('departments.view')): ?><a class="tab-link" href="<?= e(url('admin/departments.php')) ?>">Departments</a><?php endif; ?>
</div>
<div class="page-head">
  <div><h1 class="page-title"><i class="fa-solid fa-users"></i> Users</h1><p class="page-sub">Staff accounts with access to this dashboard.</p></div>
  <?php if (can('users.create')): ?><a class="btn btn-gold" href="<?= e(url('admin/users.php?action=new')) ?>"><i class="fa-solid fa-user-plus"></i> Add User</a><?php endif; ?>
</div>
<form class="toolbar card-lite" method="get" role="search">
  <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" class="form-control" placeholder="Search name or email…" value="<?= e($q) ?>" aria-label="Search users"></div>
  <select name="role" class="form-select" onchange="this.form.submit()" aria-label="Role"><option value="">All Roles</option>
    <?php foreach (DB::all('SELECT id, name FROM roles ORDER BY level DESC') as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $rid === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="status" class="form-select" onchange="this.form.submit()" aria-label="Status"><option value="">All Statuses</option><option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="disabled" <?= ($_GET['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
  <button class="btn btn-navy">Filter</button>
</form>

<div id="crud-table">
<?php if (!$users): echo empty_state('fa-users', 'No users found');
else: ?>
<div class="table-card"><div class="table-responsive"><table class="table admin-table align-middle">
  <thead><tr><th>User</th><th>Role</th><th>Departments</th><th>Last Login</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): $manage = can_manage_user($u); $self = (int) $u['id'] === user_id(); ?>
    <tr>
      <td data-label="User"><div class="d-flex align-items-center gap-2">
        <?php if ($u['avatar']): ?><img class="avatar-sm" src="<?= e(media_url($u['avatar'])) ?>" alt=""><?php else: ?><span class="avatar-sm"><?= e(initials($u['first_name'] . ' ' . $u['last_name'])) ?></span><?php endif; ?>
        <div><strong class="cell-title"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></strong><?= $self ? ' <span class="badge text-bg-light">You</span>' : '' ?><div class="cell-sub"><?= e($u['email']) ?></div></div></div></td>
      <td data-label="Role"><span class="role-pill<?= $u['is_super'] ? ' super' : '' ?>"><?= e($u['role_name']) ?></span>
        <?php if ($u['overrides']): ?><div class="cell-sub"><i class="fa-solid fa-sliders"></i> <?= (int) $u['overrides'] ?> custom permission<?= $u['overrides'] > 1 ? 's' : '' ?></div><?php endif; ?></td>
      <td data-label="Departments" class="small"><?= e($u['departments'] ?: '—') ?></td>
      <td data-label="Last Login" class="small"><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) . '<div class="cell-sub">' . e($u['last_login_ip']) . '</div>' : '<span class="text-muted">Never</span>' ?></td>
      <td data-label="Status"><?= status_badge($u['status']) ?></td>
      <td class="text-end text-nowrap">
        <?php if ($manage): ?>
          <?php if (can('users.edit')): ?>
            <a class="btn-icon" href="<?= e(url('admin/users.php?action=edit&id=' . $u['id'])) ?>" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
            <button class="btn-icon" data-post-action="status" data-id="<?= (int) $u['id'] ?>" title="<?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>" aria-label="<?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?> account"><i class="fa-solid <?= $u['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i></button>
            <button class="btn-icon" data-post-action="reset" data-id="<?= (int) $u['id'] ?>" data-confirm="Generate a temporary password for <?= e($u['first_name']) ?>? Their current password will stop working." data-confirm-ok="Reset Password" title="Reset password" aria-label="Reset password"><i class="fa-solid fa-key"></i></button>
          <?php endif; ?>
          <?php if (can('users.delete')): ?>
            <button class="btn-icon danger" data-post-action="delete" data-id="<?= (int) $u['id'] ?>" data-confirm="Are you sure you want to delete <?= e($u['first_name'] . ' ' . $u['last_name']) ?>? This cannot be undone." title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
          <?php endif; ?>
        <?php elseif ($self): ?>
          <a class="btn btn-sm btn-light" href="<?= e(url('admin/profile.php')) ?>">My Profile</a>
        <?php else: ?>
          <span class="text-muted small" title="Same or higher privilege level"><i class="fa-solid fa-lock"></i> Protected</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="table-foot"><span class="small text-muted">Showing <?= count($users) ?> of <?= $p['total'] ?></span><?= pagination_links($p) ?></div>
</div>
<?php endif; ?>
</div>

<div class="modal fade" id="tempPasswordModal" tabindex="-1" aria-labelledby="tpTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <div class="modal-header"><h2 class="modal-title h5" id="tpTitle"><i class="fa-solid fa-key text-warning"></i> Temporary Password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><p>Give this temporary password to <strong data-tp-user></strong> securely. It is shown <strong>only once</strong> and must be changed at next sign-in.</p>
    <div class="input-group"><input class="form-control font-monospace fs-5" readonly data-tp-value aria-label="Temporary password"><button class="btn btn-navy" type="button" data-copy-target="[data-tp-value]"><i class="fa-regular fa-copy"></i> Copy</button></div></div>
</div></div></div>
<?php
admin_footer();
