<?php
declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Config\AppConfig;
use PDO;

/**
 * PostgreSQL PDO connection factory.
 */
class PdoFactory
{
    public static function create(): PDO
    {
        $host = AppConfig::require('PG_HOST');
        $port = AppConfig::get('PG_PORT', '5432');
        $database = AppConfig::require('PG_DB');
        $user = AppConfig::require('PG_USER');
        $password = AppConfig::require('PG_PASSWORD');
        $sslMode = AppConfig::get('PG_SSLMODE', 'prefer');

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $host,
            $port,
            $database,
            $sslMode
        );

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            return $pdo;
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Failed to connect to PostgreSQL database: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }
}
