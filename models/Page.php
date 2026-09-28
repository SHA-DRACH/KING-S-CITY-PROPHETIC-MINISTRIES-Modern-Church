<?php
class Page extends Model
{
    protected static string $table = 'pages';

    /** Load several content blocks at once, keyed by slug. */
    public static function many(array $slugs): array
    {
        if (!$slugs) {
            return [];
        }
        $rows = DB::all('SELECT * FROM pages WHERE slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')', $slugs);
        return array_column($rows, null, 'slug');
    }
}
