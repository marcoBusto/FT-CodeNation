import LogoSimbolo from './LogoSimbolo'

// Nombre del producto: símbolo de Code Nation + "FT-CodeNation". Pensado para
// ir sobre fondo oscuro (barra superior y login), igual que en SisCom.
export function Marca({ tamaño = 'normal' }) {
  const grande = tamaño === 'grande'
  return (
    <div className="flex items-center gap-3">
      <LogoSimbolo className={grande ? 'h-12 w-auto' : 'h-9 w-auto'} />
      <div className="leading-none">
        <p className={`font-display tracking-wide text-white ${grande ? 'text-5xl' : 'text-4xl'}`}>FT-CodeNation</p>
        <p className="mt-0.5 text-[11px] font-medium tracking-wider text-gray-400 uppercase">
          Stock e insumos agrícolas
        </p>
      </div>
    </div>
  )
}

// Crédito fijo del producto. Sobre fondo oscuro "NATION" va en amarillo, como
// en el logo; sobre blanco en violeta, porque el amarillo no se lee sobre blanco.
export function PieDeMarca({ className = '', oscuro = false }) {
  return (
    <footer className={`px-4 py-6 text-center text-xs text-gray-400 ${className}`}>
      FT-CodeNation · Powered by{' '}
      <a href="https://codenation.com.ar" target="_blank" rel="noreferrer" className="font-semibold hover:underline">
        CODE<span className={oscuro ? 'text-brand-yellow' : 'text-brand-purple-dark'}>NATION</span>.AR
      </a>{' '}
      © {new Date().getFullYear()}
    </footer>
  )
}
