<?php
declare(strict_types=1);

namespace FixListed\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Connects lazily, so a page that serves entirely from cache
 * never opens a connection — which matters on shared hosting, where the
 * concurrent-connection limit is low and shared with every other site on the
 * account.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly array $settings)
    {
    }

    public static function fromConfig(): self
    {
        return new self([
            'host'    => Config::get('db.host', 'localhost'),
            'port'    => (int) Config::get('db.port', 3306),
            'name'    => Config::require('db.name'),
            'user'    => Config::require('db.user'),
            'pass'    => (string) Config::get('db.pass', ''),
            'charset' => Config::get('db.charset', 'utf8mb4'),
        ]);
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        // charset in the DSN is not optional: without it the client negotiates
        // latin1 and every em-dash and accented name comes back mangled.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->settings['host'],
            $this->settings['port'],
            $this->settings['name'],
            $this->settings['charset'],
        );

        try {
            $this->pdo = new PDO($dsn, $this->settings['user'], $this->settings['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, not client-side interpolation.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            // The message carries the credentials; never let it reach a visitor.
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo()->lastInsertId();
    }

    public function affected(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /** Runs $work in a transaction, rolling back on any exception. */
    public function transaction(callable $work): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $work($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
