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

    private static function validar(array $datos): array
    {
        $errores = [];

        if (trim($datos['nombre'] ?? '') === '') {
            $errores[] = 'Falta indicar el nombre del campo.';
        }

        return $errores;
    }
}
