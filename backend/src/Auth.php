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

    public static function generarToken(int $usuarioId, int $tenantId, string $nombre, string $email): string
    {
        $ahora = time();
        $payload = [
            'sub' => $usuarioId,
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'email' => $email,
            'iat' => $ahora,
            'exp' => $ahora + self::DURACION_SEGUNDOS,
        ];

        return JWT::encode($payload, self::secreto(), self::ALGORITMO);
    }

    // Lanza una excepción (capturada en index.php como 401) si el token
    // falta, es inválido, o venció -- así ningún endpoint puede ejecutarse
    // sin una sesión válida.
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

        return [
            'usuario_id' => (int) $payload->sub,
            'tenant_id' => (int) $payload->tenant_id,
            'nombre' => $payload->nombre,
            'email' => $payload->email,
        ];
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
