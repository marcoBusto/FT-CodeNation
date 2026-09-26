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
}
