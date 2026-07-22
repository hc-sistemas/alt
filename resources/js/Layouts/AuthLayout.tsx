import React from 'react'
import { useThemeStore } from '@/Stores/themeStore'

interface Props {
    children: React.ReactNode
}

export default function AuthLayout({ children }: Props) {
    const { theme, toggleTheme } = useThemeStore()

    return (
        <div className="min-h-screen flex" style={{ background: 'var(--bg-main)', color: 'var(--text-main)' }}>
            {/* Panel izquierdo decorativo */}
            <div className="hidden md:flex md:w-[60%] relative overflow-hidden"
                style={{ background: 'linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #0F172A 100%)' }}>

                {/* Patrón de puntos */}
                <div className="absolute inset-0 opacity-10"
                    style={{
                        backgroundImage: 'radial-gradient(circle, #F59E0B 1px, transparent 1px)',
                        backgroundSize: '30px 30px'
                    }} />

                {/* Círculos decorativos */}
                <div className="absolute top-20 left-20 w-64 h-64 rounded-full opacity-5"
                    style={{ background: 'radial-gradient(circle, #F59E0B, transparent)' }} />
                <div className="absolute bottom-20 right-20 w-96 h-96 rounded-full opacity-5"
                    style={{ background: 'radial-gradient(circle, #F59E0B, transparent)' }} />

                <div className="relative z-10 flex flex-col items-center justify-center w-full p-12 text-center">
                    {/* Logo */}
                    <div className="mb-8">
                        <img src="/images/logo-altamira.png" alt="Altamira Light & Sound"
                            className="w-full max-w-sm mx-auto" />
                    </div>

                    <div className="max-w-sm">
                        <h2 className="text-2xl font-semibold text-white mb-3">
                            Sistema de Gestión Empresarial
                        </h2>
                        <p className="text-slate-400 text-base leading-relaxed mb-6">
                            Gestión integrada de ventas, inventario, contabilidad y más para tu empresa.
                        </p>
                    </div>

                    {/* Badges de módulos */}
                    <div className="mt-10 flex flex-wrap gap-2 justify-center max-w-xs">
                        {['Ventas', 'Inventario', 'Contabilidad', 'RRHH', 'Taller', 'Bancos'].map(m => (
                            <span key={m} className="px-3 py-1 rounded-full text-xs border text-slate-300"
                                style={{ borderColor: 'rgba(245,158,11,0.3)', background: 'rgba(245,158,11,0.05)' }}>
                                {m}
                            </span>
                        ))}
                    </div>
                </div>
            </div>

            {/* Panel derecho - formulario */}
            <div className="flex-1 flex flex-col items-center justify-center p-8 relative"
                style={{ background: 'var(--bg-card)' }}>
                {/* Toggle tema */}
                <button onClick={toggleTheme}
                    className="absolute top-4 right-4 p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    title={theme === 'light' ? 'Modo oscuro' : 'Modo claro'}>
                    {theme === 'light' ? (
                        <svg className="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    ) : (
                        <svg className="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    )}
                </button>

                {/* Logo móvil */}
                <div className="md:hidden mb-8 text-center">
                    <img src="/images/logo-altamira.png" alt="Altamira Light & Sound" className="w-full max-w-xs mx-auto" />
                </div>

                <div className="w-full max-w-sm">
                    {children}
                </div>

                <p className="mt-8 text-xs" style={{ color: 'var(--text-muted)' }}>
                    © {new Date().getFullYear()} Altamira Light & Sound. Todos los derechos reservados.
                </p>
            </div>
        </div>
    )
}
