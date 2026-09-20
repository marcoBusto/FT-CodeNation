<?php

final class CategoriaControllerTest extends DatabaseTestCase
{
    public function testCrearDevuelveElMismoIdSiYaExiste(): void
    {
        $tenantId = $this->crearTenant();
        $primera = CategoriaController::crear($tenantId, ['nombre' => 'Herbicida']);
        $segunda = CategoriaController::crear($tenantId, ['nombre' => 'Herbicida']);

        $this->assertSame($primera['id'], $segunda['id']);
    }

    public function testCrearRechazaNombreVacio(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = CategoriaController::crear($tenantId, ['nombre' => '']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEditarRenombra(): void
    {
        $tenantId = $this->crearTenant();
        $categoriaId = $this->crearCategoria($tenantId, 'Herbicida');

        $resultado = CategoriaController::editar($tenantId, $categoriaId, ['nombre' => 'Herbicidas']);

        $this->assertArrayHasKey('id', $resultado);
    }

    public function testEliminarSeBloqueaSiUnInsumoLaUsa(): void
    {
        $tenantId = $this->crearTenant();
        $categoriaId = $this->crearCategoria($tenantId, 'Herbicida');
        $this->crearInsumo($tenantId, 'Glifosato', 'litros', null, $categoriaId);

        $resultado = CategoriaController::eliminar($tenantId, $categoriaId);

        $this->assertArrayHasKey('errores', $resultado);
    }
}
