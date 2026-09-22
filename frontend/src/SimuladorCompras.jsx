import { useEffect, useState } from 'react'
import { apiFetch } from './api'

function SimuladorCompras() {
  const [insumos, setInsumos] = useState([])
  const [insumoId, setInsumoId] = useState('')
  const [cantidad, setCantidad] = useState('')
  const [precioUsd, setPrecioUsd] = useState('')
  const [cotizacion, setCotizacion] = useState('')
  const [fuenteCotizacion, setFuenteCotizacion] = useState(null)
  const [buscandoCotizacion, setBuscandoCotizacion] = useState(false)
  const [resultado, setResultado] = useState(null)
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    apiFetch('/insumos/stock')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setInsumos(res.data)
      })
      .catch((err) => setError(err.message))
  }, [])

  const buscarCotizacion = () => {
    setBuscandoCotizacion(true)
    setFuenteCotizacion(null)
    apiFetch('/simulador-compras/cotizacion-dolar').then((res) => {
      setBuscandoCotizacion(false)
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setCotizacion(String(res.data.cotizacion))
        setFuenteCotizacion(res.data.fuente)
      }
    })
  }

  const calcular = (evento) => {
    evento.preventDefault()
    setErrores([])
    setResultado(null)

    apiFetch('/simulador-compras/estimar', {
      method: 'POST',
      body: JSON.stringify({
        insumo_id: insumoId,
        cantidad,
        precio_unitario_usd: precioUsd,
        cotizacion,
      }),
    }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setResultado(res.data)
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

      <form onSubmit={calcular} className="space-y-3 rounded-md border border-gray-200 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Simulador de compras</h2>
        <p className="text-xs text-gray-400">
          Estimación rápida del costo de una compra en dólares — no queda guardada como historial.
        </p>

        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <label className="block">
            <span className="text-sm text-gray-700">Insumo</span>
            <select required value={insumoId} onChange={(e) => setInsumoId(e.target.value)} className="campo mt-1">
              <option value="">Elegir...</option>
              {insumos.map((i) => (
                <option key={i.id} value={i.id}>
                  {i.nombre}
                </option>
              ))}
            </select>
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Cantidad</span>
            <input
              type="number"
              min="0.01"
              step="0.01"
              required
              value={cantidad}
              onChange={(e) => setCantidad(e.target.value)}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Precio unitario (USD)</span>
            <input
              type="number"
              min="0.01"
              step="0.01"
              required
              value={precioUsd}
              onChange={(e) => setPrecioUsd(e.target.value)}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Cotización dólar (ARS)</span>
            <div className="mt-1 flex gap-2">
              <input
                type="number"
                min="0.01"
                step="0.01"
                required
                value={cotizacion}
                onChange={(e) => {
                  setCotizacion(e.target.value)
                  setFuenteCotizacion(null)
                }}
                className="campo"
              />
              <button
                type="button"
                onClick={buscarCotizacion}
                disabled={buscandoCotizacion}
                className="whitespace-nowrap rounded-md border border-brand-primary px-2 text-xs text-brand-primary disabled:opacity-40"
              >
                {buscandoCotizacion ? '...' : 'Actualizar'}
              </button>
            </div>
            {fuenteCotizacion && <p className="mt-1 text-xs text-gray-400">Fuente: {fuenteCotizacion}</p>}
          </label>
        </div>

        {insumos.length === 0 && !error && (
          <p className="text-xs text-amber-600">Todavía no hay insumos cargados — agregá uno en la pestaña Insumos.</p>
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
              Total: U$S {resultado.total_usd} · $ {resultado.total_ars} ARS
            </p>
            <p className="text-xs text-gray-500">
              {resultado.insumo_nombre} ({resultado.unidad_medida}) · cotización usada: $
              {resultado.cotizacion_usada} ARS/USD
            </p>
          </div>
        )}
      </form>
    </div>
  )
}

export default SimuladorCompras
