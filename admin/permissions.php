<?php
/**
 * Permission catalogue + role × permission matrix.
 * Cells are toggleable (AJAX) for roles the current user may manage.
 * Only a Super Administrator can define new permissions.
 */
require __DIR__ . '/partials/init.php';
require_permission('permissions.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'toggle') {
        require_permission('roles.edit');
        $role = DB::one('SELECT * FROM roles WHERE id = ?', [(int) post('role_id')]) ?? abort(404);
        $pid = (int) post('permission_id');
        if (!can_manage_role($role) || !in_array($pid, grantable_permission_ids(), true)) {
            abort(403, 'You cannot change this permission for this role.');
        }
        $has = (bool) DB::value('SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND permission_id = ?', [$role['id'], $pid]);
        $name = DB::value('SELECT name FROM permissions WHERE id = ?', [$pid]);
        if ($has) {
            DB::delete('role_permissions', 'role_id = ? AND permission_id = ?', [$role['id'], $pid]);
        } else {
            DB::insert('role_permissions', ['role_id' => $role['id'], 'permission_id' => $pid]);
        }
        log_activity($has ? 'revoke' : 'grant', 'permissions', ($has ? 'Revoked ' : 'Granted ') . "$name " . ($has ? 'from' : 'to') . " role {$role['name']}");
        json_response(['ok' => true, 'message' => ($has ? 'Revoked ' : 'Granted ') . $name . ($has ? ' from ' : ' to ') . $role['name'] . '.', 'granted' => !$has]);
    }
    if ($action === 'create') {
        if (!is_super_admin()) {
            abort(403, 'Only a Super Administrator can create permissions.');
        }
        $name = strtolower(post('name'));
        $errors = [];
        if (!preg_match('/^[a-z_]+\.[a-z_]+$/', $name)) {
            $errors['name'] = 'Use the format module.action, e.g. reports.view';
        } elseif (DB::value('SELECT COUNT(*) FROM permissions WHERE name = ?', [$name])) {
            $errors['name'] = 'This permission already exists.';
        }
        if (post('label') === '') {
            $errors['label'] = 'Label is required.';
        }
        if ($errors) {
            json_response(['ok' => false, 'message' => 'Please correct the highlighted fields.', 'errors' => $errors], 422);
        }
        DB::insert('permissions', ['name' => $name, 'label' => post('label'), 'module' => post('module') ?: ucfirst(strtok($name, '.')), 'description' => post('description') ?: null, 'is_critical' => !empty($_POST['is_critical']) ? 1 : 0]);
        log_activity('create', 'permissions', "Created permission $name");
        respond(true, 'Permission created.', 'admin/permissions.php');
    }
    abort(404);
}

$roles = DB::all('SELECT * FROM roles ORDER BY level DESC');
$matrix = [];
foreach (DB::all('SELECT role_id, permission_id FROM role_permissions') as $rp) {
    $matrix[$rp['role_id']][$rp['permission_id']] = true;
}
$grantable = array_flip(grantable_permission_ids());
$overrides = DB::all('SELECT up.granted, p.name AS perm, CONCAT(u.first_name, " ", u.last_name) AS user_name, u.id AS user_id, r.name AS role_name
    FROM user_permissions up JOIN permissions p ON p.id = up.permission_id JOIN users u ON u.id = up.user_id JOIN roles r ON r.id = u.role_id ORDER BY u.first_name, p.name');

admin_header('Permissions', 'permissions');
?>
<div class="page-head">
  <div><h1 class="page-title"><i class="fa-solid fa-key"></i> Permissions</h1>
  <p class="page-sub">Role defaults below. <?= can('roles.edit') ? 'Click a cell to grant or revoke for a role.' : '' ?> <i class="fa-solid fa-shield-halved text-danger"></i> = critical (Super Administrator only).</p></div>
  <?php if (is_super_admin()): ?><button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#permModal"><i class="fa-solid fa-plus"></i> New Permission</button><?php endif; ?>
</div>

<div class="table-card"><div class="table-responsive matrix-wrap"><table class="table perm-table align-middle mb-0">
  <thead><tr><th scope="col">Permission</th><?php foreach ($roles as $r): ?><th scope="col" class="text-center"><?= e($r['name']) ?></th><?php endforeach; ?></tr></thead>
  <tbody>
  <?php foreach (permissions_by_module() as $module => $perms): ?>
    <tr class="module-row"><th colspan="<?= count($roles) + 1 ?>"><?= e($module) ?></th></tr>
    <?php foreach ($perms as $p): ?>
      <tr><th scope="row" class="fw-normal"><span class="d-block"><?= e($p['label']) ?><?= $p['is_critical'] ? ' <i class="fa-solid fa-shield-halved text-danger"></i>' : '' ?></span><code class="small"><?= e($p['name']) ?></code></th>
      <?php foreach ($roles as $r):
          $on = $r['is_super'] || isset($matrix[$r['id']][$p['id']]);
          $editable = !$r['is_super'] && can('roles.edit') && can_manage_role($r) && isset($grantable[(int) $p['id']]); ?>
        <td class="text-center">
          <?php if ($editable): ?>
            <button type="button" class="matrix-cell<?= $on ? ' on' : '' ?>" data-matrix-toggle data-role="<?= (int) $r['id'] ?>" data-perm="<?= (int) $p['id'] ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>" aria-label="<?= e($p['label'] . ' for ' . $r['name']) ?>"><i class="fa-solid <?= $on ? 'fa-check' : 'fa-minus' ?>"></i></button>
          <?php else: ?>
            <span class="matrix-cell static<?= $on ? ' on' : '' ?>" aria-label="<?= $on ? 'Granted' : 'Not granted' ?>"><i class="fa-solid <?= $on ? 'fa-check' : 'fa-minus' ?>"></i></span>
          <?php endif; ?>
        </td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </tbody></table></div></div>

<div class="panel mt-4">
  <h2 class="panel-title">Direct User Overrides</h2>
  <p class="small text-muted">Permissions granted to, or removed from, individual users on top of their role.</p>
  <?php if (!$overrides): echo empty_state('fa-sliders', 'No user-specific overrides', 'All users currently use their role defaults.');
  else: ?>
  <div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Role</th><th>Permission</th><th>Override</th></tr></thead><tbody>
    <?php foreach ($overrides as $o): ?>
      <tr><td><a href="<?= e(url('admin/users.php?action=edit&id=' . $o['user_id'])) ?>"><?= e($o['user_name']) ?></a></td><td><?= e($o['role_name']) ?></td><td><code><?= e($o['perm']) ?></code></td>
      <td><?= $o['granted'] ? '<span class="badge text-bg-success">+ Granted</span>' : '<span class="badge text-bg-danger">− Revoked</span>' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<?php if (is_super_admin()): ?>
<div class="modal fade" id="permModal" tabindex="-1" aria-labelledby="permModalTitle" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" data-ajax method="post" novalidate>
  <div class="modal-header"><h2 class="modal-title h5" id="permModalTitle">New Permission</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="create"><div class="row g-3">
    <?= form_field('name', ['label' => 'Key', 'required' => true, 'placeholder' => 'reports.view', 'help' => 'Checked in code with can(\'reports.view\').']) ?>
    <?= form_field('label', ['label' => 'Label', 'required' => true, 'placeholder' => 'View Reports']) ?>
    <?= form_field('module', ['label' => 'Module', 'placeholder' => 'Reports']) ?>
    <?= form_field('description', ['label' => 'Description']) ?>
    <?= form_field('is_critical', ['label' => 'Critical', 'type' => 'checkbox', 'check_label' => 'Critical (only Super Administrators may grant)']) ?>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-gold" data-loading-text="Saving…">Create</button></div>
</form></div></div>
<?php endif; ?>
<?php
admin_footer();
