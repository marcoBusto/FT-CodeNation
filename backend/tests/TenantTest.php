<?php

final class TenantTest extends DatabaseTestCase
{
    public function testRechazaHeaderFaltante(): void
    {
        $this->expectException(DomainException::class);

        Tenant::resolverDesdeHeader(null);
    }

    public function testRechazaHeaderNoNumerico(): void
    {
        $this->expectException(DomainException::class);

        Tenant::resolverDesdeHeader('abc');
    }

    public function testRechazaTenantInexistente(): void
    {
        $this->expectException(DomainException::class);

        Tenant::resolverDesdeHeader('999999');
    }

    public function testRechazaTenantInactivo(): void
    {
        $tenantId = $this->crearTenant();
        $this->pdo->prepare("UPDATE tenants SET estado = 'inactivo' WHERE id = :id")->execute(['id' => $tenantId]);

        $this->expectException(DomainException::class);

        Tenant::resolverDesdeHeader((string) $tenantId);
    }

    public function testResuelveTenantActivo(): void
    {
        $tenantId = $this->crearTenant();

        $this->assertSame($tenantId, Tenant::resolverDesdeHeader((string) $tenantId));
    }
}
