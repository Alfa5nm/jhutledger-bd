<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('JHUTLEDGER_DB_HOST') ?: '127.0.0.1';
    $port = getenv('JHUTLEDGER_DB_PORT') ?: '3306';
    $name = getenv('JHUTLEDGER_DB_NAME') ?: 'jhutledger_db';
    $user = getenv('JHUTLEDGER_DB_USER') ?: 'root';
    $password = getenv('JHUTLEDGER_DB_PASSWORD') ?: '';
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
