<?php

final class LoteControllerTest extends DatabaseTestCase
{
    public function testCrearGuardaPoligonoYPerimetro(): void
    {
        $tenantId = $this->crearTenant();
        $campoId = $this->crearCampo($tenantId);
        $poligono = [
            ['lat' => -33.8, 'lng' => -61.5],
            ['lat' => -33.81, 'lng' => -61.5],
            ['lat' => -33.81, 'lng' => -61.49],
        ];

        $resultado = LoteController::crear($tenantId, [
            'campo_id' => $campoId,
            'nombre' => 'Lote Norte',
            'hectareas' => 25.5,
            'perimetro_metros' => 1200.4,
            'poligono' => $poligono,
        ]);

        $this->assertArrayHasKey('id', $resultado);

        $lotes = LoteController::listar($tenantId);
        $this->assertSame(1200.4, (float) $lotes[0]['perimetro_metros']);
        $this->assertSame($poligono, $lotes[0]['poligono']);
    }

    public function testCrearPermitePoligonoVacio(): void
    {
        $tenantId = $this->crearTenant();
        $campoId = $this->crearCampo($tenantId);

        $resultado = LoteController::crear($tenantId, [
            'campo_id' => $campoId,
            'nombre' => 'Lote Sur',
            'hectareas' => 10,
        ]);

        $this->assertArrayHasKey('id', $resultado);
        $lotes = LoteController::listar($tenantId);
        $this->assertNull($lotes[0]['poligono']);
    }

    public function testCrearRechazaPoligonoConMenosDeTresPuntos(): void
    {
        $tenantId = $this->crearTenant();
        $campoId = $this->crearCampo($tenantId);

        $resultado = LoteController::crear($tenantId, [
            'campo_id' => $campoId,
            'nombre' => 'Lote Inválido',
            'hectareas' => 10,
            'poligono' => [['lat' => -33.8, 'lng' => -61.5], ['lat' => -33.81, 'lng' => -61.5]],
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEstimarInsumoMultiplicaHectareasPorDosis(): void
    {
        $resultado = LoteController::estimarInsumo(['hectareas' => 20, 'dosis_por_ha' => 150]);

        $this->assertSame(3000.0, $resultado['cantidad_total_estimada']);
    }

    public function testEstimarInsumoRechazaValoresNoPositivos(): void
    {
        $resultado = LoteController::estimarInsumo(['hectareas' => 0, 'dosis_por_ha' => 150]);

        $this->assertArrayHasKey('errores', $resultado);
    }
}
