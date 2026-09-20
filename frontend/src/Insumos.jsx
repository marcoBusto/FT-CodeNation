import { useEffect, useState } from 'react'
import { apiFetch } from './api'

const NUEVA_OPCION = '__nueva__'
const INSUMO_VACIO = { nombre: '', marca_id: '', categoria_id: '', unidad_medida: 'litros' }

function Insumos() {
  const [stock, setStock] = useState([])
  const [marcas, setMarcas] = useState([])
  const [categorias, setCategorias] = useState([])
  const [nuevoInsumo, setNuevoInsumo] = useState(INSUMO_VACIO)
  const [nuevaMarcaNombre, setNuevaMarcaNombre] = useState('')
  const [nuevaCategoriaNombre, setNuevaCategoriaNombre] = useState('')
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)
  const [editandoId, setEditandoId] = useState(null)
  const [insumoEnEdicion, setInsumoEnEdicion] = useState(INSUMO_VACIO)
  const [erroresEdicion, setErroresEdicion] = useState([])

  const cargarStock = () => {
    apiFetch('/insumos/stock')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setStock(res.data)
      })
      .catch((err) => setError(err.message))
  }

  const cargarCatalogos = () => {
    apiFetch('/marcas').then((res) => setMarcas(res.data ?? []))
    apiFetch('/categorias').then((res) => setCategorias(res.data ?? []))
  }

  useEffect(() => {
    cargarStock()
    cargarCatalogos()
  }, [])

  const actualizarInsumo = (campo) => (evento) => {
    setNuevoInsumo({ ...nuevoInsumo, [campo]: evento.target.value })
  }

  // Si el usuario eligió "+ Nueva marca/categoría...", primero la crea y
  // recién con el id que devuelve arma el insumo. Evita una pantalla aparte
  // solo para administrar catálogos chicos.
  const resolverIdCatalogo = (ruta, valorSeleccionado, nombreNuevo) => {
    if (valorSeleccionado !== NUEVA_OPCION) {
      return Promise.resolve(valorSeleccionado || '')
    }
    return apiFetch(ruta, { method: 'POST', body: JSON.stringify({ nombre: nombreNuevo }) }).then((res) => {
      if (res.error) throw new Error(res.error.mensaje)
      return String(res.data.id)
    })
  }

  const crearInsumo = (evento) => {
    evento.preventDefault()
    setErrores([])

    Promise.all([
      resolverIdCatalogo('/marcas', nuevoInsumo.marca_id, nuevaMarcaNombre),
      resolverIdCatalogo('/categorias', nuevoInsumo.categoria_id, nuevaCategoriaNombre),
    ])
      .then(([marca_id, categoria_id]) => {
        const cuerpo = { ...nuevoInsumo, marca_id, categoria_id }
        return apiFetch('/insumos', { method: 'POST', body: JSON.stringify(cuerpo) })
      })
      .then((res) => {
        if (res.error) {
          setErrores(res.error.detalles ?? [res.error.mensaje])
          return
        }
        setNuevoInsumo(INSUMO_VACIO)
        setNuevaMarcaNombre('')
        setNuevaCategoriaNombre('')
        cargarStock()
        cargarCatalogos()
      })
      .catch((err) => setErrores([err.message]))
  }

  const empezarEdicion = (insumo) => {
    setEditandoId(insumo.id)
    setInsumoEnEdicion({
      nombre: insumo.nombre,
      marca_id: insumo.marca_id ?? '',
      categoria_id: insumo.categoria_id ?? '',
      unidad_medida: insumo.unidad_medida,
    })
    setErroresEdicion([])
  }

  const cancelarEdicion = () => {
    setEditandoId(null)
    setErroresEdicion([])
  }

  const guardarEdicion = (id) => {
    setErroresEdicion([])
    apiFetch(`/insumos/${id}`, { method: 'PUT', body: JSON.stringify(insumoEnEdicion) }).then((res) => {
      if (res.error) {
        setErroresEdicion(res.error.detalles ?? [res.error.mensaje])
      } else {
        setEditandoId(null)
        cargarStock()
      }
    })
  }

  const desactivarInsumo = (insumo) => {
    if (!window.confirm(`¿Desactivar "${insumo.nombre}"? Deja de aparecer en las listas, pero no se borra su historial de movimientos.`)) {
      return
    }
    apiFetch(`/insumos/${insumo.id}`, { method: 'DELETE' }).then((res) => {
      if (!res.error) cargarStock()
    })
  }

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">
          No se pudo conectar con el backend: {error}
        </p>
      )}

      <form onSubmit={crearInsumo} className="space-y-3 rounded-md border border-gray-200 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Nuevo insumo</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <label className="block">
            <span className="text-sm text-gray-700">Nombre</span>
            <input
              type="text"
              required
              value={nuevoInsumo.nombre}
              onChange={actualizarInsumo('nombre')}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Marca</span>
            <select value={nuevoInsumo.marca_id} onChange={actualizarInsumo('marca_id')} className="campo mt-1">
              <option value="">Sin marca</option>
              {marcas.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.nombre}
                </option>
              ))}
              <option value={NUEVA_OPCION}>+ Nueva marca...</option>
            </select>
            {nuevoInsumo.marca_id === NUEVA_OPCION && (
              <input
                type="text"
                required
                placeholder="Nombre de la marca"
                value={nuevaMarcaNombre}
                onChange={(e) => setNuevaMarcaNombre(e.target.value)}
                className="campo mt-1"
              />
            )}
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Categoría</span>
            <select
              value={nuevoInsumo.categoria_id}
              onChange={actualizarInsumo('categoria_id')}
              className="campo mt-1"
            >
              <option value="">Sin categoría</option>
              {categorias.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre}
                </option>
              ))}
              <option value={NUEVA_OPCION}>+ Nueva categoría...</option>
            </select>
            {nuevoInsumo.categoria_id === NUEVA_OPCION && (
              <input
                type="text"
                required
                placeholder="Nombre de la categoría"
                value={nuevaCategoriaNombre}
                onChange={(e) => setNuevaCategoriaNombre(e.target.value)}
                className="campo mt-1"
              />
            )}
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Unidad de medida</span>
            <select
              required
              value={nuevoInsumo.unidad_medida}
              onChange={actualizarInsumo('unidad_medida')}
              className="campo mt-1"
            >
              <option value="litros">Litros</option>
              <option value="kg">Kg</option>
              <option value="bolsas">Bolsas</option>
            </select>
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
          Crear insumo
        </button>
      </form>

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-200">
        {stock.map((i) =>
          editandoId === i.id ? (
            <li key={i.id} className="space-y-2 p-3 text-sm">
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <input
                  type="text"
                  value={insumoEnEdicion.nombre}
                  onChange={(e) => setInsumoEnEdicion({ ...insumoEnEdicion, nombre: e.target.value })}
                  className="campo"
                />
                <select
                  value={insumoEnEdicion.marca_id}
                  onChange={(e) => setInsumoEnEdicion({ ...insumoEnEdicion, marca_id: e.target.value })}
                  className="campo"
                >
                  <option value="">Sin marca</option>
                  {marcas.map((m) => (
                    <option key={m.id} value={m.id}>
                      {m.nombre}
                    </option>
                  ))}
                </select>
                <select
                  value={insumoEnEdicion.categoria_id}
                  onChange={(e) => setInsumoEnEdicion({ ...insumoEnEdicion, categoria_id: e.target.value })}
                  className="campo"
                >
                  <option value="">Sin categoría</option>
                  {categorias.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.nombre}
                    </option>
                  ))}
                </select>
                <select
                  value={insumoEnEdicion.unidad_medida}
                  onChange={(e) => setInsumoEnEdicion({ ...insumoEnEdicion, unidad_medida: e.target.value })}
                  className="campo"
                >
                  <option value="litros">Litros</option>
                  <option value="kg">Kg</option>
                  <option value="bolsas">Bolsas</option>
                </select>
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
                  onClick={() => guardarEdicion(i.id)}
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
            <li key={i.id} className="flex items-center justify-between p-3 text-sm">
              <span className="text-gray-900">
                {i.nombre}
                {i.marca_nombre && <span className="text-gray-500"> · {i.marca_nombre}</span>}
                {i.categoria_nombre && <span className="ml-2 text-xs text-brand-primary">{i.categoria_nombre}</span>}
              </span>
              <div className="flex items-center gap-3">
                <span className={Number(i.stock_actual) <= 0 ? 'font-medium text-red-600' : 'text-gray-500'}>
                  {i.stock_actual} {i.unidad_medida}
                </span>
                <button type="button" onClick={() => empezarEdicion(i)} className="text-xs text-brand-primary underline">
                  Editar
                </button>
                <button type="button" onClick={() => desactivarInsumo(i)} className="text-xs text-red-600 underline">
                  Desactivar
                </button>
              </div>
            </li>
          )
        )}
        {stock.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay insumos cargados.</li>}
      </ul>
    </div>
  )
}

export default Insumos
