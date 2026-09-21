<?php

final class CombustibleControllerTest extends DatabaseTestCase
{
    public function testEstimarCalculaLitrosYCostoEnBaseAHectareas(): void
    {
        $tenantId = $this->crearTenant();
        $laborId = $this->crearLabor($tenantId, 'Pulverización', 3.0);
        $estacionId = $this->crearEstacionCombustible($tenantId, 'YPF Leones', 900.0);

        $resultado = CombustibleController::estimar($tenantId, [
            'hectareas' => '100',
            'labor_id' => $laborId,
            'estacion_id' => $estacionId,
        ]);

        $this->assertSame(300.0, $resultado['litros_estimados']);
        $this->assertSame(270000.0, $resultado['costo_estimado']);
        $this->assertSame('Pulverización', $resultado['labor_nombre']);
        $this->assertSame('YPF Leones', $resultado['estacion_nombre']);
    }

    public function testEstimarRechazaHectareasInvalidas(): void
    {
        $tenantId = $this->crearTenant();
        $laborId = $this->crearLabor($tenantId);
        $estacionId = $this->crearEstacionCombustible($tenantId);

        $resultado = CombustibleController::estimar($tenantId, [
            'hectareas' => '0',
            'labor_id' => $laborId,
            'estacion_id' => $estacionId,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEstimarRechazaLaborDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $laborDeB = $this->crearLabor($tenantB);
        $estacionDeA = $this->crearEstacionCombustible($tenantA);

        $resultado = CombustibleController::estimar($tenantA, [
            'hectareas' => '50',
            'labor_id' => $laborDeB,
            'estacion_id' => $estacionDeA,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEstimarRechazaEstacionDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $laborDeA = $this->crearLabor($tenantA);
        $estacionDeB = $this->crearEstacionCombustible($tenantB);

        $resultado = CombustibleController::estimar($tenantA, [
            'hectareas' => '50',
            'labor_id' => $laborDeA,
            'estacion_id' => $estacionDeB,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }
}
