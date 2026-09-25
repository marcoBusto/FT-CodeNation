import { useEffect, useState } from 'react'
import { apiFetch } from './api'

const MOVIMIENTO_VACIO = {
  tipo: 'ENTRADA',
  insumo_id: '',
  lote_id: '',
  dosis_por_ha: '',
  cantidad_total: '',
  observacion: '',
}

function Movimientos() {
  const [movimientos, setMovimientos] = useState([])
  const [insumos, setInsumos] = useState([])
  const [lotes, setLotes] = useState([])
  const [movimiento, setMovimiento] = useState(MOVIMIENTO_VACIO)
  const [errores, setErrores] = useState([])
  const [error, setError] = useState(null)

  const cargarMovimientos = () => {
    apiFetch('/movimientos')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setMovimientos(res.data)
      })
      .catch((err) => setError(err.message))
  }

  useEffect(() => {
    cargarMovimientos()
    apiFetch('/insumos').then((res) => setInsumos(res.data ?? []))
    apiFetch('/lotes').then((res) => setLotes(res.data ?? []))
  }, [])

  const actualizarMovimiento = (campo) => (evento) => {
    setMovimiento({ ...movimiento, [campo]: evento.target.value })
  }

  const cambiarTipo = (evento) => {
    // Al cambiar de tipo se limpian lote/dosis: son obligatorios solo en
    // EGRESO_LOTE y no tiene sentido arrastrarlos a otro tipo de movimiento.
    setMovimiento({ ...MOVIMIENTO_VACIO, tipo: evento.target.value })
  }

  const registrarMovimiento = (evento) => {
    evento.preventDefault()
    setErrores([])

    apiFetch('/movimientos', { method: 'POST', body: JSON.stringify(movimiento) }).then((res) => {
      if (res.error) {
        setErrores(res.error.detalles ?? [res.error.mensaje])
      } else {
        setMovimiento({ ...MOVIMIENTO_VACIO, tipo: movimiento.tipo })
        cargarMovimientos()
      }
    })
  }

  const esEgresoLote = movimiento.tipo === 'EGRESO_LOTE'
  const esAjuste = movimiento.tipo === 'AJUSTE'

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">
          No se pudo conectar con el backend: {error}
        </p>
      )}

      <form onSubmit={registrarMovimiento} className="space-y-3 rounded-md border border-gray-300 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Nuevo movimiento</h2>
        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm text-gray-700">Tipo</span>
            <select value={movimiento.tipo} onChange={cambiarTipo} className="campo mt-1">
              <option value="ENTRADA">Entrada</option>
              <option value="EGRESO_LOTE">Egreso a lote</option>
              <option value="AJUSTE">Ajuste</option>
            </select>
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Insumo</span>
            <select
              required
              value={movimiento.insumo_id}
              onChange={actualizarMovimiento('insumo_id')}
              className="campo mt-1"
            >
              <option value="">Elegir...</option>
              {insumos.map((i) => (
                <option key={i.id} value={i.id}>
                  {i.nombre}
                  {i.marca_nombre && ` · ${i.marca_nombre}`} ({i.unidad_medida})
                </option>
              ))}
            </select>
          </label>
        </div>

        {esEgresoLote && (
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="text-sm text-gray-700">Lote</span>
              <select
                required
                value={movimiento.lote_id}
                onChange={actualizarMovimiento('lote_id')}
                className="campo mt-1"
              >
                <option value="">Elegir...</option>
                {lotes.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.nombre} ({l.campo_nombre}, {l.hectareas} ha)
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="text-sm text-gray-700">Dosis / ha (opcional)</span>
              <input
                type="number"
                step="0.001"
                min="0"
                value={movimiento.dosis_por_ha}
                onChange={actualizarMovimiento('dosis_por_ha')}
                className="campo mt-1"
              />
            </label>
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <label className="block">
            <span className="text-sm text-gray-700">
              Cantidad total {esAjuste && '(negativo para descontar)'}
            </span>
            <input
              type="number"
              step="0.001"
              required
              value={movimiento.cantidad_total}
              onChange={actualizarMovimiento('cantidad_total')}
              className="campo mt-1"
            />
          </label>
          <label className="block">
            <span className="text-sm text-gray-700">Observación</span>
            <input
              type="text"
              value={movimiento.observacion}
              onChange={actualizarMovimiento('observacion')}
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
          Registrar movimiento
        </button>
      </form>

      <ul className="divide-y divide-gray-200 rounded-md border border-gray-300">
        {movimientos.map((m) => (
          <li key={m.id} className="p-3 text-sm">
            <div className="flex items-center justify-between gap-2">
              <span className="font-medium text-gray-900">{m.tipo}</span>
              <span className="text-gray-700">{m.cantidad_total} {m.unidad_medida}</span>
            </div>
            <div className="mt-1 text-gray-700">
              {m.insumo_nombre}
              {m.lote_nombre && ` · ${m.lote_nombre}`}
              {m.dosis_por_ha && ` · ${m.dosis_por_ha}/ha`}
              {' · '}
              {m.usuario_nombre} · {m.fecha_hora}
            </div>
            {m.observacion && <div className="mt-1 text-gray-600">{m.observacion}</div>}
          </li>
        ))}
        {movimientos.length === 0 && <li className="p-3 text-sm text-gray-700">Todavía no hay movimientos.</li>}
      </ul>
    </div>
  )
}

export default Movimientos
