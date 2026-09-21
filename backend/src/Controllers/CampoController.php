<?php

class CampoController
{
    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT id, nombre, ubicacion, latitud, longitud, estado, creado_en
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
            'INSERT INTO campos (tenant_id, nombre, ubicacion, latitud, longitud)
             VALUES (:tenant_id, :nombre, :ubicacion, :latitud, :longitud)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'ubicacion' => ($datos['ubicacion'] ?? '') !== '' ? trim($datos['ubicacion']) : null,
            'latitud' => self::coordenadaOpcional($datos['latitud'] ?? null),
            'longitud' => self::coordenadaOpcional($datos['longitud'] ?? null),
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
            'UPDATE campos SET nombre = :nombre, ubicacion = :ubicacion, latitud = :latitud, longitud = :longitud
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'ubicacion' => ($datos['ubicacion'] ?? '') !== '' ? trim($datos['ubicacion']) : null,
            'latitud' => self::coordenadaOpcional($datos['latitud'] ?? null),
            'longitud' => self::coordenadaOpcional($datos['longitud'] ?? null),
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

        $latitud = $datos['latitud'] ?? '';
        if ($latitud !== '' && $latitud !== null && (!is_numeric($latitud) || (float) $latitud < -90 || (float) $latitud > 90)) {
            $errores[] = 'La latitud debe ser un número entre -90 y 90.';
        }

        $longitud = $datos['longitud'] ?? '';
        if ($longitud !== '' && $longitud !== null && (!is_numeric($longitud) || (float) $longitud < -180 || (float) $longitud > 180)) {
            $errores[] = 'La longitud debe ser un número entre -180 y 180.';
        }

        return $errores;
    }

    private static function coordenadaOpcional($valor): ?float
    {
        return ($valor ?? '') !== '' ? (float) $valor : null;
    }
}
