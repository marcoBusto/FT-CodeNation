<?php

final class ReporteControllerTest extends DatabaseTestCase
{
    public function testPdfCamposLotesGeneraUnPdfValido(): void
    {
        $tenantId = $this->crearTenant();
        $campoId = $this->crearCampo($tenantId, 'Campo Norte');
        $this->crearLote($tenantId, $campoId, 25.5, 'Lote 1');

        $pdf = ReporteController::pdfCamposLotes($tenantId);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testPdfCamposLotesFuncionaSinDatosCargados(): void
    {
        $tenantId = $this->crearTenant();

        $pdf = ReporteController::pdfCamposLotes($tenantId);

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testPdfInsumosStockGeneraUnPdfValido(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearInsumo($tenantId, 'Glifosato');

        $pdf = ReporteController::pdfInsumosStock($tenantId);

        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
