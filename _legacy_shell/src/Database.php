<?php
/**
 * TawasulOS Database Configuration
 *
 * Loads database connection settings from config.php and provides
 * connection management utilities.
 */

namespace Tos;

class Database
{
    private static ?Database $instance = null;
    private \PDO $pdo;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    public static function getInstance(array $config = null): Database
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new \RuntimeException('Database configuration required for first instantiation');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    private function connect(): void
    {
        $host = $this->config['host'] ?? 'localhost';
        $name = $this->config['name'] ?? 'tawasul';
        $user = $this->config['user'] ?? 'tawasul';
        $pass = $this->config['pass'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
        $this->pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->query($sql, $params)->fetch() ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $this->query($sql, array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $setParts = [];
        $setValues = [];
        foreach ($data as $column => $value) {
            $setParts[] = "`{$column}` = ?";
            $setValues[] = $value;
        }

        $whereParts = [];
        $whereValues = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $whereValues[] = $value;
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
        $stmt = $this->query($sql, array_merge($setValues, $whereValues));
        return $stmt->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $whereParts = [];
        $whereValues = [];
        foreach ($where as $column => $value) {
            $whereParts[] = "`{$column}` = ?";
            $whereValues[] = $value;
        }

        $sql = "DELETE FROM `{$table}` WHERE " . implode(' AND ', $whereParts);
        $stmt = $this->query($sql, $whereValues);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}