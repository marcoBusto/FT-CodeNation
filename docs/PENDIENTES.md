# Pendientes

Última actualización: 2026-11-16. El detalle y la justificación de cada decisión está en [DECISIONES.md](DECISIONES.md); acá solo el estado y el orden.

## A. Operativos heredados de la beta (no son funcionalidades nuevas)

La etapa "solo correcciones" de la beta se cerró el 2026-11-16 al arrancar la fase 2; estos puntos siguen abiertos en paralelo.

**Deploy pendiente:** el fix de sesión vencida (commit `a3542bc`) está en GitHub pero no en Vercel: falta `npx vercel login` en la PC de Marco.

| # | Pendiente | Por qué importa | Estado |
|---|-----------|-----------------|--------|
| 1 | Backup diario de la base de producción (`ops/backup_stock.sh` + cron) | Protege contra errores de datos o borrados accidentales | Hecho (2026-11-16): cron diario 3:45 con el usuario de base `stock_backup` (solo lectura), 14 días de retención en `/home/bitnami/backups/db` |
| 2 | Activar snapshots automáticos de Lightsail (consola de AWS) | El backup en el mismo servidor no protege si el servidor se pierde; el snapshot es la copia externa | Pendiente |
| 3 | Separar al cliente (Gino) del tenant de demo | Hoy comparte tenant 1 con el usuario de Marco; sus datos deben quedar aislados | Pendiente |
| 4 | Límite de intentos de login | Protección contra fuerza bruta | Pendiente |
| 5 | Rotar credenciales expuestas en el chat: contraseña root de MySQL, contraseña de `msbusto@gmail.com`, contraseña admin de WordPress | Quedaron escritas en conversaciones | Pendiente |
| 6 | Escaneo completo con Wordfence en WordPress | Quedó pendiente del incidente de seguridad del 2026-09-19 | Pendiente |
| 7 | Cargar los campos/lotes reales del cliente y borrar los datos de prueba | La base todavía puede tener "Campo Norte/Sur" genéricos | Confirmar con Marco |
| 8 | Capacidad del servidor: 1 GB de RAM compartido con WordPress, PHP-FPM con `max_children = 6` | Ya hubo un 503 por saturación; si crece el uso se repite | Monitorear; decidir upgrade o instancia propia |
| 9 | Plan comercial: cerrar entrega inicial y mantenimiento, y dejar por escrito con el vendedor que el mantenimiento es 100% de Marco | Se discutió sin cerrar | Pausado |
| 10 | 20 casos de QA que requieren a una persona (WhatsApp desde celular real, diálogo de impresión, sesión vencida a los 7 días) | No automatizables | Pendiente |

## B. Fase 2 (iniciada el 2026-11-16) — orden sugerido

| # | Funcionalidad | Estado del diseño | Notas |
|---|---------------|-------------------|-------|
| 1 | Cultivo / campaña por lote (`cultivos`, `siembras`, `siembra_id` opcional en movimientos) | Construido y probado en local (2026-11-16); falta deploy | Requiere aplicar la migración 007 en producción antes de subir el backend |
| 2 | N.º de lote SENASA en cada ENTRADA + link a la página pública de AgroTraza | Diseñado | Versión chica de la trazabilidad por bidón; sin QR propio ni app nativa |
| 3 | Costos por insumo y por campaña, y reportes por campaña | Sin diseñar | Depende de 1 |
| 4 | Agente de IA, v1 solo lectura | Diseñado | Falta definir quién paga el uso de la API y dónde vive el chat |
| 5 | Lector de QR / código de barras desde la cámara del navegador | Idea | Mejora de 2; sigue dentro de la web app, no nativa |
| 6 | Recordatorio de devolución de envases vacíos (Ley 27.279 / CampoLimpio, 1 año desde la compra) | Idea | Problema distinto a la trazabilidad de uso |
| 7 | Agente de IA, v2 con escritura y confirmación explícita | Diseñado | Después de medir costos de la v1 |
| 8 | Funcionamiento sin señal en el campo (web offline) | Idea | Solo si el cliente lo necesita |
| 9 | Alta de usuarios por pantalla, recuperar contraseña por email, 2FA opcional | Sin diseñar | Hoy los usuarios se crean a mano en la base |
| 10 | Módulo de drones (recibir el archivo que genera el dron) | Sin definir formato | Mencionado en el README |

## C. Cronograma (acordado con Marco el 2026-11-16)

La lista de la sección B se ordenó el 2026-10-08 pero nunca tuvo fechas. Este cronograma asume una actualización por semana y un deploy al cierre de cada una. Las fechas se corren si una semana no se trabaja; el orden no.

| Semana | Qué se hace | Necesita de Marco antes de empezar |
|--------|-------------|------------------------------------|
| 16–22 nov | Cerrar el deploy de Siembras (frontend a Vercel, incluye el fix de sesión vencida). Operativos: snapshots de Lightsail (A2), rotar credenciales (A5), quitar la línea duplicada del cron de backup, confirmar el 20 nov que el certificado de `stock` se renovó solo | `npx vercel login`; entrar a la consola de AWS |
| 23–29 nov | N.º de lote SENASA en cada ENTRADA + link a AgroTraza (B2). Límite de intentos de login (A4) | Nada: B2 ya está diseñado |
| 30 nov–13 dic | Costos por insumo y por campaña, y reportes por campaña (B3). Primera semana diseño y reglas, segunda construcción y tests | Definir reglas: de dónde sale el precio (por compra o precio vigente), moneda, qué reportes hacen falta |
| 14–20 dic | Agente de IA v1, solo lectura (B4) | Decidir quién paga el uso de la API y dónde vive el chat |
| 21 dic–3 ene | Sin funcionalidades nuevas: separar a Gino del tenant de demo (A3), cargar datos reales (A7), los 20 casos de QA manual (A10) | Datos reales del cliente |
| Enero | Lector de QR desde la cámara (B5) y recordatorio de envases vacíos (B6) | Confirmar que siguen siendo prioridad |
| Sin fecha | Agente v2 con escritura (B7, después de medir costos de la v1), offline (B8), alta de usuarios y recuperación de contraseña (B9), drones (B10), Wordfence (A6), plan comercial (A9) | — |

Capacidad del servidor (A8): medido el 2026-11-16, disco al 21% y memoria al límite con la configuración ya en el mínimo. Decidir el upgrade a 2 GB antes de sumar el agente de IA o el primer cliente con uso diario.

## Descartado

- App nativa (Kotlin/Flutter) para el lector de QR: un cambio de stack no justificado; se resuelve en la web app actual.
- Integrar los web services de SENASA para *reportar* movimientos: para el productor es opcional, la obligación es del distribuidor.
