import { useEffect, useState } from 'react'
import { apiFetch, cerrarSesion, guardarSesion, obtenerToken, obtenerUsuario, EVENTO_SESION_VENCIDA } from './api'
import Campos from './Campos'
import Lotes from './Lotes'
import Siembras from './Siembras'
import Insumos from './Insumos'
import Movimientos from './Movimientos'
import Combustible from './Combustible'
import Reportes from './Reportes'
import SimuladorCompras from './SimuladorCompras'
import CambiarContrasena from './CambiarContrasena'
import { Marca, PieDeMarca } from './Marca'

function App() {
  const [usuario, setUsuario] = useState(obtenerUsuario())
  const [vista, setVista] = useState('insumos')
  const [sesionVencida, setSesionVencida] = useState(false)

  // api.js avisa cuando el servidor rechaza el token (venció o fue
  // invalidado): se vuelve al login en vez de dejar las pantallas en error.
  useEffect(() => {
    const alVencer = () => {
      setSesionVencida(true)
      setUsuario(null)
    }
    window.addEventListener(EVENTO_SESION_VENCIDA, alVencer)
    return () => window.removeEventListener(EVENTO_SESION_VENCIDA, alVencer)
  }, [])

  const ingresar = (usuarioNuevo) => {
    setSesionVencida(false)
    setUsuario(usuarioNuevo)
  }

  if (!obtenerToken() || !usuario) {
    return <Login onIngresar={ingresar} sesionVencida={sesionVencida} />
  }

  const salir = () => {
    cerrarSesion()
    setUsuario(null)
  }

  return (
    <div className="flex min-h-screen flex-col">
      <header className="bg-brand-navy text-white">
        <div className="mx-auto max-w-4xl px-4 pt-4 sm:px-8">
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
          <Marca />
          <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span className="text-gray-200">{usuario.nombre}</span>
            <button onClick={() => setVista('cuenta')} className="text-gray-400 hover:text-white hover:underline">
              Cambiar contraseña
            </button>
            <button
              onClick={salir}
              className="rounded-full border border-brand-yellow px-3 py-1 text-xs font-semibold text-brand-yellow hover:bg-brand-yellow hover:text-brand-ink"
            >
              Cerrar sesión
            </button>
          </div>
        </div>
        <nav className="mt-4 flex flex-wrap gap-1">
          <Pestaña activa={vista === 'campos'} onClick={() => setVista('campos')}>
            Campos
          </Pestaña>
          <Pestaña activa={vista === 'lotes'} onClick={() => setVista('lotes')}>
            Lotes
          </Pestaña>
          <Pestaña activa={vista === 'siembras'} onClick={() => setVista('siembras')}>
            Siembras
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
        </div>
      </header>

      <main className="mx-auto w-full max-w-4xl flex-1 p-4 sm:p-8">
        {vista === 'campos' && <Campos />}
        {vista === 'lotes' && <Lotes />}
        {vista === 'siembras' && <Siembras />}
        {vista === 'insumos' && <Insumos />}
        {vista === 'movimientos' && <Movimientos />}
        {vista === 'combustible' && <Combustible />}
        {vista === 'reportes' && <Reportes />}
        {vista === 'simulador' && <SimuladorCompras />}
        {vista === 'cuenta' && <CambiarContrasena />}
      </main>

      <PieDeMarca className="border-t border-gray-200" />
    </div>
  )
}

function Pestaña({ activa, onClick, children }) {
  return (
    <button
      onClick={onClick}
      className={`border-b-4 px-3 py-2 text-sm font-medium ${
        activa
          ? 'border-brand-yellow text-white'
          : 'border-transparent text-gray-400 hover:text-white'
      }`}
    >
      {children}
    </button>
  )
}

function Login({ onIngresar, sesionVencida }) {
  const [email, setEmail] = useState('')
  const [contrasena, setContrasena] = useState('')
  const [error, setError] = useState(null)
  const [cargando, setCargando] = useState(false)

  const ingresar = (evento) => {
    evento.preventDefault()
    setError(null)
    setCargando(true)

    // Sin el catch, un fallo de red (sin señal en el campo, servidor caído)
    // dejaba el botón trabado en "Ingresando..." sin ningún mensaje.
    apiFetch('/login', { method: 'POST', body: JSON.stringify({ email, contrasena }) })
      .then((res) => {
        if (res.error) {
          setError(res.error.detalles?.[0] ?? res.error.mensaje)
        } else {
          guardarSesion(res.data.token, res.data.usuario)
          onIngresar(res.data.usuario)
        }
      })
      .catch(() => setError('No se pudo conectar con el servidor. Revisá la conexión e intentá de nuevo.'))
      .finally(() => setCargando(false))
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-brand-navy p-4">
      <div className="mb-8">
        <Marca tamaño="grande" />
      </div>
      <form onSubmit={ingresar} className="w-full max-w-sm space-y-5 rounded-xl bg-white p-6 shadow-xl">
        <div>
          <h1 className="text-lg font-semibold text-brand-ink">Ingresar</h1>
          <p className="mt-1 text-sm text-gray-500">Ingresá con tu email y contraseña.</p>
        </div>
        {sesionVencida && (
          <p className="rounded-md bg-amber-50 p-2 text-sm text-amber-800">
            Tu sesión venció. Ingresá de nuevo para continuar.
          </p>
        )}
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
          className="w-full rounded-full bg-brand-yellow px-5 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-yellow-dark disabled:opacity-50"
        >
          {cargando ? 'Ingresando...' : 'Ingresar'}
        </button>
      </form>
      <PieDeMarca className="mt-6" oscuro />
    </div>
  )
}

export default App
