<?php

// Catálogo de labores (siembra, pulverización, cosecha...) con su consumo de
// combustible estimado en litros por hectárea. Mismo patrón CRUD que
// MarcaController/CategoriaController.
class LaborController
{
    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, litros_por_hectarea FROM labores WHERE tenant_id = :tenant_id ORDER BY nombre'
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
            'INSERT INTO labores (tenant_id, nombre, litros_por_hectarea) VALUES (:tenant_id, :nombre, :litros)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'litros' => (float) $datos['litros_por_hectarea'],
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['La labor indicada no existe.']];
        }

        $errores = self::validar($tenantId, $datos, $id);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE labores SET nombre = :nombre, litros_por_hectarea = :litros
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'litros' => (float) $datos['litros_por_hectarea'],
        ]);

        return ['id' => $id];
    }

    // Borrado físico: la labor solo se usa en el calculador estimativo (no
    // persiste movimientos que dependan de ella), así que no hay historial
    // que se rompa al borrarla.
    public static function eliminar(int $tenantId, int $id): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['La labor indicada no existe.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM labores WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }

    private static function existe(int $tenantId, int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM labores WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function validar(int $tenantId, array $datos, ?int $idActual = null): array
    {
        $errores = [];

        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            $errores[] = 'Falta indicar el nombre de la labor.';
        }

        $litros = $datos['litros_por_hectarea'] ?? '';
        if ($litros === '' || !is_numeric($litros) || (float) $litros <= 0) {
            $errores[] = 'Los litros por hectárea deben ser un número mayor a cero.';
        }

        if ($nombre !== '') {
            $pdo = Database::getConnection();
            $sql = 'SELECT 1 FROM labores WHERE tenant_id = :tenant_id AND nombre = :nombre';
            $parametros = ['tenant_id' => $tenantId, 'nombre' => $nombre];
            if ($idActual !== null) {
                $sql .= ' AND id != :id';
                $parametros['id'] = $idActual;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametros);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ya existe otra labor con ese nombre.';
            }
        }

        return $errores;
    }
}
