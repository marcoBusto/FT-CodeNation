<?php

// No persiste nada: es una estimación rápida (litros y costo) para planificar
// la carga de combustible antes de una labor, igual criterio que
// LoteController::estimarInsumo.
class CombustibleController
{
    public static function estimar(int $tenantId, array $datos): array
    {
        $pdo = Database::getConnection();
        $errores = [];

        $hectareas = $datos['hectareas'] ?? '';
        if ($hectareas === '' || !is_numeric($hectareas) || (float) $hectareas <= 0) {
            $errores[] = 'Las hectáreas deben ser un número mayor a cero.';
        }

        $labor = null;
        $laborId = $datos['labor_id'] ?? '';
        if ($laborId === '' || !ctype_digit((string) $laborId)) {
            $errores[] = 'Falta indicar la labor.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT nombre, litros_por_hectarea FROM labores WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute(['id' => $laborId, 'tenant_id' => $tenantId]);
            $labor = $stmt->fetch();
            if (!$labor) {
                $errores[] = 'La labor indicada no existe.';
            }
        }

        $estacion = null;
        $estacionId = $datos['estacion_id'] ?? '';
        if ($estacionId === '' || !ctype_digit((string) $estacionId)) {
            $errores[] = 'Falta indicar la estación de combustible.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT nombre, precio_por_litro, actualizado_en
                 FROM estaciones_combustible WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute(['id' => $estacionId, 'tenant_id' => $tenantId]);
            $estacion = $stmt->fetch();
            if (!$estacion) {
                $errores[] = 'La estación indicada no existe.';
            }
        }

        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $litrosEstimados = round((float) $hectareas * (float) $labor['litros_por_hectarea'], 2);
        $costoEstimado = round($litrosEstimados * (float) $estacion['precio_por_litro'], 2);

        return [
            'litros_estimados' => $litrosEstimados,
            'costo_estimado' => $costoEstimado,
            'labor_nombre' => $labor['nombre'],
            'estacion_nombre' => $estacion['nombre'],
            'precio_actualizado_en' => $estacion['actualizado_en'],
        ];
    }
}
