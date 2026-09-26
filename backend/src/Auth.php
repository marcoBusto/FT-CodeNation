<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Emite y valida los tokens de sesión (JWT) que reemplazan al header
// X-Tenant-Id manual -- ver docs/DECISIONES.md, "Resolución de tenant".
// El token viaja en el header custom X-Auth-Token en vez del estándar
// "Authorization: Bearer" porque algunas configuraciones de Apache no
// dejan pasar ese header a PHP; un header propio evita ese problema y
// sigue el mismo patrón que ya usaba X-Tenant-Id.
class Auth
{
    private const ALGORITMO = 'HS256';
    private const DURACION_SEGUNDOS = 7 * 24 * 60 * 60; // 7 días

    public static function generarToken(int $usuarioId, int $tenantId, string $nombre, string $email, int $tokenVersion): string
    {
        $ahora = time();
        $payload = [
            'sub' => $usuarioId,
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'email' => $email,
            'tv' => $tokenVersion,
            'iat' => $ahora,
            'exp' => $ahora + self::DURACION_SEGUNDOS,
        ];

        return JWT::encode($payload, self::secreto(), self::ALGORITMO);
    }

    // Lanza una excepción (capturada en index.php como 401) si el token
    // falta, es inválido, venció, o quedó invalidado por un logout
    // posterior -- así ningún endpoint puede ejecutarse sin una sesión
    // realmente vigente.
    public static function resolverDesdeToken(?string $token): array
    {
        if ($token === null || $token === '') {
            throw new DomainException('Falta iniciar sesión.');
        }

        try {
            $payload = JWT::decode($token, new Key(self::secreto(), self::ALGORITMO));
        } catch (Throwable $e) {
            throw new DomainException('La sesión no es válida o venció. Iniciá sesión de nuevo.');
        }

        $usuarioId = (int) $payload->sub;
        $tokenVersion = (int) ($payload->tv ?? 0);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT token_version FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
        $versionActual = $stmt->fetchColumn();

        if ($versionActual === false || (int) $versionActual !== $tokenVersion) {
            throw new DomainException('La sesión no es válida o venció. Iniciá sesión de nuevo.');
        }

        return [
            'usuario_id' => $usuarioId,
            'tenant_id' => (int) $payload->tenant_id,
            'nombre' => $payload->nombre,
            'email' => $payload->email,
        ];
    }

    // Invalida de una todos los tokens ya emitidos para este usuario
    // (logout real, no solo del lado del cliente).
    public static function invalidarSesiones(int $usuarioId): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE usuarios SET token_version = token_version + 1 WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
    }

    private static function secreto(): string
    {
        $secreto = Database::obtenerVariable('JWT_SECRET');
        if ($secreto === '') {
            throw new DomainException('Falta configurar JWT_SECRET en el servidor.');
        }

        return $secreto;
    }
}
