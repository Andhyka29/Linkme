<?php
declare(strict_types=1);

/**
 * Konfigurasi Database untuk Railway MySQL
 */

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Ambil dari Railway environment variables
        $host = getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost';
        $port = getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: '3306';
        $db   = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'linkhub';
        $user = getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root';
        $pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS')     ?: '';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $db
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Untuk production, jangan tampilkan error detail
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    return $pdo;
}

function initDatabase(): void
{
    $pdo = getPDO();

    // Buat tabel jika belum ada
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        url VARCHAR(500) NOT NULL,
        category VARCHAR(100) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )');
}