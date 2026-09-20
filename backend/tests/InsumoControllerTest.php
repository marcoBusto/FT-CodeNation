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
        $this->crearInsumo($tenantId, 'Glifosato', 'litros', 'Roundup');

        $resultado = InsumoController::crear($tenantId, [
            'nombre' => 'Glifosato',
            'marca' => 'Panzer Gold',
            'unidad_medida' => 'litros',
        ]);

        $this->assertArrayHasKey('id', $resultado);
    }

    public function testCrearRechazaMismoNombreYMismaMarca(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearInsumo($tenantId, 'Glifosato', 'litros', 'Roundup');

        $resultado = InsumoController::crear($tenantId, [
            'nombre' => 'Glifosato',
            'marca' => 'Roundup',
            'unidad_medida' => 'litros',
        ]);

        $this->assertArrayHasKey('errores', $resultado);
    }
}
