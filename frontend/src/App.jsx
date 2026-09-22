import { useState } from 'react'
import { apiFetch, cerrarSesion, guardarSesion, obtenerToken, obtenerUsuario } from './api'
import Campos from './Campos'
import Lotes from './Lotes'
import Insumos from './Insumos'
import Movimientos from './Movimientos'
import Combustible from './Combustible'
import Reportes from './Reportes'
import SimuladorCompras from './SimuladorCompras'

function App() {
  const [usuario, setUsuario] = useState(obtenerUsuario())
  const [vista, setVista] = useState('insumos')

  if (!obtenerToken() || !usuario) {
    return <Login onIngresar={setUsuario} />
  }

  const salir = () => {
    cerrarSesion()
    setUsuario(null)
  }

  return (
    <div className="mx-auto max-w-2xl p-8">
      <header>
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-semibold text-brand-primary-dark">Stock e Insumos Agrícolas</h1>
          <div className="flex items-center gap-3 text-xs text-gray-500">
            <span>{usuario.nombre}</span>
            <button onClick={salir} className="text-gray-400 underline hover:text-gray-600">
              Cerrar sesión
            </button>
          </div>
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
          <Pestaña activa={vista === 'reportes'} onClick={() => setVista('reportes')}>
            Reportes
          </Pestaña>
          <Pestaña activa={vista === 'simulador'} onClick={() => setVista('simulador')}>
            Simulador de compras
          </Pestaña>
        </nav>
      </header>

      <main>
        {vista === 'campos' && <Campos />}
        {vista === 'lotes' && <Lotes />}
        {vista === 'insumos' && <Insumos />}
        {vista === 'movimientos' && <Movimientos />}
        {vista === 'combustible' && <Combustible />}
        {vista === 'reportes' && <Reportes />}
        {vista === 'simulador' && <SimuladorCompras />}
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

function Login({ onIngresar }) {
  const [email, setEmail] = useState('')
  const [contrasena, setContrasena] = useState('')
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(false)

  const ingresar = (evento) => {
    evento.preventDefault()
    setError(null)
    setCargando(true)

    apiFetch('/login', { method: 'POST', body: JSON.stringify({ email, contrasena }) }).then((res) => {
      setCargando(false)
      if (res.error) {
        setError(res.error.detalles?.[0] ?? res.error.mensaje)
      } else {
        guardarSesion(res.data.token, res.data.usuario)
        onIngresar(res.data.usuario)
      }
    })
  }

  return (
    <div className="mx-auto max-w-sm p-8">
      <h1 className="text-xl font-semibold text-brand-primary-dark">Stock e Insumos Agrícolas</h1>
      <form onSubmit={ingresar} className="mt-4 space-y-3">
        <label className="block">
          <span className="text-sm text-gray-700">Email</span>
          <input
            type="email"
            required
            autoFocus
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="campo mt-1"
          />
        </label>
        <label className="block">
          <span className="text-sm text-gray-700">Contraseña</span>
          <input
            type="password"
            required
            value={contrasena}
            onChange={(e) => setContrasena(e.target.value)}
            className="campo mt-1"
          />
        </label>

        {error && <p className="rounded-md bg-red-50 p-2 text-sm text-red-700">{error}</p>}

        <button
          type="submit"
          disabled={cargando}
          className="w-full rounded-md bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-dark disabled:opacity-50"
        >
          {cargando ? 'Ingresando...' : 'Ingresar'}
        </button>
      </form>
    </div>
  )
}

export default App
