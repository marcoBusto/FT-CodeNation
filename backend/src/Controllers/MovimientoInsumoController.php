<?php

class MovimientoInsumoController
{
    private const TIPOS_VALIDOS = ['ENTRADA', 'EGRESO_LOTE', 'AJUSTE'];

    // Filtros opcionales por insumo y/o lote, siempre acotado al tenant.
    public static function listar(int $tenantId, ?int $insumoId = null, ?int $loteId = null): array
    {
        $condiciones = ['m.tenant_id = :tenant_id'];
        $parametros = ['tenant_id' => $tenantId];

        if ($insumoId !== null) {
            $condiciones[] = 'm.insumo_id = :insumo_id';
            $parametros['insumo_id'] = $insumoId;
        }
        if ($loteId !== null) {
            $condiciones[] = 'm.lote_id = :lote_id';
            $parametros['lote_id'] = $loteId;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT
                m.id, m.tipo, m.cantidad_total, m.dosis_por_ha, m.observacion, m.fecha_hora,
                i.nombre AS insumo_nombre, i.unidad_medida,
                l.nombre AS lote_nombre,
                u.nombre AS usuario_nombre
             FROM movimientos_insumo m
             INNER JOIN insumos i ON i.id = m.insumo_id
             LEFT JOIN lotes l ON l.id = m.lote_id
             INNER JOIN usuarios u ON u.id = m.usuario_id
             WHERE ' . implode(' AND ', $condiciones) . '
             ORDER BY m.fecha_hora DESC, m.id DESC'
        );
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    public static function registrar(int $tenantId, int $usuarioId, array $datos): array
    {
        $errores = self::validar($tenantId, $datos);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $tipo = $datos['tipo'];
        $insumoId = (int) $datos['insumo_id'];
        $cantidadTotal = (float) $datos['cantidad_total'];

        $pdo->beginTransaction();
        try {
            // Bloquea la fila del insumo para serializar movimientos concurrentes
            // sobre el mismo insumo: sin esto, dos egresos simultáneos podrían
            // leer el mismo stock "antes" y ambos aprobarse aunque juntos lo
            // dejen negativo.
            $stmtLock = $pdo->prepare('SELECT id FROM insumos WHERE id = :id FOR UPDATE');
            $stmtLock->execute(['id' => $insumoId]);

            if ($tipo === 'EGRESO_LOTE' || $tipo === 'AJUSTE') {
                $stockActual = self::calcularStock($pdo, $tenantId, $insumoId);
                $delta = $tipo === 'EGRESO_LOTE' ? -$cantidadTotal : $cantidadTotal;
                if ($stockActual + $delta < 0) {
                    $pdo->rollBack();

                    return ['errores' => ['El movimiento dejaría el stock del insumo en negativo.']];
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO movimientos_insumo
                    (tenant_id, insumo_id, lote_id, usuario_id, tipo, dosis_por_ha, cantidad_total, observacion)
                 VALUES (:tenant_id, :insumo_id, :lote_id, :usuario_id, :tipo, :dosis_por_ha, :cantidad_total, :observacion)'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'insumo_id' => $insumoId,
                'lote_id' => ($datos['lote_id'] ?? '') !== '' ? (int) $datos['lote_id'] : null,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'dosis_por_ha' => ($datos['dosis_por_ha'] ?? '') !== '' ? (float) $datos['dosis_por_ha'] : null,
                'cantidad_total' => $cantidadTotal,
                'observacion' => ($datos['observacion'] ?? '') !== '' ? $datos['observacion'] : null,
            ]);

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();

            return ['id' => $id];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function calcularStock(PDO $pdo, int $tenantId, int $insumoId): float
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(
                CASE tipo
                    WHEN 'ENTRADA' THEN cantidad_total
                    WHEN 'EGRESO_LOTE' THEN -cantidad_total
                    ELSE cantidad_total
                END
             ), 0) AS stock
             FROM movimientos_insumo
             WHERE tenant_id = :tenant_id AND insumo_id = :insumo_id"
        );
        $stmt->execute(['tenant_id' => $tenantId, 'insumo_id' => $insumoId]);

        return (float) $stmt->fetchColumn();
    }

    private static function validar(int $tenantId, array $datos): array
    {
        $errores = [];
        $pdo = Database::getConnection();

        $tipo = $datos['tipo'] ?? '';
        if (!in_array($tipo, self::TIPOS_VALIDOS, true)) {
            $errores[] = 'El tipo de movimiento debe ser ENTRADA, EGRESO_LOTE o AJUSTE.';
        }

        $insumoId = $datos['insumo_id'] ?? '';
        if ($insumoId === '' || !ctype_digit((string) $insumoId)) {
            $errores[] = 'Falta indicar el insumo.';
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM insumos WHERE id = :id AND tenant_id = :tenant_id AND estado = 'activo'");
            $stmt->execute(['id' => $insumoId, 'tenant_id' => $tenantId]);
            if (!$stmt->fetchColumn()) {
                $errores[] = 'El insumo indicado no existe.';
            }
        }

        $loteId = $datos['lote_id'] ?? '';
        if ($tipo === 'EGRESO_LOTE' && $loteId === '') {
            $errores[] = 'El lote es obligatorio en un egreso a lote.';
        }
        if ($loteId !== '') {
            if (!ctype_digit((string) $loteId)) {
                $errores[] = 'El lote indicado no es válido.';
            } else {
                $stmt = $pdo->prepare("SELECT 1 FROM lotes WHERE id = :id AND tenant_id = :tenant_id AND estado = 'activo'");
                $stmt->execute(['id' => $loteId, 'tenant_id' => $tenantId]);
                if (!$stmt->fetchColumn()) {
                    $errores[] = 'El lote indicado no existe.';
                }
            }
        }

        $dosisPorHa = $datos['dosis_por_ha'] ?? '';
        if ($dosisPorHa !== '') {
            if ($tipo !== 'EGRESO_LOTE') {
                $errores[] = 'La dosis por hectárea solo aplica a un egreso a lote.';
            } elseif (!is_numeric($dosisPorHa) || (float) $dosisPorHa <= 0) {
                $errores[] = 'La dosis por hectárea debe ser un número mayor a cero.';
            }
        }

        $cantidadTotal = $datos['cantidad_total'] ?? '';
        if ($cantidadTotal === '' || !is_numeric($cantidadTotal)) {
            $errores[] = 'La cantidad total es obligatoria y debe ser numérica.';
        } elseif (in_array($tipo, ['ENTRADA', 'EGRESO_LOTE'], true) && (float) $cantidadTotal <= 0) {
            $errores[] = 'La cantidad total debe ser mayor a cero en ENTRADA y EGRESO_LOTE.';
        } elseif ($tipo === 'AJUSTE' && (float) $cantidadTotal === 0.0) {
            $errores[] = 'La cantidad total de un ajuste no puede ser cero.';
        }

        return $errores;
    }
}
