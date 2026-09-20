import { useEffect, useRef, useState } from 'react'
import { cargarGoogleMaps } from './cargarGoogleMaps'

const CENTRO_PAMPEANA = { lat: -33.8, lng: -61.5 }

function MapaLote({ onPoligonoCompleto }) {
  const contenedorRef = useRef(null)
  const mapaRef = useRef(null)
  const mapsRef = useRef(null)
  const poligonoRef = useRef(null)
  const clickListenerRef = useRef(null)
  const [metricas, setMetricas] = useState(null)
  const [error, setError] = useState(null)
  const [dibujando, setDibujando] = useState(true)
  const [cantidadPuntos, setCantidadPuntos] = useState(0)

  useEffect(() => {
    let cancelado = false

    cargarGoogleMaps()
      .then((maps) => {
        if (cancelado || !contenedorRef.current) return
        mapsRef.current = maps

        const mapa = new maps.Map(contenedorRef.current, {
          center: CENTRO_PAMPEANA,
          zoom: 13,
          mapTypeId: 'satellite',
        })
        mapaRef.current = mapa

        const poligono = new maps.Polygon({
          fillColor: '#1f4b33',
          fillOpacity: 0.25,
          strokeColor: '#1f4b33',
          strokeWeight: 2,
          clickable: false,
          map: mapa,
        })
        poligonoRef.current = poligono

        iniciarDibujo(maps, mapa, poligono)
      })
      .catch((err) => setError(err.message))

    return () => {
      cancelado = true
      if (clickListenerRef.current && mapsRef.current) {
        mapsRef.current.event.removeListener(clickListenerRef.current)
      }
      poligonoRef.current?.setMap(null)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Google discontinuó DrawingManager (ver aviso en
  // https://developers.google.com/maps/deprecations): en vez de un modo de
  // dibujo automático, cada click en el mapa agrega un vértice al polígono.
  const iniciarDibujo = (maps, mapa, poligono) => {
    const path = poligono.getPath()
    path.clear()
    poligono.setOptions({ clickable: false })
    setCantidadPuntos(0)
    setMetricas(null)
    setDibujando(true)

    clickListenerRef.current = maps.event.addListener(mapa, 'click', (evento) => {
      path.push(evento.latLng)
      setCantidadPuntos(path.getLength())
    })
  }

  const finalizarPoligono = () => {
    const maps = mapsRef.current
    const poligono = poligonoRef.current
    if (!maps || !poligono || poligono.getPath().getLength() < 3) return

    maps.event.removeListener(clickListenerRef.current)
    clickListenerRef.current = null
    poligono.setOptions({ editable: true, clickable: true })
    setDibujando(false)

    const recalcular = () => calcularMetricas(maps, poligono, onPoligonoCompleto, setMetricas)
    recalcular()
    maps.event.addListener(poligono.getPath(), 'set_at', recalcular)
    maps.event.addListener(poligono.getPath(), 'insert_at', recalcular)
    maps.event.addListener(poligono.getPath(), 'remove_at', recalcular)
  }

  const redibujar = () => {
    const maps = mapsRef.current
    const mapa = mapaRef.current
    const poligono = poligonoRef.current
    if (!maps || !mapa || !poligono) return

    poligono.setOptions({ editable: false })
    iniciarDibujo(maps, mapa, poligono)
  }

  return (
    <div className="relative">
      <div ref={contenedorRef} className="h-96 w-full rounded-md border border-gray-200" />

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}

      {dibujando && !error && (
        <div className="absolute left-3 top-3 rounded-md bg-white/95 p-3 text-sm shadow-md">
          <p className="text-gray-700">Hacé click en el mapa para marcar cada esquina del lote.</p>
          <p className="mt-1 text-xs text-gray-500">
            {cantidadPuntos} punto{cantidadPuntos === 1 ? '' : 's'} marcado{cantidadPuntos === 1 ? '' : 's'}
          </p>
          <button
            type="button"
            onClick={finalizarPoligono}
            disabled={cantidadPuntos < 3}
            className="mt-2 rounded-md bg-brand-primary px-3 py-1 text-xs text-white disabled:opacity-40"
          >
            Terminar polígono
          </button>
        </div>
      )}

      {metricas && (
        <div className="absolute right-3 top-3 rounded-md bg-white/95 p-3 shadow-md">
          <p className="text-sm font-semibold text-brand-primary-dark">{metricas.areaHectareas} ha</p>
          <p className="text-xs text-gray-500">Perímetro: {metricas.perimetroMetros} m</p>
          <button type="button" onClick={redibujar} className="mt-2 text-xs text-brand-primary underline">
            Redibujar
          </button>
        </div>
      )}
    </div>
  )
}

function calcularMetricas(maps, poligono, onPoligonoCompleto, setMetricas) {
  const path = poligono.getPath()
  const areaHectareas = Number((maps.geometry.spherical.computeArea(path) / 10000).toFixed(2))

  const coordenadas = path.getArray().map((latLng) => ({ lat: latLng.lat(), lng: latLng.lng() }))
  const pathCerrado = [...coordenadas, coordenadas[0]].map((c) => new maps.LatLng(c.lat, c.lng))
  const perimetroMetros = Number(maps.geometry.spherical.computeLength(pathCerrado).toFixed(1))

  const resultado = { areaHectareas, perimetroMetros, coordenadas }
  setMetricas(resultado)
  onPoligonoCompleto?.(resultado)
}

export default MapaLote
