<?php
class Setting
{
    /** Upsert several settings in one go. */
    public static function saveMany(array $values, string $group): void
    {
        foreach ($values as $key => $value) {
            DB::query(
                'INSERT INTO church_settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value, $group]
            );
        }
    }

    public static function group(string $group): array
    {
        return array_column(DB::all('SELECT setting_key, setting_value FROM church_settings WHERE setting_group = ?', [$group]), 'setting_value', 'setting_key');
    }
}
