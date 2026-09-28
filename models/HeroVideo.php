<?php
class HeroVideo extends Model
{
    protected static string $table = 'hero_videos';

    /** The active background video for a public page (cached per request). */
    public static function active(string $page = 'home'): ?array
    {
        static $cache = [];
        if (!array_key_exists($page, $cache)) {
            $cache[$page] = DB::one('SELECT * FROM hero_videos WHERE page = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1', [$page]);
        }
        return $cache[$page];
    }

    /** Only one video may be active per page. */
    public static function activate(int $id): void
    {
        $page = DB::value('SELECT page FROM hero_videos WHERE id = ?', [$id]);
        DB::transaction(function () use ($id, $page) {
            DB::query('UPDATE hero_videos SET is_active = 0 WHERE page = ?', [$page]);
            DB::query('UPDATE hero_videos SET is_active = 1 WHERE id = ?', [$id]);
        });
    }

    public static function hasVideo(?array $v): bool
    {
        return $v && ($v['video_mp4'] || $v['video_webm']);
    }
}
