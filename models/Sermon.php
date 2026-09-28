<?php
class Sermon extends Model
{
    protected static string $table = 'sermons';

    private const SELECT = 'SELECT s.*, c.name AS category_name, c.slug AS category_slug
                              FROM sermons s LEFT JOIN sermon_categories c ON c.id = s.category_id';

    /** Public library with filter (all|video|audio|featured|<category slug>) and search. */
    public static function published(string $filter = 'all', string $search = '', int $limit = 9, int $offset = 0): array
    {
        [$where, $params] = self::filterSql($filter, $search);
        return DB::all(self::SELECT . " WHERE $where ORDER BY s.sermon_date DESC, s.id DESC LIMIT $limit OFFSET $offset", $params);
    }

    public static function countPublished(string $filter = 'all', string $search = ''): int
    {
        [$where, $params] = self::filterSql($filter, $search);
        return (int) DB::value("SELECT COUNT(*) FROM sermons s LEFT JOIN sermon_categories c ON c.id = s.category_id WHERE $where", $params);
    }

    private static function filterSql(string $filter, string $search): array
    {
        $where = ["s.status = 'published'"];
        $params = [];
        switch ($filter) {
            case 'video':    $where[] = "s.media_type = 'video'"; break;
            case 'audio':    $where[] = "s.media_type = 'audio'"; break;
            case 'featured': $where[] = 's.is_featured = 1'; break;
            case 'all':
            case '':         break;
            default:         $where[] = 'c.slug = ?'; $params[] = $filter;
        }
        if ($search !== '') {
            $where[] = '(s.title LIKE ? OR s.speaker LIKE ? OR s.scripture LIKE ? OR s.description LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    public static function latest(): ?array
    {
        return DB::one(self::SELECT . " WHERE s.status = 'published' ORDER BY s.sermon_date DESC, s.id DESC LIMIT 1");
    }

    public static function bySlug(string $slug): ?array
    {
        return DB::one(self::SELECT . " WHERE s.slug = ? AND s.status = 'published'", [$slug]);
    }

    public static function related(array $sermon, int $limit = 3): array
    {
        return DB::all(self::SELECT . " WHERE s.status = 'published' AND s.id <> ?
            ORDER BY (s.category_id <=> ?) DESC, s.sermon_date DESC LIMIT $limit", [$sermon['id'], $sermon['category_id']]);
    }

    public static function incrementViews(int $id): void
    {
        DB::query('UPDATE sermons SET views = views + 1 WHERE id = ?', [$id]);
    }

    public static function categories(): array
    {
        return DB::all('SELECT * FROM sermon_categories ORDER BY sort_order, name');
    }

    public static function thumb(array $s): string
    {
        if (!empty($s['thumbnail'])) {
            return media_url($s['thumbnail']);
        }
        return youtube_thumb($s['video_url'] ?? null) ?? media_url(null, 'assets/images/placeholders/sermon.svg');
    }
}
