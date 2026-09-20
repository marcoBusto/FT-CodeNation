<?php

final class InsumoControllerTest extends DatabaseTestCase
{
    public function testCrearRechazaNombreDuplicadoEnElMismoTenant(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearInsumo($tenantId, 'Glifosato');

        $resultado = InsumoController::crear($tenantId, ['nombre' => 'Glifosato', 'unidad_medida' => 'litros']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCrearPermiteElMismoNombreEnTenantsDistintos(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $this->crearInsumo($tenantA, 'Glifosato');

        $resultado = InsumoController::crear($tenantB, ['nombre' => 'Glifosato', 'unidad_medida' => 'litros']);

        $this->assertArrayHasKey('id', $resultado);
    }

    public function testCrearRechazaUnidadDeMedidaInvalida(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = InsumoController::crear($tenantId, ['nombre' => 'Urea', 'unidad_medida' => 'toneladas']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCrearPermiteElMismoNombreConMarcaDistinta(): void
    {
        $tenantId = $this->crearTenant();
        $roundup = $this->crearMarca($tenantId, 'Roundup');
        $this->crearInsumo($tenantId, 'Glifosato', 'litros', $roundup);

        $panzerGold = MarcaController::crear($tenantId, ['nombre' => 'Panzer Gold']);
        $resultado = InsumoController::crear($tenantId, [
            'nombre' => 'Glifosato',
            'marca_id' => $panzerGold['id'],
            'unidad_medida' => 'litros',
        ]);

        $this->assertArrayHasKey('id', $resultado);
    }

    public function testCrearRechazaMismoNombreYMismaMarca(): void
    {
        $tenantId = $this->crearTenant();
        $roundup = $this->crearMarca($tenantId, 'Roundup');
        $this->crearInsumo($tenantId, 'Glifosato', 'litros', $roundup);

        $resultado = InsumoController::crear($tenantId, [
            'nombre' => 'Glifosato',
            'marca_id' => $roundup,
            'unidad_medida' => 'litros',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCrearRechazaMarcaDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $marcaDeA = $this->crearMarca($tenantA, 'Roundup');

        $resultado = InsumoController::crear($tenantB, [
            'nombre' => 'Glifosato',
            'marca_id' => $marcaDeA,
            'unidad_medida' => 'litros',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCrearAceptaCategoriaOpcional(): void
    {
        $tenantId = $this->crearTenant();
        $categoriaId = $this->crearCategoria($tenantId, 'Herbicida');

        $resultado = InsumoController::crear($tenantId, [
            'nombre' => 'Glifosato',
            'categoria_id' => $categoriaId,
            'unidad_medida' => 'litros',
        ]);

        $this->assertArrayHasKey('id', $resultado);
    }

    public function testEditarPuedeCambiarMarcaSinChocarConsigoMismo(): void
    {
        $tenantId = $this->crearTenant();
        $roundup = $this->crearMarca($tenantId, 'Roundup');
        $panzerGold = $this->crearMarca($tenantId, 'Panzer Gold');
        $insumoId = $this->crearInsumo($tenantId, 'Glifosato', 'litros', $roundup);

        // Editar sin cambiar nada (misma marca) no debería chocar contra sí mismo.
        $resultado = InsumoController::editar($tenantId, $insumoId, [
            'nombre' => 'Glifosato',
            'marca_id' => $roundup,
            'unidad_medida' => 'litros',
        ]);
        $this->assertArrayHasKey('id', $resultado);

        $resultado = InsumoController::editar($tenantId, $insumoId, [
            'nombre' => 'Glifosato',
            'marca_id' => $panzerGold,
            'unidad_medida' => 'litros',
        ]);
        $this->assertArrayHasKey('id', $resultado);

        $insumos = InsumoController::listar($tenantId);
        $this->assertSame($panzerGold, $insumos[0]['marca_id']);
    }

    public function testEliminarLoSacaDelListado(): void
    {
        $tenantId = $this->crearTenant();
        $insumoId = $this->crearInsumo($tenantId);

        $resultado = InsumoController::eliminar($tenantId, $insumoId);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, InsumoController::listar($tenantId));
    }
}
