<?php
class Gallery extends Model
{
    protected static string $table = 'gallery';

    public static function published(?string $categorySlug = null, int $limit = 24, int $offset = 0): array
    {
        $params = [];
        $where = 'g.is_published = 1';
        if ($categorySlug) {
            $where .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        return DB::all("SELECT g.*, c.name AS category_name, c.slug AS category_slug FROM gallery g
            LEFT JOIN gallery_categories c ON c.id = g.category_id WHERE $where
            ORDER BY g.created_at DESC, g.id DESC LIMIT $limit OFFSET $offset", $params);
    }

    public static function countPublished(?string $categorySlug = null): int
    {
        return (int) DB::value(
            'SELECT COUNT(*) FROM gallery g LEFT JOIN gallery_categories c ON c.id = g.category_id WHERE g.is_published = 1'
            . ($categorySlug ? ' AND c.slug = ?' : ''),
            $categorySlug ? [$categorySlug] : []
        );
    }

    public static function categories(): array
    {
        return DB::all('SELECT c.*, (SELECT COUNT(*) FROM gallery g WHERE g.category_id = c.id AND g.is_published = 1) AS items
            FROM gallery_categories c ORDER BY c.sort_order, c.name');
    }
}
