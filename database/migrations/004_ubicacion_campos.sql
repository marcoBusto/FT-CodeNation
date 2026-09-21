-- Coordenadas reales del campo (lat/lng), fijadas arrastrando un marcador en
-- el mapa (mismo Google Maps que ya se usa para dibujar el polígono de los
-- lotes). Ambas nullable: un campo puede seguir cargándose solo con el texto
-- libre de "ubicacion" si todavía no se marcó en el mapa.
ALTER TABLE campos
    ADD COLUMN latitud DECIMAL(10, 7) NULL AFTER ubicacion,
    ADD COLUMN longitud DECIMAL(10, 7) NULL AFTER latitud;
