<?php
/**
 * Role-Based Access Control.
 *
 *   USER → ROLE → ROLE PERMISSIONS  (+ DIRECT USER GRANTS − DIRECT USER REVOKES)
 *        → DEPARTMENTS (scopes department-owned content)
 *
 * Every check here runs on the server. Hiding a button in the UI is only
 * cosmetic; each controller action also calls require_permission().
 */

/** Effective permission names for a user (cached per request). */
function user_permission_names(int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }
    $rows = DB::all(
        'SELECT p.name FROM permissions p
           JOIN role_permissions rp ON rp.permission_id = p.id
           JOIN users u ON u.role_id = rp.role_id
          WHERE u.id = ?
            AND p.id NOT IN (SELECT permission_id FROM user_permissions WHERE user_id = ? AND granted = 0)
         UNION
         SELECT p.name FROM permissions p
           JOIN user_permissions up ON up.permission_id = p.id
          WHERE up.user_id = ? AND up.granted = 1',
        [$userId, $userId, $userId]
    );
    return $cache[$userId] = array_flip(array_column($rows, 'name'));
}

function is_super_admin(?array $user = null): bool
{
    $user ??= current_user();
    return $user !== null && (int) $user['is_super'] === 1;
}

function can(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (is_super_admin($user)) {
        return true;
    }
    return isset(user_permission_names((int) $user['id'])[$permission]);
}

function can_any(array $permissions): bool
{
    foreach ($permissions as $p) {
        if (can($p)) {
            return true;
        }
    }
    return false;
}

/** Abort with 403 unless the current user holds the permission. */
function require_permission(string|array $permission): void
{
    require_login();
    $ok = is_array($permission) ? can_any($permission) : can($permission);
    if (!$ok) {
        log_activity('access_denied', 'security', 'Denied: ' . (is_array($permission) ? implode('|', $permission) : $permission) . ' on ' . current_path());
        abort(403, 'You do not have permission to perform this action. Contact your administrator if you need access.');
    }
}

// ---------------------------------------------------------------------
//  Department scoping
// ---------------------------------------------------------------------
function user_department_ids(?int $userId = null): array
{
    static $cache = [];
    $userId ??= user_id();
    if (!$userId) {
        return [];
    }
    return $cache[$userId] ??= array_map('intval', array_column(
        DB::all('SELECT department_id FROM department_users WHERE user_id = ?
                 UNION SELECT id FROM departments WHERE head_user_id = ?', [$userId, $userId]),
        'department_id'
    ));
}

/** Users without departments.all_access only see/manage their own department's content. */
function has_all_department_access(): bool
{
    return can('departments.all_access');
}

function can_access_department(?int $departmentId): bool
{
    if (has_all_department_access()) {
        return true;
    }
    return $departmentId !== null && in_array($departmentId, user_department_ids(), true);
}

// ---------------------------------------------------------------------
//  Anti-escalation rules for managing users, roles and permissions
// ---------------------------------------------------------------------
function current_role_level(): int
{
    return (int) (current_user()['role_level'] ?? 0);
}

/** Can the current user manage (edit / disable / reset) this target user? */
function can_manage_user(array $target): bool
{
    $me = current_user();
    if (!$me) {
        return false;
    }
    if ((int) $target['id'] === (int) $me['id']) {
        return false; // use the Profile page for yourself
    }
    if (is_super_admin($me)) {
        return true;
    }
    $targetLevel = (int) ($target['role_level'] ?? DB::value('SELECT level FROM roles WHERE id = ?', [$target['role_id']]));
    $targetSuper = (int) ($target['is_super'] ?? DB::value('SELECT is_super FROM roles WHERE id = ?', [$target['role_id']]));
    return !$targetSuper && $targetLevel < current_role_level();
}

/** Roles the current user may assign: strictly below their own level, never Super Admin. */
function assignable_roles(): array
{
    if (is_super_admin()) {
        return DB::all('SELECT * FROM roles ORDER BY level DESC, name');
    }
    return DB::all('SELECT * FROM roles WHERE is_super = 0 AND level < ? ORDER BY level DESC, name', [current_role_level()]);
}

function role_assignable(int $roleId): bool
{
    return in_array($roleId, array_map('intval', array_column(assignable_roles(), 'id')), true);
}

/** Can the current user edit this role's permission matrix? */
function can_manage_role(array $role): bool
{
    if ((int) $role['is_super'] === 1) {
        return false; // Super Administrator always has everything
    }
    if (is_super_admin()) {
        return true;
    }
    return can('roles.edit') && (int) $role['level'] < current_role_level();
}

/**
 * Permission IDs the current user may grant or revoke:
 * Super Admin → all; others need permissions.assign, may only pass on what
 * they themselves hold, and never critical permissions.
 */
function grantable_permission_ids(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    if (is_super_admin()) {
        return $ids = array_map('intval', array_column(DB::all('SELECT id FROM permissions'), 'id'));
    }
    if (!can('permissions.assign') && !can('roles.edit')) {
        return $ids = [];
    }
    $mine = array_keys(user_permission_names((int) user_id()));
    if (!$mine) {
        return $ids = [];
    }
    $in = implode(',', array_fill(0, count($mine), '?'));
    return $ids = array_map('intval', array_column(DB::all("SELECT id FROM permissions WHERE is_critical = 0 AND name IN ($in)", $mine), 'id'));
}

function role_permission_ids(int $roleId): array
{
    return array_map('intval', array_column(DB::all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]), 'permission_id'));
}

/**
 * Save a user's direct overrides so their effective permissions equal $desiredIds,
 * touching only permissions the current admin is allowed to grant.
 */
function sync_user_permissions(int $userId, int $roleId, array $desiredIds): void
{
    $desired = array_flip(array_map('intval', $desiredIds));
    $rolePerms = array_flip(role_permission_ids($roleId));
    $me = user_id();

    foreach (grantable_permission_ids() as $pid) {
        $want = isset($desired[$pid]);
        $fromRole = isset($rolePerms[$pid]);
        DB::delete('user_permissions', 'user_id = ? AND permission_id = ?', [$userId, $pid]);
        if ($want && !$fromRole) {
            DB::insert('user_permissions', ['user_id' => $userId, 'permission_id' => $pid, 'granted' => 1, 'granted_by' => $me]);
        } elseif (!$want && $fromRole) {
            DB::insert('user_permissions', ['user_id' => $userId, 'permission_id' => $pid, 'granted' => 0, 'granted_by' => $me]);
        }
    }
}

/** All permissions grouped by module, for checkbox matrices. */
function permissions_by_module(): array
{
    $grouped = [];
    foreach (DB::all('SELECT * FROM permissions ORDER BY module, id') as $p) {
        $grouped[$p['module']][] = $p;
    }
    return $grouped;
}
