import { useEffect } from 'react'
import { router, usePage, Head, Link } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { cn } from '@/lib/utils'
import { ChevronLeft, CheckCircle, AlertTriangle, GitMerge } from 'lucide-react'
import type { ConciliacionBancaria, PartidaTransito, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Props extends PageProps {
    conciliacion: ConciliacionBancaria & {
        banco_caja: { nombre: string; saldo_actual: number }
        partidas: (PartidaTransito & {
            movimiento?: { descripcion: string; monto: number; tipo: string }
        })[]
    }
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) => '$' + Number(n ?? 0).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── Página ───────────────────────────────────────────────────────────────────

export default function ConciliacionShow() {
    const { conciliacion, flash } = usePage<Props>().props
    const tieneDif = Math.abs(Number(conciliacion.diferencia)) > 0.01

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error as string)
    }, [flash?.success, flash?.error])

    function marcarConciliada() {
        router.patch(route('bancos.conciliaciones.conciliar', conciliacion.id), {}, {
            onSuccess: () => notify.ok('Conciliación marcada como conciliada'),
            onError: () => notify.error('Error al conciliar'),
        })
    }

    return (
        <AppLayout title="Detalle Conciliación" suppressFlash>
            <Head title={`Conciliación — ${conciliacion.banco_caja?.nombre}`} />

            {/* Header */}
            <div className="px-6 pt-6 mb-6">
                <Link href={route('bancos.conciliaciones.index')}
                    className="inline-flex items-center gap-1.5 text-sm mb-4 hover:opacity-70 transition-opacity"
                    style={{ color: 'var(--text-muted)' }}>
                    <ChevronLeft className="w-4 h-4" /> Volver a conciliaciones
                </Link>

                <div className="flex items-start justify-between flex-wrap gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl" style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                            <GitMerge size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                                {conciliacion.banco_caja?.nombre}
                            </h1>
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Corte al {(conciliacion as any).fecha_corte}
                            </p>
                        </div>
                    </div>

                    {conciliacion.estado !== 'conciliada' && (
                        <button onClick={marcarConciliada}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white"
                            style={{ background: 'var(--primary)' }}>
                            <CheckCircle className="w-4 h-4" /> Marcar como Conciliada
                        </button>
                    )}
                </div>
            </div>

            {/* Cards resumen */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 px-6 mb-6">
                <div className="rounded-xl border p-4"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Saldo Banco (estado de cuenta)</p>
                    <p className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>{fmt(conciliacion.saldo_banco)}</p>
                </div>
                <div className="rounded-xl border p-4"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Saldo Sistema</p>
                    <p className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>{fmt(conciliacion.saldo_sistema)}</p>
                </div>
                <div className={cn(
                    'rounded-xl border p-4',
                    tieneDif
                        ? 'border-red-300 dark:border-red-700'
                        : 'border-green-300 dark:border-green-700'
                )} style={{
                    background: tieneDif ? 'rgba(239,68,68,0.08)' : 'rgba(16,185,129,0.08)',
                    borderColor: tieneDif ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)',
                }}>
                    <div className="flex items-center gap-2 mb-1">
                        {tieneDif
                            ? <AlertTriangle className="w-4 h-4 text-red-500" />
                            : <CheckCircle className="w-4 h-4 text-green-500" />
                        }
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Diferencia</p>
                    </div>
                    <p className={cn('text-2xl font-bold', tieneDif ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                        {fmt(conciliacion.diferencia)}
                    </p>
                    {!tieneDif && <p className="text-xs text-green-600 dark:text-green-400 mt-1">Cuentas cuadradas</p>}
                </div>
            </div>

            {/* Estado */}
            <div className="px-6 mb-6 flex items-center gap-3">
                {conciliacion.estado === 'conciliada'
                    ? <span className="px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Conciliada</span>
                    : <span className="px-3 py-1.5 rounded-full text-sm font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-600">Pendiente de conciliación</span>
                }
                {conciliacion.descripcion && (
                    <p className="text-sm" style={{ color: 'var(--text-muted)' }}>{conciliacion.descripcion}</p>
                )}
            </div>

            {/* Partidas en tránsito */}
            <div className="px-6 pb-8">
                <h2 className="text-sm font-semibold mb-3 uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                    Partidas en tránsito ({conciliacion.partidas?.length ?? 0})
                </h2>

                {(!conciliacion.partidas || conciliacion.partidas.length === 0) ? (
                    <div className="rounded-xl border p-8 text-center"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <CheckCircle className="w-10 h-10 text-green-500 opacity-50 mx-auto mb-3" />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Sin partidas en tránsito — todos los movimientos están cuadrados
                        </p>
                    </div>
                ) : (
                    <div className="border rounded-xl overflow-hidden"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                            style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                            <span className="col-span-1">Tipo</span>
                            <span className="col-span-2">Fecha</span>
                            <span className="col-span-6">Descripción</span>
                            <span className="col-span-2 text-right">Monto</span>
                            <span className="col-span-1 text-center">Cuadrado</span>
                        </div>

                        {conciliacion.partidas.map(p => (
                            <div key={p.id}
                                className="grid grid-cols-12 gap-2 px-4 py-3 border-b items-center text-sm"
                                style={{ borderColor: 'var(--border)' }}>
                                <div className="col-span-1">
                                    <span className={cn(
                                        'px-1.5 py-0.5 rounded text-[10px] font-medium',
                                        p.tipo === 'sistema'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                    )}>
                                        {p.tipo === 'sistema' ? 'Sistema' : 'Banco'}
                                    </span>
                                </div>
                                <div className="col-span-2">
                                    <p className="text-xs font-mono" style={{ color: 'var(--text-muted)' }}>{p.fecha ?? '—'}</p>
                                </div>
                                <div className="col-span-6 min-w-0">
                                    <p className="text-xs truncate" style={{ color: 'var(--text-main)' }}>{p.descripcion ?? '—'}</p>
                                </div>
                                <div className="col-span-2 text-right">
                                    <p className="text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                        {p.monto != null ? fmt(p.monto) : '—'}
                                    </p>
                                </div>
                                <div className="col-span-1 flex justify-center">
                                    {p.conciliada
                                        ? <CheckCircle className="w-4 h-4 text-green-500" />
                                        : <AlertTriangle className="w-4 h-4 text-orange-500" />
                                    }
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
