import { useEffect, useState } from 'react'
import { apiFetch, apiFetchBlob } from './api'

function Reportes() {
  const [campos, setCampos] = useState([])
  const [lotes, setLotes] = useState([])
  const [stock, setStock] = useState([])
  const [error, setError] = useState(null)

  useEffect(() => {
    apiFetch('/campos').then((res) => setCampos(res.data ?? []))
    apiFetch('/lotes').then((res) => setLotes(res.data ?? []))
    apiFetch('/insumos/stock')
      .then((res) => {
        if (res.error) throw new Error(res.error.mensaje)
        setStock(res.data)
      })
      .catch((err) => setError(err.message))
  }, [])

  const lotesPorCampo = (campoId) => lotes.filter((l) => l.campo_id === campoId)

  return (
    <div className="mt-6 space-y-8">
      {error && (
        <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">
          No se pudo conectar con el backend: {error}
        </p>
      )}

      <ReporteCamposLotes campos={campos} lotesPorCampo={lotesPorCampo} />
      <ReporteInsumosStock stock={stock} />
    </div>
  )
}

function ReporteCamposLotes({ campos, lotesPorCampo }) {
  const titulo = 'Reporte de campos y lotes'

  const filas = campos.map((c) => {
    const lotesDelCampo = lotesPorCampo(c.id)
    const totalHectareas = lotesDelCampo.reduce((acc, l) => acc + Number(l.hectareas), 0)
    return { campo: c, lotes: lotesDelCampo, totalHectareas }
  })

  return (
    <div className="space-y-3 rounded-md border border-gray-200 p-4">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-medium text-brand-primary-dark">{titulo}</h2>
        <AccionesReporte
          rutaPdf="/reportes/campos-lotes"
          nombreArchivo="campos-lotes.pdf"
          contenidoImprimible={() => htmlCamposLotes(titulo, filas)}
        />
      </div>

      {filas.length === 0 && <p className="text-sm text-gray-500">Todavía no hay campos cargados.</p>}

      {filas.map(({ campo, lotes: lotesDelCampo, totalHectareas }) => (
        <div key={campo.id} className="rounded-md border border-gray-100 p-3">
          <p className="text-sm font-medium text-gray-900">{campo.nombre}</p>
          {campo.ubicacion && <p className="text-xs text-gray-500">{campo.ubicacion}</p>}
          {lotesDelCampo.length === 0 ? (
            <p className="mt-2 text-xs text-gray-400">Sin lotes cargados.</p>
          ) : (
            <table className="mt-2 w-full text-xs">
              <thead>
                <tr className="text-left text-gray-500">
                  <th className="py-1">Lote</th>
                  <th className="py-1">Hectáreas</th>
                </tr>
              </thead>
              <tbody>
                {lotesDelCampo.map((l) => (
                  <tr key={l.id} className="border-t border-gray-100">
                    <td className="py-1 text-gray-900">{l.nombre}</td>
                    <td className="py-1 text-gray-500">{l.hectareas} ha</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          {lotesDelCampo.length > 0 && (
            <p className="mt-1 text-xs font-medium text-brand-primary">Total: {totalHectareas.toFixed(2)} ha</p>
          )}
        </div>
      ))}
    </div>
  )
}

function ReporteInsumosStock({ stock }) {
  const titulo = 'Reporte de insumos y stock'

  return (
    <div className="space-y-3 rounded-md border border-gray-200 p-4">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-medium text-brand-primary-dark">{titulo}</h2>
        <AccionesReporte
          rutaPdf="/reportes/insumos-stock"
          nombreArchivo="insumos-stock.pdf"
          contenidoImprimible={() => htmlInsumosStock(titulo, stock)}
        />
      </div>

      {stock.length === 0 ? (
        <p className="text-sm text-gray-500">Todavía no hay insumos cargados.</p>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-gray-500">
              <th className="py-1">Insumo</th>
              <th className="py-1">Marca</th>
              <th className="py-1">Categoría</th>
              <th className="py-1">Stock</th>
            </tr>
          </thead>
          <tbody>
            {stock.map((i) => (
              <tr key={i.id} className="border-t border-gray-100">
                <td className="py-1 text-gray-900">{i.nombre}</td>
                <td className="py-1 text-gray-500">{i.marca_nombre ?? '-'}</td>
                <td className="py-1 text-gray-500">{i.categoria_nombre ?? '-'}</td>
                <td className={`py-1 ${Number(i.stock_actual) <= 0 ? 'font-medium text-red-600' : 'text-gray-500'}`}>
                  {i.stock_actual} {i.unidad_medida}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

// Botones comunes a los dos reportes: imprimir, descargar PDF y compartir por
// WhatsApp. "rutaPdf" pega contra el backend (dompdf); "contenidoImprimible"
// arma el HTML que se abre en una pestaña aparte para usar el diálogo nativo
// de impresión del navegador (sin depender del PDF del backend).
function AccionesReporte({ rutaPdf, nombreArchivo, contenidoImprimible }) {
  const [cargando, setCargando] = useState(false)
  const [error, setError] = useState(null)

  const imprimir = () => {
    const ventana = window.open('', '_blank')
    if (!ventana) return
    ventana.document.write(contenidoImprimible())
    ventana.document.close()
    ventana.focus()
    ventana.print()
  }

  const descargarPdf = () => {
    setError(null)
    setCargando(true)
    apiFetchBlob(rutaPdf)
      .then((blob) => {
        const url = URL.createObjectURL(blob)
        const enlace = document.createElement('a')
        enlace.href = url
        enlace.download = nombreArchivo
        enlace.click()
        URL.revokeObjectURL(url)
      })
      .catch((err) => setError(err.message))
      .finally(() => setCargando(false))
  }

  // Web Share API con archivo: funciona en Chrome/Safari de celular y manda
  // el PDF directo a la app de WhatsApp. Donde no está disponible (la mayoría
  // de los navegadores de escritorio), cae a un link de WhatsApp Web con
  // texto — el PDF hay que adjuntarlo a mano después de descargarlo.
  const compartirPorWhatsapp = () => {
    setError(null)
    setCargando(true)
    apiFetchBlob(rutaPdf)
      .then(async (blob) => {
        const archivo = new File([blob], nombreArchivo, { type: 'application/pdf' })
        if (navigator.canShare?.({ files: [archivo] })) {
          await navigator.share({ files: [archivo], title: nombreArchivo })
        } else {
          window.open(`https://wa.me/?text=${encodeURIComponent('Te comparto el ' + nombreArchivo)}`, '_blank')
          setError('Tu navegador no permite adjuntar el archivo directo: descargalo y adjuntalo a mano en WhatsApp.')
        }
      })
      .catch((err) => {
        if (err.name !== 'AbortError') setError(err.message)
      })
      .finally(() => setCargando(false))
  }

  return (
    <div className="flex flex-col items-end gap-1">
      <div className="flex gap-2 text-xs">
        <button type="button" onClick={imprimir} className="text-brand-primary underline">
          Imprimir
        </button>
        <button type="button" onClick={descargarPdf} disabled={cargando} className="text-brand-primary underline disabled:opacity-40">
          Descargar PDF
        </button>
        <button type="button" onClick={compartirPorWhatsapp} disabled={cargando} className="text-brand-primary underline disabled:opacity-40">
          Compartir por WhatsApp
        </button>
      </div>
      {error && <p className="text-xs text-red-600">{error}</p>}
    </div>
  )
}

function estilosImprimibles() {
  return `
    body { font-family: sans-serif; color: #1f2937; padding: 24px; }
    h1 { color: #1f4b33; font-size: 18px; } h2 { color: #1f4b33; font-size: 14px; margin-top: 16px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; font-size: 12px; }
    th { background: #f3f4f6; }
    .fecha { color: #6b7280; font-size: 11px; }
    .total { font-weight: bold; }
  `
}

function htmlCamposLotes(titulo, filas) {
  const filasHtml = filas
    .map(({ campo, lotes: lotesDelCampo, totalHectareas }) => {
      const ubicacion = campo.ubicacion ? `<p>Ubicación: ${campo.ubicacion}</p>` : ''
      if (lotesDelCampo.length === 0) {
        return `<h2>${campo.nombre}</h2>${ubicacion}<p><em>Sin lotes cargados.</em></p>`
      }
      const filasLotes = lotesDelCampo
        .map((l) => `<tr><td>${l.nombre}</td><td>${l.hectareas}</td></tr>`)
        .join('')
      return `<h2>${campo.nombre}</h2>${ubicacion}<table><tr><th>Lote</th><th>Hectáreas</th></tr>${filasLotes}</table><p class="total">Total: ${totalHectareas.toFixed(2)} ha</p>`
    })
    .join('')

  return `<html><head><meta charset="utf-8"><title>${titulo}</title><style>${estilosImprimibles()}</style></head>
    <body><h1>${titulo}</h1><p class="fecha">Generado el ${new Date().toLocaleString('es-AR')}</p>${filasHtml || '<p><em>Todavía no hay campos cargados.</em></p>'}</body></html>`
}

function htmlInsumosStock(titulo, stock) {
  const filasHtml = stock
    .map(
      (i) =>
        `<tr><td>${i.nombre}</td><td>${i.marca_nombre ?? '-'}</td><td>${i.categoria_nombre ?? '-'}</td><td>${i.stock_actual} ${i.unidad_medida}</td></tr>`
    )
    .join('')

  const tabla = stock.length
    ? `<table><tr><th>Insumo</th><th>Marca</th><th>Categoría</th><th>Stock</th></tr>${filasHtml}</table>`
    : '<p><em>Todavía no hay insumos cargados.</em></p>'

  return `<html><head><meta charset="utf-8"><title>${titulo}</title><style>${estilosImprimibles()}</style></head>
    <body><h1>${titulo}</h1><p class="fecha">Generado el ${new Date().toLocaleString('es-AR')}</p>${tabla}</body></html>`
}

export default Reportes
