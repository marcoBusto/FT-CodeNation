<?php

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class AuthTest extends DatabaseTestCase
{
    public function testGenerarTokenYResolverDevuelveLosMismosDatos(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');

        // token_version 0 porque crearUsuario() no lo toca y esa es la
        // columna por default -- Auth::resolverDesdeToken ahora exige que
        // coincida con la del usuario en la base, no solo que la firma sea
        // válida.
        $token = Auth::generarToken($usuarioId, $tenantId, 'Marco', 'marco@example.com', 0);

        $resultado = Auth::resolverDesdeToken($token);

        $this->assertSame($usuarioId, $resultado['usuario_id']);
        $this->assertSame($tenantId, $resultado['tenant_id']);
        $this->assertSame('Marco', $resultado['nombre']);
        $this->assertSame('marco@example.com', $resultado['email']);
    }

    public function testResolverRechazaTokenConVersionVencidaPorLogout(): void
    {
        $tenantId = $this->crearTenant();
        $usuarioId = $this->crearUsuario($tenantId, 'marco@example.com');

        $token = Auth::generarToken($usuarioId, $tenantId, 'Marco', 'marco@example.com', 0);
        Auth::invalidarSesiones($usuarioId);

        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken($token);
    }

    public function testResolverRechazaTokenVacio(): void
    {
        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken('');
    }

    public function testResolverRechazaTokenInvalido(): void
    {
        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken('esto-no-es-un-token-valido');
    }

    public function testResolverRechazaTokenExpirado(): void
    {
        $payload = [
            'sub' => 1,
            'tenant_id' => 1,
            'nombre' => 'Test',
            'email' => 'test@example.com',
            'iat' => time() - 1000,
            'exp' => time() - 500,
        ];
        $tokenVencido = JWT::encode($payload, 'secreto-de-test-no-usar-en-produccion', 'HS256');

        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken($tokenVencido);
    }

    public function testResolverRechazaTokenFirmadoConOtroSecreto(): void
    {
        $payload = [
            'sub' => 1,
            'tenant_id' => 1,
            'nombre' => 'Test',
            'email' => 'test@example.com',
            'iat' => time(),
            'exp' => time() + 3600,
        ];
        $tokenApocrifo = JWT::encode($payload, 'otro-secreto-cualquiera-pero-igual-de-largo-que-el-real', 'HS256');

        $this->expectException(DomainException::class);
        Auth::resolverDesdeToken($tokenApocrifo);
    }
}
