<?php
session_start();

// Database connection settings
$dbHost = 'localhost';
$dbName = 'databeis';
$dbUser = 'mario';
$dbPass = 'R2UatlIPULnKx3gw4M4i';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function ensurePostsUpdatedAt(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!tableHasColumn($pdo, 'posts', 'updated_at')) {
        try {
            $pdo->exec(
                'ALTER TABLE posts ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            );
        } catch (PDOException $e) {
            // Migration failed or column already exists, continue without breaking the app.
        }
    }
}

function getPostsDateField(PDO $pdo): string {
    return tableHasColumn($pdo, 'posts', 'updated_at') ? 'updated_at' : 'created_at';
}

function ensureUsersProfileColumns(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'last_login' => 'DATETIME NULL',
        'display_name' => 'VARCHAR(255) NULL',
        'avatar' => 'VARCHAR(255) NULL',
        'banner' => 'VARCHAR(255) NULL',
    ];

    foreach ($columns as $column => $definition) {
        if (!tableHasColumn($pdo, 'users', $column)) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
            } catch (PDOException $e) {
                // Ignore any migration error and continue.
            }
        }
    }
}

function checkLogin() {
    if (empty($_SESSION['user'])) {
        header('Location: login');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

ensureUsersProfileColumns($pdo);
