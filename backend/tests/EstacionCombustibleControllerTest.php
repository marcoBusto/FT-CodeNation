<?php

final class EstacionCombustibleControllerTest extends DatabaseTestCase
{
    public function testCrearGuardaPrecioPorLitro(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = EstacionCombustibleController::crear($tenantId, ['nombre' => 'YPF Leones', 'precio_por_litro' => '950']);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame(950.0, (float) EstacionCombustibleController::listar($tenantId)[0]['precio_por_litro']);
    }

    public function testCrearRechazaPrecioNegativoOCero(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = EstacionCombustibleController::crear($tenantId, ['nombre' => 'YPF Leones', 'precio_por_litro' => '0']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testCrearRechazaNombreDuplicadoEnElMismoTenant(): void
    {
        $tenantId = $this->crearTenant();
        $this->crearEstacionCombustible($tenantId, 'Axion Leones');

        $resultado = EstacionCombustibleController::crear($tenantId, ['nombre' => 'Axion Leones', 'precio_por_litro' => '900']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testListarSoloDevuelveEstacionesDelTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $this->crearEstacionCombustible($tenantA, 'YPF Leones');
        $this->crearEstacionCombustible($tenantB, 'Axion Leones');

        $resultado = EstacionCombustibleController::listar($tenantA);

        $this->assertCount(1, $resultado);
        $this->assertSame('YPF Leones', $resultado[0]['nombre']);
    }

    public function testEditarActualizaElPrecio(): void
    {
        $tenantId = $this->crearTenant();
        $estacionId = $this->crearEstacionCombustible($tenantId, 'YPF Leones', 900.0);

        $resultado = EstacionCombustibleController::editar($tenantId, $estacionId, ['nombre' => 'YPF Leones', 'precio_por_litro' => '980']);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertSame(980.0, (float) EstacionCombustibleController::listar($tenantId)[0]['precio_por_litro']);
    }

    public function testEliminarLaSacaDelListado(): void
    {
        $tenantId = $this->crearTenant();
        $estacionId = $this->crearEstacionCombustible($tenantId);

        $resultado = EstacionCombustibleController::eliminar($tenantId, $estacionId);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, EstacionCombustibleController::listar($tenantId));
    }

    public function testEliminarRechazaEstacionDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $estacionDeA = $this->crearEstacionCombustible($tenantA);

        $resultado = EstacionCombustibleController::eliminar($tenantB, $estacionDeA);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertCount(1, EstacionCombustibleController::listar($tenantA));
    }
}
