import { useEffect, useState } from 'react'
import { apiFetch } from './api'

const NUEVA_OPCION = '__nueva__'

// La campaña agrícola argentina va de julio a junio: en noviembre de 2026
// la campaña en curso es 2026/27, en marzo de 2027 sigue siendo 2026/27.
const campaniaDe = (anioInicio) => `${anioInicio}/${String((anioInicio + 1) % 100).padStart(2, '0')}`

const anioCampaniaActual = () => {
  const hoy = new Date()
  return hoy.getMonth() >= 6 ? hoy.getFullYear() : hoy.getFullYear() - 1
}

// De la próxima campaña hacia atrás, para poder cargar historia.
const opcionesCampania = () => {
  const actual = anioCampaniaActual()
  return [1, 0, -1, -2, -3, -4].map((d) => campaniaDe(actual + d))
}

const siembraVacia = () => ({
  lote_id: '',
  cultivo_id: '',
  campania: campaniaDe(anioCampaniaActual()),
  fecha_siembra: '',
  fecha_cosecha: '',
})

const formatearFecha = (fecha) => fecha.split('-').reverse().join('/')

function Siembras() {
  const [siembras, setSiembras] = useState([])
  const [lotes, setLotes] = useState([])
  const [cultivos, setCultivos] = useState([])
  const [nuevaSiembra, setNuevaSiembra] = useState(siembraVacia)
  const [nuevoCultivoNombre, setNuevoCultivoNombre] = useState('')
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)
  const [editandoId, setEditandoId] = useState(null)
  const [siembraEnEdicion, setSiembraEnEdicion] = useState(siembraVacia)
  const [erroresEdicion, setErroresEdicion] = useState([])
  const [errorLista, setErrorLista] = useState(null)

  const cargarSiembras = () => {
    apiFetch('/siembras')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setSiembras(res.data)
      })
      .catch((err) => setError(err.message))
  }

  const cargarCultivos = () => {
    apiFetch('/cultivos')
      .then((res) => setCultivos(res.data ?? []))
      .catch(() => {})
  }

  useEffect(() => {
    cargarSiembras()
    cargarCultivos()
    apiFetch('/lotes')
      .then((res) => setLotes(res.data ?? []))
      .catch(() => {})
  }, [])

  const actualizarSiembra = (campo) => (evento) => {
    setNuevaSiembra({ ...nuevaSiembra, [campo]: evento.target.value })
  }

  // Igual que marcas/categorías en Insumos: si eligió "+ Nuevo cultivo...",
  // primero lo crea y con ese id arma la siembra.
  const resolverCultivoId = () => {
    if (nuevaSiembra.cultivo_id !== NUEVA_OPCION) {
      return Promise.resolve(nuevaSiembra.cultivo_id)
    }
    return apiFetch('/cultivos', { method: 'POST', body: JSON.stringify({ nombre: nuevoCultivoNombre }) }).then(
      (res) => {
        if (res.error) throw new Error(res.error.detalles?.[0] ?? res.error.mensaje)
        return String(res.data.id)
      },
    )
  }

  const crearSiembra = (evento) => {
    evento.preventDefault()
    setErrores([])

    resolverCultivoId()
      .then((cultivo_id) =>
        apiFetch('/siembras', { method: 'POST', body: JSON.stringify({ ...nuevaSiembra, cultivo_id }) }),
      )
      .then((res) => {
        if (res.error) {
          setErrores(res.error.detalles ?? [res.error.mensaje])
          return
        }
        // Conserva la campaña elegida: lo habitual es cargar varios lotes
        // de la misma campaña seguidos.
        setNuevaSiembra({ ...siembraVacia(), campania: nuevaSiembra.campania })
        setNuevoCultivoNombre('')
        cargarSiembras()
        cargarCultivos()
      })
      .catch((err) => setErrores([err.message]))
  }

  const empezarEdicion = (siembra) => {
    setEditandoId(siembra.id)
    setSiembraEnEdicion({
      lote_id: String(siembra.lote_id),
      cultivo_id: String(siembra.cultivo_id),
      campania: siembra.campania,
      fecha_siembra: siembra.fecha_siembra ?? '',
      fecha_cosecha: siembra.fecha_cosecha ?? '',
    })
    setErroresEdicion([])
    setErrorLista(null)
  }

  const cancelarEdicion = () => {
    setEditandoId(null)
    setErroresEdicion([])
  }

  const guardarEdicion = (id) => {
    setErroresEdicion([])
    apiFetch(`/siembras/${id}`, { method: 'PUT', body: JSON.stringify(siembraEnEdicion) })
      .then((res) => {
        if (res.error) {
          setErroresEdicion(res.error.detalles ?? [res.error.mensaje])
        } else {
          setEditandoId(null)
          cargarSiembras()
        }
      })
      .catch((err) => setErroresEdicion([err.message]))
  }

  const eliminarSiembra = (siembra) => {
    setErrorLista(null)
    if (!window.confirm(`¿Eliminar la siembra de ${siembra.cultivo_nombre} ${siembra.campania} en "${siembra.lote_nombre}"?`)) {
      return
    }
    apiFetch(`/siembras/${siembra.id}`, { method: 'DELETE' })
      .then((res) => {
        if (res.error) {
          setErrorLista(res.error.detalles?.[0] ?? res.error.mensaje)
        } else {
          cargarSiembras()
        }
      })
      .catch((err) => setErrorLista(err.message))
  }

  // Una siembra vieja puede ser de una campaña que ya no está en la lista
  // corta de opciones: se agrega para no perderla al editar.
  const campaniasPara = (campania) => {
    const opciones = opcionesCampania()
    return opciones.includes(campania) ? opciones : [campania, ...opciones]
  }

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">No se pudieron cargar los datos: {error}</p>
      )}

      <form onSubmit={crearSiembra} className="space-y-3 rounded-md border border-gray-300 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Nueva siembra</h2>
        <p className="text-xs text-gray-700">
          Qué cultivo tiene cada lote en cada campaña. Después, al registrar un egreso de insumo a ese lote, se
          puede imputar a la siembra.
        </p>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <label className="block">
            <span className="text-sm text-gray-700">Lote</span>
            <select required value={nuevaSiembra.lote_id} onChange={actualizarSiembra('lote_id')} className="campo mt-1">
              <option value="">Elegir...</option>
              {lotes.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.nombre} ({l.campo_nombre}, {l.hectareas} ha)
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Cultivo</span>
            <select
              required
              value={nuevaSiembra.cultivo_id}
              onChange={actualizarSiembra('cultivo_id')}
              className="campo mt-1"
            >
              <option value="">Elegir...</option>
              {cultivos.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre}
                </option>
              ))}
              <option value={NUEVA_OPCION}>+ Nuevo cultivo...</option>
            </select>
            {nuevaSiembra.cultivo_id === NUEVA_OPCION && (
              <input
                type="text"
                required
                placeholder="Nombre del cultivo"
                value={nuevoCultivoNombre}
                onChange={(e) => setNuevoCultivoNombre(e.target.value)}
                className="campo mt-1"
              />
            )}
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Campaña</span>
            <select required value={nuevaSiembra.campania} onChange={actualizarSiembra('campania')} className="campo mt-1">
              {opcionesCampania().map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </label>
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <label className="block">
            <span className="text-sm text-gray-700">Fecha de siembra (opcional)</span>
            <input
              type="date"
              value={nuevaSiembra.fecha_siembra}
              onChange={actualizarSiembra('fecha_siembra')}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Fecha de cosecha (opcional)</span>
            <input
              type="date"
              value={nuevaSiembra.fecha_cosecha}
              onChange={actualizarSiembra('fecha_cosecha')}
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
          Cargar siembra
        </button>
      </form>

      {errorLista && <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">{errorLista}</p>}

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-300">
        {siembras.map((s) =>
          editandoId === s.id ? (
            <li key={s.id} className="space-y-2 p-3 text-sm">
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <select
                  aria-label="Lote"
                  value={siembraEnEdicion.lote_id}
                  onChange={(e) => setSiembraEnEdicion({ ...siembraEnEdicion, lote_id: e.target.value })}
                  className="campo"
                >
                  {lotes.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.nombre} ({l.campo_nombre})
                    </option>
                  ))}
                </select>
                <select
                  aria-label="Cultivo"
                  value={siembraEnEdicion.cultivo_id}
                  onChange={(e) => setSiembraEnEdicion({ ...siembraEnEdicion, cultivo_id: e.target.value })}
                  className="campo"
                >
                  {cultivos.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.nombre}
                    </option>
                  ))}
                </select>
                <select
                  aria-label="Campaña"
                  value={siembraEnEdicion.campania}
                  onChange={(e) => setSiembraEnEdicion({ ...siembraEnEdicion, campania: e.target.value })}
                  className="campo"
                >
                  {campaniasPara(siembraEnEdicion.campania).map((c) => (
                    <option key={c} value={c}>
                      {c}
                    </option>
                  ))}
                </select>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <label className="block">
                  <span className="text-xs text-gray-700">Fecha de siembra</span>
                  <input
                    type="date"
                    value={siembraEnEdicion.fecha_siembra}
                    onChange={(e) => setSiembraEnEdicion({ ...siembraEnEdicion, fecha_siembra: e.target.value })}
                    className="campo mt-1"
                  />
                </label>
                <label className="block">
                  <span className="text-xs text-gray-700">Fecha de cosecha</span>
                  <input
                    type="date"
                    value={siembraEnEdicion.fecha_cosecha}
                    onChange={(e) => setSiembraEnEdicion({ ...siembraEnEdicion, fecha_cosecha: e.target.value })}
                    className="campo mt-1"
                  />
                </label>
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
                  onClick={() => guardarEdicion(s.id)}
                  className="rounded-md bg-brand-primary px-3 py-1 text-xs text-white"
                >
                  Guardar
                </button>
                <button type="button" onClick={cancelarEdicion} className="text-xs text-gray-700 underline">
                  Cancelar
                </button>
              </div>
            </li>
          ) : (
            <li key={s.id} className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
              <div>
                <div className="text-gray-900">
                  <span className="font-medium">{s.cultivo_nombre}</span> · {s.campania}
                  <span
                    className={`ml-2 rounded px-1.5 py-0.5 text-xs ${
                      s.fecha_cosecha ? 'bg-gray-100 text-gray-700' : 'bg-green-50 text-brand-primary-dark'
                    }`}
                  >
                    {s.fecha_cosecha ? 'Cosechada' : 'En curso'}
                  </span>
                </div>
                <div className="mt-1 text-gray-700">
                  {s.lote_nombre} ({s.campo_nombre}, {s.hectareas} ha)
                  {s.fecha_siembra && ` · Siembra ${formatearFecha(s.fecha_siembra)}`}
                  {s.fecha_cosecha && ` · Cosecha ${formatearFecha(s.fecha_cosecha)}`}
                </div>
              </div>
              <div className="flex items-center gap-3">
                <button type="button" onClick={() => empezarEdicion(s)} className="text-xs text-brand-primary underline">
                  Editar
                </button>
                <button type="button" onClick={() => eliminarSiembra(s)} className="text-xs text-red-600 underline">
                  Eliminar
                </button>
              </div>
            </li>
          ),
        )}
        {siembras.length === 0 && <li className="p-3 text-sm text-gray-700">Todavía no hay siembras cargadas.</li>}
      </ul>
    </div>
  )
}

export default Siembras
