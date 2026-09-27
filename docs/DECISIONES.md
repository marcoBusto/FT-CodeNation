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

## 2026-09-21 — Ubicación de campos: coordenadas reales (mapa) en vez de solo texto libre

**Decisión:** `campos` suma `latitud`/`longitud` (DECIMAL, opcionales). Se completan arrastrando un marcador en un mapa (`frontend/src/MapaCampo.jsx`), reutilizando el mismo script de Google Maps que ya cargaba `MapaLote.jsx` — sin agregar una API nueva ni un costo adicional. El campo de texto libre `ubicacion` se mantiene como referencia adicional opcional (ej. "tranquera azul").

**Motivo:** pedido del usuario en auditoría (2026-09-21): reemplazar el texto libre por una ubicación real. Se descartó un desplegable de ciudades fijas (alternativa más simple pero menos precisa) a favor del mapa, decisión tomada por el usuario tras comparar alternativas.

---

## 2026-09-21 — Combustible: catálogo de labores + estaciones con precio manual, calculador sin persistencia

**Decisión:** se agregan dos catálogos nuevos por tenant — `labores` (litros por hectárea estimados, ej. "Pulverización" ~3 l/ha) y `estaciones_combustible` (precio por litro cargado a mano). Con eso, `POST /combustible/estimar` calcula litros y costo estimados para un lote (`hectareas × litros_por_hectarea × precio_por_litro`). No persiste nada, mismo criterio que `estimarInsumo`.

**Motivo:** pedido del usuario en auditoría (2026-09-21). Se descartó buscar el precio de combustible automáticamente desde una fuente online porque **no existe una API pública y gratuita confiable de precios por surtidor/zona en Argentina** — la alternativa real sería scraping frágil (se rompe si la página fuente cambia) o un servicio de terceros pago. El usuario eligió carga manual explícitamente por esto. Tampoco se agregó un catálogo de "maquinaria": el usuario eligió que el consumo dependa de la labor, no del equipo usado.

**Dato específico del usuario, no hardcodeado:** las estaciones reales mencionadas (Gulf Agro, Axion, YPF — Leones, Córdoba) no se precargaron en el sistema porque es multi-tenant y esos datos son propios del negocio del usuario, no una regla general de la aplicación. Las carga el usuario mismo desde la pestaña Combustible.

---

## 2026-09-21 — Reportes: PDF generado en el backend (dompdf) + impresión y WhatsApp resueltos en el navegador

**Decisión:** se agregó `dompdf/dompdf` (primera dependencia de negocio del backend — antes solo había dependencias de test) para generar PDF de dos reportes (`campos-lotes`, `insumos-stock`) desde HTML armado en `ReporteController`. "Imprimir" no usa el backend: abre una pestaña con una vista imprimible y llama al diálogo nativo del navegador. "Compartir por WhatsApp" intenta la Web Share API (`navigator.share` con el PDF como archivo adjunto, funciona en navegadores de celular) y si no está disponible cae a un link `wa.me` con aviso de que hay que adjuntar el PDF a mano.

**Motivo:** pedido del usuario en auditoría (2026-09-21). Se descartó integrar la WhatsApp Business API (envío 100% automático) porque requiere una cuenta business con costo por mensaje y aprobación — desproporcionado para el pedido ("enviar por WhatsApp o imprimir o descargar"). El usuario eligió la opción sin costo, aceptando que en navegadores de escritorio el adjunto del PDF sea manual.

---

## 2026-09-22 — Deploy a producción: sin `git` en el servidor, `apt` descartado por riesgo con MariaDB de Bitnami

**Hito/problema resuelto:** al deployar el backend (ubicación, combustible, reportes), el servidor de Lightsail (`WordPress_Multisite-CodeNation`) no tenía `git` instalado. `sudo apt-get install git` falló por dependencias rotas que además querían instalar `mariadb-server` desde el repositorio de Debian — **se abortó ese camino a propósito**: esa instancia corre la MariaDB propia de Bitnami (fuera de `apt`, en `/opt/bitnami/mariadb`) sirviendo varios sitios de clientes (no solo `stock.codenation.com.ar`); una segunda instalación de MariaDB por `apt` podía chocar con la que ya está corriendo.

**Cómo se resolvió:** se copiaron los archivos de código (`src/`, `public/`, `config/`, `composer.json`, `composer.lock` — sin `vendor/` ni `.env`) por `scp` directo desde la máquina de desarrollo, con backup previo del código anterior en `/home/bitnami/backups/`. Para las dependencias (`dompdf`), se instaló Composer con el instalador oficial vía `curl`/`php` (no toca `apt` ni el sistema), y se corrió `composer install --no-dev` directo en el servidor.

**Nota operativa para el próximo deploy:** no asumir que `git`/`composer` están disponibles en este servidor. El método de arriba (scp + composer vía curl) es el que funciona sin tocar paquetes del sistema. Además, la terminal SSH embebida de Lightsail (la del navegador) no ejecuta bien comandos pegados en **varias líneas** a la vez — conviene armar todo en un solo renglón con `;` entre comandos.

**Corte de conexión SSH directa:** durante el deploy, la conexión SSH directa desde la máquina de desarrollo (vía `scp`/`ssh` con la clave `.pem`) se cortó a mitad de una operación y no se pudo restablecer en el resto de la sesión (timeout total, sin que fuera `fail2ban` — no está instalado — ni el firewall de Lightsail, que permite SSH desde cualquier IP). Se terminó el deploy relevando comandos a través de la terminal del navegador de Lightsail. Puede ser el mismo tipo de fricción de red/antivirus ya documentado en esta máquina (ver la nota de Avast más arriba) — si se repite, no asumir que el servidor está caído, probar por la terminal del navegador antes de alarmarse.

---

## 2026-09-22 — Login real: JWT en header custom `X-Auth-Token`, reemplaza `X-Tenant-Id`

**Decisión:** se agregó `POST /login` (email + contraseña, valida contra `usuarios.password_hash` que ya existía en el esquema) que devuelve un token firmado (JWT, `firebase/php-jwt`). Todas las demás rutas ahora exigen ese token en el header `X-Auth-Token` — reemplaza al header manual `X-Tenant-Id` que el usuario tenía que tipear a mano. El backend saca `tenant_id` **y** `usuario_id` del token, así que los movimientos de stock ya no se atribuyen al "primer usuario del tenant" (placeholder eliminado junto con `src/Tenant.php`) sino al usuario que efectivamente inició sesión. Sesión válida por 7 días. `src/Auth.php` reemplaza a `src/Tenant.php` tal como estaba previsto en la entrada de "Resolución de tenant" de más arriba.

**Motivo:** pedido del usuario (2026-09-22, antes del lunes) — reemplazar el ingreso por ID de tenant por un login real, sin que la interfaz mencione la palabra "tenant" (el usuario final no sabe qué es).

**Por qué un header custom y no `Authorization: Bearer` estándar:** algunas configuraciones de Apache (y ya tuvimos sorpresas con este hosting, ver las entradas de arriba) no dejan pasar el header `Authorization` a PHP salvo configuración adicional. Un header propio evita ese riesgo y sigue el mismo patrón que ya funcionaba con `X-Tenant-Id`.

**Fuera de esta primera versión, a propósito:** recuperar contraseña por email (necesitaría infraestructura de envío de mails que no existe) y una pantalla para dar de alta usuarios nuevos (se siguen creando a mano en la base, igual que los tenants). El primer usuario real (`msbusto@gmail.com`, tenant "Agro Demo") se cargó a mano reemplazando el usuario placeholder "Admin Demo".

**Cómo aplicar en el próximo deploy:** el servidor de producción necesita la variable `JWT_SECRET` en `backend/.env` (generar con `openssl rand -hex 32`, nunca reusar la de desarrollo) antes de que el login funcione ahí.

---

## 2026-09-25 — Agente de IA en la web: diseño para la próxima actualización (NO construido todavía)

**Pedido del usuario:** en la auditoría de UX del 2026-09-25, se pidió sumar un agente de IA a la web. Al preguntar el alcance, Marco lo definió abierto: responder preguntas sobre los datos cargados, ayudar a cargar movimientos por chat, y servir de soporte/ayuda general del sistema — con lugar para crecer más adelante. Es la primera vez que Marco diseña un sistema con IA integrada, así que este registro es más explicativo de lo habitual.

**Qué significa "agente de IA" acá, en criollo:** un cuadro de chat en la web donde el usuario escribe en lenguaje natural (ej. "¿cuánto glifosato me queda?" o "cargá 100 litros de glifosato al lote 3") y un modelo de lenguaje (tipo el que arma este mismo documento) interpreta el pedido, consulta o modifica los datos del sistema, y responde en español.

**Decisión de alcance para la v1 (recomendada, pendiente de confirmar antes de construir):**
- **v1 = solo lectura.** El agente puede responder preguntas sobre stock, campos, lotes, movimientos — nunca escribe nada en la base todavía.
- **v2 (después, no en esta primera etapa) = escritura con confirmación.** Cargar movimientos por chat, pero mostrando siempre "esto es lo que voy a registrar, ¿confirmás?" antes de guardar — nunca que el agente actúe solo sin que el usuario vea y apruebe el movimiento exacto.

**Por qué separar en dos etapas:**
1. **Costo real y recurrente:** a diferencia del resto del sistema (que corre en un servidor ya pago), cada mensaje al agente de IA tiene un costo de uso (se paga por la cantidad de texto que procesa el modelo). Antes de dejarlo escribir datos libremente, conviene medir cuánto se usa y cuánto cuesta en la práctica con la versión de solo consulta.
2. **Seguridad de los datos:** los movimientos de stock son un ledger inmutable (ver la entrada de arriba sobre "Stock negativo") — una vez cargado un movimiento no se borra, solo se corrige con un ajuste nuevo. Un agente que escribe mal un dato por malinterpretar un mensaje es más riesgoso que un formulario, porque el usuario no eligió cada campo a mano. La confirmación explícita antes de guardar es la manera de mitigar esto sin perder la comodidad del chat.

**Forma técnica prevista (para cuando se construya):**
- **Proveedor:** API de Claude (Anthropic) — mismo motor que ya se usa para desarrollar este sistema con Claude Code, evita sumar un proveedor nuevo a evaluar.
- **Backend:** un endpoint nuevo (ej. `POST /agente/consulta`) que recibe el mensaje del usuario, se lo pasa a la API de Claude junto con "herramientas" (tool calling) conectadas a los controllers que ya existen — para v1, solo las de lectura: `CampoController::listar`, `LoteController::listar`, `InsumoController::stock`, `MovimientoInsumoController::listar`. El modelo decide qué herramienta llamar según la pregunta, y arma la respuesta en español con el resultado.
- **Frontend:** un componente de chat (cuadro de mensajes + input), nueva pestaña o widget flotante — a definir cuál cuando se construya.
- **Autenticación:** el endpoint del agente exige el mismo `X-Auth-Token` (JWT) que el resto de la API, así el agente solo ve los datos del tenant del usuario logueado — nunca de otro cliente.

**Fuera de esta primera versión, a propósito:** escritura de datos por chat (v2), y cualquier acción fuera de consultar/cargar información del propio negocio (nada de administrar el sistema, usuarios, ni configuración desde el chat).

**Pendiente antes de construir:** confirmar con Marco el modelo de costos (¿la API de Claude se paga con una cuenta de Marco, y ese costo entra en el abono de mantenimiento técnico que se está definiendo con el cliente?), y decidir dónde vive el chat en la interfaz.

## 2026-09-27 — Trazabilidad de bidón por QR: diseño para la próxima actualización (NO construido todavía)

**Pedido del usuario:** reunión de Marco con un ingeniero agrónomo (2026-09-27): poder escanear el QR de un bidón/envase y ver de dónde viene (origen: proveedor, fecha de compra) **y** dónde se usó (en qué lotes de campo, cuándo). No solo el origen — trazabilidad completa origen→uso.

**Por qué esto es más grande que "agregar una pantalla":** hoy el stock de un insumo es un número agregado (`SUM` de todos los movimientos de ese insumo). No existe el concepto de "esta cantidad específica vino de esta compra puntual". Para trazar un bidón concreto hace falta separar el stock por **partida de compra** (ojo: se usa la palabra "partida", no "lote" — "lote" ya significa parcela de campo en este sistema, y mezclar los dos términos sería confuso).

**Diseño propuesto:**
- Tabla nueva `partidas`: una partida se crea automáticamente cada vez que se registra una ENTRADA (1 ENTRADA = 1 partida). Guarda `codigo` (lo que representa el QR — ver más abajo), `proveedor` (opcional), y queda ligada al `movimiento_insumo` de esa ENTRADA para no duplicar cantidad/fecha.
- `movimientos_insumo` suma un `partida_id` opcional: al registrar un EGRESO, el usuario **escanea el QR del bidón que está usando**, y el sistema descuenta de esa partida puntual (no de un promedio automático tipo FIFO — más simple de implementar y coincide con el flujo real: "agarro este bidón, lo escaneo, cargo cuánto usé").
- Pantalla/endpoint nuevo "Escanear QR": dado un código, muestra la partida (insumo, proveedor, fecha y cantidad de ingreso) y la lista de egresos que se hicieron desde esa partida puntual (fecha, lote de campo, cantidad) — ahí está la trazabilidad completa que pidió el ingeniero.
- Todo opcional/aditivo: un tenant que no quiere usar esto puede seguir cargando movimientos sin partida, como hasta ahora.

**Pendiente de investigar antes de poder dimensionar el trabajo real (Marco no lo sabe todavía):** si el QR **ya viene de fábrica** en el bidón (y en ese caso, si es único por bidón individual o se repite por lote de fabricación completo), o si **el sistema tiene que generar e imprimir su propio QR** al cargar cada ENTRADA. Esto cambia bastante el alcance:
- Si ya viene de fábrica: el sistema solo necesita poder *leer* un QR (cámara del celular) y guardar ese código tal cual.
- Si lo generamos nosotros: además hace falta generar la imagen del QR e imprimirlo/pegarlo en el bidón físicamente, y una librería de generación de QR (a evaluar cuál, sin sumar dependencias de más — regla 3 de CLAUDE.md).
- En ambos casos hace falta una librería de **lectura** de QR desde la cámara del navegador (nueva dependencia de frontend, a definir cuál).

**Tercera opción a investigar (idea de Marco, 2026-09-27):** en vez de depender de un QR físico en el bidón, el mayorista/distribuidor podría tener su propia API de rastreo de lote/partida -- si existe y el cliente puede acceder a ella, sería más confiable que leer una etiqueta física (no depende de que el bidón conserve el QR en buen estado). Hay que confirmar con el cliente si el mayorista real que usa ofrece algo así.

**Siguiente paso concreto:** Marco tiene que averiguar con el cliente/ingeniero (a) cómo son los bidones reales que usan (¿traen QR o código de barras de fábrica? ¿es único por bidón?), y (b) si el mayorista/distribuidor tiene alguna API o sistema de rastreo de lote que se pueda integrar, antes de que esto se pueda dimensionar en serio.

## 2026-09-27 — Hallazgo que simplifica la trazabilidad: existe SENASA AgroTraza (sistema nacional)

**Investigación pedida por Marco** (¿el mayorista tiene alguna API de rastreo?): sí, y es mejor de lo esperado — no es de un mayorista puntual, es **nacional y obligatorio por ley**.

**Lo que se confirmó (fuentes oficiales, argentina.gob.ar):**
- SENASA opera el "Sistema Nacional de Trazabilidad de Productos Fitosanitarios" ("AgroTraza"), obligatorio para toda la cadena comercial (importador → distribuidor → comercio) desde el 1° de abril de 2025.
- Cada lote de producto vendido debe informarse (recepción, envío, venta), incluyendo el CUIT del comprador final. El **remito/factura de cada compra ya trae el número de lote SENASA** por normativa.
- El QR en el envase es opcional para el distribuidor, pero **si lo usan, la normativa exige que apunte a una URL de SENASA** con esta forma: `https://aps2.senasa.gov.ar/agrotraza/src/app/?action=showDetailPublicProduct&productCode={codigo}&batchId={lote}` — página pública, sin necesidad de credenciales, con el detalle oficial del producto y lote.
- Existe además una **API REST documentada** (ambiente de test y producción, manuales técnicos publicados) para consultar códigos de producto, tipo de envase, principio activo, etc. — pensada para que empresas de la cadena reporten movimientos, pero los métodos de "consulta" son de uso más abierto.

**Cómo cambia el diseño de la entrada anterior (2026-09-27, "Trazabilidad de bidón por QR"):**
- **No hace falta construir un sistema propio de "partidas" con QR generado por nosotros para la v1.** El número de lote SENASA ya es el identificador universal, y el usuario ya lo tiene a mano en cada remito.
- MVP más simple y rápido: al registrar una ENTRADA, campo opcional "N° de lote" (texto libre, tal como figura en el remito). Guardarlo en `movimientos_insumo` (o en la futura tabla `partidas` si se quiere separar stock por lote — esa parte del diseño anterior sigue siendo válida si se quiere trazar consumo interno) alcanza para lo básico.
- Para "ver el origen": un simple link/botón "Ver en SENASA" que arma la URL pública de arriba con el código de producto y lote cargados — sin scrapear ni pedir acceso a nada, es una página pública.
- Para "ver dónde se usó": eso sigue siendo 100% interno (nuestros propios movimientos), como ya estaba diseñado.
- Leer el QR físico del bidón con la cámara (para no tener que tipear el lote a mano) sigue siendo una mejora futura opcional, no un requisito para la v1 de esta función — se puede lanzar sin cámara todavía.

**Todavía pendiente:** confirmar con el cliente si el `productCode` de SENASA es un dato que van a tener a mano fácilmente (además del número de lote), y si el distribuidor que usa el cliente efectivamente participa del sistema (es obligatorio, pero vale confirmar en la práctica).

## 2026-09-27 — Descartada la migración a app nativa; aclaración sobre obligaciones SENASA

**Contexto:** Marco compartió una respuesta de otra herramienta de IA sugiriendo desarrollar una APK nativa (Kotlin/Flutter) con lector GS1 DataMatrix, GPS, base local offline (Room/SQLite) e integración con los web services de SENASA para *reportar* movimientos (`WS_INFO_EMPRESAS`).

**Decisión: no se sigue ese camino.**
- **Cambio de stack descartado:** pasar a app nativa es una migración tecnológica mayor no justificada (regla 4 de CLAUDE.md) frente a una alternativa mucho más chica: leer QR/código de barras desde la cámara del navegador, dentro de la web app React ya existente (librerías JS para esto, sin librería nueva de peso todavía a definir cuál si se llega a necesitar).
- **No hace falta integrar los web services de SENASA para reportar.** Se confirmó (fuente: manual técnico de SENASA) que la obligación de declarar cada venta con el CUIT del comprador es del **distribuidor**, no del productor/usuario final — para el productor, informar sus propias compras a SENASA es **opcional**. Alcanza con consultar/mostrar la página pública de SENASA (ver entrada anterior), no con reportar nada.
- **Trabajo offline (zonas rurales sin señal):** preocupación legítima, pero resoluble con tecnología web (service worker + almacenamiento local del navegador) más adelante si hace falta — no requiere una app nativa. No es un requisito para la v1.

**Hallazgo nuevo, separado de la trazabilidad de uso:** Ley 27.279 (CampoLimpio) obliga al productor a devolver el envase vacío dentro del año de la compra a un punto autorizado. Es un problema distinto (gestión de residuos, no trazabilidad de aplicación) — candidato a una funcionalidad futura simple ("recordatorio: este bidón vence su plazo de devolución el [fecha compra + 1 año]"), pero no se mezcla con el diseño de trazabilidad de uso ya registrado arriba.

## 2026-09-27 — Alcance de la v1 (beta): todo lo construido hasta hoy

**Decisión (Marco):** la primera versión beta que usa el cliente (Gino Biciuffa) es **el sistema completo tal como está al commit `a388767`**: Stock e Insumos (campos, lotes con mapa, insumos con marcas/categorías, movimientos), Combustible, Reportes (PDF/imprimir/WhatsApp), Simulador de compras y login real con JWT.

**Qué implica:**
- Durante la beta no se suman funcionalidades nuevas: el foco es **corregir errores** que reporte el cliente o que salgan de la auditoría.
- Lo ya diseñado pero no construido (cultivo/campaña por lote, N° de lote SENASA, agente de IA) queda para la versión siguiente, después de cerrar la beta.
- Puntos operativos a revisar en la auditoría (no son features nuevas del producto): separar al cliente del tenant de demo, backups automáticos de la base de producción, cambio de contraseña del usuario y límite de intentos de login.
