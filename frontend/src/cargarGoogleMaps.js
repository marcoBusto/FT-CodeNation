// Inyecta el script de Google Maps una sola vez (aunque se llame desde
// varios componentes) y devuelve una promesa que resuelve con window.google.maps.
let promesaCarga = null

export function cargarGoogleMaps() {
  if (window.google?.maps) return Promise.resolve(window.google.maps)
  if (promesaCarga) return promesaCarga

  promesaCarga = new Promise((resolve, reject) => {
    const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY
    if (!apiKey) {
      reject(new Error('Falta configurar VITE_GOOGLE_MAPS_API_KEY.'))
      return
    }

    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=drawing,geometry`
    script.async = true
    script.onload = () => resolve(window.google.maps)
    script.onerror = () => reject(new Error('No se pudo cargar Google Maps.'))
    document.head.appendChild(script)
  })

  return promesaCarga
}
