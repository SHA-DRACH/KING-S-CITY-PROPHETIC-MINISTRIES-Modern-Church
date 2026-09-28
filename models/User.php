<?php
class User extends Model
{
    protected static string $table = 'users';

    public static function withRole(int $id): ?array
    {
        return DB::one('SELECT u.*, r.name AS role_name, r.slug AS role_slug, r.level AS role_level, r.is_super
            FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [$id]);
    }

    public static function departmentIds(int $userId): array
    {
        return array_map('intval', array_column(DB::all('SELECT department_id FROM department_users WHERE user_id = ?', [$userId]), 'department_id'));
    }

    public static function syncDepartments(int $userId, array $departmentIds, bool $isHead): void
    {
        DB::delete('department_users', 'user_id = ?', [$userId]);
        foreach (array_unique(array_map('intval', $departmentIds)) as $did) {
            if ($did > 0) {
                DB::insert('department_users', ['department_id' => $did, 'user_id' => $userId, 'is_head' => $isHead ? 1 : 0]);
                if ($isHead) {
                    DB::update('departments', ['head_user_id' => $userId], 'id = ? AND head_user_id IS NULL', [$did]);
                }
            }
        }
    }

    public static function staffOptions(): array
    {
        return DB::all("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE status = 'active' ORDER BY first_name");
    }
}
