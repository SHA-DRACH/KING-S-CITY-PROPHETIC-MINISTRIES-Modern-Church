<?php
class Department extends Model
{
    protected static string $table = 'departments';

    public static function publicList(): array
    {
        return DB::all("SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) AS head_user_name, u.email AS head_email
            FROM departments d LEFT JOIN users u ON u.id = d.head_user_id
            WHERE d.status = 'active' AND d.is_public = 1 ORDER BY d.sort_order, d.name");
    }

    /** Departments available in dropdowns for the current user (scoped unless all-access). */
    public static function options(): array
    {
        if (has_all_department_access()) {
            return DB::all('SELECT id, name FROM departments ORDER BY sort_order, name');
        }
        $ids = user_department_ids();
        if (!$ids) {
            return [];
        }
        return DB::all('SELECT id, name FROM departments WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY sort_order, name', $ids);
    }

    public static function members(int $departmentId): array
    {
        return DB::all("SELECT u.id, u.first_name, u.last_name, u.email, du.is_head, r.name AS role_name
            FROM department_users du JOIN users u ON u.id = du.user_id JOIN roles r ON r.id = u.role_id
            WHERE du.department_id = ? ORDER BY du.is_head DESC, u.first_name", [$departmentId]);
    }
}
