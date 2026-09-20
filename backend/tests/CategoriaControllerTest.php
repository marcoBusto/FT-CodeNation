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
}
