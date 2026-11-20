<?php
/**
 * Conexión PDO a MySQL (singleton).
 * Uso: $pdo = Database::conexion();
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? 'prode';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepares nativos de MySQL
            ]);
        }
        return self::$pdo;
    }
}
