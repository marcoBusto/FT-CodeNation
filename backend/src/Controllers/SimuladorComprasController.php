<?php

// Simulador de compras: no persiste nada (ver docs/DECISIONES.md), mismo
// criterio que estimarInsumo/combustible-estimar. La cotización del dólar
// se consulta a una API pública sin API key (DolarAPI) -- no hay ningún
// secreto que gestionar ni exponer. Si la consulta automática falla, el
// usuario siempre puede tipear la cotización a mano en /estimar.
class SimuladorComprasController
{
    private const URL_COTIZACION = 'https://dolarapi.com/v1/dolares/mayorista';

    public static function cotizacionDolar(): array
    {
        if (!function_exists('curl_init')) {
            return ['errores' => ['No se pudo consultar la cotización automática. Ingresala a mano.']];
        }

        $ch = curl_init(self::URL_COTIZACION);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $respuesta = curl_exec($ch);
        $huboError = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($respuesta === false || $huboError) {
            return ['errores' => ['No se pudo consultar la cotización automática. Ingresala a mano.']];
        }

        $datos = json_decode($respuesta, true);
        if (!isset($datos['venta']) || !is_numeric($datos['venta'])) {
            return ['errores' => ['La cotización automática no devolvió un valor válido. Ingresala a mano.']];
        }

        return [
            'cotizacion' => (float) $datos['venta'],
            'fuente' => 'DolarAPI (mayorista)',
            'fecha' => $datos['fechaActualizacion'] ?? null,
        ];
    }

    public static function estimar(int $tenantId, array $datos): array
    {
        $errores = [];
        $pdo = Database::getConnection();

        $insumo = null;
        $insumoId = $datos['insumo_id'] ?? '';
        if ($insumoId === '' || !ctype_digit((string) $insumoId)) {
            $errores[] = 'Falta indicar el insumo.';
        } else {
            $stmt = $pdo->prepare('SELECT nombre, unidad_medida FROM insumos WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $insumoId, 'tenant_id' => $tenantId]);
            $insumo = $stmt->fetch();
            if (!$insumo) {
                $errores[] = 'El insumo indicado no existe.';
            }
        }

        $cantidad = $datos['cantidad'] ?? '';
        if ($cantidad === '' || !is_numeric($cantidad) || (float) $cantidad <= 0) {
            $errores[] = 'La cantidad debe ser un número mayor a cero.';
        }

        $precioUsd = $datos['precio_unitario_usd'] ?? '';
        if ($precioUsd === '' || !is_numeric($precioUsd) || (float) $precioUsd <= 0) {
            $errores[] = 'El precio unitario en dólares debe ser un número mayor a cero.';
        }

        $cotizacion = $datos['cotizacion'] ?? '';
        if ($cotizacion === '' || !is_numeric($cotizacion) || (float) $cotizacion <= 0) {
            $errores[] = 'La cotización del dólar debe ser un número mayor a cero.';
        }

        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $totalUsd = round((float) $cantidad * (float) $precioUsd, 2);
        $totalArs = round($totalUsd * (float) $cotizacion, 2);

        return [
            'insumo_nombre' => $insumo['nombre'],
            'unidad_medida' => $insumo['unidad_medida'],
            'total_usd' => $totalUsd,
            'cotizacion_usada' => (float) $cotizacion,
            'total_ars' => $totalArs,
        ];
    }
}
