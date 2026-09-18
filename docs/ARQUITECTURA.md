# Arquitectura

## Flujo general

```
React
  ↓ petición HTTP/HTTPS
API REST (PHP)
  ↓
Lógica de negocio
  ↓
MySQL/MariaDB
  ↓ respuesta JSON
React
```

## Capas

### 1. Frontend — React + Vite + Tailwind CSS
Interfaz de usuario. Se comunica con el backend exclusivamente mediante peticiones HTTP (`fetch`/`axios`) a la API REST. No tiene acceso directo a la base de datos ni a credenciales de esta.

### 2. API REST — PHP
Punto de entrada único de toda petición del frontend. Recibe la petición HTTP, la valida, ejecuta la lógica de negocio correspondiente y devuelve una respuesta en formato JSON. Vive en `backend/`.

### 3. Lógica de negocio
Reglas propias del sistema, separadas del código que solo recibe/envía HTTP. Vive en `backend/src/`.

### 4. Base de datos — MySQL/MariaDB
Persistencia de los datos. Solo el backend PHP tiene acceso a ella; el frontend nunca se conecta directamente.

## Por qué React no accede directamente a MySQL

- **Seguridad:** las credenciales de la base de datos nunca deben llegar al navegador del usuario.
- **Control:** toda regla de negocio se aplica en un único lugar (el backend), no duplicada o inconsistente entre distintos clientes.
- **Portabilidad:** el backend puede cambiar de proveedor de hosting, o el frontend puede cambiar de framework, sin que el otro lado se entere — se comunican solo por HTTP/JSON.

## Entornos

| Entorno | Frontend | Backend | Base de datos |
|---|---|---|---|
| Desarrollo actual | Local (Vite dev server) | Local (PHP built-in server o similar) | MySQL/MariaDB local |
| Producción futura | [completar] | [completar] | [completar] |

La arquitectura está pensada para que esta migración a producción no requiera rehacer la aplicación, solo cambiar configuración (URLs, variables de entorno, credenciales).
