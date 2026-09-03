<?php
/**
 * Database.php — Gestiona la conexión a MySQL mediante PDO.
 * Usa un patrón de conexión única: se abre UNA sola conexión por
 * petición y se reutiliza, en lugar de abrir varias.
 */
class Database
{
    private static ?PDO $connection = null;

    /**
     * Devuelve la conexión PDO activa; la crea si aún no existe.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            // Cargamos las credenciales desde el archivo de configuración
            $config = require __DIR__ . '/../config/database.php';

            // DSN (Data Source Name): describe a qué base conectarse
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['dbname'],
                $config['charset']
            );

            // Opciones que definen el comportamiento de PDO
            $options = [
                // Ante cualquier error, lanza una excepción (permite responder 500 limpio)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Devuelve las filas como arreglos asociativos (ideal para json_encode)
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Usa sentencias preparadas REALES del servidor, no emuladas por PHP
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $options
            );
        }

        return self::$connection;
    }
}