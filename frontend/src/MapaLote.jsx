import { useEffect, useRef, useState } from 'react'
import { cargarGoogleMaps } from './cargarGoogleMaps'

const CENTRO_PAMPEANA = { lat: -33.8, lng: -61.5 }

function MapaLote({ onPoligonoCompleto }) {
  const contenedorRef = useRef(null)
  const drawingManagerRef = useRef(null)
  const poligonoRef = useRef(null)
  const mapsRef = useRef(null)
  const [metricas, setMetricas] = useState(null)
  const [error, setError] = useState(null)

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

        const drawingManager = new maps.drawing.DrawingManager({
          drawingMode: maps.drawing.OverlayType.POLYGON,
          drawingControl: true,
          drawingControlOptions: { drawingModes: [maps.drawing.OverlayType.POLYGON] },
          polygonOptions: {
            fillColor: '#1f4b33',
            fillOpacity: 0.25,
            strokeColor: '#1f4b33',
            strokeWeight: 2,
            editable: true,
          },
        })
        drawingManager.setMap(mapa)
        drawingManagerRef.current = drawingManager

        maps.event.addListener(drawingManager, 'polygoncomplete', (poligono) => {
          poligonoRef.current?.setMap(null)
          poligonoRef.current = poligono
          drawingManager.setDrawingMode(null)

          const recalcular = () => calcularMetricas(maps, poligono, onPoligonoCompleto, setMetricas)
          recalcular()
          maps.event.addListener(poligono.getPath(), 'set_at', recalcular)
          maps.event.addListener(poligono.getPath(), 'insert_at', recalcular)
          maps.event.addListener(poligono.getPath(), 'remove_at', recalcular)
        })
      })
      .catch((err) => setError(err.message))

    return () => {
      cancelado = true
      poligonoRef.current?.setMap(null)
      drawingManagerRef.current?.setMap(null)
    }
  }, [])

  const redibujar = () => {
    poligonoRef.current?.setMap(null)
    poligonoRef.current = null
    setMetricas(null)
    drawingManagerRef.current?.setDrawingMode(mapsRef.current.drawing.OverlayType.POLYGON)
  }

  return (
    <div className="relative">
      <div ref={contenedorRef} className="h-96 w-full rounded-md border border-gray-200" />

      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}

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
