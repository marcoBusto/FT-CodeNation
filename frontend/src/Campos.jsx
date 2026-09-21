import { useEffect, useState } from 'react'
import { apiFetch } from './api'
import MapaCampo from './MapaCampo'

const CAMPO_VACIO = { nombre: '', ubicacion: '', latitud: '', longitud: '' }

function Campos() {
  const [campos, setCampos] = useState([])
  const [nuevoCampo, setNuevoCampo] = useState(CAMPO_VACIO)
  const [mapaKey, setMapaKey] = useState(0)
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)
  const [editandoId, setEditandoId] = useState(null)
  const [campoEnEdicion, setCampoEnEdicion] = useState(CAMPO_VACIO)
  const [erroresEdicion, setErroresEdicion] = useState([])

  const cargarCampos = () => {
    apiFetch('/campos')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setCampos(res.data)
      })
      .catch((err) => setError(err.message))
  }

  useEffect(cargarCampos, [])

  const actualizarCampo = (campo) => (evento) => {
    setNuevoCampo({ ...nuevoCampo, [campo]: evento.target.value })
  }

  const crearCampo = (evento) => {
    evento.preventDefault()
    setErrores([])

    apiFetch('/campos', { method: 'POST', body: JSON.stringify(nuevoCampo) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setNuevoCampo(CAMPO_VACIO)
        setMapaKey((k) => k + 1)
        cargarCampos()
      }
    })
  }

  const alFijarUbicacion = ({ lat, lng }) => {
    setNuevoCampo((actual) => ({ ...actual, latitud: String(lat), longitud: String(lng) }))
  }

  const alFijarUbicacionEdicion = ({ lat, lng }) => {
    setCampoEnEdicion((actual) => ({ ...actual, latitud: String(lat), longitud: String(lng) }))
  }

  const empezarEdicion = (campo) => {
    setEditandoId(campo.id)
    setCampoEnEdicion({
      nombre: campo.nombre,
      ubicacion: campo.ubicacion ?? '',
      latitud: campo.latitud ?? '',
      longitud: campo.longitud ?? '',
    })
    setErroresEdicion([])
  }

  const cancelarEdicion = () => {
    setEditandoId(null)
    setErroresEdicion([])
  }

  const guardarEdicion = (id) => {
    setErroresEdicion([])
    apiFetch(`/campos/${id}`, { method: 'PUT', body: JSON.stringify(campoEnEdicion) }).then((res) => {
      if (res.error) {
        setErroresEdicion(res.error.detalles ?? [res.error.mensaje])
      } else {
        setEditandoId(null)
        cargarCampos()
      }
    })
  }

  const desactivarCampo = (campo) => {
    if (!window.confirm(`¿Desactivar "${campo.nombre}"? Deja de aparecer en las listas, pero no se borra su historial.`)) {
      return
    }
    apiFetch(`/campos/${campo.id}`, { method: 'DELETE' }).then((res) => {
      if (!res.error) cargarCampos()
    })
  }

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">
          No se pudo conectar con el backend: {error}
        </p>
      )}

      <form onSubmit={crearCampo} className="space-y-3 rounded-md border border-gray-200 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Nuevo campo</h2>
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm text-gray-700">Nombre</span>
            <input
              type="text"
              required
              value={nuevoCampo.nombre}
              onChange={actualizarCampo('nombre')}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Ubicación</span>
            <input
              type="text"
              value={nuevoCampo.ubicacion}
              onChange={actualizarCampo('ubicacion')}
              className="campo mt-1"
            />
          </label>
        </div>

        <div>
          <span className="text-sm text-gray-700">Ubicación en el mapa (opcional)</span>
          <MapaCampo key={mapaKey} onUbicacionCambiada={alFijarUbicacion} />
          {nuevoCampo.latitud && (
            <p className="mt-1 text-xs text-brand-primary">
              Coordenada marcada: {Number(nuevoCampo.latitud).toFixed(5)}, {Number(nuevoCampo.longitud).toFixed(5)}
            </p>
          )}
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
          Crear campo
        </button>
      </form>

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-200">
        {campos.map((c) =>
          editandoId === c.id ? (
            <li key={c.id} className="space-y-2 p-3 text-sm">
              <div className="grid grid-cols-2 gap-3">
                <input
                  type="text"
                  value={campoEnEdicion.nombre}
                  onChange={(e) => setCampoEnEdicion({ ...campoEnEdicion, nombre: e.target.value })}
                  className="campo"
                />
                <input
                  type="text"
                  value={campoEnEdicion.ubicacion}
                  onChange={(e) => setCampoEnEdicion({ ...campoEnEdicion, ubicacion: e.target.value })}
                  className="campo"
                  placeholder="Ubicación"
                />
              </div>
              <div>
                <MapaCampo
                  latitud={campoEnEdicion.latitud || null}
                  longitud={campoEnEdicion.longitud || null}
                  onUbicacionCambiada={alFijarUbicacionEdicion}
                />
                {campoEnEdicion.latitud && (
                  <p className="mt-1 text-xs text-brand-primary">
                    Coordenada marcada: {Number(campoEnEdicion.latitud).toFixed(5)}, {Number(campoEnEdicion.longitud).toFixed(5)}
                  </p>
                )}
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
                  onClick={() => guardarEdicion(c.id)}
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
            <li key={c.id} className="flex items-center justify-between p-3 text-sm">
              <div>
                <div className="text-gray-900">{c.nombre}</div>
                {c.ubicacion && <div className="text-gray-500">{c.ubicacion}</div>}
                {c.latitud != null && (
                  <div className="text-xs text-brand-primary">
                    · marcado en mapa ({Number(c.latitud).toFixed(4)}, {Number(c.longitud).toFixed(4)})
                  </div>
                )}
              </div>
              <div className="flex gap-3 text-xs">
                <button type="button" onClick={() => empezarEdicion(c)} className="text-brand-primary underline">
                  Editar
                </button>
                <button type="button" onClick={() => desactivarCampo(c)} className="text-red-600 underline">
                  Desactivar
                </button>
              </div>
            </li>
          )
        )}
        {campos.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay campos cargados.</li>}
      </ul>
    </div>
  )
}

export default Campos
