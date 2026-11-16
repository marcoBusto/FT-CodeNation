-- Cultivo / campaña por lote (ver docs/DECISIONES.md, 2026-11-16).
-- 100% aditiva: no modifica ni borra datos existentes.

-- Catálogo de cultivos por tenant (ej. "Soja", "Maíz"). Mismo patrón que
-- marcas/categorias.
CREATE TABLE cultivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    UNIQUE KEY uq_cultivos_tenant_nombre (tenant_id, nombre),
    INDEX idx_cultivos_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué cultivo tuvo cada lote en cada campaña. El cultivo NO es una columna de
-- lotes porque el mismo lote rota de cultivo entre campañas, y puede tener
-- dos en la misma (ej. trigo y soja de segunda): por eso la unicidad es
-- lote + cultivo + campaña, no lote + campaña.
-- "En curso" = fecha_cosecha IS NULL.
CREATE TABLE siembras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    lote_id INT NOT NULL,
    cultivo_id INT NOT NULL,
    campania CHAR(7) NOT NULL COMMENT 'Formato AAAA/AA, ej. 2025/26',
    fecha_siembra DATE NULL,
    fecha_cosecha DATE NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (lote_id) REFERENCES lotes(id),
    FOREIGN KEY (cultivo_id) REFERENCES cultivos(id),
    UNIQUE KEY uq_siembras_lote_cultivo_campania (lote_id, cultivo_id, campania),
    INDEX idx_siembras_tenant (tenant_id),
    INDEX idx_siembras_cultivo (cultivo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opcional (NULL): los movimientos anteriores quedan sin siembra (el ledger
-- no se reescribe) y un egreso nuevo puede registrarse sin indicarla.
ALTER TABLE movimientos_insumo
    ADD COLUMN siembra_id INT NULL COMMENT 'Solo aplica cuando tipo = EGRESO_LOTE' AFTER lote_id,
    ADD FOREIGN KEY (siembra_id) REFERENCES siembras(id),
    ADD INDEX idx_mov_insumo_siembra (siembra_id);

-- Catálogo inicial de referencia para los tenants que ya existen (cultivos
-- de la zona pampeana). Cada tenant puede renombrarlos, borrarlos o sumar.
INSERT INTO cultivos (tenant_id, nombre)
SELECT t.id, c.nombre
FROM tenants t
CROSS JOIN (
    SELECT 'Soja' AS nombre UNION ALL SELECT 'Maíz' UNION ALL SELECT 'Trigo'
    UNION ALL SELECT 'Girasol' UNION ALL SELECT 'Sorgo' UNION ALL SELECT 'Cebada'
    UNION ALL SELECT 'Alfalfa' UNION ALL SELECT 'Avena'
) c;
