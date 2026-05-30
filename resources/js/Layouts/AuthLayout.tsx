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
                        <div className="w-24 h-24 rounded-full border-2 flex items-center justify-center mb-4 mx-auto"
                            style={{ borderColor: '#F59E0B', background: 'rgba(245,158,11,0.1)' }}>
                            <svg viewBox="0 0 100 100" className="w-14 h-14" fill="none">
                                <circle cx="50" cy="50" r="40" stroke="#F59E0B" strokeWidth="3" />
                                <path d="M30 60 L50 25 L70 60" stroke="#F59E0B" strokeWidth="4" strokeLinejoin="round" fill="none" />
                                <path d="M35 60 L65 60" stroke="#F59E0B" strokeWidth="3" />
                                <circle cx="50" cy="50" r="6" fill="#F59E0B" />
                            </svg>
                        </div>
                        <h1 className="text-4xl font-bold text-white mb-1">ALTAMIRA</h1>
                        <p className="text-xl font-light" style={{ color: '#F59E0B' }}>Light & Sound</p>
                    </div>

                    <div className="max-w-sm">
                        <h2 className="text-2xl font-semibold text-white mb-3">
                            Sistema de Gestión Empresarial
                        </h2>
                        <p className="text-slate-400 text-base leading-relaxed mb-6">
                            Gestión integrada de ventas, inventario, contabilidad y más para tu empresa.
                        </p>
                        <p className="text-sm italic" style={{ color: '#F59E0B' }}>
                            "Ahora las luces se ven Diferente"
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
                    <h1 className="text-3xl font-bold" style={{ color: 'var(--text-main)' }}>ALTAMIRA</h1>
                    <p className="text-sm" style={{ color: '#F59E0B' }}>Light & Sound</p>
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
