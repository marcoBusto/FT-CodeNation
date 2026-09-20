<?php

class CampoController
{
    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, nombre, ubicacion, estado, creado_en
             FROM campos
             WHERE tenant_id = :tenant_id AND estado = 'activo'
             ORDER BY nombre"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    public static function crear(int $tenantId, array $datos): array
    {
        $errores = self::validar($datos);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO campos (tenant_id, nombre, ubicacion)
             VALUES (:tenant_id, :nombre, :ubicacion)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'ubicacion' => ($datos['ubicacion'] ?? '') !== '' ? trim($datos['ubicacion']) : null,
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['El campo indicado no existe.']];
        }

        $errores = self::validar($datos);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE campos SET nombre = :nombre, ubicacion = :ubicacion
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'ubicacion' => ($datos['ubicacion'] ?? '') !== '' ? trim($datos['ubicacion']) : null,
        ]);

        return ['id' => $id];
    }

    // Baja lógica (estado = 'inactivo'), nunca DELETE físico: un campo puede
    // tener lotes e insumos con movimientos que dependen de que siga
    // existiendo la fila para no romper el historial.
    public static function eliminar(int $tenantId, int $id): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['El campo indicado no existe.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE campos SET estado = 'inactivo' WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }

    private static function existe(int $tenantId, int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM campos WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function validar(array $datos): array
    {
        $errores = [];

        if (trim($datos['nombre'] ?? '') === '') {
            $errores[] = 'Falta indicar el nombre del campo.';
        }

        return $errores;
    }
}
