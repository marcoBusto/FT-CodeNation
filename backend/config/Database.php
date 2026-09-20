<?php

// Conexión PDO a MySQL/MariaDB. Las credenciales se leen de variables de
// entorno (nunca hardcodeadas) para poder cambiar de entorno (local ->
// producción) sin tocar código.

class Database
{
    private static ?PDO $connection = null;

    // Algunos hostings compartidos deshabilitan putenv() por seguridad (el
    // valor se setea igual, pero getenv() nunca lo ve). Este array es el
    // respaldo para esos casos: cargarEnv() lo llena siempre, además de
    // intentar putenv(), y obtenerVariable() lo usa si getenv() no sirvió.
    private static array $env = [];

    // Público porque index.php necesita el .env cargado desde el arranque
    // (ej. para armar el header CORS con FRONTEND_URL), no recién cuando se
    // abre la primera conexión a la base de datos.
    public static function inicializarEnv(): void
    {
        self::cargarEnv();
    }

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
            $valor = trim($valor);
            if (getenv($clave) === false && !isset(self::$env[$clave])) {
                self::$env[$clave] = $valor;
                @putenv($clave . '=' . $valor);
            }
        }
    }

    public static function obtenerVariable(string $clave, string $porDefecto = ''): string
    {
        $valor = getenv($clave);

        return $valor !== false ? $valor : (self::$env[$clave] ?? $porDefecto);
    }

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::cargarEnv();
            $host = self::obtenerVariable('DB_HOST', '127.0.0.1');
            $port = self::obtenerVariable('DB_PORT', '3306');
            $database = self::obtenerVariable('DB_DATABASE', 'nombre_de_la_base'); // TODO: completar
            $username = self::obtenerVariable('DB_USERNAME', 'root');
            $password = self::obtenerVariable('DB_PASSWORD', '');

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
