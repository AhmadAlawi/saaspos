<?php

namespace App\Actions\Installer;

use PDO;
use PDOException;

class TestDatabaseConnection
{
    /**
     * @return array{ok: bool, error?: string, server_version?: string}
     */
    public function __invoke(string $host, int $port, string $database, string $username, string $password): array
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            return [
                'ok'             => true,
                'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            ];
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
