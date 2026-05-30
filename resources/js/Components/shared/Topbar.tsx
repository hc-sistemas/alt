import { Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { Bell, Menu, ChevronDown, Sun, Moon, LogOut, User, Building2 } from 'lucide-react'
import { useThemeStore } from '@/Stores/themeStore'
import { cn } from '@/lib/utils'
import type { PageProps } from '@/types'

interface Props {
    onMobileMenu: () => void
    pageTitle?: string
}

export default function Topbar({ onMobileMenu, pageTitle }: Props) {
    const { auth, empresa_activa, empresas_usuario } = usePage<PageProps>().props
    const { theme, toggleTheme } = useThemeStore()
    const [userMenuOpen, setUserMenuOpen] = useState(false)
    const [empresaMenuOpen, setEmpresaMenuOpen] = useState(false)
    const [notifOpen, setNotifOpen] = useState(false)

    const user = auth.user

    function logout() {
        router.post(route('logout'))
    }

    function cambiarEmpresa(id: number) {
        setEmpresaMenuOpen(false)
        router.post(route('empresa.cambiar'), { empresa_id: id })
    }

    return (
        <header className="h-14 flex items-center gap-3 px-4 border-b shrink-0"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

            {/* Hamburger - móvil */}
            <button onClick={onMobileMenu}
                className="md:hidden p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                style={{ color: 'var(--text-muted)' }}>
                <Menu className="w-5 h-5" />
            </button>

            {/* Título */}
            {pageTitle && (
                <h1 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                    {pageTitle}
                </h1>
            )}

            <div className="flex-1" />

            {/* Selector empresa */}
            {empresas_usuario.length > 1 && (
                <div className="relative">
                    <button
                        onClick={() => setEmpresaMenuOpen(!empresaMenuOpen)}
                        className="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                    >
                        <Building2 className="w-4 h-4" style={{ color: '#F59E0B' }} />
                        <span className="max-w-32 truncate">{empresa_activa?.nombre_comercial ?? 'Empresa'}</span>
                        <ChevronDown className="w-3 h-3" style={{ color: 'var(--text-muted)' }} />
                    </button>

                    {empresaMenuOpen && (
                        <>
                            <div className="fixed inset-0 z-10" onClick={() => setEmpresaMenuOpen(false)} />
                            <div className="absolute right-0 top-full mt-1 w-56 rounded-lg shadow-lg border z-20"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                                <p className="text-xs font-medium px-3 py-2" style={{ color: 'var(--text-muted)' }}>
                                    Cambiar empresa
                                </p>
                                {empresas_usuario.map(e => (
                                    <button key={e.id}
                                        onClick={() => cambiarEmpresa(e.id!)}
                                        className={cn(
                                            'w-full flex items-center gap-2 px-3 py-2 text-sm text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800',
                                            e.id === empresa_activa?.id && 'font-medium'
                                        )}
                                        style={e.id === empresa_activa?.id ? { color: '#F59E0B' } : { color: 'var(--text-main)' }}
                                    >
                                        {e.id === empresa_activa?.id && <span className="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" />}
                                        <span className="truncate">{e.nombre_comercial}</span>
                                    </button>
                                ))}
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* Notificaciones */}
            <div className="relative">
                <button
                    onClick={() => setNotifOpen(!notifOpen)}
                    className="relative p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    style={{ color: 'var(--text-muted)' }}
                >
                    <Bell className="w-5 h-5" />
                    <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-red-500" />
                </button>

                {notifOpen && (
                    <>
                        <div className="fixed inset-0 z-10" onClick={() => setNotifOpen(false)} />
                        <div className="absolute right-0 top-full mt-1 w-80 rounded-lg shadow-lg border z-20"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <div className="flex items-center justify-between px-4 py-3 border-b"
                                style={{ borderColor: 'var(--border)' }}>
                                <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Notificaciones
                                </p>
                            </div>
                            <div className="p-4 text-center">
                                <Bell className="w-8 h-8 mx-auto mb-2" style={{ color: 'var(--text-muted)' }} />
                                <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                    No hay notificaciones nuevas
                                </p>
                            </div>
                        </div>
                    </>
                )}
            </div>

            {/* Toggle tema */}
            <button
                onClick={toggleTheme}
                className="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                style={{ color: 'var(--text-muted)' }}
                title={theme === 'light' ? 'Modo oscuro' : 'Modo claro'}
            >
                {theme === 'light'
                    ? <Moon className="w-5 h-5" />
                    : <Sun className="w-5 h-5 text-amber-400" />
                }
            </button>

            {/* Avatar usuario */}
            <div className="relative">
                <button
                    onClick={() => setUserMenuOpen(!userMenuOpen)}
                    className="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    <div className="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold text-black"
                        style={{ background: '#F59E0B' }}>
                        {user?.nombre?.charAt(0).toUpperCase() ?? 'U'}
                    </div>
                    <div className="hidden sm:block text-left max-w-28">
                        <p className="text-xs font-medium leading-none truncate" style={{ color: 'var(--text-main)' }}>
                            {user?.nombre}
                        </p>
                        <p className="text-xs leading-none mt-0.5 capitalize" style={{ color: 'var(--text-muted)' }}>
                            {user?.perfil}
                        </p>
                    </div>
                    <ChevronDown className="w-3 h-3 hidden sm:block" style={{ color: 'var(--text-muted)' }} />
                </button>

                {userMenuOpen && (
                    <>
                        <div className="fixed inset-0 z-10" onClick={() => setUserMenuOpen(false)} />
                        <div className="absolute right-0 top-full mt-1 w-48 rounded-lg shadow-lg border z-20"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <div className="px-3 py-2 border-b" style={{ borderColor: 'var(--border)' }}>
                                <p className="text-sm font-medium truncate" style={{ color: 'var(--text-main)' }}>{user?.nombre}</p>
                                <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>{user?.email}</p>
                            </div>
                            <div className="py-1">
                                <button className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
                                    style={{ color: 'var(--text-main)' }}>
                                    <User className="w-4 h-4" />
                                    Mi Perfil
                                </button>
                                <button
                                    onClick={logout}
                                    className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-red-500"
                                >
                                    <LogOut className="w-4 h-4" />
                                    Cerrar Sesión
                                </button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </header>
    )
}
