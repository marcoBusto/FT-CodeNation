import { useEffect, useState } from 'react'
import { apiFetch } from './api'

const LOTE_VACIO = { campo_id: '', nombre: '', hectareas: '' }

function Lotes() {
  const [lotes, setLotes] = useState([])
  const [campos, setCampos] = useState([])
  const [nuevoLote, setNuevoLote] = useState(LOTE_VACIO)
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)

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

  const crearLote = (evento) => {
    evento.preventDefault()
    setErrores([])

    apiFetch('/lotes', { method: 'POST', body: JSON.stringify(nuevoLote) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setNuevoLote(LOTE_VACIO)
        cargarLotes()
      }
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
        {lotes.map((l) => (
          <li key={l.id} className="flex items-center justify-between p-3 text-sm">
            <div>
              <span className="text-gray-900">{l.nombre}</span>
              <span className="ml-2 text-gray-500">({l.campo_nombre})</span>
            </div>
            <span className="text-gray-500">{l.hectareas} ha</span>
          </li>
        ))}
        {lotes.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay lotes cargados.</li>}
      </ul>
    </div>
  )
}

export default Lotes
