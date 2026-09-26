-- Corrección encontrada en la corrida de QA del 2026-09-26: "Cerrar sesión"
-- solo borraba el token en el navegador: el JWT seguía siendo válido en el
-- servidor hasta que venciera solo (7 días), aunque el usuario ya hubiera
-- cerrado sesión. token_version permite invalidarlo de verdad: cada token
-- lleva la versión vigente al momento de emitirse; si no coincide con la
-- actual del usuario, se rechaza. Cerrar sesión (o un cambio de contraseña
-- a futuro) incrementa esta columna, invalidando de una todos los tokens ya
-- emitidos para ese usuario.
ALTER TABLE usuarios
    ADD COLUMN token_version INT NOT NULL DEFAULT 0;

-- Nota: el otro hallazgo de la corrida de QA (nombres repetidos de Campos y
-- Lotes) se resuelve en PHP, no acá -- mismo criterio que ya usa
-- InsumoController: una restricción UNIQUE de base no distingue registros
-- desactivados de activos, y bloquearía reusar el nombre de un campo/lote
-- viejo que ya se dio de baja (ej. un campo vendido).
