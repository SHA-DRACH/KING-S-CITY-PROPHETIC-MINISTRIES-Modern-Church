<?php
class Giving extends Model
{
    protected static string $table = 'giving_transactions';

    public static function categories(): array
    {
        return DB::all('SELECT * FROM giving_categories WHERE is_active = 1 ORDER BY sort_order, name');
    }

    public static function methods(): array
    {
        return DB::all('SELECT * FROM giving_methods WHERE is_active = 1 ORDER BY sort_order, name');
    }

    public static function newReference(): string
    {
        do {
            $ref = 'KC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        } while (DB::value('SELECT COUNT(*) FROM giving_transactions WHERE reference = ?', [$ref]));
        return $ref;
    }

    /** Confirmed USD totals per month for the last $months months (for charts). */
    public static function monthlyTotals(int $months = 6): array
    {
        $rows = DB::all("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(amount) total FROM giving_transactions
            WHERE status = 'confirmed' AND currency = 'USD'
              AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)
            GROUP BY ym", [$months - 1]);
        $map = array_column($rows, 'total', 'ym');
        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("first day of -$i month"));
            $out[] = ['label' => date('M', strtotime($ym . '-01')), 'total' => (float) ($map[$ym] ?? 0)];
        }
        return $out;
    }
}
