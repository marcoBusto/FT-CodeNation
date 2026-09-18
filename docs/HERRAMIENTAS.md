# Herramientas y entorno de desarrollo

Referencia rápida de las herramientas concretas usadas en los proyectos de CodeNation-SC — útil para armar el prompt inicial de un sistema nuevo y mantener el mismo entorno entre proyectos.

## Lenguajes y frameworks

- **Frontend:** React 19 + Vite + Tailwind CSS v4 (plugin `@tailwindcss/vite`, sin `postcss.config.js` aparte)
- **Backend:** PHP (sin framework — API REST hecha a mano, sin Laravel/Symfony)
- **Base de datos:** MySQL/MariaDB (SQL estándar, sin stored procedures ni triggers — la lógica vive en PHP)

## Entorno local

- **Servidor local (Apache + PHP + MySQL/MariaDB):** WAMP (`wamp64`)
- **Gestor de dependencias backend:** Composer
- **Gestor de dependencias frontend:** npm (via Vite)
- **Linter frontend:** oxlint

## Control de versiones

- Git + GitHub (repos privados por proyecto)

## Asistente de desarrollo

- Claude Code, guiado por un `CLAUDE.md` por proyecto con las reglas de trabajo de CodeNation-SC (ver [CLAUDE.md](../CLAUDE.md))

## Convenciones que se repiten entre proyectos

- Conexión a base de datos vía PDO, credenciales en `backend/.env` (nunca hardcodeadas ni commiteadas) — ver [backend/config/Database.php](../backend/config/Database.php)
- Formato de respuesta JSON estándar en toda la API (`{ data, error }`) — ver [backend/public/index.php](../backend/public/index.php)
- Datos críticos (stock, saldos, dinero) como ledger inmutable: tablas de movimientos que nunca se editan ni se borran, en vez de columnas de saldo mutables
- Cambios de esquema como archivos de migración numerados en `database/migrations/`, `database/schema.sql` siempre refleja el estado completo actual
- Decisiones técnicas importantes documentadas en `docs/DECISIONES.md`, con motivo y alternativas descartadas
