<?php

// Una siembra = qué cultivo tuvo un lote en una campaña (ver
// database/migrations/007_cultivos_siembras.sql). Es lo que permite después
// agrupar los egresos de insumo por cultivo y campaña.
class SiembraController
{
    // Las en curso (sin fecha de cosecha) van primero y, dentro de cada grupo,
    // la más reciente arriba: el frontend sugiere la primera en curso del
    // lote al registrar un egreso.
    public static function listar(int $tenantId, ?int $loteId = null): array
    {
        $condiciones = ['s.tenant_id = :tenant_id'];
        $parametros = ['tenant_id' => $tenantId];
        if ($loteId !== null) {
            $condiciones[] = 's.lote_id = :lote_id';
            $parametros['lote_id'] = $loteId;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT
                s.id, s.lote_id, s.cultivo_id, s.campania, s.fecha_siembra, s.fecha_cosecha,
                l.nombre AS lote_nombre, l.hectareas,
                ca.nombre AS campo_nombre,
                cu.nombre AS cultivo_nombre
             FROM siembras s
             INNER JOIN lotes l ON l.id = s.lote_id
             INNER JOIN campos ca ON ca.id = l.campo_id
             INNER JOIN cultivos cu ON cu.id = s.cultivo_id
             WHERE ' . implode(' AND ', $condiciones) . '
             ORDER BY (s.fecha_cosecha IS NOT NULL), s.campania DESC, s.fecha_siembra DESC, s.id DESC'
        );
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
            'INSERT INTO siembras (tenant_id, lote_id, cultivo_id, campania, fecha_siembra, fecha_cosecha)
             VALUES (:tenant_id, :lote_id, :cultivo_id, :campania, :fecha_siembra, :fecha_cosecha)'
        );
        $stmt->execute(['tenant_id' => $tenantId] + self::columnas($datos));

        return ['id' => (int) $pdo->lastInsertId()];
    }

    public static function editar(int $tenantId, int $id, array $datos): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['La siembra indicada no existe.']];
        }

        $errores = self::validar($tenantId, $datos, $id);
        if (!empty($errores)) {
            return ['errores' => $errores];
        }

        // Si ya hay egresos imputados a esta siembra, cambiarla de lote los
        // dejaría apuntando a una siembra de otro lote.
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT lote_id FROM siembras WHERE id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() !== (int) $datos['lote_id'] && self::cantidadMovimientos($tenantId, $id) > 0) {
            return ['errores' => ['No se puede cambiar el lote: ya hay movimientos registrados en esta siembra.']];
        }

        $stmt = $pdo->prepare(
            'UPDATE siembras
             SET lote_id = :lote_id, cultivo_id = :cultivo_id, campania = :campania,
                 fecha_siembra = :fecha_siembra, fecha_cosecha = :fecha_cosecha
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId] + self::columnas($datos));

        return ['id' => $id];
    }

    // Borrado físico (sirve para corregir una carga equivocada), bloqueado si
    // ya hay movimientos imputados: el ledger no se toca.
    public static function eliminar(int $tenantId, int $id): array
    {
        if (!self::existe($tenantId, $id)) {
            return ['errores' => ['La siembra indicada no existe.']];
        }

        $cantidad = self::cantidadMovimientos($tenantId, $id);
        if ($cantidad > 0) {
            return ['errores' => ["No se puede eliminar: hay {$cantidad} movimiento(s) registrados en esta siembra."]];
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM siembras WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return ['id' => $id];
    }

    private static function existe(int $tenantId, int $id): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT 1 FROM siembras WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (bool) $stmt->fetchColumn();
    }

    private static function cantidadMovimientos(int $tenantId, int $id): int
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT COUNT(*) FROM movimientos_insumo WHERE siembra_id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private static function columnas(array $datos): array
    {
        return [
            'lote_id' => (int) $datos['lote_id'],
            'cultivo_id' => (int) $datos['cultivo_id'],
            'campania' => $datos['campania'],
            'fecha_siembra' => ($datos['fecha_siembra'] ?? '') !== '' ? $datos['fecha_siembra'] : null,
            'fecha_cosecha' => ($datos['fecha_cosecha'] ?? '') !== '' ? $datos['fecha_cosecha'] : null,
        ];
    }

    private static function validar(int $tenantId, array $datos, ?int $idActual = null): array
    {
        $errores = [];
        $pdo = Database::getConnection();

        $loteId = $datos['lote_id'] ?? '';
        $loteValido = false;
        if ($loteId === '' || !ctype_digit((string) $loteId)) {
            $errores[] = 'Falta indicar el lote.';
        } else {
            $stmt = $pdo->prepare("SELECT 1 FROM lotes WHERE id = :id AND tenant_id = :tenant_id AND estado = 'activo'");
            $stmt->execute(['id' => $loteId, 'tenant_id' => $tenantId]);
            $loteValido = (bool) $stmt->fetchColumn();
            if (!$loteValido) {
                $errores[] = 'El lote indicado no existe.';
            }
        }

        $cultivoId = $datos['cultivo_id'] ?? '';
        $cultivoValido = false;
        if ($cultivoId === '' || !ctype_digit((string) $cultivoId)) {
            $errores[] = 'Falta indicar el cultivo.';
        } else {
            $stmt = $pdo->prepare('SELECT 1 FROM cultivos WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute(['id' => $cultivoId, 'tenant_id' => $tenantId]);
            $cultivoValido = (bool) $stmt->fetchColumn();
            if (!$cultivoValido) {
                $errores[] = 'El cultivo indicado no existe.';
            }
        }

        $campania = $datos['campania'] ?? '';
        $campaniaValida = self::esCampaniaValida($campania);
        if (!$campaniaValida) {
            $errores[] = 'La campaña debe tener el formato AAAA/AA con años consecutivos (ej. 2025/26).';
        }

        $fechaSiembra = $datos['fecha_siembra'] ?? '';
        $fechaCosecha = $datos['fecha_cosecha'] ?? '';
        $fechasValidas = true;
        if ($fechaSiembra !== '' && !self::esFechaValida($fechaSiembra)) {
            $errores[] = 'La fecha de siembra no es válida.';
            $fechasValidas = false;
        }
        if ($fechaCosecha !== '' && !self::esFechaValida($fechaCosecha)) {
            $errores[] = 'La fecha de cosecha no es válida.';
            $fechasValidas = false;
        }
        if ($fechasValidas && $fechaSiembra !== '' && $fechaCosecha !== '' && $fechaCosecha < $fechaSiembra) {
            $errores[] = 'La fecha de cosecha no puede ser anterior a la de siembra.';
        }

        if ($loteValido && $cultivoValido && $campaniaValida) {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM siembras
                 WHERE lote_id = :lote_id AND cultivo_id = :cultivo_id AND campania = :campania AND id != :id'
            );
            $stmt->execute([
                'lote_id' => $loteId,
                'cultivo_id' => $cultivoId,
                'campania' => $campania,
                'id' => $idActual ?? 0,
            ]);
            if ($stmt->fetchColumn()) {
                $errores[] = 'Ese lote ya tiene cargado ese cultivo en esa campaña.';
            }
        }

        return $errores;
    }

    // "2025/26": cuatro dígitos, barra, y los dos últimos del año siguiente.
    private static function esCampaniaValida(mixed $campania): bool
    {
        if (!is_string($campania) || !preg_match('#^(\d{4})/(\d{2})$#', $campania, $m)) {
            return false;
        }

        return ((int) $m[1] + 1) % 100 === (int) $m[2];
    }

    private static function esFechaValida(mixed $fecha): bool
    {
        if (!is_string($fecha)) {
            return false;
        }
        $parseada = DateTime::createFromFormat('!Y-m-d', $fecha);

        return $parseada !== false && $parseada->format('Y-m-d') === $fecha;
    }
}
