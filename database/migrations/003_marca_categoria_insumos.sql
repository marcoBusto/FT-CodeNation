-- Catálogo de marcas comerciales por tenant (ej. "Roundup", "Panzer Gold").
CREATE TABLE marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_marcas_tenant_nombre (tenant_id, nombre),
    INDEX idx_marcas_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogo de categorías por tenant (ej. "Herbicida", "Fertilizante", "Semilla").
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_categorias_tenant_nombre (tenant_id, nombre),
    INDEX idx_categorias_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ambas opcionales (NULL): un insumo puede cargarse sin marca/categoría
-- definida todavía. La unicidad de insumos se revalida en PHP (no en la
-- base) porque con columnas NULL, MySQL/MariaDB no las considera iguales
-- entre sí en una UNIQUE KEY (dejaría colar duplicados sin marca/categoría).
ALTER TABLE insumos
    ADD COLUMN marca_id INT NULL AFTER nombre,
    ADD COLUMN categoria_id INT NULL AFTER marca_id,
    ADD FOREIGN KEY (marca_id) REFERENCES marcas(id),
    ADD FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    DROP INDEX uq_insumos_tenant_nombre,
    ADD INDEX idx_insumos_marca (marca_id),
    ADD INDEX idx_insumos_categoria (categoria_id);
