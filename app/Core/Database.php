<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query in the application goes through here so that
 * prepared statements are the only way data reaches MySQL.
 */
final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        /** @var array<string, string> $config */
        $config = Config::get('database', []);

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'],
        );

        try {
            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                    // charset= in the DSN leaves the connection collation at the
                    // server default (general_ci on Hostinger), which clashes with
                    // the unicode_ci tables on string-function comparisons.
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $config['charset'] . ' COLLATE utf8mb4_unicode_ci',
                ],
            );
        } catch (PDOException $e) {
            throw new PDOException(
                'Could not connect to the `' . $config['database'] . '` database. '
                . 'Check the DB_* values in .env. (' . $e->getMessage() . ')',
                (int) $e->getCode(),
            );
        }

        return self::$connection;
    }

    /**
     * @param array<string|int, mixed> $bindings
     */
    public static function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    /**
     * @param  array<string|int, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = self::run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string|int, mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = self::run($sql, $bindings)->fetchAll();

        return $rows;
    }

    /**
     * @param array<string|int, mixed> $bindings
     */
    public static function statement(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::connection()->lastInsertId();
    }

    /**
     * The next serial number (the "#" shown on list rows) for a table: one more
     * than the highest in use. $where narrows which rows are numbered together.
     * $table and $where are code constants, never user input.
     */
    public static function nextSerial(string $table, string $where = '1 = 1'): int
    {
        $row = self::selectOne("SELECT COALESCE(MAX(serial_no), 0) + 1 AS next FROM {$table} WHERE {$where}");

        return (int) ($row['next'] ?? 1);
    }

    /**
     * Renumber a table 1, 2, 3 ... oldest-first, closing the gap a delete leaves.
     */
    public static function resequence(string $table, string $where = '1 = 1'): void
    {
        self::run('SET @serial := 0');
        self::run("UPDATE {$table} SET serial_no = (@serial := @serial + 1) WHERE {$where} ORDER BY id");
    }

    /**
     * Wrap a user-supplied search term for a LIKE comparison, escaping the
     * wildcards so a `%` typed into a search box matches a literal per cent
     * sign instead of everything.
     */
    public static function like(string $term): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term) . '%';
    }
}
