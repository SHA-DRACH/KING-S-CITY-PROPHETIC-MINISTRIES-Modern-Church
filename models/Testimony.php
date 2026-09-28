<?php
class Testimony extends Model
{
    protected static string $table = 'testimonies';

    /** Only testimonies the giver allowed AND staff published are ever shown publicly. */
    public static function published(int $limit = 6, int $offset = 0): array
    {
        return DB::all("SELECT id, name, title, testimony, photo, created_at FROM testimonies
            WHERE status = 'published' AND permission_to_publish = 1 ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    }

    public static function countPublished(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM testimonies WHERE status = 'published' AND permission_to_publish = 1");
    }
}
