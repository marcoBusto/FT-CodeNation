<?php

final class CultivoControllerTest extends DatabaseTestCase
{
    public function testCrearDevuelveElMismoIdSiYaExiste(): void
    {
        $tenantId = $this->crearTenant();
        $primero = CultivoController::crear($tenantId, ['nombre' => 'Soja']);
        $segundo = CultivoController::crear($tenantId, ['nombre' => 'Soja']);

        $this->assertSame($primero['id'], $segundo['id']);
    }

    public function testCrearRechazaNombreVacio(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = CultivoController::crear($tenantId, ['nombre' => '  ']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testListarSoloDevuelveCultivosDelTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $this->crearCultivo($tenantA, 'Soja');
        $this->crearCultivo($tenantB, 'Maíz');

        $resultado = CultivoController::listar($tenantA);

        $this->assertCount(1, $resultado);
        $this->assertSame('Soja', $resultado[0]['nombre']);
    }

    public function testEditarRenombraYRechazaNombreRepetido(): void
    {
        $tenantId = $this->crearTenant();
        $sojaId = $this->crearCultivo($tenantId, 'Soja');
        $this->crearCultivo($tenantId, 'Maíz');

        $repetido = CultivoController::editar($tenantId, $sojaId, ['nombre' => 'Maíz']);
        $this->assertArrayHasKey('errores', $repetido);

        $resultado = CultivoController::editar($tenantId, $sojaId, ['nombre' => 'Soja de segunda']);
        $this->assertArrayHasKey('id', $resultado);
        $this->assertContains('Soja de segunda', array_column(CultivoController::listar($tenantId), 'nombre'));
    }

    public function testEditarNoTocaCultivosDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $cultivoDeB = $this->crearCultivo($tenantB, 'Maíz');

        $resultado = CultivoController::editar($tenantA, $cultivoDeB, ['nombre' => 'Hackeado']);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertSame('Maíz', CultivoController::listar($tenantB)[0]['nombre']);
    }

    public function testEliminarFuncionaSiNadieLoUsa(): void
    {
        $tenantId = $this->crearTenant();
        $cultivoId = $this->crearCultivo($tenantId);

        $resultado = CultivoController::eliminar($tenantId, $cultivoId);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, CultivoController::listar($tenantId));
    }

    public function testEliminarSeBloqueaSiUnaSiembraLoUsa(): void
    {
        $tenantId = $this->crearTenant();
        $cultivoId = $this->crearCultivo($tenantId);
        $loteId = $this->crearLote($tenantId, $this->crearCampo($tenantId));
        $this->crearSiembra($tenantId, $loteId, $cultivoId);

        $resultado = CultivoController::eliminar($tenantId, $cultivoId);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertCount(1, CultivoController::listar($tenantId));
    }
}
