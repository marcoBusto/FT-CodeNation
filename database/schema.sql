-- =============================================================================
-- ESQUEMA BASE DE DATOS: [Nombre del proyecto]
-- Motor: MySQL/MariaDB
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- DROP TABLE IF EXISTS ...;

SET FOREIGN_KEY_CHECKS = 1;

-- Convención de este equipo de trabajo:
--   - Motor InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci en todas las tablas.
--   - Datos críticos (stock, saldos, caja, etc.) como ledger inmutable: tablas de
--     movimientos donde cada fila es un evento que nunca se edita ni se borra,
--     en vez de columnas de saldo mutables. Ver docs/DECISIONES.md del proyecto
--     de referencia (Sistema de Gestión Comercial) para el razonamiento completo.
--   - Reglas de validación de negocio (obligatoriedad condicional, formato, etc.)
--     se validan en el backend, no con CHECK/triggers en la base, por portabilidad
--     entre MySQL y MariaDB.
--   - Cada cambio de esquema es un archivo nuevo en database/migrations/
--     (001_..., 002_..., etc.), nunca se edita un archivo de migración ya aplicado.

-- CREATE TABLE ejemplo (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     nombre VARCHAR(150) NOT NULL,
--     creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
