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
}
