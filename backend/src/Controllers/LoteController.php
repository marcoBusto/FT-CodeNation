<?php

class LoteController
{
    // $campoId opcional: sin filtro devuelve los lotes de todos los campos del tenant.
    public static function listar(int $tenantId, ?int $campoId = null): array
    {
        $pdo = Database::getConnection();
        $condicionCampo = $campoId !== null ? 'AND l.campo_id = :campo_id' : '';
        $stmt = $pdo->prepare(
            "SELECT l.id, l.campo_id, c.nombre AS campo_nombre, l.nombre, l.hectareas, l.estado, l.creado_en
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
            'INSERT INTO lotes (tenant_id, campo_id, nombre, hectareas)
             VALUES (:tenant_id, :campo_id, :nombre, :hectareas)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'campo_id' => (int) $datos['campo_id'],
            'nombre' => trim($datos['nombre']),
            'hectareas' => (float) $datos['hectareas'],
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
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

        return $errores;
    }
}
