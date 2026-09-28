<?php
/**
 * Roles and their default permission sets.
 * Only roles strictly below your own level are editable; the Super
 * Administrator role always has every permission and cannot be edited.
 */
require __DIR__ . '/partials/init.php';
require_permission('roles.view');

$action = $_POST['action'] ?? $_GET['action'] ?? 'index';

function load_role(int $id): array
{
    $role = DB::one('SELECT * FROM roles WHERE id = ?', [$id]);
    if (!$role) {
        abort(404, 'Role not found.');
    }
    return $role;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save') {
        $id = (int) post('id');
        $role = $id ? load_role($id) : null;
        require_permission($role ? 'roles.edit' : 'roles.create');
        if ($role && !can_manage_role($role)) {
            abort(403, 'You cannot modify this role.');
        }
        $name = post('name');
        $level = (int) post('level');
        $errors = [];
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'] = 'Role name is required (max 80 characters).';
        }
        $maxLevel = is_super_admin() ? 99 : current_role_level() - 1;
        if ($level < 1 || $level > $maxLevel) {
            $errors['level'] = "Level must be between 1 and $maxLevel.";
        }
        $slug = slugify($name);
        if (DB::value('SELECT COUNT(*) FROM roles WHERE slug = ? AND id <> ?', [$slug, $id])) {
            $errors['name'] = 'A role with this name already exists.';
        }
        if ($errors) {
            json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
        }

        DB::transaction(function () use ($role, &$id, $name, $slug, $level) {
            $data = ['name' => $name, 'description' => post('description') ?: null, 'level' => $level];
            if ($role) {
                if (!$role['is_system']) {
                    $data['slug'] = $slug;
                }
                DB::update('roles', $data, 'id = ?', [$id]);
            } else {
                $data['slug'] = str_replace('-', '_', $slug);
                $id = DB::insert('roles', $data);
            }
            // Only permissions this admin may grant are touched; others are preserved.
            $wanted = array_flip(array_map('intval', (array) ($_POST['permissions'] ?? [])));
            foreach (grantable_permission_ids() as $pid) {
                DB::delete('role_permissions', 'role_id = ? AND permission_id = ?', [$id, $pid]);
                if (isset($wanted[$pid])) {
                    DB::insert('role_permissions', ['role_id' => $id, 'permission_id' => $pid]);
                }
            }
        });
        log_activity($role ? 'update' : 'create', 'roles', ($role ? 'Updated role ' : 'Created role ') . $name . ' (' . count((array) ($_POST['permissions'] ?? [])) . ' permissions)');
        respond(true, $role ? 'Role updated.' : 'Role created.', 'admin/roles.php');
    }

    if ($action === 'delete') {
        require_permission('roles.delete');
        $role = load_role((int) post('id'));
        if ($role['is_system'] || !can_manage_role($role)) {
            json_response(['ok' => false, 'message' => 'System roles cannot be deleted.'], 422);
        }
        if (DB::value('SELECT COUNT(*) FROM users WHERE role_id = ?', [$role['id']])) {
            json_response(['ok' => false, 'message' => 'Reassign the users of this role before deleting it.'], 422);
        }
        DB::delete('roles', 'id = ?', [$role['id']]);
        log_activity('delete', 'roles', 'Deleted role ' . $role['name']);
        json_response(['ok' => true, 'message' => 'Role deleted.']);
    }
    abort(404);
}

if ($action === 'new' || $action === 'edit') {
    $role = $action === 'edit' ? load_role(query_int('id')) : null;
    require_permission($role ? 'roles.edit' : 'roles.create');
    if ($role && !can_manage_role($role)) {
        abort(403, 'You cannot modify this role.');
    }
    $current = $role ? role_permission_ids((int) $role['id']) : [];
    $grantable = array_flip(grantable_permission_ids());

    admin_header($role ? 'Edit Role' : 'New Role', 'roles');
    ?>
    <div class="page-head">
      <div><h1 class="page-title"><i class="fa-solid fa-user-shield"></i> <?= $role ? 'Edit Role: ' . e($role['name']) : 'Create Role' ?></h1>
      <p class="page-sub">These are the default permissions for everyone with this role. Individual users can still be adjusted on their account.</p></div>
      <a class="btn btn-light" href="<?= e(url('admin/roles.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Roles</a>
    </div>
    <form data-ajax method="post" action="<?= e(url('admin/roles.php')) ?>" class="row g-4" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($role['id'] ?? 0) ?>">
      <div class="col-lg-4"><div class="panel"><div class="row g-3">
        <?= form_field('name', ['label' => 'Role Name', 'required' => true, 'max' => 80], $role['name'] ?? '') ?>
        <?= form_field('description', ['label' => 'Description', 'type' => 'textarea', 'rows' => 3], $role['description'] ?? '') ?>
        <?= form_field('level', ['label' => 'Privilege Level', 'type' => 'number', 'required' => true, 'step' => '1', 'help' => 'Higher = more senior. Users can only manage roles and users below their own level (yours: ' . current_role_level() . ').'], $role['level'] ?? 20) ?>
      </div></div></div>
      <div class="col-lg-8"><div class="panel">
        <div class="d-flex justify-content-between flex-wrap gap-2"><h2 class="panel-title">Role Permissions</h2>
          <div><button type="button" class="btn btn-sm btn-light" data-check-all="1">Select all</button> <button type="button" class="btn btn-sm btn-light" data-check-all="0">Clear</button></div></div>
        <div class="perm-matrix">
          <?php foreach (permissions_by_module() as $module => $perms): ?>
            <fieldset class="perm-group"><legend><?= e($module) ?></legend>
              <?php foreach ($perms as $p): $locked = !isset($grantable[(int) $p['id']]); ?>
                <label class="perm-item<?= $locked ? ' locked' : '' ?>" title="<?= e($p['name']) ?>">
                  <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $current, true) ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                  <span><?= e($p['label']) ?><?php if ($p['is_critical']): ?> <i class="fa-solid fa-shield-halved text-danger" title="Critical"></i><?php endif; ?></span>
                  <?php if ($locked): ?><i class="fa-solid fa-lock lock"></i><?php endif; ?>
                </label>
              <?php endforeach; ?>
            </fieldset>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="sticky-actions"><a class="btn btn-light" href="<?= e(url('admin/roles.php')) ?>">Cancel</a><button class="btn btn-gold btn-lg" data-loading-text="Saving…"><i class="fa-solid fa-check"></i> Save Role</button></div>
      </div>
    </form>
    <?php
    admin_footer();
    exit;
}

$roles = DB::all('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users,
    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perms FROM roles r ORDER BY r.level DESC');
$totalPerms = (int) DB::value('SELECT COUNT(*) FROM permissions');

admin_header('Roles', 'roles');
?>
<div class="page-head">
  <div><h1 class="page-title"><i class="fa-solid fa-user-shield"></i> Roles</h1><p class="page-sub">Each role carries a default permission set. Levels prevent anyone from managing people above them.</p></div>
  <?php if (can('roles.create')): ?><a class="btn btn-gold" href="<?= e(url('admin/roles.php?action=new')) ?>"><i class="fa-solid fa-plus"></i> New Role</a><?php endif; ?>
</div>
<div class="row g-3" id="crud-table">
  <?php foreach ($roles as $r): $manage = can_manage_role($r); ?>
    <div class="col-md-6 col-xl-4">
      <div class="role-card<?= $r['is_super'] ? ' super' : '' ?>">
        <div class="d-flex justify-content-between align-items-start">
          <div><h2 class="h5 mb-1"><?= e($r['name']) ?></h2><span class="small text-muted">Level <?= (int) $r['level'] ?><?= $r['is_system'] ? ' · System role' : '' ?></span></div>
          <span class="icon-chip"><i class="fa-solid <?= $r['is_super'] ? 'fa-crown' : 'fa-user-shield' ?>"></i></span>
        </div>
        <p class="small text-muted mt-2"><?= e($r['description']) ?></p>
        <div class="role-stats"><span><i class="fa-solid fa-users"></i> <?= (int) $r['users'] ?> users</span>
          <span><i class="fa-solid fa-key"></i> <?= $r['is_super'] ? 'All permissions' : (int) $r['perms'] . ' / ' . $totalPerms ?></span></div>
        <?php if (!$r['is_super']): ?><div class="progress mt-2" style="height:6px" role="progressbar" aria-label="Permission coverage"><div class="progress-bar bg-warning" style="width:<?= $totalPerms ? round($r['perms'] / $totalPerms * 100) : 0 ?>%"></div></div><?php endif; ?>
        <div class="mt-3 d-flex gap-2">
          <?php if ($manage && can('roles.edit')): ?><a class="btn btn-sm btn-navy" href="<?= e(url('admin/roles.php?action=edit&id=' . $r['id'])) ?>"><i class="fa-solid fa-pen"></i> Edit Permissions</a><?php endif; ?>
          <?php if ($manage && can('roles.delete') && !$r['is_system']): ?><button class="btn btn-sm btn-outline-danger" data-post-action="delete" data-id="<?= (int) $r['id'] ?>" data-confirm="Delete the role “<?= e($r['name']) ?>”?"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
          <?php if (!$manage): ?><span class="small text-muted"><i class="fa-solid fa-lock"></i> <?= $r['is_super'] ? 'Always has full access' : 'Above your authority' ?></span><?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php
admin_footer();
