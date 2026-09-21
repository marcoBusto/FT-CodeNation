// En Vercel se configura VITE_API_URL apuntando al backend real
// (ej. https://stock.codenation.com.ar); en desarrollo local cae a localhost.
const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

// Todavía no existe login (ver docs/DECISIONES.md, "Resolución de tenant"):
// el tenant se indica a mano y viaja en cada request vía X-Tenant-Id. Cuando
// se construya login, este archivo es el único lugar que cambia.
export function obtenerTenantId() {
  return localStorage.getItem('tenant_id') ?? ''
}

export function guardarTenantId(tenantId) {
  localStorage.setItem('tenant_id', tenantId)
}

export function apiFetch(ruta, opciones = {}) {
  return fetch(`${API_URL}${ruta}`, {
    ...opciones,
    headers: {
      'Content-Type': 'application/json',
      'X-Tenant-Id': obtenerTenantId(),
      ...opciones.headers,
    },
  }).then((res) => res.json())
}

// Para endpoints que devuelven un archivo (ej. los PDF de /reportes) en vez
// de JSON: acá no hay contrato {data, error} que parsear.
export function apiFetchBlob(ruta) {
  return fetch(`${API_URL}${ruta}`, {
    headers: { 'X-Tenant-Id': obtenerTenantId() },
  }).then((res) => {
    if (!res.ok) throw new Error('No se pudo generar el reporte.')
    return res.blob()
  })
}
