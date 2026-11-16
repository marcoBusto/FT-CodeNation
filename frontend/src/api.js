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

// Invalida el token en el servidor (ver Auth::invalidarSesiones) y recién
// después borra la sesión local -- así "cerrar sesión" hace que ese token
// deje de servir para cualquiera que lo tenga, no solo en este navegador.
// Si falla la llamada (sin conexión, etc.) igual se borra localmente: el
// usuario no debe quedar trabado sin poder salir.
function borrarSesionLocal() {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('auth_usuario')
}

export function cerrarSesion() {
  return apiFetch('/logout', { method: 'POST' })
    .catch(() => {})
    .finally(borrarSesionLocal)
}

// El backend responde 401 solo cuando el token falta, venció (7 días) o fue
// invalidado por un logout. Sin esto la app quedaba "adentro" con un token
// muerto y cada pantalla mostraba un error en vez de volver al login.
// App.jsx escucha este evento para mostrar la pantalla de ingreso.
export const EVENTO_SESION_VENCIDA = 'sesion-vencida'

function avisarSiSesionVencida(res, tokenEnviado) {
  if (res.status === 401 && tokenEnviado) {
    borrarSesionLocal()
    window.dispatchEvent(new Event(EVENTO_SESION_VENCIDA))
  }
  return res
}

// fetch solo rechaza cuando no hubo respuesta (sin señal, servidor caído):
// se traduce el "Failed to fetch" del navegador a un mensaje entendible.
function fetchConToken(ruta, opciones) {
  const token = obtenerToken()
  return fetch(`${API_URL}${ruta}`, {
    ...opciones,
    headers: { 'X-Auth-Token': token, ...opciones.headers },
  }).then(
    (res) => avisarSiSesionVencida(res, token),
    () => {
      throw new Error('No se pudo conectar con el servidor. Revisá la conexión e intentá de nuevo.')
    },
  )
}

export function apiFetch(ruta, opciones = {}) {
  return fetchConToken(ruta, {
    ...opciones,
    headers: { 'Content-Type': 'application/json', ...opciones.headers },
  }).then((res) => res.json())
}

// Para endpoints que devuelven un archivo (ej. los PDF de /reportes) en vez
// de JSON: acá no hay contrato {data, error} que parsear.
export function apiFetchBlob(ruta) {
  return fetchConToken(ruta, {}).then((res) => {
    if (!res.ok) throw new Error('No se pudo generar el reporte.')
    return res.blob()
  })
}
