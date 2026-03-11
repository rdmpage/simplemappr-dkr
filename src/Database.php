<?php
/**
 * Database class for SimpleMappr
 *
 * SQLite database singleton using PDO.
 */

declare(strict_types=1);

namespace SimpleMappr;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        $dbPath = DATABASE_PATH;
        $dbDir = dirname($dbPath);

        // Create directory if it doesn't exist
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $isNewDb = !file_exists($dbPath);

        $this->pdo = new PDO(
            'sqlite:' . $dbPath,
            null,
            null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        // Enable foreign keys
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // Initialize schema if new database
        if ($isNewDb) {
            $this->initializeSchema();
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepare a statement
     */
    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    /**
     * Execute a query and return all results
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return first result
     */
    public function queryOne(string $sql, array $params = []): ?object
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Execute an insert and return last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Execute an update
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = array_map(fn($col) => "$col = :$col", array_keys($data));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            $where
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));

        return $stmt->rowCount();
    }

    /**
     * Execute a delete
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM %s WHERE %s', $table, $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Initialize database schema
     */
    private function initializeSchema(): void
    {
        $schema = <<<SQL
-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider TEXT NOT NULL CHECK (provider IN ('orcid', 'google')),
    provider_id TEXT NOT NULL,
    email TEXT,
    display_name TEXT,
    given_name TEXT,
    family_name TEXT,
    role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'administrator')),
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    last_login_at TEXT,
    UNIQUE (provider, provider_id)
);

CREATE INDEX IF NOT EXISTS idx_users_provider ON users(provider, provider_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Maps table
CREATE TABLE IF NOT EXISTS maps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    config TEXT NOT NULL,
    imported_at TEXT,
    import_source TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE (user_id, title)
);

CREATE INDEX IF NOT EXISTS idx_maps_user_id ON maps(user_id);
CREATE INDEX IF NOT EXISTS idx_maps_created_at ON maps(created_at);
CREATE INDEX IF NOT EXISTS idx_maps_title ON maps(title);

-- Sessions table
CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    data TEXT,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    expires_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expires_at ON sessions(expires_at);

-- Shares table
CREATE TABLE IF NOT EXISTS shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    map_id INTEGER NOT NULL UNIQUE,
    token TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
    FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_shares_token ON shares(token);

-- Trigger to update maps.updated_at
CREATE TRIGGER IF NOT EXISTS update_maps_timestamp
AFTER UPDATE ON maps
BEGIN
    UPDATE maps SET updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now')
    WHERE id = NEW.id;
END;
SQL;

        $this->pdo->exec($schema);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
