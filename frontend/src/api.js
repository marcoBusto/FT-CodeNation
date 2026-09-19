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
