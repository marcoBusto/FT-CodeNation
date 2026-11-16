<?php

// Catálogo de cultivos por tenant (soja, maíz, trigo...). Mismo patrón que
// MarcaController: catálogo chico, alta idempotente por nombre.
class CultivoController
{
    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, nombre FROM cultivos WHERE tenant_id = :tenant_id ORDER BY nombre');
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function crear(int $tenantId, array $datos): array
    {
        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            return ['errores' => ['Falta indicar el nombre del cultivo.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM cultivos WHERE tenant_id = :tenant_id AND nombre = :nombre');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);
        $existente = $stmt->fetchColumn();
        if ($existente) {
            return ['id' => (int) $existente];
        }

        $stmt = $pdo->prepare('INSERT INTO cultivos (tenant_id, nombre) VALUES (:tenant_id, :nombre)');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM cultivos WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        if (!$stmt->fetchColumn()) {
            return ['errores' => ['El cultivo indicado no existe.']];
        }

        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            return ['errores' => ['Falta indicar el nombre del cultivo.']];
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM cultivos WHERE tenant_id = :tenant_id AND nombre = :nombre AND id != :id'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre, 'id' => $id]);
        if ($stmt->fetchColumn()) {
            return ['errores' => ['Ya existe otro cultivo con ese nombre.']];
        }

        $stmt = $pdo->prepare('UPDATE cultivos SET nombre = :nombre WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'nombre' => $nombre]);

        return ['id' => $id];
    }

    // Borrado físico, bloqueado si alguna siembra lo usa (mismo criterio que
    // marcas/categorías con los insumos).
    public static function eliminar(int $tenantId, int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM cultivos WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        if (!$stmt->fetchColumn()) {
            return ['errores' => ['El cultivo indicado no existe.']];
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM siembras WHERE cultivo_id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $cantidadSiembras = (int) $stmt->fetchColumn();
        if ($cantidadSiembras > 0) {
            return ['errores' => ["No se puede eliminar: hay {$cantidadSiembras} siembra(s) de este cultivo."]];
        }

        $stmt = $pdo->prepare('DELETE FROM cultivos WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }
}
