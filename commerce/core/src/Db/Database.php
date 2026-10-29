<?php

declare(strict_types=1);

namespace Commerce\Core\Db;

use DateTimeInterface;
use PDO;
use PDOException;
use PDOStatement;

/** Thin PDO wrapper: exceptions on, native prepares, UTC session, transactions retried on deadlock. */
final class Database
{
    private const RETRYABLE = [1213, 1205]; // deadlock, lock wait timeout
    private int $depth = 0;

    public function __construct(private readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $pdo->exec("SET time_zone = '+00:00'");
        // READ COMMITTED avoids gap locks, so concurrent inserts of the same idempotency key wait on the unique
        // index and fail cleanly with a duplicate-key error instead of deadlocking. Requires ROW or MIXED binlog format.
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }

    public static function connect(string $dsn, string $user, string $password): self
    {
        return new self(new PDO($dsn, $user, $password, [PDO::MYSQL_ATTR_FOUND_ROWS => false]));
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Runs $fn in a transaction. A deadlock or lock timeout rolls back and retries the whole closure,
     * so closures must not have side effects outside the database. A nested call runs inside a SAVEPOINT:
     * if it throws, only its own writes are undone and the outer transaction can carry on.
     *
     * @template T
     * @param callable(self): T $fn
     * @return T
     */
    public function transaction(callable $fn, int $attempts = 4): mixed
    {
        if ($this->pdo->inTransaction()) {
            $sp = 'sp' . (++$this->depth);
            $this->pdo->exec("SAVEPOINT {$sp}");
            try {
                $result = $fn($this);
                $this->pdo->exec("RELEASE SAVEPOINT {$sp}");
                return $result;
            } catch (\Throwable $e) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
                throw $e;
            } finally {
                $this->depth--;
            }
        }
        for ($try = 1; ; $try++) {
            $this->pdo->beginTransaction();
            try {
                $result = $fn($this);
                $this->pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                if ($try < $attempts && self::isRetryable($e)) {
                    usleep(random_int(2_000, 20_000) * $try);
                    continue;
                }
                throw $e;
            }
        }
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_map(self::bindable(...), $params));
        return $stmt;
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public static function isDuplicateKey(\Throwable $e): bool
    {
        return $e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062;
    }

    public static function isRetryable(\Throwable $e): bool
    {
        return $e instanceof PDOException && in_array($e->errorInfo[1] ?? null, self::RETRYABLE, true);
    }

    public static function ts(DateTimeInterface $t): string
    {
        return $t->format('Y-m-d H:i:s.u');
    }

    private static function bindable(mixed $v): mixed
    {
        return match (true) {
            $v instanceof DateTimeInterface => self::ts($v),
            is_bool($v) => (int) $v,
            $v instanceof \BackedEnum => $v->value,
            default => $v,
        };
    }
}
