import { Link, router, usePage } from '@inertiajs/react'
import { useState, useEffect, useRef } from 'react'
import { Bell, Menu, ChevronDown, Sun, Moon, LogOut, User, Building2, Check, CheckCheck } from 'lucide-react'
import { useThemeStore } from '@/Stores/themeStore'
import { cn } from '@/lib/utils'
import { estiloHeaderEmpresa } from '@/lib/empresaHeader'
import BuscadorModulos from '@/Components/shared/BuscadorModulos'
import type { PageProps, Notificacion } from '@/types'

interface Props {
    onMobileMenu: () => void
    pageTitle?: string
}

export default function Topbar({ onMobileMenu, pageTitle }: Props) {
    const { auth, empresa_activa, empresas_usuario, notificaciones_no_leidas } = usePage<PageProps>().props
    const { theme, toggleTheme } = useThemeStore()
    const [userMenuOpen, setUserMenuOpen] = useState(false)
    const [empresaMenuOpen, setEmpresaMenuOpen] = useState(false)
    const [notifOpen, setNotifOpen] = useState(false)
    const [notificaciones, setNotificaciones] = useState<Notificacion[]>([])
    const [noLeidas, setNoLeidas] = useState(notificaciones_no_leidas)
    const [cargando, setCargando] = useState(false)
    const fetchedRef = useRef(false)

    const user = auth.user

    // Sync badge count when Inertia shared prop changes (e.g. after page nav)
    useEffect(() => {
        setNoLeidas(notificaciones_no_leidas)
    }, [notificaciones_no_leidas])

    async function fetchNotificaciones() {
        if (fetchedRef.current) return
        fetchedRef.current = true
        setCargando(true)
        try {
            const res = await fetch(route('notificaciones.index'), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            if (res.ok) {
                const data = await res.json() as { notificaciones: Notificacion[]; no_leidas: number }
                setNotificaciones(data.notificaciones)
                setNoLeidas(data.no_leidas)
            }
        } finally {
            setCargando(false)
        }
    }

    function toggleNotif() {
        const next = !notifOpen
        setNotifOpen(next)
        if (next) fetchNotificaciones()
    }

    async function marcarLeida(id: number) {
        await fetch(route('notificaciones.leer', { id }), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        })
        setNotificaciones(prev => prev.map(n => n.id === id ? { ...n, leida: true } : n))
        setNoLeidas(prev => Math.max(0, prev - 1))
    }

    async function marcarTodasLeidas() {
        await fetch(route('notificaciones.leer-todas'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        })
        setNotificaciones(prev => prev.map(n => ({ ...n, leida: true })))
        setNoLeidas(0)
    }

    function logout() {
        router.post(route('logout'))
    }

    // Altamira Import (RUC 1755265848001): azul marino oscuro tomado del logo.
    const esImport = empresa_activa?.ruc === '1755265848001'
    // Altamira Matriz (RUC 1711293454001): azul acero tomado de la referencia del equipo.
    const esMatriz = empresa_activa?.ruc === '1711293454001'

    function colorEmpresa(ruc?: string | null) {
        // Matriz (fondo dorado tenue) vs Import (fondo blanco, letras doradas) — para distinguirlas de un vistazo
        if (ruc === '1755265848001') {
            return { text: '#F59E0B', bg: '#FFFFFF', border: 'rgba(245,158,11,0.3)' }
        }
        return { text: '#F59E0B', bg: 'rgba(245,158,11,0.1)', border: 'rgba(245,158,11,0.3)' }
    }

    function cambiarEmpresa(id: number) {
        setEmpresaMenuOpen(false)
        router.post(route('empresa.cambiar'), { empresa_id: id })
    }

    function formatFechaNotif(dateStr: string) {
        const d = new Date(dateStr)
        const now = new Date()
        const diffMs = now.getTime() - d.getTime()
        const diffMin = Math.floor(diffMs / 60000)
        if (diffMin < 1) return 'ahora'
        if (diffMin < 60) return `hace ${diffMin}m`
        const diffH = Math.floor(diffMin / 60)
        if (diffH < 24) return `hace ${diffH}h`
        return d.toLocaleDateString('es-EC', { day: '2-digit', month: 'short' })
    }

    // Con barra de color de empresa (Matriz/Import) el texto es siempre blanco, en claro y oscuro.
    const conColor   = esImport || esMatriz
    const txtMain    = conColor ? '#FFFFFF' : 'var(--text-main)'
    const txtMuted   = conColor ? 'rgba(255,255,255,0.8)' : 'var(--text-muted)'
    const hoverBoton = conColor ? 'hover:bg-white/10' : 'hover:bg-slate-100 dark:hover:bg-slate-800'

    const estilo = estiloHeaderEmpresa(empresa_activa?.ruc)

    return (
        <header className={cn('flex items-center gap-3 px-4 shrink-0', estilo ? 'min-h-[68px]' : 'h-14 border-b')}
            style={estilo
                ? { background: estilo.fondo, borderBottom: `3px solid ${estilo.linea}`, color: '#FFFFFF' }
                : { background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

            {/* Hamburger - móvil */}
            <button onClick={onMobileMenu}
                className={`md:hidden p-1.5 rounded-lg ${hoverBoton} transition-colors`}
                style={{ color: txtMuted }}>
                <Menu className="w-5 h-5" />
            </button>

            {/* Título */}
            {pageTitle && (
                <h1 className={conColor ? 'text-lg font-bold' : 'text-base font-semibold'} style={{ color: txtMain }}>
                    {pageTitle}
                </h1>
            )}

            <div className="flex-1" />

            <BuscadorModulos conColor={conColor} />

            {/* Empresa activa — badge fijo cuando el usuario solo tiene una empresa */}
            {empresas_usuario.length <= 1 && empresa_activa && estilo && (
                <PillEmpresa nombre={empresa_activa.nombre_comercial ?? ''} estilo={estilo} />
            )}
            {empresas_usuario.length <= 1 && empresa_activa && !estilo && (
                <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm"
                    style={{
                        borderColor: colorEmpresa(empresa_activa.ruc).border,
                        background: colorEmpresa(empresa_activa.ruc).bg,
                        color: colorEmpresa(empresa_activa.ruc).text,
                    }}>
                    <Building2 className="w-4 h-4" />
                    <span className="max-w-32 truncate font-medium">{empresa_activa.nombre_comercial}</span>
                </div>
            )}

            {/* Selector empresa */}
            {empresas_usuario.length > 1 && (
                <div className="relative">
                    {estilo ? (
                        <PillEmpresa nombre={empresa_activa?.nombre_comercial ?? 'Empresa'} estilo={estilo} onClick={() => setEmpresaMenuOpen(!empresaMenuOpen)} />
                    ) : (
                    <button
                        onClick={() => setEmpresaMenuOpen(!empresaMenuOpen)}
                        className="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm transition-colors hover:opacity-80"
                        style={{
                            borderColor: colorEmpresa(empresa_activa?.ruc).border,
                            background: colorEmpresa(empresa_activa?.ruc).bg,
                            color: colorEmpresa(empresa_activa?.ruc).text,
                        }}
                    >
                        <Building2 className="w-4 h-4" />
                        <span className="max-w-32 truncate font-medium">{empresa_activa?.nombre_comercial ?? 'Empresa'}</span>
                        <ChevronDown className="w-3 h-3" />
                    </button>
                    )}

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
                                        style={e.id === empresa_activa?.id ? { color: colorEmpresa(e.ruc).text } : { color: 'var(--text-main)' }}
                                    >
                                        {e.id === empresa_activa?.id && (
                                            <span className="w-1.5 h-1.5 rounded-full shrink-0" style={{ background: colorEmpresa(e.ruc).text }} />
                                        )}
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
                    onClick={toggleNotif}
                    className={`relative ${conColor ? 'w-11 h-11 rounded-xl flex items-center justify-center' : 'p-2 rounded-lg'} ${hoverBoton} transition-colors`}
                    style={{ color: txtMuted }}
                >
                    <Bell className="w-5 h-5" />
                    {noLeidas > 0 && (
                        <span className="absolute top-1 right-1 min-w-4 h-4 px-0.5 rounded-full bg-red-500 flex items-center justify-center text-white text-[9px] font-bold leading-none">
                            {noLeidas > 9 ? '9+' : noLeidas}
                        </span>
                    )}
                </button>

                {notifOpen && (
                    <>
                        <div className="fixed inset-0 z-10" onClick={() => setNotifOpen(false)} />
                        <div className="absolute right-0 top-full mt-1 w-80 rounded-lg shadow-lg border z-20 flex flex-col max-h-105"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                            {/* Header */}
                            <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                                style={{ borderColor: 'var(--border)' }}>
                                <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Notificaciones
                                    {noLeidas > 0 && (
                                        <span className="ml-2 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white">
                                            {noLeidas}
                                        </span>
                                    )}
                                </p>
                                {noLeidas > 0 && (
                                    <button
                                        onClick={marcarTodasLeidas}
                                        className="flex items-center gap-1 text-xs hover:underline"
                                        style={{ color: '#F59E0B' }}
                                        title="Marcar todas como leídas"
                                    >
                                        <CheckCheck className="w-3.5 h-3.5" />
                                        Todo leído
                                    </button>
                                )}
                            </div>

                            {/* Lista */}
                            <div className="overflow-y-auto flex-1">
                                {cargando ? (
                                    <div className="p-6 text-center">
                                        <div className="w-5 h-5 border-2 border-amber-400 border-t-transparent rounded-full animate-spin mx-auto" />
                                    </div>
                                ) : notificaciones.length === 0 ? (
                                    <div className="p-6 text-center">
                                        <Bell className="w-8 h-8 mx-auto mb-2" style={{ color: 'var(--text-muted)' }} />
                                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                            No hay notificaciones
                                        </p>
                                    </div>
                                ) : (
                                    notificaciones.map(n => (
                                        <div
                                            key={n.id}
                                            className={cn(
                                                'flex gap-3 px-4 py-3 border-b last:border-b-0 transition-colors',
                                                !n.leida
                                                    ? 'bg-amber-50/60 dark:bg-amber-900/10'
                                                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'
                                            )}
                                            style={{ borderColor: 'var(--border)' }}
                                        >
                                            {/* Indicador no leída */}
                                            <div className="pt-1 shrink-0">
                                                {!n.leida
                                                    ? <span className="w-2 h-2 rounded-full bg-amber-500 block mt-0.5" />
                                                    : <span className="w-2 h-2 block" />
                                                }
                                            </div>

                                            {/* Contenido — clickeable cuando trae `url` (ej. link de descarga) */}
                                            {n.url ? (
                                                <a href={n.url} onClick={() => !n.leida && marcarLeida(n.id)}
                                                    className="flex-1 min-w-0">
                                                    <p className="text-xs font-semibold leading-snug truncate underline"
                                                        style={{ color: 'var(--text-main)' }}>
                                                        {n.titulo}
                                                    </p>
                                                    {n.mensaje && (
                                                        <p className="text-xs mt-0.5 leading-snug line-clamp-2"
                                                            style={{ color: 'var(--text-muted)' }}>
                                                            {n.mensaje}
                                                        </p>
                                                    )}
                                                    <p className="text-[10px] mt-1" style={{ color: 'var(--text-muted)' }}>
                                                        {formatFechaNotif(n.created_at)}
                                                    </p>
                                                </a>
                                            ) : (
                                                <div className="flex-1 min-w-0">
                                                    <p className="text-xs font-semibold leading-snug truncate"
                                                        style={{ color: 'var(--text-main)' }}>
                                                        {n.titulo}
                                                    </p>
                                                    {n.mensaje && (
                                                        <p className="text-xs mt-0.5 leading-snug line-clamp-2"
                                                            style={{ color: 'var(--text-muted)' }}>
                                                            {n.mensaje}
                                                        </p>
                                                    )}
                                                    <p className="text-[10px] mt-1" style={{ color: 'var(--text-muted)' }}>
                                                        {formatFechaNotif(n.created_at)}
                                                    </p>
                                                </div>
                                            )}

                                            {/* Acción marcar leída */}
                                            {!n.leida && (
                                                <button
                                                    onClick={() => marcarLeida(n.id)}
                                                    className="shrink-0 p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                                                    title="Marcar como leída"
                                                    style={{ color: 'var(--text-muted)' }}
                                                >
                                                    <Check className="w-3.5 h-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    ))
                                )}
                            </div>
                        </div>
                    </>
                )}
            </div>

            {/* Toggle tema */}
            <button
                onClick={toggleTheme}
                className={`${conColor ? 'w-11 h-11 rounded-xl flex items-center justify-center' : 'p-2 rounded-lg'} ${hoverBoton} transition-colors`}
                style={{ color: txtMuted }}
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
                    className={`flex items-center gap-2 p-1 rounded-lg ${hoverBoton} transition-colors`}
                >
                    <div className={cn('rounded-full flex items-center justify-center text-sm text-black', conColor ? 'w-[38px] h-[38px] font-extrabold' : 'w-8 h-8 font-semibold')}
                        style={{ background: conColor ? '#F5A30F' : '#F59E0B', color: conColor ? '#1A1204' : undefined }}>
                        {user?.nombre?.charAt(0).toUpperCase() ?? 'U'}
                    </div>
                    <div className="hidden sm:block text-left max-w-28">
                        <p className={cn('leading-none truncate', conColor ? 'text-[13px] font-bold' : 'text-xs font-medium')} style={{ color: txtMain }}>
                            {user?.nombre}
                        </p>
                        <p className={cn('leading-none mt-1 text-xs', !conColor && 'capitalize')} style={{ color: estilo ? estilo.etiqueta : txtMuted }}>
                            {user?.perfil}
                        </p>
                    </div>
                    <ChevronDown className="w-3 h-3 hidden sm:block" style={{ color: txtMuted }} />
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

function PillEmpresa({ nombre, estilo, onClick }: {
    nombre: string
    estilo: NonNullable<ReturnType<typeof estiloHeaderEmpresa>>
    onClick?: () => void
}) {
    const interactivo = !!onClick
    return (
        <button type="button" onClick={onClick} disabled={!interactivo}
            aria-label={`Empresa activa: ${nombre}`}
            className="flex items-center gap-2.5 h-11 pl-3 pr-3.5 rounded-xl border text-white disabled:cursor-default"
            style={{ borderColor: 'rgba(255,255,255,0.22)', background: 'rgba(255,255,255,0.08)' }}>
            <span className="w-2.5 h-2.5 rounded-full shrink-0"
                style={{ background: estilo.punto, boxShadow: `0 0 0 4px ${estilo.punto}47` }} />
            <span className="flex flex-col items-start leading-[1.15]">
                <span className="text-[10px] font-semibold uppercase tracking-[0.12em]" style={{ color: estilo.etiqueta }}>
                    Empresa activa
                </span>
                <span className="text-sm font-bold max-w-40 truncate">{nombre}</span>
            </span>
            {interactivo && <ChevronDown className="w-4 h-4" style={{ color: estilo.etiqueta }} />}
        </button>
    )
}
