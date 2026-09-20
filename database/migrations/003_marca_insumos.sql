-- Agrega la marca comercial del insumo (ej. nombre="Glifosato", marca="Roundup").
-- Es NOT NULL DEFAULT '' (no NULL) para que la restricción de unicidad de abajo
-- funcione: en MySQL, dos NULL nunca se consideran iguales en una UNIQUE KEY,
-- así que con NULL se podrían cargar duplicados sin marca sin que la base lo note.
ALTER TABLE insumos
    ADD COLUMN marca VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre,
    DROP INDEX uq_insumos_tenant_nombre,
    ADD UNIQUE KEY uq_insumos_tenant_nombre_marca (tenant_id, nombre, marca);
