# CLAUDE.md

Contexto para Claude Code al trabajar en este repositorio.

## Proyecto

[Nombre y descripción corta del sistema — completar al arrancar el proyecto]. Estado actual: **en configuración inicial**, sin funcionalidades implementadas todavía.

## Stack

- **Frontend:** React + Vite + Tailwind CSS
- **Backend:** PHP, API REST, respuestas JSON
- **Base de datos:** MySQL/MariaDB
- **Control de versiones:** Git + GitHub
- **Frontend en producción:** [completar — ej. Vercel]
- **Backend (etapa experimental):** local
- **Backend (producción futura):** [completar — VPS/hosting compatible, sin rehacer la aplicación]

Ver [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) y [docs/DECISIONES.md](docs/DECISIONES.md) para el detalle y la justificación de estas decisiones.

## Arquitectura fundamental

```
React → HTTP/HTTPS → API REST (PHP) → lógica de negocio → MySQL/MariaDB → JSON → React
```

React **nunca** se conecta directamente a MySQL. Toda funcionalidad nueva debe respetar este flujo. Al implementar una funcionalidad, explicar el recorrido concreto: petición HTTP → endpoint → PHP → base de datos → respuesta JSON → React.

## Perfil del desarrollador

El usuario (Marco) tiene experiencia principalmente en frontend, conocimientos beginner de React y conocimientos limitados de backend y bases de datos. Ante una decisión técnica importante:

- explicar qué significa,
- explicar por qué es necesaria,
- explicar las alternativas,
- recomendar una opción,
- no asumir conocimientos avanzados de backend/DB.

## Reglas de trabajo

1. No desarrollar funcionalidades sin acuerdo previo.
2. No hacer deploy sin aprobación explícita.
3. No instalar dependencias innecesarias.
4. No cambiar tecnologías del stack sin justificarlo.
5. No agregar frameworks importantes sin aprobación.
6. No inventar reglas de negocio: preguntar cuando no estén definidas.
7. No hacer cambios destructivos.
8. Antes de cambios importantes, explicar qué se va a hacer.
9. Git se usa como mecanismo de control y recuperación (commits frecuentes y descriptivos).
10. El proyecto debe poder mantenerse y depurarse mediante Claude Code.
11. Los bugs se investigan hasta encontrar la causa raíz antes de modificarlos — no parchear síntomas.
12. Las funcionalidades críticas deben tener tests.
13. La arquitectura debe ser portable (no atada a un proveedor de hosting específico).
14. El sistema debe poder migrar a producción posteriormente sin rehacer la aplicación.
15. Las decisiones importantes quedan documentadas en [docs/DECISIONES.md](docs/DECISIONES.md).

## Estructura del repositorio

```
frontend/     React + Vite + Tailwind
backend/      API REST en PHP (public/, src/, config/, tests/)
database/     schema.sql y migrations/
docs/         documentación de arquitectura y decisiones
```

## Comandos útiles

_Pendiente — se completará cuando exista código funcional en `frontend/` y `backend/`._
