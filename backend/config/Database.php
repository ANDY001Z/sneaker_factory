<?php

declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = Config::require('DB_HOST');
            $port = Config::get('DB_PORT', '5432');
            $name = Config::require('DB_NAME');
            $user = Config::require('DB_USER');
            $pass = Config::require('DB_PASSWORD');

            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=5',
                $host,
                $port,
                $name
            );

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new PDOException('No se pudo establecer conexión con la base de datos.', (int) $e->getCode(), $e);
            }
        }

        return self::$connection;
    }

    public static function ping(): bool
    {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}