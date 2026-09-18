# FT-CodeNation

[Nombre y descripción corta del sistema — completar al arrancar el proyecto].

**Estado actual: en configuración inicial.** Todavía no hay funcionalidades implementadas. Este repositorio arranca como plantilla de trabajo (estructura, convenciones y patrones de código de CodeNation-SC), sin datos ni lógica de negocio de ningún proyecto anterior.

## Stack

- **Frontend:** React + Vite + Tailwind CSS
- **Backend:** PHP (API REST, respuestas JSON)
- **Base de datos:** MySQL / MariaDB
- **Control de versiones:** Git + GitHub

## Arquitectura

```
React
  ↓ HTTP/HTTPS
API REST (PHP)
  ↓
Lógica de negocio
  ↓
MySQL/MariaDB
  ↓ JSON
React
```

React **nunca** se conecta directamente a la base de datos: toda la comunicación pasa por la API REST en PHP. Ver [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) para el detalle de cada capa.

## Estructura del proyecto

```
frontend/     React + Vite + Tailwind
backend/      API REST en PHP
  public/       punto de entrada expuesto (index.php)
  src/          lógica de negocio, controladores, modelos
  config/       configuración (conexión a DB, variables de entorno)
  tests/        tests del backend
database/     esquema y migraciones de MySQL/MariaDB
docs/         documentación de arquitectura y decisiones
```

## Cómo levantar el proyecto localmente

Requiere PHP y MySQL/MariaDB instalados localmente (por ejemplo vía WAMP, XAMPP o Laragon).

1. Crear la base de datos y aplicar el schema:
   ```
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS nombre_de_la_base CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root nombre_de_la_base < database/schema.sql
   ```
2. Crear `backend/.env` (no se commitea) con los datos de conexión reales:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nombre_de_la_base
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Levantar el servidor embebido de PHP, usando `index.php` como router (para que rutas como `/recurso` funcionen, no solo `/`):
   ```
   php -S localhost:8000 -t backend/public backend/public/index.php
   ```

## Documentación

- [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) — arquitectura del sistema y flujo de datos
- [docs/DECISIONES.md](docs/DECISIONES.md) — registro de decisiones técnicas y su justificación
- [CLAUDE.md](CLAUDE.md) — contexto y reglas de trabajo para asistencia con Claude Code
