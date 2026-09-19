<?php

class InsumoController
{
    private const UNIDADES_VALIDAS = ['litros', 'kg', 'bolsas'];

    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, nombre, unidad_medida, estado, creado_en
             FROM insumos
             WHERE tenant_id = :tenant_id AND estado = 'activo'
             ORDER BY nombre"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    // Stock actual por insumo, calculado desde el ledger (nunca guardado en columna).
    // ENTRADA suma, EGRESO_LOTE resta, AJUSTE suma/resta según su propio signo.
    public static function stock(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT
                i.id,
                i.nombre,
                i.unidad_medida,
                COALESCE(SUM(
                    CASE m.tipo
                        WHEN 'ENTRADA' THEN m.cantidad_total
                        WHEN 'EGRESO_LOTE' THEN -m.cantidad_total
                        ELSE m.cantidad_total
                    END
                ), 0) AS stock_actual
             FROM insumos i
             LEFT JOIN movimientos_insumo m ON m.insumo_id = i.id AND m.tenant_id = i.tenant_id
             WHERE i.tenant_id = :tenant_id AND i.estado = 'activo'
             GROUP BY i.id, i.nombre, i.unidad_medida
             ORDER BY i.nombre"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function crear(int $tenantId, array $datos): array
    {
        $errores = self::validar($tenantId, $datos);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO insumos (tenant_id, nombre, unidad_medida)
             VALUES (:tenant_id, :nombre, :unidad_medida)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'unidad_medida' => $datos['unidad_medida'],
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    private static function validar(int $tenantId, array $datos): array
    {
        $errores = [];
        $nombre = trim($datos['nombre'] ?? '');

        if ($nombre === '') {
            $errores[] = 'Falta indicar el nombre del insumo.';
        } else {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                'SELECT 1 FROM insumos WHERE tenant_id = :tenant_id AND nombre = :nombre'
            );
            $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ya existe un insumo con ese nombre.';
            }
        }

        if (!in_array($datos['unidad_medida'] ?? '', self::UNIDADES_VALIDAS, true)) {
            $errores[] = 'La unidad de medida debe ser litros, kg o bolsas.';
        }

        return $errores;
    }
}
