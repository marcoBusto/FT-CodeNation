-- =============================================================================
-- ESQUEMA BASE DE DATOS: Gestión de Stock e Insumos Agrícolas (Multi-Tenant)
-- Motor: MySQL/MariaDB
-- Características: Multi-tenant (aislamiento por tenant_id), stock inmutable
--                  basado en movimientos (ENTRADA / EGRESO_LOTE / AJUSTE)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS movimientos_insumo;
DROP TABLE IF EXISTS insumos;
DROP TABLE IF EXISTS marcas;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS lotes;
DROP TABLE IF EXISTS campos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS tenants;

SET FOREIGN_KEY_CHECKS = 1;

-- Convención de este equipo de trabajo:
--   - Motor InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci en todas las tablas.
--   - Multi-tenant por columna: toda tabla de datos de negocio tiene tenant_id,
--     y toda consulta del backend filtra por él (ver docs/ARQUITECTURA.md,
--     sección "Resolución de tenant").
--   - Stock como ledger inmutable: cada fila de movimientos_insumo es un evento
--     que nunca se edita ni se borra; el stock actual se calcula sumando estas
--     filas, nunca se guarda como columna mutable. Ver docs/DECISIONES.md.
--   - Reglas de validación de negocio (obligatoriedad condicional, formato,
--     signo, stock negativo, etc.) se validan en el backend, no con
--     CHECK/triggers en la base, por portabilidad entre MySQL y MariaDB.
--   - Cada cambio de esquema es un archivo nuevo en database/migrations/
--     (001_..., 002_..., etc.), nunca se edita un archivo de migración ya aplicado.

-- 1. TENANTS
CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. USUARIOS
-- Todavía no existe login real (mismo criterio que el proyecto de referencia,
-- ver DECISIONES.md "Usuario placeholder por tenant"): cada movimiento se
-- atribuye por ahora al primer usuario activo del tenant.
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_usuarios_tenant_email (tenant_id, email),
    INDEX idx_usuarios_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CAMPOS
CREATE TABLE campos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    ubicacion VARCHAR(255) NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    INDEX idx_campos_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. LOTES
CREATE TABLE lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    campo_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    hectareas DECIMAL(10, 2) NOT NULL,
    perimetro_metros DECIMAL(12, 2) NULL,
    poligono JSON NULL COMMENT 'Array [{lat,lng}, ...] del polígono dibujado en el mapa',
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (campo_id) REFERENCES campos(id),
    INDEX idx_lotes_tenant (tenant_id),
    INDEX idx_lotes_campo (campo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. MARCAS (catálogo de marcas comerciales por tenant)
CREATE TABLE marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_marcas_tenant_nombre (tenant_id, nombre),
    INDEX idx_marcas_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CATEGORIAS (catálogo de categorías de insumo por tenant)
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_categorias_tenant_nombre (tenant_id, nombre),
    INDEX idx_categorias_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. INSUMOS
CREATE TABLE insumos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    marca_id INT NULL,
    categoria_id INT NULL,
    unidad_medida ENUM('litros', 'kg', 'bolsas') NOT NULL,
    estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (marca_id) REFERENCES marcas(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    INDEX idx_insumos_tenant (tenant_id),
    INDEX idx_insumos_marca (marca_id),
    INDEX idx_insumos_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. MOVIMIENTOS DE INSUMO (Ledger Inmutable de Stock)
-- Reglas validadas en el backend, no en la base de datos (portabilidad MySQL/MariaDB):
--   - cantidad_total es siempre positiva en ENTRADA y EGRESO_LOTE; el signo lo
--     determina "tipo" (ENTRADA suma, EGRESO_LOTE resta).
--   - en AJUSTE, cantidad_total se guarda con signo (positivo suma, negativo
--     resta), porque una corrección puede ir en cualquier sentido.
--   - lote_id es obligatorio en EGRESO_LOTE (para poder calcular dosis/ha) y
--     opcional en ENTRADA/AJUSTE.
--   - dosis_por_ha solo aplica a EGRESO_LOTE (informativo, no recalcula
--     cantidad_total).
--   - un EGRESO_LOTE se rechaza si deja el stock del insumo en negativo.
CREATE TABLE movimientos_insumo (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    insumo_id INT NOT NULL,
    lote_id INT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('ENTRADA', 'EGRESO_LOTE', 'AJUSTE') NOT NULL,
    dosis_por_ha DECIMAL(10, 3) NULL COMMENT 'Solo aplica cuando tipo = EGRESO_LOTE',
    cantidad_total DECIMAL(12, 3) NOT NULL COMMENT 'Con signo en AJUSTE; siempre positiva en ENTRADA/EGRESO_LOTE',
    observacion TEXT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id),
    FOREIGN KEY (lote_id) REFERENCES lotes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_mov_insumo_tenant (tenant_id),
    INDEX idx_mov_insumo_insumo (insumo_id),
    INDEX idx_mov_insumo_lote (lote_id),
    INDEX idx_mov_insumo_fecha (fecha_hora)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
