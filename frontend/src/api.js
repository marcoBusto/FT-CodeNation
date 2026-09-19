const API_URL = 'http://localhost:8000'

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
