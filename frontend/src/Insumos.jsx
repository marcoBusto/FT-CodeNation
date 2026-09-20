import { useEffect, useState } from 'react'
import { apiFetch } from './api'

const INSUMO_VACIO = { nombre: '', marca: '', unidad_medida: 'litros' }

function Insumos() {
  const [stock, setStock] = useState([])
  const [nuevoInsumo, setNuevoInsumo] = useState(INSUMO_VACIO)
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)

  const cargarStock = () => {
    apiFetch('/insumos/stock')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setStock(res.data)
      })
      .catch((err) => setError(err.message))
  }

  useEffect(cargarStock, [])

  const actualizarInsumo = (campo) => (evento) => {
    setNuevoInsumo({ ...nuevoInsumo, [campo]: evento.target.value })
  }

  const crearInsumo = (evento) => {
    evento.preventDefault()
    setErrores([])

    apiFetch('/insumos', { method: 'POST', body: JSON.stringify(nuevoInsumo) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setNuevoInsumo(INSUMO_VACIO)
        cargarStock()
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

      <form onSubmit={crearInsumo} className="space-y-3 rounded-md border border-gray-200 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Nuevo insumo</h2>
        <div className="grid grid-cols-3 gap-3">
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
            <span className="text-sm text-gray-700">Marca (opcional)</span>
            <input
              type="text"
              value={nuevoInsumo.marca}
              onChange={actualizarInsumo('marca')}
              className="campo mt-1"
            />
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
        {stock.map((i) => (
          <li key={i.id} className="flex items-center justify-between p-3 text-sm">
            <span className="text-gray-900">
              {i.nombre}
              {i.marca && <span className="text-gray-500"> · {i.marca}</span>}
            </span>
            <span className={Number(i.stock_actual) <= 0 ? 'font-medium text-red-600' : 'text-gray-500'}>
              {i.stock_actual} {i.unidad_medida}
            </span>
          </li>
        ))}
        {stock.length === 0 && <li className="p-3 text-sm text-gray-500">Todavía no hay insumos cargados.</li>}
      </ul>
    </div>
  )
}

export default Insumos
