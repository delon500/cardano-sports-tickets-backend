<?php
require_once __DIR__ . "/../Config/env.php";

final class DB 
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;

        $envPath = dirname(__DIR__, 2) . "/.env";
        Env::load($envPath);

        $host = Env::get("DB_HOST", "127.0.0.1");
        $port = Env::get("DB_PORT", "3306");
        $name = Env::get("DB_NAME", "sports_tickets");
        $user = Env::get("DB_USER", "root");
        $pass = Env::get("DB_PASS", "");
        $charset = Env::get("DB_CHARSET", "utf8mb4");

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo; 
    }
}