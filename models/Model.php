<?php
/**
 * Minimal base model: table-bound finders shared by all models.
 * Complex queries live in the specific model classes.
 */
abstract class Model
{
    protected static string $table;

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM `' . static::$table . '` WHERE id = ?', [$id]);
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        if (!preg_match('/^[a-z_]+$/', $column)) {
            throw new InvalidArgumentException('Bad column');
        }
        return DB::one('SELECT * FROM `' . static::$table . "` WHERE `$column` = ? LIMIT 1", [$value]);
    }

    public static function count(string $where = '1', array $params = []): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM `' . static::$table . "` WHERE $where", $params);
    }

    public static function create(array $data): int
    {
        return DB::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data): void
    {
        DB::update(static::$table, $data, 'id = ?', [$id]);
    }

    public static function deleteById(int $id): void
    {
        DB::delete(static::$table, 'id = ?', [$id]);
    }
}
