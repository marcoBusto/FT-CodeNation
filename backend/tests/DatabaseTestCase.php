<?php

use PHPUnit\Framework\TestCase;

// Base para tests que necesitan datos reales en la base de datos de test.
// Cada test arranca con las tablas vacías y crea sus propios tenants/campos/
// insumos con los helpers de acá, en vez de depender de datos precargados.
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = Database::getConnection();

        if (!str_ends_with((string) getenv('DB_DATABASE'), '_test')) {
            throw new RuntimeException(
                'Los tests deben correr contra una base de datos de test (nombre terminado en _test). ' .
                'Revisá tests/bootstrap.php.'
            );
        }

        foreach (['movimientos_insumo', 'insumos', 'marcas', 'categorias', 'estaciones_combustible', 'labores', 'lotes', 'campos', 'usuarios', 'tenants'] as $tabla) {
            $this->pdo->exec("DELETE FROM {$tabla}");
        }
    }

    protected function crearTenant(string $nombre = 'Tenant de prueba'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tenants (nombre) VALUES (:nombre)');
        $stmt->execute(['nombre' => $nombre]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearUsuario(int $tenantId, string $email = 'test@example.com'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (tenant_id, nombre, email, password_hash)
             VALUES (:tenant_id, :nombre, :email, :hash)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => 'Usuario de prueba',
            'email' => $email,
            'hash' => password_hash('secreto', PASSWORD_DEFAULT),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearCampo(int $tenantId, string $nombre = 'Campo de prueba'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO campos (tenant_id, nombre) VALUES (:tenant_id, :nombre)');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearLote(int $tenantId, int $campoId, float $hectareas = 10.0, string $nombre = 'Lote de prueba'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lotes (tenant_id, campo_id, nombre, hectareas)
             VALUES (:tenant_id, :campo_id, :nombre, :hectareas)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'campo_id' => $campoId,
            'nombre' => $nombre,
            'hectareas' => $hectareas,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearInsumo(int $tenantId, string $nombre = 'Glifosato', string $unidad = 'litros', ?int $marcaId = null, ?int $categoriaId = null): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO insumos (tenant_id, nombre, marca_id, categoria_id, unidad_medida)
             VALUES (:tenant_id, :nombre, :marca_id, :categoria_id, :unidad_medida)'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'nombre' => $nombre,
            'marca_id' => $marcaId,
            'categoria_id' => $categoriaId,
            'unidad_medida' => $unidad,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearMarca(int $tenantId, string $nombre = 'Roundup'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO marcas (tenant_id, nombre) VALUES (:tenant_id, :nombre)');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearCategoria(int $tenantId, string $nombre = 'Herbicida'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO categorias (tenant_id, nombre) VALUES (:tenant_id, :nombre)');
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearLabor(int $tenantId, string $nombre = 'Pulverización', float $litrosPorHectarea = 3.0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO labores (tenant_id, nombre, litros_por_hectarea) VALUES (:tenant_id, :nombre, :litros)'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre, 'litros' => $litrosPorHectarea]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function crearEstacionCombustible(int $tenantId, string $nombre = 'YPF Leones', float $precioPorLitro = 900.0): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO estaciones_combustible (tenant_id, nombre, precio_por_litro) VALUES (:tenant_id, :nombre, :precio)'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'nombre' => $nombre, 'precio' => $precioPorLitro]);

        return (int) $this->pdo->lastInsertId();
    }
}
