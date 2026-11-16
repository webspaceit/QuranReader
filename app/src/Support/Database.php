<?php
declare(strict_types=1);

namespace Quran\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thin PDO wrapper.
 *
 * Every query in this project goes through prepared statements here - there is
 * no string interpolation of user input into SQL anywhere in the code base.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) Config::get('database.host', 'localhost');
        $port    = (int) Config::get('database.port', 3306);
        $name    = (string) Config::get('database.database', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        try {
            self::$pdo = new PDO(
                $dsn,
                (string) Config::get('database.username', ''),
                (string) Config::get('database.password', ''),
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                    PDO::ATTR_TIMEOUT            => 10,
                ]
            );
            self::$pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage(), ['dsn' => $dsn]);
            throw new RuntimeException(
                'Could not connect to the database. Check the credentials in app/config/config.php.',
                0,
                $e
            );
        }

        return self::$pdo;
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function first(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $params
     * @return mixed
     */
    public static function value(string $sql, array $params = [])
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $params
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::execute($sql, $params);
        return (int) self::connection()->lastInsertId();
    }

    /**
     * Run a callback inside a transaction.
     *
     * @return mixed
     */
    public static function transaction(callable $callback)
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Used by the installer/health check. */
    public static function ping(): bool
    {
        try {
            return (int) self::value('SELECT 1') === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
