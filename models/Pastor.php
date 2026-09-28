<?php
class Pastor extends Model
{
    protected static string $table = 'pastor_profiles';

    public static function primary(): ?array
    {
        return DB::one('SELECT * FROM pastor_profiles ORDER BY is_primary DESC, id ASC LIMIT 1');
    }
}
