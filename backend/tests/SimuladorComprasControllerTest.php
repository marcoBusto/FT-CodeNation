<?php

final class SimuladorComprasControllerTest extends DatabaseTestCase
{
    public function testEstimarCalculaTotalUsdYArs(): void
    {
        $tenantId = $this->crearTenant();
        $insumoId = $this->crearInsumo($tenantId, 'Urea granulada', 'kg');

        $resultado = SimuladorComprasController::estimar($tenantId, [
            'insumo_id' => $insumoId,
            'cantidad' => '10',
            'precio_unitario_usd' => '100',
            'cotizacion' => '1487.50',
        ]);

        $this->assertSame('Urea granulada', $resultado['insumo_nombre']);
        $this->assertSame('kg', $resultado['unidad_medida']);
        $this->assertSame(1000.0, $resultado['total_usd']);
        $this->assertSame(1487500.0, $resultado['total_ars']);
    }

    public function testEstimarRechazaInsumoDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $insumoDeB = $this->crearInsumo($tenantB);

        $resultado = SimuladorComprasController::estimar($tenantA, [
            'insumo_id' => $insumoDeB,
            'cantidad' => '10',
            'precio_unitario_usd' => '5',
            'cotizacion' => '1000',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEstimarRechazaCantidadInvalida(): void
    {
        $tenantId = $this->crearTenant();
        $insumoId = $this->crearInsumo($tenantId);

        $resultado = SimuladorComprasController::estimar($tenantId, [
            'insumo_id' => $insumoId,
            'cantidad' => '0',
            'precio_unitario_usd' => '5',
            'cotizacion' => '1000',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEstimarRechazaCotizacionInvalida(): void
    {
        $tenantId = $this->crearTenant();
        $insumoId = $this->crearInsumo($tenantId);

        $resultado = SimuladorComprasController::estimar($tenantId, [
            'insumo_id' => $insumoId,
            'cantidad' => '10',
            'precio_unitario_usd' => '5',
            'cotizacion' => '-1',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }
}
