<?php
class Event extends Model
{
    protected static string $table = 'events';

    private const SELECT = 'SELECT e.*, c.name AS category_name, d.name AS department_name
                              FROM events e
                              LEFT JOIN event_categories c ON c.id = e.category_id
                              LEFT JOIN departments d ON d.id = e.department_id';

    public static function upcoming(int $limit = 3, int $offset = 0, ?int $categoryId = null): array
    {
        $params = [];
        $cat = '';
        if ($categoryId) {
            $cat = ' AND e.category_id = ?';
            $params[] = $categoryId;
        }
        return DB::all(self::SELECT . " WHERE e.status IN ('published','cancelled') AND COALESCE(e.end_date, e.event_date) >= CURDATE() $cat
            ORDER BY e.event_date ASC, e.start_time ASC LIMIT $limit OFFSET $offset", $params);
    }

    public static function countUpcoming(?int $categoryId = null): int
    {
        return (int) DB::value(
            "SELECT COUNT(*) FROM events e WHERE e.status IN ('published','cancelled') AND COALESCE(e.end_date, e.event_date) >= CURDATE()"
            . ($categoryId ? ' AND e.category_id = ?' : ''),
            $categoryId ? [$categoryId] : []
        );
    }

    public static function past(int $limit = 6, int $offset = 0): array
    {
        return DB::all(self::SELECT . " WHERE e.status = 'published' AND COALESCE(e.end_date, e.event_date) < CURDATE()
            ORDER BY e.event_date DESC LIMIT $limit OFFSET $offset");
    }

    public static function countPast(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM events WHERE status = 'published' AND COALESCE(end_date, event_date) < CURDATE()");
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one(self::SELECT . " WHERE e.slug = ? AND e.status IN ('published','cancelled')", [$slug]);
    }

    public static function categories(): array
    {
        return DB::all('SELECT * FROM event_categories ORDER BY sort_order, name');
    }
}
