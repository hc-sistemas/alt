import { useEffect, useState } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { cn } from '@/lib/utils'
import { LogIn, LogOut, Clock, Calendar, AlertCircle, CheckCircle2, Users } from 'lucide-react'
import type { Colaborador, Asistencia, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ResumenItem {
    id: number
    colaborador: { id: number; apellidos: string; nombres: string }
    fecha: string
    hora_entrada: string | null
    hora_salida: string | null
    minutos_atraso: number
    horas_extra: number | null
}

interface Props extends PageProps {
    colaborador: Colaborador | null
    asistenciaHoy: Asistencia | null
    historial: Asistencia[]
    resumenDia: ResumenItem[] | null
    server_time: string
    es_admin: boolean
    flash: { success?: string; error?: string }
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (m: string) => toast.success(m, { style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error: (m: string) => toast.error(m,   { autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

// BUG 2: forzar timezone Ecuador independientemente del TZ del navegador
function formatHora(iso: string | null): string {
    if (!iso) return '—'
    return new Date(iso).toLocaleTimeString('es-EC', {
        hour: '2-digit', minute: '2-digit', timeZone: 'America/Guayaquil',
    })
}

// BUG 3: fecha viene como "YYYY-MM-DD" (sin cast Eloquent); se toma solo los primeros 10 chars
// para ser robusto ante cualquier serialización de Eloquent
function formatFecha(fecha: string): string {
    const d = fecha.substring(0, 10)
    return new Date(d + 'T12:00:00Z').toLocaleDateString('es-EC', {
        weekday: 'short', day: '2-digit', month: 'short', timeZone: 'America/Guayaquil',
    })
}

// ─── Reloj digital ───────────────────────────────────────────────────────────

function RelojDigital({ serverTime }: { serverTime: string }) {
    const offset = Date.now() - new Date(serverTime).getTime()
    const [ahora, setAhora] = useState(() => new Date(Date.now() - offset))

    useEffect(() => {
        const id = setInterval(() => {
            setAhora(new Date(Date.now() - offset))
        }, 1000)
        return () => clearInterval(id)
    }, [offset])

    const h = String(ahora.getHours()).padStart(2, '0')
    const m = String(ahora.getMinutes()).padStart(2, '0')
    const s = String(ahora.getSeconds()).padStart(2, '0')
    const fecha = ahora.toLocaleDateString('es-EC', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })

    return (
        <div className="text-center">
            <p className="text-6xl font-mono font-bold tabular-nums tracking-tight" style={{ color: 'var(--primary)' }}>
                {h}:{m}<span className="text-4xl opacity-70">:{s}</span>
            </p>
            <p className="mt-1 text-sm capitalize" style={{ color: 'var(--text-muted)' }}>{fecha}</p>
        </div>
    )
}

// ─── Badge estado asistencia ──────────────────────────────────────────────────

function EstadoAsistencia({ a }: { a: Asistencia | null }) {
    if (!a) return (
        <div className="flex items-center gap-2 text-sm" style={{ color: 'var(--text-muted)' }}>
            <Clock className="w-4 h-4" />
            Sin registro hoy
        </div>
    )
    if (a.hora_entrada && !a.hora_salida) return (
        <div className="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400 font-medium">
            <CheckCircle2 className="w-4 h-4" />
            Entrada: {formatHora(a.hora_entrada as unknown as string)}
            {(a.minutos_atraso ?? 0) > 0 && (
                <span className="ml-1 text-amber-600 dark:text-amber-400 text-xs font-normal">
                    ({a.minutos_atraso} min atraso)
                </span>
            )}
        </div>
    )
    if (a.hora_entrada && a.hora_salida) return (
        <div className="flex items-center gap-2 text-sm font-medium" style={{ color: 'var(--text-muted)' }}>
            <CheckCircle2 className="w-4 h-4 text-blue-500" />
            {formatHora(a.hora_entrada as unknown as string)} → {formatHora(a.hora_salida as unknown as string)}
            {(a.horas_extra ?? 0) > 0 && (
                <span className="text-purple-500 text-xs font-normal ml-1">+{a.horas_extra}h extra</span>
            )}
        </div>
    )
    return null
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function AsistenciaIndex() {
    const { colaborador, asistenciaHoy, historial, resumenDia, server_time, es_admin, flash } =
        usePage<Props>().props

    const [procesando, setProcesando] = useState(false)

    // BUG 1: mover fuera del render body para que se dispare solo cuando cambia flash,
    // no en cada re-render (ej. setProcesando(false) causaba un segundo toast)
    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
    }, [flash?.success])

    useEffect(() => {
        if (flash?.error) notify.error(flash.error)
    }, [flash?.error])

    const tieneEntrada   = !!(asistenciaHoy?.hora_entrada)
    const tieneSalida    = !!(asistenciaHoy?.hora_salida)
    const puedeEntrada   = !!colaborador && !tieneEntrada
    const puedeSalida    = !!colaborador && tieneEntrada && !tieneSalida

    function registrar(tipo: 'entrada' | 'salida') {
        setProcesando(true)
        router.post(
            tipo === 'entrada'
                ? route('rrhh.asistencia.entrada')
                : route('rrhh.asistencia.salida'),
            {},
            {
                onFinish: () => setProcesando(false),
                onError: (e) => notify.error(Object.values(e)[0] ?? 'Error al registrar.'),
            }
        )
    }

    return (
        <AppLayout title="Asistencia" suppressFlash>
            <Head title="Asistencia — RRHH" />
            <ToastContainer position="top-right" />

            <PageHeader
                title="Asistencia"
                description="Timbre digital — hora tomada del servidor"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Asistencia' }]}
            />

            <div className="p-6 space-y-6 max-w-4xl">

                {/* ── Timbre ── */}
                <div className="rounded-2xl border p-8 text-center space-y-6"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    <RelojDigital serverTime={server_time} />

                    {!colaborador ? (
                        <div className="flex items-center justify-center gap-2 rounded-xl p-4 text-sm"
                            style={{ background: 'var(--bg-main)', color: 'var(--text-muted)' }}>
                            <AlertCircle className="w-4 h-4 text-amber-500" />
                            Tu usuario no está vinculado a ningún colaborador activo.
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {/* Nombre del colaborador */}
                            <p className="text-lg font-semibold" style={{ color: 'var(--text-main)' }}>
                                {colaborador.apellidos} {colaborador.nombres}
                            </p>

                            {/* Estado de hoy */}
                            <div className="flex justify-center">
                                <EstadoAsistencia a={asistenciaHoy} />
                            </div>

                            {/* Botón timbre */}
                            {!tieneSalida && (
                                <div className="flex justify-center">
                                    {puedeEntrada && (
                                        <button
                                            onClick={() => registrar('entrada')}
                                            disabled={procesando}
                                            className={cn(
                                                'flex items-center gap-3 px-8 py-4 rounded-2xl text-base font-bold transition-all shadow-lg',
                                                procesando
                                                    ? 'opacity-50 cursor-not-allowed'
                                                    : 'hover:scale-105 active:scale-95'
                                            )}
                                            style={{
                                                background: 'linear-gradient(135deg, #10b981, #059669)',
                                                color: '#fff',
                                            }}
                                        >
                                            <LogIn className="w-6 h-6" />
                                            {procesando ? 'Registrando…' : 'Registrar Entrada'}
                                        </button>
                                    )}

                                    {puedeSalida && (
                                        <button
                                            onClick={() => registrar('salida')}
                                            disabled={procesando}
                                            className={cn(
                                                'flex items-center gap-3 px-8 py-4 rounded-2xl text-base font-bold transition-all shadow-lg',
                                                procesando
                                                    ? 'opacity-50 cursor-not-allowed'
                                                    : 'hover:scale-105 active:scale-95'
                                            )}
                                            style={{
                                                background: 'linear-gradient(135deg, #3b82f6, #2563eb)',
                                                color: '#fff',
                                            }}
                                        >
                                            <LogOut className="w-6 h-6" />
                                            {procesando ? 'Registrando…' : 'Registrar Salida'}
                                        </button>
                                    )}

                                    {tieneSalida && (
                                        <p className="text-sm font-medium text-blue-500">
                                            Jornada completada hoy.
                                        </p>
                                    )}
                                </div>
                            )}

                            {tieneSalida && (
                                <p className="text-sm font-medium text-blue-500 text-center">
                                    Jornada completada. ¡Hasta mañana!
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* ── Historial del mes (colaborador) ── */}
                {historial && historial.length > 0 && (
                    <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        <div className="px-4 py-3 flex items-center gap-2 border-b"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <Calendar className="w-4 h-4" style={{ color: 'var(--primary)' }} />
                            <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                Historial del mes
                            </span>
                        </div>
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['Fecha', 'Entrada', 'Salida', 'Atraso', 'H. Extra'].map(h => (
                                        <th key={h} className="text-left px-3 py-2 font-medium uppercase tracking-wider"
                                            style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {historial.map(a => (
                                    <tr key={a.id} className="border-t hover:bg-black/5 transition-colors"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-3 py-2" style={{ color: 'var(--text-main)' }}>
                                            {formatFecha(a.fecha)}
                                        </td>
                                        <td className="px-3 py-2 font-mono" style={{ color: 'var(--text-main)' }}>
                                            {formatHora(a.hora_entrada as unknown as string)}
                                        </td>
                                        <td className="px-3 py-2 font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {formatHora(a.hora_salida as unknown as string)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {(a.minutos_atraso ?? 0) > 0 ? (
                                                <span className="text-amber-600 dark:text-amber-400 font-medium">
                                                    {a.minutos_atraso} min
                                                </span>
                                            ) : (
                                                <span className="text-emerald-500">—</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {(a.horas_extra ?? 0) > 0 ? (
                                                <span className={cn(
                                                    'font-medium text-xs',
                                                    a.tipo_extra === 'extraordinaria' ? 'text-purple-500' : 'text-blue-500'
                                                )}>
                                                    {a.horas_extra}h {a.tipo_extra === 'extraordinaria' ? 'ext' : 'sup'}
                                                </span>
                                            ) : <span style={{ color: 'var(--text-muted)' }}>—</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* ── Resumen del día (solo admin) ── */}
                {es_admin && resumenDia && (
                    <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        <div className="px-4 py-3 flex items-center gap-2 border-b"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <Users className="w-4 h-4" style={{ color: 'var(--primary)' }} />
                            <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                Resumen del día — {resumenDia.length} registro{resumenDia.length !== 1 ? 's' : ''}
                            </span>
                        </div>
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['Colaborador', 'Entrada', 'Salida', 'Atraso', 'H. Extra'].map(h => (
                                        <th key={h} className="text-left px-3 py-2 font-medium uppercase tracking-wider"
                                            style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {resumenDia.map(a => (
                                    <tr key={a.id} className="border-t hover:bg-black/5 transition-colors"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-3 py-2 font-medium" style={{ color: 'var(--text-main)' }}>
                                            {a.colaborador.apellidos} {a.colaborador.nombres}
                                        </td>
                                        <td className="px-3 py-2 font-mono" style={{ color: 'var(--text-main)' }}>
                                            {formatHora(a.hora_entrada)}
                                        </td>
                                        <td className="px-3 py-2 font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {formatHora(a.hora_salida)}
                                        </td>
                                        <td className="px-3 py-2">
                                            {(a.minutos_atraso ?? 0) > 0 ? (
                                                <span className="text-amber-600 dark:text-amber-400 font-medium">
                                                    {a.minutos_atraso} min
                                                </span>
                                            ) : <span className="text-emerald-500">—</span>}
                                        </td>
                                        <td className="px-3 py-2">
                                            {(a.horas_extra ?? 0) > 0 ? (
                                                <span className="text-purple-500 font-medium">{a.horas_extra}h</span>
                                            ) : <span style={{ color: 'var(--text-muted)' }}>—</span>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
