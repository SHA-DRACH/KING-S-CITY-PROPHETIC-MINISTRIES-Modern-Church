<?php
class PrayerRequest extends Model
{
    protected static string $table = 'prayer_requests';

    /**
     * Public prayer wall: ONLY requests the visitor explicitly marked public.
     * Contact details are never selected, and surnames are shortened.
     */
    public static function publicWall(int $limit = 6): array
    {
        $rows = DB::all("SELECT id, name, request, status, created_at FROM prayer_requests
            WHERE is_public = 1 AND status <> 'closed' ORDER BY created_at DESC LIMIT $limit");
        foreach ($rows as &$r) {
            $parts = preg_split('/\s+/', trim($r['name']));
            $r['name'] = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '');
        }
        return $rows;
    }

    public static function statusCounts(): array
    {
        $counts = array_fill_keys(array_keys(PRAYER_STATUSES), 0);
        foreach (DB::all('SELECT status, COUNT(*) c FROM prayer_requests GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['c'];
        }
        return $counts;
    }
}
