<?php
/**
 * Photo albums. "downloads" albums let members find and download their own
 * photos by date; "event" albums are the reference gallery of church life.
 */
class Album extends Model
{
    protected static string $table = 'photo_albums';

    public const TYPES = ['downloads' => 'Photo Downloads', 'event' => 'Event Gallery'];

    private const SELECT = "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
            (SELECT COUNT(*) FROM gallery g WHERE g.album_id = a.id AND g.is_published = 1) AS photo_count,
            COALESCE(
              (SELECT COALESCE(g.thumb_path, g.file_path) FROM gallery g WHERE g.id = a.cover_id),
              (SELECT COALESCE(g.thumb_path, g.file_path) FROM gallery g WHERE g.album_id = a.id AND g.is_published = 1 ORDER BY g.id LIMIT 1)
            ) AS cover
        FROM photo_albums a LEFT JOIN gallery_categories c ON c.id = a.category_id";

    /** Human label: "Sunday, October 12, 2026" or "October 2026" (+ title when set). */
    public static function dateLabel(array $a, string $dayFormat = 'l, F j, Y'): string
    {
        return $a['date_precision'] === 'month' ? date('F Y', strtotime($a['album_date'])) : date($dayFormat, strtotime($a['album_date']));
    }

    public static function label(array $a): string
    {
        return $a['title'] ?: self::dateLabel($a);
    }

    /** Published albums for the public site. */
    public static function publicList(string $type, ?string $month = null, ?string $categorySlug = null, int $limit = 24, int $offset = 0): array
    {
        [$where, $params] = self::publicWhere($type, $month, $categorySlug);
        return DB::all(self::SELECT . " WHERE $where HAVING photo_count > 0 ORDER BY a.album_date DESC, a.id DESC LIMIT $limit OFFSET $offset", $params);
    }

    public static function countPublic(string $type, ?string $month = null, ?string $categorySlug = null): int
    {
        [$where, $params] = self::publicWhere($type, $month, $categorySlug);
        return (int) DB::value("SELECT COUNT(*) FROM photo_albums a LEFT JOIN gallery_categories c ON c.id = a.category_id
            WHERE $where AND EXISTS (SELECT 1 FROM gallery g WHERE g.album_id = a.id AND g.is_published = 1)", $params);
    }

    private static function publicWhere(string $type, ?string $month, ?string $categorySlug): array
    {
        $where = 'a.is_published = 1 AND a.type = ?';
        $params = [$type];
        if ($month) {
            $where .= " AND DATE_FORMAT(a.album_date, '%Y-%m') = ?";
            $params[] = $month;
        }
        if ($categorySlug) {
            $where .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        return [$where, $params];
    }

    /** Months that have published albums, newest first: ['2026-10' => 'October 2026', …] */
    public static function months(string $type): array
    {
        $rows = DB::all("SELECT DISTINCT DATE_FORMAT(a.album_date, '%Y-%m') ym FROM photo_albums a
            WHERE a.is_published = 1 AND a.type = ? AND EXISTS (SELECT 1 FROM gallery g WHERE g.album_id = a.id AND g.is_published = 1)
            ORDER BY ym DESC", [$type]);
        $out = [];
        foreach ($rows as $r) {
            $out[$r['ym']] = date('F Y', strtotime($r['ym'] . '-01'));
        }
        return $out;
    }

    public static function findPublic(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE a.id = ? AND a.is_published = 1', [$id]);
    }

    public static function findWithStats(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE a.id = ?', [$id]);
    }

    public static function photos(int $albumId, bool $publishedOnly = true, int $limit = 60, int $offset = 0): array
    {
        return DB::all('SELECT * FROM gallery WHERE album_id = ?' . ($publishedOnly ? ' AND is_published = 1' : '') . " ORDER BY id LIMIT $limit OFFSET $offset", [$albumId]);
    }

    public static function countPhotos(int $albumId, bool $publishedOnly = true): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM gallery WHERE album_id = ?' . ($publishedOnly ? ' AND is_published = 1' : ''), [$albumId]);
    }

    /** Albums the current staff user may manage (department-scoped when needed). */
    public static function adminList(string $type = '', int $limit = 24, int $offset = 0): array
    {
        [$where, $params] = self::adminWhere($type);
        return DB::all(self::SELECT . " WHERE $where ORDER BY a.album_date DESC, a.id DESC LIMIT $limit OFFSET $offset", $params);
    }

    public static function countAdmin(string $type = ''): int
    {
        [$where, $params] = self::adminWhere($type);
        return (int) DB::value("SELECT COUNT(*) FROM photo_albums a WHERE $where", $params);
    }

    private static function adminWhere(string $type): array
    {
        $where = '1';
        $params = [];
        if (isset(self::TYPES[$type])) {
            $where .= ' AND a.type = ?';
            $params[] = $type;
        }
        if (!has_all_department_access()) {
            $ids = user_department_ids() ?: [0];
            $where .= ' AND a.department_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        return [$where, $params];
    }

    public static function canManage(array $album): bool
    {
        return has_all_department_access() || in_array((int) $album['department_id'], user_department_ids(), true);
    }
}
