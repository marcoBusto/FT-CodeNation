# FT-CodeNation

[Nombre y descripción corta del sistema — completar al arrancar el proyecto].

> Metodología de trabajo propia de **CodeNation-SC** (Marco Busto): estructura de carpetas, reglas de trabajo (`CLAUDE.md`) y patrones de código que se repiten en todos los proyectos, para mantener coherencia entre sistemas distintos. No pertenece a ningún cliente ni proyecto en particular.

**Estado actual: en configuración inicial.** Todavía no hay funcionalidades implementadas ni datos de negocio — arranca completamente desde cero.

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
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS stock_agricola CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root stock_agricola < database/schema.sql
   ```
2. Copiar `backend/.env.example` a `backend/.env` (no se commitea) y ajustar los datos de conexión reales.
3. Instalar las dependencias del backend (PHPUnit, solo para tests) y levantar el servidor embebido de PHP, usando `index.php` como router (para que rutas como `/recurso` funcionen, no solo `/`):
   ```
   cd backend && composer install
   php -S localhost:8000 -t public public/index.php
   ```
4. Levantar el frontend:
   ```
   cd frontend && npm install && npm run dev
   ```

### Correr los tests del backend

Los tests corren contra su propia base de datos (nunca contra la de desarrollo), fijada en `backend/tests/bootstrap.php`. Crearla una vez:
```
mysql -u root -e "CREATE DATABASE IF NOT EXISTS stock_agricola_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root stock_agricola_test < database/schema.sql
cd backend && vendor/bin/phpunit
```

## Roadmap — segunda versión (no prevista para esta entrega)

- Módulo de drones: que reciba directamente el archivo que genera el dron (en vez de carga manual). Falta definir formato de archivo y flujo exacto.

## Documentación

- [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) — arquitectura del sistema y flujo de datos
- [docs/DECISIONES.md](docs/DECISIONES.md) — registro de decisiones técnicas y su justificación
- [docs/HERRAMIENTAS.md](docs/HERRAMIENTAS.md) — herramientas y entorno de desarrollo concretos (útil para armar el prompt inicial de un proyecto nuevo)
- [CLAUDE.md](CLAUDE.md) — contexto y reglas de trabajo para asistencia con Claude Code
