-- Catálogo de labores agrícolas con su consumo de combustible estimado por
-- hectárea (ej. "Pulverización" ~3 l/ha, "Siembra directa" ~8 l/ha). Editable
-- por tenant, mismo patrón que marcas/categorías de insumos (ver
-- 003_marca_categoria_insumos.sql).
CREATE TABLE labores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    litros_por_hectarea DECIMAL(6, 2) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_labores_tenant_nombre (tenant_id, nombre),
    INDEX idx_labores_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estaciones de combustible con precio cargado a mano: no existe una fuente
-- pública y gratuita de precios por surtidor/zona en Argentina (ver
-- docs/DECISIONES.md), así que el precio de referencia lo actualiza el
-- usuario cuando cambia.
CREATE TABLE estaciones_combustible (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    precio_por_litro DECIMAL(10, 2) NOT NULL,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_estaciones_tenant_nombre (tenant_id, nombre),
    INDEX idx_estaciones_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
