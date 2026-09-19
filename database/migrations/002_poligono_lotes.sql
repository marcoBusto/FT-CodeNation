-- =============================================================================
-- MIGRACIÓN 002: polígono y perímetro del lote (dibujado en el mapa)
-- =============================================================================

ALTER TABLE lotes
    ADD COLUMN perimetro_metros DECIMAL(12, 2) NULL AFTER hectareas,
    ADD COLUMN poligono JSON NULL COMMENT 'Array [{lat,lng}, ...] del polígono dibujado en el mapa' AFTER perimetro_metros;
