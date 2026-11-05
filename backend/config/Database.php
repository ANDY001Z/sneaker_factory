<?php

namespace Config;

use PDO;
use PDOException;
use Exception;

class Database {
    private static ?PDO $connection = null;

    // Prevenimos la instanciación directa
    private function __construct() {}

    /**
     * Obtiene la instancia única de la conexión a la base de datos (Singleton).
     */
    public static function getConnection(): PDO {
        if (self::$connection === null) {
            try {
                // Recuperamos las variables de entorno inyectadas por Docker
                $host = getenv('DB_HOST') ?: 'db';
                $port = getenv('DB_PORT') ?: '5432';
                $dbName = getenv('DB_NAME');
                $user = getenv('DB_USER');
                $password = getenv('DB_PASSWORD');

                if (!$dbName || !$user || !$password) {
                    throw new Exception("Faltan credenciales de base de datos en el entorno.");
                }

                $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";
                
                self::$connection = new PDO($dsn, $user, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores SQL
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Devuelve arrays asociativos por defecto
                    PDO::ATTR_EMULATE_PREPARES => false, // Mayor seguridad contra Inyección SQL
                ]);

            } catch (PDOException $e) {
                // En producción, no deberíamos mostrar el mensaje real del error por seguridad
                // error_log("Error de conexión a la BD: " . $e->getMessage());
                throw new Exception("Error interno del servidor al conectar con la base de datos.");
            }
        }

        return self::$connection;
    }
}
