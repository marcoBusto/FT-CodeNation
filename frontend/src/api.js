// En Vercel se configura VITE_API_URL apuntando al backend real
// (ej. https://stock.codenation.com.ar); en desarrollo local cae a localhost.
const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

// Sesión real (login con email/contraseña, ver docs/DECISIONES.md): el
// backend devuelve un token firmado (JWT) que viaja en el header
// X-Auth-Token en cada request. Reemplaza al header manual X-Tenant-Id
// que usaba este proyecto antes de tener login.
export function obtenerToken() {
  return localStorage.getItem('auth_token') ?? ''
}

export function obtenerUsuario() {
  try {
    return JSON.parse(localStorage.getItem('auth_usuario') ?? 'null')
  } catch {
    return null
  }
}

export function guardarSesion(token, usuario) {
  localStorage.setItem('auth_token', token)
  localStorage.setItem('auth_usuario', JSON.stringify(usuario))
}

export function cerrarSesion() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('auth_usuario')
}

export function apiFetch(ruta, opciones = {}) {
  return fetch(`${API_URL}${ruta}`, {
    ...opciones,
    headers: {
      'Content-Type': 'application/json',
      'X-Auth-Token': obtenerToken(),
      ...opciones.headers,
    },
  }).then((res) => res.json())
}

// Para endpoints que devuelven un archivo (ej. los PDF de /reportes) en vez
// de JSON: acá no hay contrato {data, error} que parsear.
export function apiFetchBlob(ruta) {
  return fetch(`${API_URL}${ruta}`, {
    headers: { 'X-Auth-Token': obtenerToken() },
  }).then((res) => {
    if (!res.ok) throw new Error('No se pudo generar el reporte.')
    return res.blob()
  })
}
