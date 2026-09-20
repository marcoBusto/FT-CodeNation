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
}
