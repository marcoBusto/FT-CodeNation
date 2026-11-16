<?php

final class SiembraControllerTest extends DatabaseTestCase
{
    private int $tenantId;
    private int $loteId;
    private int $sojaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = $this->crearTenant();
        $this->loteId = $this->crearLote($this->tenantId, $this->crearCampo($this->tenantId));
        $this->sojaId = $this->crearCultivo($this->tenantId, 'Soja');
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'lote_id' => $this->loteId,
            'cultivo_id' => $this->sojaId,
            'campania' => '2025/26',
        ];
    }

    public function testCrearGuardaLaSiembraConSusFechas(): void
    {
        $resultado = SiembraController::crear($this->tenantId, $this->datos([
            'fecha_siembra' => '2025-11-10',
            'fecha_cosecha' => '2026-04-20',
        ]));

        $this->assertArrayHasKey('id', $resultado);
        $siembra = SiembraController::listar($this->tenantId)[0];
        $this->assertSame('Soja', $siembra['cultivo_nombre']);
        $this->assertSame('2025/26', $siembra['campania']);
        $this->assertSame('2025-11-10', $siembra['fecha_siembra']);
        $this->assertSame('2026-04-20', $siembra['fecha_cosecha']);
    }

    public function testLasFechasSonOpcionales(): void
    {
        $resultado = SiembraController::crear($this->tenantId, $this->datos(['fecha_siembra' => '', 'fecha_cosecha' => '']));

        $this->assertArrayHasKey('id', $resultado);
        $this->assertNull(SiembraController::listar($this->tenantId)[0]['fecha_siembra']);
    }

    public function testRechazaCampaniaMalFormada(): void
    {
        foreach (['2025', '2025-26', '25/26', '2025/27', '2025/2026', ''] as $campania) {
            $resultado = SiembraController::crear($this->tenantId, $this->datos(['campania' => $campania]));
            $this->assertArrayHasKey('errores', $resultado, "Debería rechazar la campaña '{$campania}'");
        }
    }

    public function testAceptaElCambioDeSiglo(): void
    {
        $resultado = SiembraController::crear($this->tenantId, $this->datos(['campania' => '2099/00']));

        $this->assertArrayHasKey('id', $resultado);
    }

    public function testRechazaCosechaAnteriorALaSiembra(): void
    {
        $resultado = SiembraController::crear($this->tenantId, $this->datos([
            'fecha_siembra' => '2025-11-10',
            'fecha_cosecha' => '2025-10-01',
        ]));

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testRechazaFechaInexistente(): void
    {
        $resultado = SiembraController::crear($this->tenantId, $this->datos(['fecha_siembra' => '2025-02-30']));

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testRechazaElMismoCultivoDosVecesEnLaMismaCampania(): void
    {
        SiembraController::crear($this->tenantId, $this->datos());

        $repetida = SiembraController::crear($this->tenantId, $this->datos());

        $this->assertArrayHasKey('errores', $repetida);
        $this->assertCount(1, SiembraController::listar($this->tenantId));
    }

    // Doble cultivo: trigo y soja de segunda en el mismo lote y campaña.
    public function testPermiteDosCultivosDistintosEnLaMismaCampania(): void
    {
        $trigoId = $this->crearCultivo($this->tenantId, 'Trigo');
        SiembraController::crear($this->tenantId, $this->datos(['cultivo_id' => $trigoId]));

        $segunda = SiembraController::crear($this->tenantId, $this->datos());

        $this->assertArrayHasKey('id', $segunda);
        $this->assertCount(2, SiembraController::listar($this->tenantId));
    }

    public function testPermiteElMismoCultivoEnOtraCampania(): void
    {
        SiembraController::crear($this->tenantId, $this->datos());

        $otra = SiembraController::crear($this->tenantId, $this->datos(['campania' => '2026/27']));

        $this->assertArrayHasKey('id', $otra);
    }

    public function testRechazaLoteOCultivoDeOtroTenant(): void
    {
        $otroTenant = $this->crearTenant('Otro');
        $loteAjeno = $this->crearLote($otroTenant, $this->crearCampo($otroTenant));
        $cultivoAjeno = $this->crearCultivo($otroTenant, 'Maíz');

        $conLoteAjeno = SiembraController::crear($this->tenantId, $this->datos(['lote_id' => $loteAjeno]));
        $conCultivoAjeno = SiembraController::crear($this->tenantId, $this->datos(['cultivo_id' => $cultivoAjeno]));

        $this->assertArrayHasKey('errores', $conLoteAjeno);
        $this->assertArrayHasKey('errores', $conCultivoAjeno);
    }

    public function testListarSoloDevuelveSiembrasDelTenantYFiltraPorLote(): void
    {
        $otroLote = $this->crearLote($this->tenantId, $this->crearCampo($this->tenantId, 'Otro campo'), 5.0, 'Otro lote');
        $this->crearSiembra($this->tenantId, $this->loteId, $this->sojaId);
        $this->crearSiembra($this->tenantId, $otroLote, $this->sojaId);
        $otroTenant = $this->crearTenant('Otro');
        $this->crearSiembra(
            $otroTenant,
            $this->crearLote($otroTenant, $this->crearCampo($otroTenant)),
            $this->crearCultivo($otroTenant, 'Maíz')
        );

        $this->assertCount(2, SiembraController::listar($this->tenantId));
        $this->assertCount(1, SiembraController::listar($this->tenantId, $otroLote));
    }

    // El frontend sugiere la primera en curso del lote al registrar un egreso.
    public function testListarPoneLasSiembrasEnCursoPrimero(): void
    {
        $trigoId = $this->crearCultivo($this->tenantId, 'Trigo');
        SiembraController::crear($this->tenantId, $this->datos([
            'cultivo_id' => $trigoId,
            'campania' => '2026/27',
            'fecha_siembra' => '2026-06-01',
            'fecha_cosecha' => '2026-12-01',
        ]));
        SiembraController::crear($this->tenantId, $this->datos(['campania' => '2025/26']));

        $siembras = SiembraController::listar($this->tenantId);

        $this->assertSame('Soja', $siembras[0]['cultivo_nombre']);
        $this->assertNull($siembras[0]['fecha_cosecha']);
    }

    public function testEditarCambiaLosDatos(): void
    {
        $id = SiembraController::crear($this->tenantId, $this->datos())['id'];

        $resultado = SiembraController::editar($this->tenantId, $id, $this->datos(['fecha_cosecha' => '2026-04-20']));

        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame('2026-04-20', SiembraController::listar($this->tenantId)[0]['fecha_cosecha']);
    }

    public function testEditarNoTocaSiembrasDeOtroTenant(): void
    {
        $id = SiembraController::crear($this->tenantId, $this->datos())['id'];
        $otroTenant = $this->crearTenant('Otro');

        $resultado = SiembraController::editar($otroTenant, $id, $this->datos(['campania' => '2030/31']));

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertSame('2025/26', SiembraController::listar($this->tenantId)[0]['campania']);
    }

    public function testEliminarFuncionaSiNoTieneMovimientos(): void
    {
        $id = SiembraController::crear($this->tenantId, $this->datos())['id'];

        $resultado = SiembraController::eliminar($this->tenantId, $id);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, SiembraController::listar($this->tenantId));
    }

    public function testNoSePuedeEliminarNiCambiarDeLoteSiTieneMovimientos(): void
    {
        $id = SiembraController::crear($this->tenantId, $this->datos())['id'];
        $usuarioId = $this->crearUsuario($this->tenantId);
        $insumoId = $this->crearInsumo($this->tenantId);
        MovimientoInsumoController::registrar($this->tenantId, $usuarioId, [
            'tipo' => 'ENTRADA', 'insumo_id' => $insumoId, 'cantidad_total' => 100,
        ]);
        MovimientoInsumoController::registrar($this->tenantId, $usuarioId, [
            'tipo' => 'EGRESO_LOTE', 'insumo_id' => $insumoId, 'lote_id' => $this->loteId,
            'siembra_id' => $id, 'cantidad_total' => 10,
        ]);
        $otroLote = $this->crearLote($this->tenantId, $this->crearCampo($this->tenantId, 'Otro campo'), 5.0, 'Otro lote');

        $eliminar = SiembraController::eliminar($this->tenantId, $id);
        $mover = SiembraController::editar($this->tenantId, $id, $this->datos(['lote_id' => $otroLote]));
        $cerrar = SiembraController::editar($this->tenantId, $id, $this->datos(['fecha_cosecha' => '2026-04-20']));

        $this->assertArrayHasKey('errores', $eliminar);
        $this->assertArrayHasKey('errores', $mover);
        $this->assertArrayHasKey('id', $cerrar);
        $this->assertCount(1, SiembraController::listar($this->tenantId));
    }
}
