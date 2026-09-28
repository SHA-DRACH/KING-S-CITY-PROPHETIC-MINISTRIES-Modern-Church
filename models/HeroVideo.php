<?php
class HeroVideo extends Model
{
    protected static string $table = 'hero_videos';

    public static function active(): ?array
    {
        return DB::one('SELECT * FROM hero_videos WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1');
    }

    /** Only one hero video may be active at a time. */
    public static function activate(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::query('UPDATE hero_videos SET is_active = 0');
            DB::query('UPDATE hero_videos SET is_active = 1 WHERE id = ?', [$id]);
        });
    }
}
