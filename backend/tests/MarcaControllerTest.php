<?php

final class MarcaControllerTest extends DatabaseTestCase
{
    public function testCrearDevuelveElMismoIdSiYaExiste(): void
    {
        $tenantId = $this->crearTenant();
        $primera = MarcaController::crear($tenantId, ['nombre' => 'Roundup']);
        $segunda = MarcaController::crear($tenantId, ['nombre' => 'Roundup']);

        $this->assertSame($primera['id'], $segunda['id']);
    }

    public function testCrearRechazaNombreVacio(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = MarcaController::crear($tenantId, ['nombre' => '  ']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testListarSoloDevuelveMarcasDelTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $this->crearMarca($tenantA, 'Roundup');
        $this->crearMarca($tenantB, 'Panzer Gold');

        $resultado = MarcaController::listar($tenantA);

        $this->assertCount(1, $resultado);
        $this->assertSame('Roundup', $resultado[0]['nombre']);
    }
}
