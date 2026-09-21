<?php

final class LaborControllerTest extends DatabaseTestCase
{
    public function testCrearGuardaLitrosPorHectarea(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = LaborController::crear($tenantId, ['nombre' => 'Siembra directa', 'litros_por_hectarea' => '8']);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame(8.0, (float) LaborController::listar($tenantId)[0]['litros_por_hectarea']);
    }

    public function testCrearRechazaLitrosNegativosOCero(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = LaborController::crear($tenantId, ['nombre' => 'Siembra directa', 'litros_por_hectarea' => '0']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCrearRechazaNombreDuplicadoEnElMismoTenant(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearLabor($tenantId, 'Pulverización');

        $resultado = LaborController::crear($tenantId, ['nombre' => 'Pulverización', 'litros_por_hectarea' => '3']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testListarSoloDevuelveLaboresDelTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $this->crearLabor($tenantA, 'Pulverización');
        $this->crearLabor($tenantB, 'Cosecha');

        $resultado = LaborController::listar($tenantA);

        $this->assertCount(1, $resultado);
        $this->assertSame('Pulverización', $resultado[0]['nombre']);
    }

    public function testEditarActualizaLitrosPorHectarea(): void
    {
        $tenantId = $this->crearTenant();
        $laborId = $this->crearLabor($tenantId, 'Pulverización', 3.0);

        $resultado = LaborController::editar($tenantId, $laborId, ['nombre' => 'Pulverización', 'litros_por_hectarea' => '3.5']);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame(3.5, (float) LaborController::listar($tenantId)[0]['litros_por_hectarea']);
    }

    public function testEliminarLaSacaDelListado(): void
    {
        $tenantId = $this->crearTenant();
        $laborId = $this->crearLabor($tenantId);

        $resultado = LaborController::eliminar($tenantId, $laborId);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, LaborController::listar($tenantId));
    }

    public function testEliminarRechazaLaborDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $laborDeA = $this->crearLabor($tenantA);

        $resultado = LaborController::eliminar($tenantB, $laborDeA);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertCount(1, LaborController::listar($tenantA));
    }
}
