# Registro de decisiones técnicas

Registro de decisiones importantes del proyecto y su justificación, para no perder el contexto de por qué se eligió algo.

---

## [FECHA] — Stack tecnológico

**Decisión:** React + Vite + Tailwind CSS (frontend), PHP con API REST en JSON (backend), MySQL/MariaDB (base de datos) — mismo stack que otros proyectos de CodeNation-SC, por coherencia de trabajo.

**Motivo:** [completar si hay algo específico de este proyecto que lo justifique además de la coherencia con otros sistemas].

---

## 2026-09-18 — Módulo de Stock e Insumos Agrícolas: multi-tenant real (a diferencia de SC-CodeNation)

**Decisión:** este sistema es multi-tenant desde el arranque — todas las tablas de negocio (`campos`, `lotes`, `insumos`, `usuarios`, `movimientos_insumo`) tienen `tenant_id`, y todas las consultas del backend filtran por él.

**Motivo:** requisito explícito del usuario para este proyecto (a diferencia del proyecto de referencia `SC-CodeNation`, que decidió explícitamente **no** ser multi-tenant — "una instalación por negocio"). Acá sí hace falta que una misma base de datos sirva a varios negocios aislados entre sí.

---

## 2026-09-18 — Resolución de tenant: header `X-Tenant-Id` (temporal, hasta que exista login)

**Decisión:** todavía no existe login. Cada request a la API indica el tenant de forma explícita vía el header `X-Tenant-Id`. `backend/public/index.php` lo valida contra la tabla `tenants` (`src/Tenant.php`) antes de que cualquier controller se ejecute — así ningún endpoint puede "olvidarse" de filtrar por tenant. El frontend lo pide una vez (pantalla simple, sin contraseña) y lo guarda en `localStorage` (`frontend/src/api.js`).

**Motivo:** mismo criterio que usó el proyecto de referencia con el "usuario placeholder" (ver su `DECISIONES.md`, 2026-09-15): no bloquear la construcción del módulo de negocio esperando a tener login real, pero dejando explícito que es una solución temporal y documentada como tal.

**Cómo se reemplaza cuando exista login:** el tenant pasa a resolverse desde la sesión del usuario autenticado en vez del header. Ningún controller cambia, porque todos reciben `tenantId` ya resuelto como parámetro — solo cambia `src/Tenant.php` y `frontend/src/api.js`.

---

## 2026-09-18 — Usuario placeholder por tenant (temporal, mismo criterio que SC-CodeNation)

**Decisión:** `usuarios` es una tabla real (con `tenant_id`), pero como todavía no hay login, cada movimiento se atribuye al primer usuario activo de ese tenant (`MovimientoInsumoController::resolverUsuarioPlaceholder`), no a un usuario elegido en el request.

**Motivo:** equivalente multi-tenant del "usuario administrador placeholder" del proyecto de referencia — no bloquear el módulo de stock por no tener login todavía.

---

## 2026-09-18 — Stock negativo: se bloquea, tanto en `EGRESO_LOTE` como en `AJUSTE`

**Decisión:** el backend rechaza (HTTP 422) cualquier movimiento que deje el stock de un insumo en negativo — un `EGRESO_LOTE` que pide más de lo disponible, o un `AJUSTE` negativo mayor al stock actual.

**Motivo:** decisión explícita del usuario para `EGRESO_LOTE`, extendida a `AJUSTE` por consistencia (permitir que un ajuste "esquive" el bloqueo de egresos no tendría sentido).

**Cómo se implementa:** `MovimientoInsumoController::registrar` calcula el stock actual dentro de una transacción, bloqueando la fila del insumo (`SELECT ... FOR UPDATE`) para que dos movimientos concurrentes sobre el mismo insumo no puedan aprobarse ambos en base al mismo stock "antes".

---

## 2026-09-18 — `AJUSTE` se guarda con signo; `ENTRADA`/`EGRESO_LOTE` siempre positivos

**Decisión:** en `movimientos_insumo`, `cantidad_total` es siempre positiva en `ENTRADA` y `EGRESO_LOTE` (el signo lo determina el tipo), pero en `AJUSTE` se guarda con signo (positivo suma stock, negativo resta), porque una corrección puede ir en cualquier sentido.

**Motivo:** decisión explícita del usuario, alternativa a separar `AJUSTE` en dos tipos (positivo/negativo) como hace `tipos_movimiento_stock` en el proyecto de referencia. Se prefirió mantener los 3 tipos exactos pedidos (`ENTRADA`/`EGRESO_LOTE`/`AJUSTE`).

---

## 2026-09-18 — Tests del backend: PHPUnit contra una base de datos real de test

**Decisión:** se instaló `phpunit/phpunit` (primera dependencia de test del proyecto) para cubrir la lógica crítica: cálculo de stock, bloqueo de stock negativo (`EGRESO_LOTE` y `AJUSTE`), y aislamiento entre tenants. Los tests corren contra una base de datos real (`stock_agricola_test`, nunca `stock_agricola`), fijada en `backend/tests/bootstrap.php` y validada en runtime por `DatabaseTestCase::setUp()` (guardarraíl: solo corre si el nombre de la base termina en `_test`).

**Motivo:** regla 12 de `CLAUDE.md` (funcionalidades críticas con tests). Se descartó mockear PDO o usar SQLite en memoria para no perder cobertura sobre comportamiento específico de MySQL/MariaDB (`FOR UPDATE`, tipos `DECIMAL`/`ENUM`, etc.).

---

## 2026-09-18 — Antivirus (Avast) bloqueando `backend/public/index.php` en esta máquina

**Hito/problema resuelto:** durante el desarrollo, Avast eliminó silenciosamente `backend/public/index.php` en más de una ocasión (sin mostrar aviso), probablemente por heurística de "posible webshell" (script PHP que abre rutas dinámicas y corre como servidor). Se resolvió agregando la carpeta del proyecto completa (`C:\Users\mchan\Proyectos\FT-CodeNation`) a las excepciones de Avast (Configuración → General → Excepciones), no solo el ejecutable `php.exe`.

**Nota:** mismo tipo de fricción con Avast ya documentado en el proyecto de referencia (interceptación SSL de Composer, resuelta ahí con `cacert.pem`) — antivirus con inspección profunda es una fuente de fricción recurrente en esta máquina de desarrollo, no algo a replicar en el servidor de producción.

---

## 2026-09-19 — Lote: polígono dibujado en mapa (Google Maps) + perímetro

**Decisión:** `lotes` suma dos columnas opcionales: `perimetro_metros` (DECIMAL) y `poligono` (JSON, array de `{lat, lng}`). Se completan desde el frontend (`frontend/src/MapaLote.jsx`, con Google Maps Drawing + Geometry libraries) cuando el usuario dibuja el lote sobre el mapa en vez de tipear las hectáreas a mano. Siguen siendo opcionales — un lote se puede seguir cargando solo con hectáreas manuales, como hasta ahora.

**Motivo:** pedido explícito del usuario para que los productores delimiten sus lotes visualmente. Se guarda como JSON plano (no tipos GIS de MySQL) por simplicidad — no hay necesidad todavía de consultas espaciales (intersecciones, distancias entre lotes, etc.) que justifiquen esa complejidad.

**Dependencia externa nueva:** requiere una API key de Google Maps (`VITE_GOOGLE_MAPS_API_KEY`), con facturación habilitada en Google Cloud (tiene cuota gratuita mensual, pero exige tarjeta cargada). La gestiona el usuario directamente, no CodeNation-SC.

**Cálculo en el backend, no en el cliente:** se agregó `POST /lotes/estimar-insumo` (`hectareas × dosis_por_ha`) como ayuda para planificar compras de insumo antes de registrar un movimiento real. No persiste nada — es una estimación descartable. `dosis_por_ha` no tiene un valor por defecto fijo en el sistema (varía por insumo/cultivo); lo indica el usuario en cada estimación, para no inventar una regla de negocio no definida.

---
