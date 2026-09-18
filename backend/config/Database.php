<?php

// Conexión PDO a MySQL/MariaDB. Las credenciales se leen de variables de
// entorno (nunca hardcodeadas) para poder cambiar de entorno (local ->
// producción) sin tocar código.

class Database
{
    private static ?PDO $connection = null;

    // PHP no carga backend/.env solo: getenv() lee variables de entorno del
    // sistema operativo, no archivos .env. Se parsea a mano acá en vez de
    // sumar una dependencia de Composer (ej. vlucas/phpdotenv) solo para esto.
    private static function cargarEnv(): void
    {
        $rutaEnv = __DIR__ . '/../.env';
        if (!file_exists($rutaEnv)) {
            return;
        }

        foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            if (getenv($clave) === false) {
                putenv($clave . '=' . trim($valor));
            }
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::cargarEnv();
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $database = getenv('DB_DATABASE') ?: 'nombre_de_la_base'; // TODO: completar
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$connection;
    }
}
