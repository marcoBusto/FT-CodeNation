<?php

final class CampoControllerTest extends DatabaseTestCase
{
    public function testEditarActualizaNombreYUbicacion(): void
    {
        $tenantId = $this->crearTenant();
        $campoId = $this->crearCampo($tenantId, 'Campo Viejo');

        $resultado = CampoController::editar($tenantId, $campoId, ['nombre' => 'Campo Nuevo', 'ubicacion' => 'Ruta 8 km 200']);

        $this->assertArrayHasKey('id', $resultado);
        $campos = CampoController::listar($tenantId);
        $this->assertSame('Campo Nuevo', $campos[0]['nombre']);
        $this->assertSame('Ruta 8 km 200', $campos[0]['ubicacion']);
    }

    public function testCrearGuardaLatitudYLongitud(): void
    {
        $tenantId = $this->crearTenant();

        CampoController::crear($tenantId, ['nombre' => 'Campo Norte', 'latitud' => '-32.9800000', 'longitud' => '-61.9500000']);

        $campos = CampoController::listar($tenantId);
        $this->assertSame(-32.98, (float) $campos[0]['latitud']);
        $this->assertSame(-61.95, (float) $campos[0]['longitud']);
    }

    public function testCrearPermiteOmitirLatitudYLongitud(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = CampoController::crear($tenantId, ['nombre' => 'Campo Sin Mapa']);

        $this->assertArrayHasKey('id', $resultado);
        $campos = CampoController::listar($tenantId);
        $this->assertNull($campos[0]['latitud']);
    }

    public function testCrearRechazaLatitudFueraDeRango(): void
    {
        $tenantId = $this->crearTenant();

        $resultado = CampoController::crear($tenantId, ['nombre' => 'Campo Inválido', 'latitud' => '200', 'longitud' => '-61']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEditarRechazaCampoDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $campoDeA = $this->crearCampo($tenantA);

        $resultado = CampoController::editar($tenantB, $campoDeA, ['nombre' => 'Hackeado']);

        $this->assertArrayHasKey('errores', $resultado);
    }

    public function testEliminarLoSacaDelListado(): void
    {
        $tenantId = $this->crearTenant();
        $campoId = $this->crearCampo($tenantId);

        $resultado = CampoController::eliminar($tenantId, $campoId);

        $this->assertArrayHasKey('id', $resultado);
        $this->assertCount(0, CampoController::listar($tenantId));
    }

    public function testEliminarRechazaCampoDeOtroTenant(): void
    {
        $tenantA = $this->crearTenant('Tenant A');
        $tenantB = $this->crearTenant('Tenant B');
        $campoDeA = $this->crearCampo($tenantA);

        $resultado = CampoController::eliminar($tenantB, $campoDeA);

        $this->assertArrayHasKey('errores', $resultado);
        $this->assertCount(1, CampoController::listar($tenantA));
    }
}
