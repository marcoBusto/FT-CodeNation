import { useEffect, useState } from 'react'
import { apiFetch } from './api'

const LABOR_VACIA = { nombre: '', litros_por_hectarea: '' }
const ESTACION_VACIA = { nombre: '', precio_por_litro: '' }

function Combustible() {
  const [labores, setLabores] = useState([])
  const [estaciones, setEstaciones] = useState([])
  const [lotes, setLotes] = useState([])
  const [error, setError] = useState(null)

  const cargarTodo = () => {
    apiFetch('/labores').then((res) => setLabores(res.data ?? []))
    apiFetch('/estaciones-combustible').then((res) => setEstaciones(res.data ?? []))
    apiFetch('/lotes')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setLotes(res.data)
      })
      .catch((err) => setError(err.message))
  }

  useEffect(cargarTodo, [])

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">
          No se pudo conectar con el backend: {error}
        </p>
      )}

      <Calculadora labores={labores} estaciones={estaciones} lotes={lotes} />
      <CatalogoLabores labores={labores} onCambio={cargarTodo} />
      <CatalogoEstaciones estaciones={estaciones} onCambio={cargarTodo} />
    </div>
  )
}

function Calculadora({ labores, estaciones, lotes }) {
  const [loteId, setLoteId] = useState('')
  const [laborId, setLaborId] = useState('')
  const [estacionId, setEstacionId] = useState('')
  const [resultado, setResultado] = useState(null)
  const [errores, setErrores] = useState([])

  const lote = lotes.find((l) => l.id === Number(loteId))

  const calcular = (evento) => {
    evento.preventDefault()
    setErrores([])
    setResultado(null)

    apiFetch('/combustible/estimar', {
      method: 'POST',
      body: JSON.stringify({ hectareas: lote?.hectareas ?? '', labor_id: laborId, estacion_id: estacionId }),
    }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setResultado(res.data)
      }
    })
  }

  return (
    <form onSubmit={calcular} className="space-y-3 rounded-md border border-gray-200 p-4">
      <h2 className="text-sm font-medium text-brand-primary-dark">Calculador estimativo de combustible</h2>
      <p className="text-xs text-gray-400">
        Estimación rápida (litros y costo) en base a las hectáreas del lote — no queda guardada como historial.
      </p>
      <div className="grid grid-cols-3 gap-3">
        <label className="block">
          <span className="text-sm text-gray-700">Lote</span>
          <select required value={loteId} onChange={(e) => setLoteId(e.target.value)} className="campo mt-1">
            <option value="">Elegir...</option>
            {lotes.map((l) => (
              <option key={l.id} value={l.id}>
                {l.nombre} ({l.campo_nombre}) — {l.hectareas} ha
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="text-sm text-gray-700">Labor</span>
          <select required value={laborId} onChange={(e) => setLaborId(e.target.value)} className="campo mt-1">
            <option value="">Elegir...</option>
            {labores.map((l) => (
              <option key={l.id} value={l.id}>
                {l.nombre} ({l.litros_por_hectarea} l/ha)
              </option>
            ))}
          </select>
        </label>
        <label className="block">
          <span className="text-sm text-gray-700">Estación</span>
          <select required value={estacionId} onChange={(e) => setEstacionId(e.target.value)} className="campo mt-1">
            <option value="">Elegir...</option>
            {estaciones.map((e) => (
              <option key={e.id} value={e.id}>
                {e.nombre} (${e.precio_por_litro}/l)
              </option>
            ))}
          </select>
        </label>
      </div>

      {labores.length === 0 && (
        <p className="text-xs text-amber-600">Todavía no hay labores cargadas — agregá una más abajo.</p>
      )}
      {estaciones.length === 0 && (
        <p className="text-xs text-amber-600">Todavía no hay estaciones cargadas — agregá una más abajo.</p>
      )}

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
        Calcular
      </button>

      {resultado && (
        <div className="rounded-md bg-brand-primary/10 p-3 text-sm">
          <p className="font-medium text-brand-primary-dark">
            {resultado.litros_estimados} litros estimados · ${resultado.costo_estimado}
          </p>
          <p className="text-xs text-gray-500">
            {resultado.labor_nombre} en {lote?.nombre} · precio de {resultado.estacion_nombre} actualizado el{' '}
            {new Date(resultado.precio_actualizado_en).toLocaleDateString('es-AR')}
          </p>
        </div>
      )}
    </form>
  )
}

function CatalogoLabores({ labores, onCambio }) {
  const [nueva, setNueva] = useState(LABOR_VACIA)
  const [errores, setErrores] = useState([])
  const [editandoId, setEditandoId] = useState(null)
  const [enEdicion, setEnEdicion] = useState(LABOR_VACIA)

  const crear = (evento) => {
    evento.preventDefault()
    setErrores([])
    apiFetch('/labores', { method: 'POST', body: JSON.stringify(nueva) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setNueva(LABOR_VACIA)
        onCambio()
      }
    })
  }

  const guardar = (id) => {
    apiFetch(`/labores/${id}`, { method: 'PUT', body: JSON.stringify(enEdicion) }).then((res) => {
      if (!res.error) {
        setEditandoId(null)
        onCambio()
      }
    })
  }

  const eliminar = (labor) => {
    if (!window.confirm(`¿Eliminar la labor "${labor.nombre}"?`)) return
    apiFetch(`/labores/${labor.id}`, { method: 'DELETE' }).then((res) => {
      if (!res.error) onCambio()
    })
  }

  return (
    <div className="space-y-3 rounded-md border border-gray-200 p-4">
      <h2 className="text-sm font-medium text-brand-primary-dark">Labores y consumo estimado</h2>

      <form onSubmit={crear} className="flex flex-wrap items-end gap-3">
        <label className="block">
          <span className="text-xs text-gray-700">Labor</span>
          <input
            type="text"
            required
            placeholder="Ej. Pulverización"
            value={nueva.nombre}
            onChange={(e) => setNueva({ ...nueva, nombre: e.target.value })}
            className="campo mt-1"
          />
        </label>
        <label className="block">
          <span className="text-xs text-gray-700">Litros / ha</span>
          <input
            type="number"
            min="0.01"
            step="0.01"
            required
            value={nueva.litros_por_hectarea}
            onChange={(e) => setNueva({ ...nueva, litros_por_hectarea: e.target.value })}
            className="campo mt-1 w-28"
          />
        </label>
        <button type="submit" className="rounded-md bg-brand-primary px-3 py-2 text-xs text-white">
          Agregar
        </button>
      </form>

      {errores.length > 0 && (
        <ul className="rounded-md bg-red-50 p-2 text-xs text-red-700">
          {errores.map((err) => (
            <li key={err}>{err}</li>
          ))}
        </ul>
      )}

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-200">
        {labores.map((l) =>
          editandoId === l.id ? (
            <li key={l.id} className="flex items-center gap-3 p-3 text-sm">
              <input
                type="text"
                value={enEdicion.nombre}
                onChange={(e) => setEnEdicion({ ...enEdicion, nombre: e.target.value })}
                className="campo"
              />
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={enEdicion.litros_por_hectarea}
                onChange={(e) => setEnEdicion({ ...enEdicion, litros_por_hectarea: e.target.value })}
                className="campo w-24"
              />
              <button type="button" onClick={() => guardar(l.id)} className="rounded-md bg-brand-primary px-3 py-1 text-xs text-white">
                Guardar
              </button>
              <button type="button" onClick={() => setEditandoId(null)} className="text-xs text-gray-500 underline">
                Cancelar
              </button>
            </li>
          ) : (
            <li key={l.id} className="flex items-center justify-between p-3 text-sm">
              <span className="text-gray-900">{l.nombre}</span>
              <div className="flex items-center gap-3">
                <span className="text-gray-500">{l.litros_por_hectarea} l/ha</span>
                <button
                  type="button"
                  onClick={() => {
                    setEditandoId(l.id)
                    setEnEdicion({ nombre: l.nombre, litros_por_hectarea: l.litros_por_hectarea })
                  }}
                  className="text-xs text-brand-primary underline"
                >
                  Editar
                </button>
                <button type="button" onClick={() => eliminar(l)} className="text-xs text-red-600 underline">
                  Eliminar
                </button>
              </div>
            </li>
          )
        )}
        {labores.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay labores cargadas.</li>}
      </ul>
    </div>
  )
}

function CatalogoEstaciones({ estaciones, onCambio }) {
  const [nueva, setNueva] = useState(ESTACION_VACIA)
  const [errores, setErrores] = useState([])
  const [editandoId, setEditandoId] = useState(null)
  const [enEdicion, setEnEdicion] = useState(ESTACION_VACIA)

  const crear = (evento) => {
    evento.preventDefault()
    setErrores([])
    apiFetch('/estaciones-combustible', { method: 'POST', body: JSON.stringify(nueva) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setNueva(ESTACION_VACIA)
        onCambio()
      }
    })
  }

  const guardar = (id) => {
    apiFetch(`/estaciones-combustible/${id}`, { method: 'PUT', body: JSON.stringify(enEdicion) }).then((res) => {
      if (!res.error) {
        setEditandoId(null)
        onCambio()
      }
    })
  }

  const eliminar = (estacion) => {
    if (!window.confirm(`¿Eliminar la estación "${estacion.nombre}"?`)) return
    apiFetch(`/estaciones-combustible/${estacion.id}`, { method: 'DELETE' }).then((res) => {
      if (!res.error) onCambio()
    })
  }

  return (
    <div className="space-y-3 rounded-md border border-gray-200 p-4">
      <h2 className="text-sm font-medium text-brand-primary-dark">Estaciones y precio por litro</h2>
      <p className="text-xs text-gray-400">
        El precio se carga a mano y hay que actualizarlo cuando cambia — no existe una fuente online gratuita y
        confiable por surtidor.
      </p>

      <form onSubmit={crear} className="flex flex-wrap items-end gap-3">
        <label className="block">
          <span className="text-xs text-gray-700">Estación</span>
          <input
            type="text"
            required
            placeholder="Ej. YPF Leones"
            value={nueva.nombre}
            onChange={(e) => setNueva({ ...nueva, nombre: e.target.value })}
            className="campo mt-1"
          />
        </label>
        <label className="block">
          <span className="text-xs text-gray-700">Precio / litro</span>
          <input
            type="number"
            min="0.01"
            step="0.01"
            required
            value={nueva.precio_por_litro}
            onChange={(e) => setNueva({ ...nueva, precio_por_litro: e.target.value })}
            className="campo mt-1 w-28"
          />
        </label>
        <button type="submit" className="rounded-md bg-brand-primary px-3 py-2 text-xs text-white">
          Agregar
        </button>
      </form>

      {errores.length > 0 && (
        <ul className="rounded-md bg-red-50 p-2 text-xs text-red-700">
          {errores.map((err) => (
            <li key={err}>{err}</li>
          ))}
        </ul>
      )}

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-200">
        {estaciones.map((e) =>
          editandoId === e.id ? (
            <li key={e.id} className="flex items-center gap-3 p-3 text-sm">
              <input
                type="text"
                value={enEdicion.nombre}
                onChange={(ev) => setEnEdicion({ ...enEdicion, nombre: ev.target.value })}
                className="campo"
              />
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={enEdicion.precio_por_litro}
                onChange={(ev) => setEnEdicion({ ...enEdicion, precio_por_litro: ev.target.value })}
                className="campo w-24"
              />
              <button type="button" onClick={() => guardar(e.id)} className="rounded-md bg-brand-primary px-3 py-1 text-xs text-white">
                Guardar
              </button>
              <button type="button" onClick={() => setEditandoId(null)} className="text-xs text-gray-500 underline">
                Cancelar
              </button>
            </li>
          ) : (
            <li key={e.id} className="flex items-center justify-between p-3 text-sm">
              <span className="text-gray-900">{e.nombre}</span>
              <div className="flex items-center gap-3">
                <span className="text-gray-500">
                  ${e.precio_por_litro}/l · actualizado {new Date(e.actualizado_en).toLocaleDateString('es-AR')}
                </span>
                <button
                  type="button"
                  onClick={() => {
                    setEditandoId(e.id)
                    setEnEdicion({ nombre: e.nombre, precio_por_litro: e.precio_por_litro })
                  }}
                  className="text-xs text-brand-primary underline"
                >
                  Editar
                </button>
                <button type="button" onClick={() => eliminar(e)} className="text-xs text-red-600 underline">
                  Eliminar
                </button>
              </div>
            </li>
          )
        )}
        {estaciones.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay estaciones cargadas.</li>}
      </ul>
    </div>
  )
}

export default Combustible
