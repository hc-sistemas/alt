import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head, Link } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { Plus, X, GitMerge, AlertTriangle, CheckCircle, Eye } from 'lucide-react'
import type { BancoCaja, ConciliacionBancaria, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ConcRow {
    id: number; banco: string | null; fecha_corte: string
    saldo_banco: number; saldo_sistema: number; diferencia: number
    estado: string; tiene_dif: boolean; created_at: string
}

interface Props extends PageProps {
    conciliaciones: ConcRow[]
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'saldo_actual'>[]
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── Modal Nueva Conciliación ─────────────────────────────────────────────────

function ConciliacionModal({ bancos, onClose }: { bancos: Props['bancos']; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        banco_caja_id: '',
        fecha_corte:   new Date().toISOString().split('T')[0],
        saldo_banco:   '',
        descripcion:   '',
        archivo:       null as File | null,
    })

    const bancoSel = bancos.find(b => b.id === Number(data.banco_caja_id))

    function submit(e: React.FormEvent) {
        e.preventDefault()
        const fd = new FormData()
        fd.append('banco_caja_id', data.banco_caja_id)
        fd.append('fecha_corte',   data.fecha_corte)
        fd.append('saldo_banco',   data.saldo_banco)
        fd.append('descripcion',   data.descripcion)
        if (data.archivo) fd.append('archivo', data.archivo)

        router.post(route('bancos.conciliaciones.store'), fd as any, {
            onSuccess: () => { notify.ok('Conciliación creada'); onClose() },
            onError: (errs) => notify.error(Object.values(errs).join(' | ')),
        })
    }

    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <div className="flex items-center justify-between mb-5">
                    <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>Nueva Conciliación</h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70"
                        style={{ color: 'var(--text-muted)' }}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Banco <span className="text-red-400">*</span></Label>
                        <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inp}>
                            <option value="">— Seleccionar —</option>
                            {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre} (saldo: {fmt(b.saldo_actual)})</option>)}
                        </select>
                        {errors.banco_caja_id && <p className="text-red-400 text-xs">{errors.banco_caja_id}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha de corte <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha_corte} onChange={e => setData('fecha_corte', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Saldo banco ($) <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" value={data.saldo_banco}
                                onChange={e => setData('saldo_banco', e.target.value)}
                                error={errors.saldo_banco} placeholder="0.00" />
                        </div>
                    </div>

                    {bancoSel && (
                        <div className="rounded-lg p-3 text-sm"
                            style={{ background: 'rgba(245,158,11,0.05)', border: '1px solid var(--border)' }}>
                            <span style={{ color: 'var(--text-muted)' }}>Saldo sistema: </span>
                            <span className="font-bold" style={{ color: 'var(--text-main)' }}>{fmt(bancoSel.saldo_actual)}</span>
                            {data.saldo_banco && (
                                <>
                                    <span className="mx-2" style={{ color: 'var(--text-muted)' }}>|</span>
                                    <span style={{ color: 'var(--text-muted)' }}>Diferencia estimada: </span>
                                    <span className={cn('font-bold', Math.abs(Number(data.saldo_banco) - bancoSel.saldo_actual) > 0.01 ? 'text-red-600' : 'text-green-600')}>
                                        {fmt(Number(data.saldo_banco) - bancoSel.saldo_actual)}
                                    </span>
                                </>
                            )}
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Descripción</Label>
                        <Input value={data.descripcion} onChange={e => setData('descripcion', e.target.value)}
                            placeholder="Notas del estado de cuenta…" />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Archivo estado de cuenta (CSV/Excel)</Label>
                        <input type="file" accept=".csv,.txt,.xlsx,.xls"
                            onChange={e => setData('archivo', e.target.files?.[0] ?? null)}
                            className="w-full text-sm"
                            style={{ color: 'var(--text-muted)' }} />
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Opcional · máx. 5 MB</p>
                    </div>

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50"
                            style={{ background: 'var(--primary)' }}>
                            <Plus className="w-4 h-4" /> Crear Conciliación
                        </button>
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function ConciliacionesIndex() {
    const { conciliaciones, bancos, flash } = usePage<Props>().props
    const [showModal, setShowModal] = useState(false)

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    return (
        <AppLayout title="Conciliación Bancaria" suppressFlash>
            <Head title="Conciliación Bancaria" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl" style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <GitMerge size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>Conciliación Bancaria</h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Cuadre de saldos banco vs sistema</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <button onClick={() => setShowModal(true)}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                        style={{ background: 'var(--primary)' }}>
                        <Plus size={15} /> Nueva Conciliación
                    </button>
                </div>
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-2">Banco</span>
                        <span className="col-span-1">Corte</span>
                        <span className="col-span-2 text-right">Saldo Banco</span>
                        <span className="col-span-2 text-right">Saldo Sistema</span>
                        <span className="col-span-2 text-right">Diferencia</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-2 text-right">Acción</span>
                    </div>

                    {conciliaciones.length === 0 && (
                        <div className="py-20 text-center">
                            <GitMerge className="w-12 h-12 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No hay conciliaciones registradas</p>
                        </div>
                    )}

                    {conciliaciones.map(c => (
                        <div key={c.id}
                            className="group grid grid-cols-12 gap-2 px-4 py-3 border-b items-center text-sm transition-colors"
                            style={{ borderColor: 'var(--border)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs font-medium truncate" style={{ color: 'var(--text-main)' }}>{c.banco ?? '—'}</p>
                                <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>Creado {c.created_at}</p>
                            </div>
                            <div className="col-span-1">
                                <p className="text-xs font-mono" style={{ color: 'var(--text-main)' }}>{c.fecha_corte}</p>
                            </div>
                            <div className="col-span-2 text-right">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{fmt(c.saldo_banco)}</p>
                            </div>
                            <div className="col-span-2 text-right">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{fmt(c.saldo_sistema)}</p>
                            </div>
                            <div className="col-span-2 text-right flex items-center justify-end gap-1">
                                {c.tiene_dif && <AlertTriangle className="w-3.5 h-3.5 text-red-500 shrink-0" />}
                                <span className={cn('text-sm font-bold', c.tiene_dif ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                                    {fmt(c.diferencia)}
                                </span>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {c.estado === 'conciliada'
                                    ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Conciliada</span>
                                    : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-600">Pendiente</span>
                                }
                            </div>
                            <div className="col-span-2 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                <Link href={route('bancos.conciliaciones.show', c.id)}
                                    className="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium border transition-colors"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.1)')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                    <Eye className="w-3.5 h-3.5" /> Ver detalle
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {showModal && <ConciliacionModal bancos={bancos} onClose={() => setShowModal(false)} />}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
