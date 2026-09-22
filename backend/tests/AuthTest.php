<?php

use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    public function testGenerarTokenYResolverDevuelveLosMismosDatos(): void
    {
        $token = Auth::generarToken(7, 3, 'Marco', 'marco@example.com');

        $resultado = Auth::resolverDesdeToken($token);

        $this->assertSame(7, $resultado['usuario_id']);
        $this->assertSame(3, $resultado['tenant_id']);
        $this->assertSame('Marco', $resultado['nombre']);
        $this->assertSame('marco@example.com', $resultado['email']);
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
