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

    public function testEditarRenombra(): void
    {
        $tenantId = $this->crearTenant();
        $marcaId = $this->crearMarca($tenantId, 'Roundup');

        $resultado = MarcaController::editar($tenantId, $marcaId, ['nombre' => 'Roundup Full']);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame('Roundup Full', MarcaController::listar($tenantId)[0]['nombre']);
    }

    public function testEliminarFuncionaSiNadieLaUsa(): void
    {
        $tenantId = $this->crearTenant();
        $marcaId = $this->crearMarca($tenantId, 'Roundup');

        $resultado = MarcaController::eliminar($tenantId, $marcaId);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, MarcaController::listar($tenantId));
    }

    public function testEliminarSeBloqueaSiUnInsumoLaUsa(): void
    {
        $tenantId = $this->crearTenant();
        $marcaId = $this->crearMarca($tenantId, 'Roundup');
        $this->crearInsumo($tenantId, 'Glifosato', 'litros', $marcaId);

        $resultado = MarcaController::eliminar($tenantId, $marcaId);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertCount(1, MarcaController::listar($tenantId));
    }
}
