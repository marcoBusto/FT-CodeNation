<?php

final class AuthControllerTest extends DatabaseTestCase
{
    public function testLoginConCredencialesValidasDevuelveToken(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId, 'marco@example.com');

        $resultado = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);

        $this->assertArrayHasKey('token', $resultado);
        $this->assertSame('marco@example.com', $resultado['usuario']['email']);

        $sesion = Auth::resolverDesdeToken($resultado['token']);
        $this->assertSame($tenantId, $sesion['tenant_id']);
    }

    public function testLoginRechazaContrasenaIncorrecta(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId, 'marco@example.com');

        $resultado = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'mala']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testLoginRechazaEmailInexistente(): void
    {
        $resultado = AuthController::login(['email' => 'nadie@example.com', 'contrasena' => 'secreto']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testLoginRechazaUsuarioInactivo(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');
        $this->pdo->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id = :id")->execute(['id' => $usuarioId]);

        $resultado = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testLoginRechazaTenantInactivo(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId, 'marco@example.com');
        $this->pdo->prepare("UPDATE tenants SET estado = 'inactivo' WHERE id = :id")->execute(['id' => $tenantId]);

        $resultado = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testLoginRechazaCamposFaltantes(): void
    {
        $resultado = AuthController::login(['email' => 'marco@example.com']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testLogoutInvalidaElTokenAnterior(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId, 'marco@example.com');

        $login = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);
        $sesion = Auth::resolverDesdeToken($login['token']);

        AuthController::logout($sesion['usuario_id']);

        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken($login['token']);
    }

    public function testLoginDespuesDeLogoutEmiteUnTokenNuevoValido(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId, 'marco@example.com');

        $primerLogin = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);
        $sesion = Auth::resolverDesdeToken($primerLogin['token']);
        AuthController::logout($sesion['usuario_id']);

        $segundoLogin = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);
        $sesionNueva = Auth::resolverDesdeToken($segundoLogin['token']);

        $this->assertSame($sesion['usuario_id'], $sesionNueva['usuario_id']);
    }

    public function testCambiarContrasenaPermiteIngresarConLaNueva(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');

        $resultado = AuthController::cambiarContrasena($usuarioId, [
            'contrasena_actual' => 'secreto',
            'contrasena_nueva' => 'otra-clave-larga',
        ]);

        $this->assertArrayHasKey('token', $resultado);
        $this->assertArrayHasKey('errores', AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']));
        $this->assertArrayHasKey('token', AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'otra-clave-larga']));
    }

    public function testCambiarContrasenaInvalidaSesionesAnterioresYDevuelveTokenValido(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');
        $loginViejo = AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']);

        $resultado = AuthController::cambiarContrasena($usuarioId, [
            'contrasena_actual' => 'secreto',
            'contrasena_nueva' => 'otra-clave-larga',
        ]);

        $this->assertSame($usuarioId, Auth::resolverDesdeToken($resultado['token'])['usuario_id']);

        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken($loginViejo['token']);
    }

    public function testCambiarContrasenaRechazaActualIncorrecta(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');

        $resultado = AuthController::cambiarContrasena($usuarioId, [
            'contrasena_actual' => 'mala',
            'contrasena_nueva' => 'otra-clave-larga',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertArrayHasKey('token', AuthController::login(['email' => 'marco@example.com', 'contrasena' => 'secreto']));
    }

    public function testCambiarContrasenaRechazaNuevaCorta(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');

        $resultado = AuthController::cambiarContrasena($usuarioId, [
            'contrasena_actual' => 'secreto',
            'contrasena_nueva' => 'corta',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCambiarContrasenaRechazaNuevaIgualALaActual(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');
        $this->pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash('clave-larga-1', PASSWORD_DEFAULT), 'id' => $usuarioId]);

        $resultado = AuthController::cambiarContrasena($usuarioId, [
            'contrasena_actual' => 'clave-larga-1',
            'contrasena_nueva' => 'clave-larga-1',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCambiarContrasenaRechazaCamposFaltantes(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');

        $this->assertArrayHasKey('errores', AuthController::cambiarContrasena($usuarioId, ['contrasena_actual' => 'secreto']));
    }
}
