<?php

class AuthController
{
    public static function login(array $datos): array
    {
        $email = trim($datos['email'] ?? '');
        $contrasena = $datos['contrasena'] ?? '';

        if ($email === '' || $contrasena === '') {
            return ['errores' => ['Falta email o contraseña.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT u.id, u.tenant_id, u.nombre, u.email, u.password_hash, u.token_version
             FROM usuarios u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = :email AND u.estado = 'activo' AND t.estado = 'activo'"
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($contrasena, $usuario['password_hash'])) {
            return ['errores' => ['Email o contraseña incorrectos.']];
        }

        $token = Auth::generarToken(
            (int) $usuario['id'],
            (int) $usuario['tenant_id'],
            $usuario['nombre'],
            $usuario['email'],
            (int) $usuario['token_version']
        );

        return [
            'token' => $token,
            'usuario' => [
                'id' => (int) $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email' => $usuario['email'],
            ],
        ];
    }

    // Invalida el token actual (y cualquier otro ya emitido) del lado del
    // servidor -- antes "cerrar sesión" solo borraba el token en el
    // navegador, y seguía siendo válido en la API hasta que venciera solo.
    public static function logout(int $usuarioId): array
    {
        Auth::invalidarSesiones($usuarioId);

        return ['ok' => true];
    }

    public const LARGO_MINIMO_CONTRASENA = 8;

    // Cambia la contraseña del usuario logueado. Exige la actual (así una
    // sesión olvidada abierta en otra compu no alcanza para cambiarla) e
    // invalida todas las sesiones anteriores -- si alguien más conocía la
    // contraseña vieja, queda afuera. Devuelve un token nuevo para que quien
    // la cambió siga logueado sin tener que ingresar de nuevo.
    public static function cambiarContrasena(int $usuarioId, array $datos): array
    {
        $actual = $datos['contrasena_actual'] ?? '';
        $nueva = $datos['contrasena_nueva'] ?? '';

        if ($actual === '' || $nueva === '') {
            return ['errores' => ['Falta la contraseña actual o la nueva.']];
        }
        if (mb_strlen($nueva) < self::LARGO_MINIMO_CONTRASENA) {
            return ['errores' => ['La contraseña nueva debe tener al menos ' . self::LARGO_MINIMO_CONTRASENA . ' caracteres.']];
        }
        if ($nueva === $actual) {
            return ['errores' => ['La contraseña nueva tiene que ser distinta de la actual.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, tenant_id, nombre, email, password_hash FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($actual, $usuario['password_hash'])) {
            return ['errores' => ['La contraseña actual no es correcta.']];
        }

        $stmt = $pdo->prepare(
            'UPDATE usuarios SET password_hash = :hash, token_version = token_version + 1 WHERE id = :id'
        );
        $stmt->execute(['hash' => password_hash($nueva, PASSWORD_DEFAULT), 'id' => $usuarioId]);

        $version = $pdo->prepare('SELECT token_version FROM usuarios WHERE id = :id');
        $version->execute(['id' => $usuarioId]);

        return [
            'token' => Auth::generarToken(
                (int) $usuario['id'],
                (int) $usuario['tenant_id'],
                $usuario['nombre'],
                $usuario['email'],
                (int) $version->fetchColumn()
            ),
        ];
    }
}
