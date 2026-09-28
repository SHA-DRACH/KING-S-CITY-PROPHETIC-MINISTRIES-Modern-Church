<?php
class Gallery extends Model
{
    protected static string $table = 'gallery';

    /** Recent published photos (homepage preview, pastor page) — only from published albums. */
    public static function published(?string $categorySlug = null, int $limit = 24, int $offset = 0): array
    {
        $params = [];
        $where = 'g.is_published = 1 AND (g.album_id IS NULL OR a.is_published = 1)';
        if ($categorySlug) {
            $where .= ' AND c.slug = ?';
            $params[] = $categorySlug;
        }
        return DB::all("SELECT g.*, COALESCE(g.thumb_path, g.file_path) AS thumb, c.name AS category_name, c.slug AS category_slug,
                a.allow_download
            FROM gallery g
            LEFT JOIN photo_albums a ON a.id = g.album_id
            LEFT JOIN gallery_categories c ON c.id = g.category_id
            WHERE $where ORDER BY g.created_at DESC, g.id DESC LIMIT $limit OFFSET $offset", $params);
    }

    public static function categories(): array
    {
        return DB::all('SELECT c.*, (SELECT COUNT(*) FROM photo_albums a WHERE a.category_id = c.id AND a.is_published = 1) AS items
            FROM gallery_categories c ORDER BY c.sort_order, c.name');
    }
}
