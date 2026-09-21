import { useState } from 'react'
import { guardarTenantId, obtenerTenantId } from './api'
import Campos from './Campos'
import Lotes from './Lotes'
import Insumos from './Insumos'
import Movimientos from './Movimientos'
import Combustible from './Combustible'

function App() {
  const [tenantId, setTenantId] = useState(obtenerTenantId())
  const [vista, setVista] = useState('insumos')

  if (!tenantId) {
    return <SeleccionarTenant onIngresar={setTenantId} />
  }

  return (
    <div className="mx-auto max-w-2xl p-8">
      <header>
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-brand-primary-dark">Stock e Insumos Agrícolas</h1>
          <button
            onClick={() => {
              guardarTenantId('')
              setTenantId('')
            }}
            className="text-xs text-gray-400 hover:text-gray-600"
          >
            Cambiar tenant (#{tenantId})
          </button>
        </div>
        <nav className="mt-4 flex flex-wrap gap-2 border-b border-gray-200">
          <Pestaña activa={vista === 'campos'} onClick={() => setVista('campos')}>
            Campos
          </Pestaña>
          <Pestaña activa={vista === 'lotes'} onClick={() => setVista('lotes')}>
            Lotes
          </Pestaña>
          <Pestaña activa={vista === 'insumos'} onClick={() => setVista('insumos')}>
            Insumos y stock
          </Pestaña>
          <Pestaña activa={vista === 'movimientos'} onClick={() => setVista('movimientos')}>
            Movimientos
          </Pestaña>
          <Pestaña activa={vista === 'combustible'} onClick={() => setVista('combustible')}>
            Combustible
          </Pestaña>
        </nav>
      </header>

      <main>
        {vista === 'campos' && <Campos />}
        {vista === 'lotes' && <Lotes />}
        {vista === 'insumos' && <Insumos />}
        {vista === 'movimientos' && <Movimientos />}
        {vista === 'combustible' && <Combustible />}
      </main>

      <footer className="mt-12 flex items-center gap-2 border-t border-gray-200 pt-4 text-xs text-gray-400">
        <img src="/codenation-logo.webp" alt="CodeNation" className="h-4 w-auto opacity-70" />
        <span>Powered by CodeNation-SC — © {new Date().getFullYear()}</span>
      </footer>
    </div>
  )
}

function Pestaña({ activa, onClick, children }) {
  return (
    <button
      onClick={onClick}
      className={`-mb-px border-b-2 px-3 py-2 text-sm font-medium ${
        activa
          ? 'border-brand-primary text-brand-primary-dark'
          : 'border-transparent text-gray-500 hover:text-gray-700'
      }`}
    >
      {children}
    </button>
  )
}

// Todavía no existe login (ver docs/DECISIONES.md, "Resolución de tenant"):
// esta pantalla es el reemplazo temporal para indicar con qué tenant trabajar.
function SeleccionarTenant({ onIngresar }) {
  const [valor, setValor] = useState('')

  const ingresar = (evento) => {
    evento.preventDefault()
    if (!valor) return
    guardarTenantId(valor)
    onIngresar(valor)
  }

  return (
    <div className="mx-auto max-w-sm p-8">
      <h1 className="text-xl font-semibold text-brand-primary-dark">Stock e Insumos Agrícolas</h1>
      <p className="mt-2 text-sm text-gray-500">
        Todavía no hay login: indicá el ID del tenant con el que querés trabajar.
      </p>
      <form onSubmit={ingresar} className="mt-4 flex gap-2">
        <input
          type="number"
          min="1"
          placeholder="ID de tenant"
          value={valor}
          onChange={(e) => setValor(e.target.value)}
          className="campo"
        />
        <button
          type="submit"
          className="rounded-md bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-dark"
        >
          Ingresar
        </button>
      </form>
    </div>
  )
}

export default App
