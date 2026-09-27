import { useState } from 'react'
import { apiFetch, guardarSesion, obtenerUsuario } from './api'

const FORMULARIO_VACIO = { contrasena_actual: '', contrasena_nueva: '', repetir: '' }

// El backend invalida todas las sesiones anteriores al cambiar la contraseña
// y devuelve un token nuevo: se guarda acá para que quien la cambió siga
// logueado (ver AuthController::cambiarContrasena).
function CambiarContrasena() {
  const [formulario, setFormulario] = useState(FORMULARIO_VACIO)
  const [errores, setErrores] = useState([])
  const [exito, setExito] = useState(false)
  const [cargando, setCargando] = useState(false)

  const actualizar = (campo) => (evento) => {
    setFormulario({ ...formulario, [campo]: evento.target.value })
  }

  const guardar = (evento) => {
    evento.preventDefault()
    setErrores([])
    setExito(false)

    if (formulario.contrasena_nueva !== formulario.repetir) {
      setErrores(['Las dos contraseñas nuevas no coinciden.'])
      return
    }

    setCargando(true)
    const { contrasena_actual, contrasena_nueva } = formulario
    apiFetch('/cambiar-contrasena', {
      method: 'POST',
      body: JSON.stringify({ contrasena_actual, contrasena_nueva }),
    })
      .then((res) => {
        if (res.error) {
          setErrores(res.error.detalles ?? [res.error.mensaje])
        } else {
          guardarSesion(res.data.token, obtenerUsuario())
          setFormulario(FORMULARIO_VACIO)
          setExito(true)
        }
      })
      .catch(() => setErrores(['No se pudo conectar con el servidor.']))
      .finally(() => setCargando(false))
  }

  return (
    <div className="mt-6">
      <form onSubmit={guardar} className="max-w-sm space-y-3 rounded-md border border-gray-300 p-4">
        <h2 className="text-sm font-medium text-brand-primary-dark">Cambiar contraseña</h2>
        <label className="block">
          <span className="text-sm text-gray-700">Contraseña actual</span>
          <input
            type="password"
            required
            autoComplete="current-password"
            value={formulario.contrasena_actual}
            onChange={actualizar('contrasena_actual')}
            className="campo mt-1"
          />
        </label>
        <label className="block">
          <span className="text-sm text-gray-700">Contraseña nueva (mínimo 8 caracteres)</span>
          <input
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            value={formulario.contrasena_nueva}
            onChange={actualizar('contrasena_nueva')}
            className="campo mt-1"
          />
        </label>
        <label className="block">
          <span className="text-sm text-gray-700">Repetir contraseña nueva</span>
          <input
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            value={formulario.repetir}
            onChange={actualizar('repetir')}
            className="campo mt-1"
          />
        </label>

        {errores.length > 0 && (
          <ul className="rounded-md bg-red-50 p-2 text-sm text-red-700">
            {errores.map((e) => (
              <li key={e}>{e}</li>
            ))}
          </ul>
        )}
        {exito && (
          <p className="rounded-md bg-green-50 p-2 text-sm text-green-800">
            Contraseña actualizada. Las sesiones abiertas en otros dispositivos se cerraron.
          </p>
        )}

        <button
          type="submit"
          disabled={cargando}
          className="w-full rounded-md bg-brand-primary px-4 py-2 text-sm font-medium text-white hover:bg-brand-primary-dark disabled:opacity-50"
        >
          {cargando ? 'Guardando...' : 'Guardar'}
        </button>
      </form>
    </div>
  )
}

export default CambiarContrasena
