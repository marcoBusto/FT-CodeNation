<?php

class LoteController
{
    // $campoId opcional: sin filtro devuelve los lotes de todos los campos del tenant.
    public static function listar(int $tenantId, ?int $campoId = null): array
    {
        $pdo = Database::getConnection();
        $condicionCampo = $campoId !== null ? 'AND l.campo_id = :campo_id' : '';
        $stmt = $pdo->prepare(
            "SELECT l.id, l.campo_id, c.nombre AS campo_nombre, l.nombre, l.hectareas,
                    l.perimetro_metros, l.poligono, l.estado, l.creado_en
             FROM lotes l
             INNER JOIN campos c ON c.id = l.campo_id
             WHERE l.tenant_id = :tenant_id AND l.estado = 'activo' {$condicionCampo}
             ORDER BY c.nombre, l.nombre"
        );
        $parametros = ['tenant_id' => $tenantId];
        if ($campoId !== null) {
            $parametros['campo_id'] = $campoId;
        }
        $stmt->execute($parametros);

        $lotes = $stmt->fetchAll();
        foreach ($lotes as &$lote) {
            $lote['poligono'] = $lote['poligono'] !== null ? json_decode($lote['poligono'], true) : null;
        }

        return $lotes;
    }

    public static function crear(int $tenantId, array $datos): array
    {
        $errores = self::validar($tenantId, $datos);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO lotes (tenant_id, campo_id, nombre, hectareas, perimetro_metros, poligono)
             VALUES (:tenant_id, :campo_id, :nombre, :hectareas, :perimetro_metros, :poligono)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'campo_id' => (int) $datos['campo_id'],
            'nombre' => trim($datos['nombre']),
            'hectareas' => (float) $datos['hectareas'],
            'perimetro_metros' => ($datos['perimetro_metros'] ?? '') !== '' ? (float) $datos['perimetro_metros'] : null,
            'poligono' => !empty($datos['poligono']) ? json_encode($datos['poligono']) : null,
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['El lote indicado no existe.']];
        }

        $errores = self::validar($tenantId, $datos);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE lotes
             SET campo_id = :campo_id, nombre = :nombre, hectareas = :hectareas,
                 perimetro_metros = :perimetro_metros, poligono = :poligono
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'campo_id' => (int) $datos['campo_id'],
            'nombre' => trim($datos['nombre']),
            'hectareas' => (float) $datos['hectareas'],
            'perimetro_metros' => ($datos['perimetro_metros'] ?? '') !== '' ? (float) $datos['perimetro_metros'] : null,
            'poligono' => !empty($datos['poligono']) ? json_encode($datos['poligono']) : null,
        ]);

        return ['id' => $id];
    }

    // Baja lógica: un lote con movimientos históricos no se puede borrar sin
    // perder el rastro de esos movimientos (ver ledger inmutable en
    // docs/DECISIONES.md), así que solo se marca inactivo.
    public static function eliminar(int $tenantId, int $id): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['El lote indicado no existe.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE lotes SET estado = 'inactivo' WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }

    private static function existe(int $tenantId, int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM lotes WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    // No persiste nada: es una estimación rápida para ayudar a planificar la
    // compra de insumo antes de registrar movimientos reales.
    public static function estimarInsumo(array $datos): array
    {
        $hectareas = $datos['hectareas'] ?? '';
        $dosisPorHa = $datos['dosis_por_ha'] ?? '';

        $errores = [];
        if ($hectareas === '' || !is_numeric($hectareas) || (float) $hectareas <= 0) {
            $errores[] = 'Las hectáreas deben ser un número mayor a cero.';
        }
        if ($dosisPorHa === '' || !is_numeric($dosisPorHa) || (float) $dosisPorHa <= 0) {
            $errores[] = 'La dosis por hectárea debe ser un número mayor a cero.';
        }
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        return ['cantidad_total_estimada' => round((float) $hectareas * (float) $dosisPorHa, 2)];
    }

    private static function validar(int $tenantId, array $datos): array
    {
        $errores = [];
        $pdo = Database::getConnection();

        $campoId = $datos['campo_id'] ?? '';
        if ($campoId === '' || !ctype_digit((string) $campoId)) {
            $errores[] = 'Falta indicar el campo.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM campos WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $campoId, 'tenant_id' => $tenantId]);
            if (!$stmt->fetchColumn()) {
                $errores[] = 'El campo indicado no existe.';
            }
        }

        if (trim($datos['nombre'] ?? '') === '') {
            $errores[] = 'Falta indicar el nombre del lote.';
        }

        $hectareas = $datos['hectareas'] ?? '';
        if ($hectareas === '' || !is_numeric($hectareas) || (float) $hectareas <= 0) {
            $errores[] = 'Las hectáreas deben ser un número mayor a cero.';
        }

        if (($datos['perimetro_metros'] ?? '') !== '' && !is_numeric($datos['perimetro_metros'])) {
            $errores[] = 'El perímetro debe ser un número.';
        }

        if (!empty($datos['poligono'])) {
            if (!is_array($datos['poligono']) || count($datos['poligono']) < 3) {
                $errores[] = 'El polígono debe tener al menos 3 puntos.';
            } else {
                foreach ($datos['poligono'] as $punto) {
                    if (!is_array($punto) || !isset($punto['lat'], $punto['lng']) || !is_numeric($punto['lat']) || !is_numeric($punto['lng'])) {
                        $errores[] = 'El polígono tiene puntos con formato inválido.';
                        break;
                    }
                }
            }
        }

        return $errores;
    }
}
