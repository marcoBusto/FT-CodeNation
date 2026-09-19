<?php

final class MovimientoInsumoControllerTest extends DatabaseTestCase
{
    public function testEntradaSumaAlStock(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId);
        $insumoId = $this->crearInsumo($tenantId);

        $resultado = MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoId,
            'cantidad_total' => 100,
        ]);

        $this->assertArrayHasKey('id', $resultado);
        $stock = InsumoController::stock($tenantId);
        $this->assertSame(100.0, (float) $stock[0]['stock_actual']);
    }

    public function testEgresoLoteExigeLoteYRestaDelStock(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId);
        $insumoId = $this->crearInsumo($tenantId);
        $campoId = $this->crearCampo($tenantId);
        $loteId = $this->crearLote($tenantId, $campoId, 20.0);

        MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoId,
            'cantidad_total' => 100,
        ]);

        $sinLote = MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'EGRESO_LOTE',
            'insumo_id' => $insumoId,
            'cantidad_total' => 40,
        ]);
        $this->assertArrayHasKey('errores', $sinLote);

        $resultado = MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'EGRESO_LOTE',
            'insumo_id' => $insumoId,
            'lote_id' => $loteId,
            'dosis_por_ha' => 2,
            'cantidad_total' => 40,
        ]);
        $this->assertArrayHasKey('id', $resultado);

        $stock = InsumoController::stock($tenantId);
        $this->assertSame(60.0, (float) $stock[0]['stock_actual']);
    }

    public function testEgresoLoteSeRechazaSiDejaStockNegativo(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId);
        $insumoId = $this->crearInsumo($tenantId);
        $campoId = $this->crearCampo($tenantId);
        $loteId = $this->crearLote($tenantId, $campoId);

        MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoId,
            'cantidad_total' => 10,
        ]);

        $resultado = MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'EGRESO_LOTE',
            'insumo_id' => $insumoId,
            'lote_id' => $loteId,
            'cantidad_total' => 15,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
        $stock = InsumoController::stock($tenantId);
        $this->assertSame(10.0, (float) $stock[0]['stock_actual'], 'El movimiento rechazado no debe haberse guardado.');
    }

    public function testAjusteConSignoSumaYResta(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId);
        $insumoId = $this->crearInsumo($tenantId);

        MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoId,
            'cantidad_total' => 50,
        ]);

        MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'AJUSTE',
            'insumo_id' => $insumoId,
            'cantidad_total' => 5,
        ]);
        $stockTrasAjustePositivo = InsumoController::stock($tenantId)[0]['stock_actual'];
        $this->assertSame(55.0, (float) $stockTrasAjustePositivo);

        MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'AJUSTE',
            'insumo_id' => $insumoId,
            'cantidad_total' => -20,
        ]);
        $stockTrasAjusteNegativo = InsumoController::stock($tenantId)[0]['stock_actual'];
        $this->assertSame(35.0, (float) $stockTrasAjusteNegativo);
    }

    public function testAjusteNoPuedeDejarStockNegativo(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId);
        $insumoId = $this->crearInsumo($tenantId);

        MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoId,
            'cantidad_total' => 10,
        ]);

        $resultado = MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'AJUSTE',
            'insumo_id' => $insumoId,
            'cantidad_total' => -30,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testMovimientosEstanAisladosPorTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $this->crearUsuario($tenantA, 'a@example.com');
        $insumoA = $this->crearInsumo($tenantA, 'Glifosato');

        $tenantB = $this->crearTenant('Tenant B');
        $this->crearUsuario($tenantB, 'b@example.com');
        $insumoB = $this->crearInsumo($tenantB, 'Glifosato');

        MovimientoInsumoController::registrar($tenantA, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoA,
            'cantidad_total' => 100,
        ]);

        $stockA = InsumoController::stock($tenantA);
        $stockB = InsumoController::stock($tenantB);

        $this->assertSame(100.0, (float) $stockA[0]['stock_actual']);
        $this->assertSame(0.0, (float) $stockB[0]['stock_actual']);
    }

    public function testRegistrarRechazaInsumoDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $this->crearUsuario($tenantA, 'a@example.com');
        $insumoA = $this->crearInsumo($tenantA);

        $tenantB = $this->crearTenant('Tenant B');
        $this->crearUsuario($tenantB, 'b@example.com');

        $resultado = MovimientoInsumoController::registrar($tenantB, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoA,
            'cantidad_total' => 10,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEntradaRechazaCantidadNoPositiva(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearUsuario($tenantId);
        $insumoId = $this->crearInsumo($tenantId);

        $resultado = MovimientoInsumoController::registrar($tenantId, [
            'tipo' => 'ENTRADA',
            'insumo_id' => $insumoId,
            'cantidad_total' => 0,
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }
}
