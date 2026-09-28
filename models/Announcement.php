<?php
class Announcement extends Model
{
    protected static string $table = 'announcements';

    /** Published announcements whose date window includes today. */
    public static function active(int $limit = 5): array
    {
        return DB::all("SELECT a.*, d.name AS department_name FROM announcements a
            LEFT JOIN departments d ON d.id = a.department_id
            WHERE a.status = 'published'
              AND (a.start_date IS NULL OR a.start_date <= CURDATE())
              AND (a.end_date IS NULL OR a.end_date >= CURDATE())
            ORDER BY COALESCE(a.start_date, DATE(a.created_at)) DESC LIMIT $limit");
    }
}
