<?php
declare(strict_types=1);

namespace TuPlanner;

use PDO;

/**
 * Conexión a PostgreSQL. Los datos de acceso vienen de variables de entorno
 * (nunca escritos en el código):
 *   TUPLANNER_DB_HOST, TUPLANNER_DB_PORT, TUPLANNER_DB_NAME, TUPLANNER_DB_USER, TUPLANNER_DB_PASS
 */
final class Db
{
    public static function conectar(): PDO
    {
        $host = getenv('TUPLANNER_DB_HOST') ?: '127.0.0.1';
        $port = getenv('TUPLANNER_DB_PORT') ?: '5432';
        $name = getenv('TUPLANNER_DB_NAME') ?: 'tuplanner';
        $user = getenv('TUPLANNER_DB_USER') ?: 'tuplanner';
        $pass = getenv('TUPLANNER_DB_PASS') ?: '';

        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET TIME ZONE 'America/Guayaquil'");
        return $pdo;
    }
}
