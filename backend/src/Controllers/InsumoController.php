<?php

class InsumoController
{
    private const UNIDADES_VALIDAS = ['litros', 'kg', 'bolsas'];

    public static function listar(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT i.id, i.nombre, i.marca_id, m.nombre AS marca_nombre,
                    i.categoria_id, c.nombre AS categoria_nombre,
                    i.unidad_medida, i.estado, i.creado_en
             FROM insumos i
             LEFT JOIN marcas m ON m.id = i.marca_id
             LEFT JOIN categorias c ON c.id = i.categoria_id
             WHERE i.tenant_id = :tenant_id AND i.estado = 'activo'
             ORDER BY i.nombre"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return $stmt->fetchAll();
    }

    // Stock actual por insumo, calculado desde el ledger (nunca guardado en columna).
    // ENTRADA suma, EGRESO_LOTE resta, AJUSTE suma/resta según su propio signo.
    public static function stock(int $tenantId): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT
                i.id,
                i.nombre,
                i.marca_id,
                m.nombre AS marca_nombre,
                i.categoria_id,
                c.nombre AS categoria_nombre,
                i.unidad_medida,
                COALESCE(SUM(
                    CASE mov.tipo
                        WHEN 'ENTRADA' THEN mov.cantidad_total
                        WHEN 'EGRESO_LOTE' THEN -mov.cantidad_total
                        ELSE mov.cantidad_total
                    END
                ), 0) AS stock_actual
             FROM insumos i
             LEFT JOIN marcas m ON m.id = i.marca_id
             LEFT JOIN categorias c ON c.id = i.categoria_id
             LEFT JOIN movimientos_insumo mov ON mov.insumo_id = i.id AND mov.tenant_id = i.tenant_id
             WHERE i.tenant_id = :tenant_id AND i.estado = 'activo'
             GROUP BY i.id, i.nombre, i.marca_id, m.nombre, i.categoria_id, c.nombre, i.unidad_medida
             ORDER BY i.nombre"
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
            'INSERT INTO insumos (tenant_id, nombre, marca_id, categoria_id, unidad_medida)
             VALUES (:tenant_id, :nombre, :marca_id, :categoria_id, :unidad_medida)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'marca_id' => self::idOpcional($datos['marca_id'] ?? null),
            'categoria_id' => self::idOpcional($datos['categoria_id'] ?? null),
            'unidad_medida' => $datos['unidad_medida'],
        ]);

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['El insumo indicado no existe.']];
        }

        $errores = self::validar($tenantId, $datos, $id);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE insumos SET nombre = :nombre, marca_id = :marca_id, categoria_id = :categoria_id, unidad_medida = :unidad_medida
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $tenantId,
            'nombre' => trim($datos['nombre']),
            'marca_id' => self::idOpcional($datos['marca_id'] ?? null),
            'categoria_id' => self::idOpcional($datos['categoria_id'] ?? null),
            'unidad_medida' => $datos['unidad_medida'],
        ]);

        return ['id' => $id];
    }

    // Baja lógica: el stock del insumo se calcula desde movimientos_insumo
    // (ledger inmutable), así que borrarlo de verdad dejaría movimientos
    // históricos apuntando a un insumo inexistente.
    public static function eliminar(int $tenantId, int $id): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['El insumo indicado no existe.']];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "UPDATE insumos SET estado = 'inactivo' WHERE id = :id AND tenant_id = :tenant_id"
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }

    private static function existe(int $tenantId, int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM insumos WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function idOpcional(mixed $valor): ?int
    {
        return ($valor === null || $valor === '') ? null : (int) $valor;
    }

    // $idAExcluir: al editar, el insumo no debe chocar contra su propia fila
    // en la verificación de duplicados.
    private static function validar(int $tenantId, array $datos, ?int $idAExcluir = null): array
    {
        $errores = [];
        $nombre = trim($datos['nombre'] ?? '');
        $marcaId = self::idOpcional($datos['marca_id'] ?? null);
        $categoriaId = self::idOpcional($datos['categoria_id'] ?? null);

        if ($nombre === '') {
            $errores[] = 'Falta indicar el nombre del insumo.';
        } else {
            $pdo = Database::getConnection();
            // "<=>" es el operador de igualdad NULL-safe de MySQL/MariaDB:
            // sin él, "marca_id = NULL" nunca es verdadero y dejaría pasar
            // duplicados de insumos sin marca cargada.
            $stmt = $pdo->prepare(
                'SELECT 1 FROM insumos
                 WHERE tenant_id = :tenant_id AND nombre = :nombre
                   AND marca_id <=> :marca_id AND categoria_id <=> :categoria_id
                   AND id != :id_excluido'
            );
            $stmt->execute([
                'tenant_id' => $tenantId,
                'nombre' => $nombre,
                'marca_id' => $marcaId,
                'categoria_id' => $categoriaId,
                'id_excluido' => $idAExcluir ?? 0,
            ]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ya existe un insumo con ese nombre, marca y categoría.';
            }
        }

        if ($marcaId !== null && !self::perteneceAlTenant($tenantId, 'marcas', $marcaId)) {
            $errores[] = 'La marca indicada no existe.';
        }

        if ($categoriaId !== null && !self::perteneceAlTenant($tenantId, 'categorias', $categoriaId)) {
            $errores[] = 'La categoría indicada no existe.';
        }

        if (!in_array($datos['unidad_medida'] ?? '', self::UNIDADES_VALIDAS, true)) {
            $errores[] = 'La unidad de medida debe ser litros, kg o bolsas.';
        }

        return $errores;
    }

    private static function perteneceAlTenant(int $tenantId, string $tabla, int $id): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT 1 FROM {$tabla} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }
}
