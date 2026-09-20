import { useEffect, useState } from 'react'
import { apiFetch } from './api'
import MapaLote from './MapaLote'

const LOTE_VACIO = { campo_id: '', nombre: '', hectareas: '' }

function Lotes() {
  const [lotes, setLotes] = useState([])
  const [campos, setCampos] = useState([])
  const [nuevoLote, setNuevoLote] = useState(LOTE_VACIO)
  const [poligono, setPoligono] = useState(null)
  const [mapaKey, setMapaKey] = useState(0)
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)
  const [editandoId, setEditandoId] = useState(null)
  const [loteEnEdicion, setLoteEnEdicion] = useState(LOTE_VACIO)
  const [erroresEdicion, setErroresEdicion] = useState([])

  const cargarLotes = () => {
    apiFetch('/lotes')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setLotes(res.data)
      })
      .catch((err) => setError(err.message))
  }

  useEffect(() => {
    cargarLotes()
    apiFetch('/campos').then((res) => setCampos(res.data ?? []))
  }, [])

  const actualizarLote = (campo) => (evento) => {
    setNuevoLote({ ...nuevoLote, [campo]: evento.target.value })
  }

  const alCompletarPoligono = (resultado) => {
    setPoligono(resultado)
    setNuevoLote((actual) => ({ ...actual, hectareas: String(resultado.areaHectareas) }))
  }

  const crearLote = (evento) => {
    evento.preventDefault()
    setErrores([])

    const cuerpo = {
      ...nuevoLote,
      perimetro_metros: poligono?.perimetroMetros ?? '',
      poligono: poligono?.coordenadas ?? null,
    }

    apiFetch('/lotes', { method: 'POST', body: JSON.stringify(cuerpo) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setNuevoLote(LOTE_VACIO)
        setPoligono(null)
        setMapaKey((k) => k + 1)
        cargarLotes()
      }
    })
  }

  const empezarEdicion = (lote) => {
    setEditandoId(lote.id)
    setLoteEnEdicion({ campo_id: lote.campo_id, nombre: lote.nombre, hectareas: lote.hectareas })
    setErroresEdicion([])
  }

  const cancelarEdicion = () => {
    setEditandoId(null)
    setErroresEdicion([])
  }

  const guardarEdicion = (id) => {
    setErroresEdicion([])
    apiFetch(`/lotes/${id}`, { method: 'PUT', body: JSON.stringify(loteEnEdicion) }).then((res) => {
      if (res.error) {
        setErroresEdicion(res.error.detalles ?? [res.error.mensaje])
      } else {
        setEditandoId(null)
        cargarLotes()
      }
    })
  }

  const desactivarLote = (lote) => {
    if (!window.confirm(`¿Desactivar "${lote.nombre}"? Deja de aparecer en las listas, pero no se borra su historial.`)) {
      return
    }
    apiFetch(`/lotes/${lote.id}`, { method: 'DELETE' }).then((res) => {
      if (!res.error) cargarLotes()
    })
  }

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">
          No se pudo conectar con el backend: {error}
        </p>
      )}

      <form onSubmit={crearLote} className="space-y-3 rounded-md border border-gray-200 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Nuevo lote</h2>
        <div className="grid grid-cols-3 gap-3">
          <label className="block">
            <span className="text-sm text-gray-700">Campo</span>
            <select required value={nuevoLote.campo_id} onChange={actualizarLote('campo_id')} className="campo mt-1">
              <option value="">Elegir...</option>
              {campos.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Nombre del lote</span>
            <input
              type="text"
              required
              value={nuevoLote.nombre}
              onChange={actualizarLote('nombre')}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Hectáreas</span>
            <input
              type="number"
              min="0.01"
              step="0.01"
              required
              value={nuevoLote.hectareas}
              onChange={actualizarLote('hectareas')}
              className="campo mt-1"
            />
          </label>
        </div>

        <div>
          <span className="text-sm text-gray-700">Dibujar el lote en el mapa (opcional)</span>
          <p className="mb-2 text-xs text-gray-400">
            Al terminar el polígono se completan las hectáreas solas — igual las podés ajustar a mano.
          </p>
          <MapaLote key={mapaKey} onPoligonoCompleto={alCompletarPoligono} />
        </div>

        {errores.length > 0 && (
          <ul className="rounded-md bg-red-50 p-3 text-sm text-red-700">
            {errores.map((err) => (
              <li key={err}>{err}</li>
            ))}
          </ul>
        )}

        <button
          type="submit"
          className="rounded-md bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-dark"
        >
          Crear lote
        </button>
      </form>

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-200">
        {lotes.map((l) =>
          editandoId === l.id ? (
            <li key={l.id} className="space-y-2 p-3 text-sm">
              <div className="grid grid-cols-3 gap-3">
                <select
                  value={loteEnEdicion.campo_id}
                  onChange={(e) => setLoteEnEdicion({ ...loteEnEdicion, campo_id: e.target.value })}
                  className="campo"
                >
                  {campos.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.nombre}
                    </option>
                  ))}
                </select>
                <input
                  type="text"
                  value={loteEnEdicion.nombre}
                  onChange={(e) => setLoteEnEdicion({ ...loteEnEdicion, nombre: e.target.value })}
                  className="campo"
                />
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={loteEnEdicion.hectareas}
                  onChange={(e) => setLoteEnEdicion({ ...loteEnEdicion, hectareas: e.target.value })}
                  className="campo"
                />
              </div>
              {erroresEdicion.length > 0 && (
                <ul className="rounded-md bg-red-50 p-2 text-xs text-red-700">
                  {erroresEdicion.map((err) => (
                    <li key={err}>{err}</li>
                  ))}
                </ul>
              )}
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => guardarEdicion(l.id)}
                  className="rounded-md bg-brand-primary px-3 py-1 text-xs text-white"
                >
                  Guardar
                </button>
                <button type="button" onClick={cancelarEdicion} className="text-xs text-gray-500 underline">
                  Cancelar
                </button>
              </div>
            </li>
          ) : (
            <li key={l.id} className="flex items-center justify-between p-3 text-sm">
              <div>
                <span className="text-gray-900">{l.nombre}</span>
                <span className="ml-2 text-gray-500">({l.campo_nombre})</span>
                {l.poligono && <span className="ml-2 text-xs text-brand-primary">· dibujado en mapa</span>}
              </div>
              <div className="flex items-center gap-3">
                <span className="text-gray-500">
                  {l.hectareas} ha{l.perimetro_metros && ` · ${l.perimetro_metros} m perímetro`}
                </span>
                <button type="button" onClick={() => empezarEdicion(l)} className="text-xs text-brand-primary underline">
                  Editar
                </button>
                <button type="button" onClick={() => desactivarLote(l)} className="text-xs text-red-600 underline">
                  Desactivar
                </button>
              </div>
            </li>
          )
        )}
        {lotes.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay lotes cargados.</li>}
      </ul>
    </div>
  )
}

export default Lotes
