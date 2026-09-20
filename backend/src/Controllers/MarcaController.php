<?php

class MarcaController
{
    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, nombre FROM marcas WHERE tenant_id = :tenant_id ORDER BY nombre');
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function crear(int $tenantId, array $datos): array
    {
        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            return ['errores' => ['Falta indicar el nombre de la marca.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id FROM marcas WHERE tenant_id = :tenant_id AND nombre = :nombre');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);
        $existente = $stmt->fetchColumn();
        if ($existente) {
            return ['id' => (int) $existente];
        }

        $stmt = $pdo->prepare('INSERT INTO marcas (tenant_id, nombre) VALUES (:tenant_id, :nombre)');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM marcas WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        if (!$stmt->fetchColumn()) {
            return ['errores' => ['La marca indicada no existe.']];
        }

        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            return ['errores' => ['Falta indicar el nombre de la marca.']];
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM marcas WHERE tenant_id = :tenant_id AND nombre = :nombre AND id != :id'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre, 'id' => $id]);
        if ($stmt->fetchColumn()) {
            return ['errores' => ['Ya existe otra marca con ese nombre.']];
        }

        $stmt = $pdo->prepare('UPDATE marcas SET nombre = :nombre WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId, 'nombre' => $nombre]);

        return ['id' => $id];
    }

    // Borrado físico (no hay ledger que dependa de esta fila), pero bloqueado
    // si algún insumo todavía la usa: reasignar la marca de esos insumos es
    // una decisión del usuario, no algo para hacer en cascada sin avisar.
    public static function eliminar(int $tenantId, int $id): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM marcas WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        if (!$stmt->fetchColumn()) {
            return ['errores' => ['La marca indicada no existe.']];
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM insumos WHERE marca_id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $cantidadInsumos = (int) $stmt->fetchColumn();
        if ($cantidadInsumos > 0) {
            return ['errores' => ["No se puede eliminar: hay {$cantidadInsumos} insumo(s) usando esta marca."]];
        }

        $stmt = $pdo->prepare('DELETE FROM marcas WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }
}
