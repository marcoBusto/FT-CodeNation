<?php

// Catálogo de estaciones de combustible con precio de referencia cargado a
// mano por el usuario (ej. "YPF Leones"). No hay fuente pública/gratuita de
// precios por surtidor en Argentina, ver docs/DECISIONES.md.
class EstacionCombustibleController
{
    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, precio_por_litro, actualizado_en
             FROM estaciones_combustible WHERE tenant_id = :tenant_id ORDER BY nombre'
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
            'INSERT INTO estaciones_combustible (tenant_id, nombre, precio_por_litro)
             VALUES (:tenant_id, :nombre, :precio)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'precio' => (float) $datos['precio_por_litro'],
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['La estación indicada no existe.']];
        }

        $errores = self::validar($tenantId, $datos, $id);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE estaciones_combustible SET nombre = :nombre, precio_por_litro = :precio
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'precio' => (float) $datos['precio_por_litro'],
        ]);

        return ['id' => $id];
    }

    // Borrado físico: igual que labores, la estación solo alimenta el
    // calculador estimativo y no queda referenciada desde ningún historial.
    public static function eliminar(int $tenantId, int $id): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['La estación indicada no existe.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM estaciones_combustible WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }

    private static function existe(int $tenantId, int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM estaciones_combustible WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function validar(int $tenantId, array $datos, ?int $idActual = null): array
    {
        $errores = [];

        $nombre = trim($datos['nombre'] ?? '');
        if ($nombre === '') {
            $errores[] = 'Falta indicar el nombre de la estación.';
        }

        $precio = $datos['precio_por_litro'] ?? '';
        if ($precio === '' || !is_numeric($precio) || (float) $precio <= 0) {
            $errores[] = 'El precio por litro debe ser un número mayor a cero.';
        }

        if ($nombre !== '') {
            $pdo = Database::getConnection();
            $sql = 'SELECT 1 FROM estaciones_combustible WHERE tenant_id = :tenant_id AND nombre = :nombre';
            $parametros = ['tenant_id' => $tenantId, 'nombre' => $nombre];
            if ($idActual !== null) {
                $sql .= ' AND id != :id';
                $parametros['id'] = $idActual;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($parametros);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ya existe otra estación con ese nombre.';
            }
        }

        return $errores;
    }
}
