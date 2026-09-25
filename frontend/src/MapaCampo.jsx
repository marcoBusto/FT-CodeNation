import { useEffect, useRef, useState } from 'react'
import { cargarGoogleMaps } from './cargarGoogleMaps'

const CENTRO_PAMPEANA = { lat: -33.8, lng: -61.5 }

// Mapa con un único marcador arrastrable: reemplaza el texto libre de
// ubicación por una coordenada real. Reutiliza el mismo script de Google
// Maps que ya carga MapaLote para no pagar una API nueva.
function MapaCampo({ latitud, longitud, onUbicacionCambiada }) {
  const contenedorRef = useRef(null)
  const marcadorRef = useRef(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    let cancelado = false

    cargarGoogleMaps()
      .then((maps) => {
        if (cancelado || !contenedorRef.current) return

        const tieneUbicacion = latitud != null && longitud != null
        const centro = tieneUbicacion ? { lat: Number(latitud), lng: Number(longitud) } : CENTRO_PAMPEANA

        const mapa = new maps.Map(contenedorRef.current, {
          center: centro,
          zoom: tieneUbicacion ? 14 : 7,
          mapTypeId: 'satellite',
        })

        const marcador = new maps.Marker({ position: centro, map: mapa, draggable: true })
        marcadorRef.current = marcador

        const notificarPosicion = (posicion) => {
          onUbicacionCambiada?.({ lat: posicion.lat(), lng: posicion.lng() })
        }

        maps.event.addListener(marcador, 'dragend', () => notificarPosicion(marcador.getPosition()))
        maps.event.addListener(mapa, 'click', (evento) => {
          marcador.setPosition(evento.latLng)
          notificarPosicion(evento.latLng)
        })
      })
      .catch((err) => setError(err.message))

    return () => {
      cancelado = true
      marcadorRef.current?.setMap(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  return (
    <div>
      <div ref={contenedorRef} className="h-72 w-full rounded-md border border-gray-300" />
      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
      {!error && (
        <p className="mt-1 text-xs text-gray-700">
          Hacé click en el mapa o arrastrá el marcador para fijar la ubicación exacta del campo.
        </p>
      )}
    </div>
  )
}

export default MapaCampo
