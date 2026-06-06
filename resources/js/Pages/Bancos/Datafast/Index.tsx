import { useState, useEffect, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { Plus, X, CreditCard, CheckCircle, Clock, DollarSign } from 'lucide-react'
import type { BancoCaja, DatafastLote, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface LoteRow {
    id: number; numero_lote: string; fecha: string; banco: string | null
    total_vouchers: number; estado: 'pendiente' | 'liquidado'
    liquidacion: null | {
        fecha_deposito: string; valor_bruto: number
        comision_datafast: number; valor_neto: number
    }
}

interface Props extends PageProps {
    lotes: LoteRow[]
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'tipo'>[]
    stats: { pendientes: number; liquidados: number; total_vouchers: number }
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: string | number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div><p className={cn('text-2xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{label}</p></div>
        </div>
    )
}

// ─── Modal Nuevo Lote ─────────────────────────────────────────────────────────

function LoteModal({ bancos, onClose }: { bancos: Props['bancos']; onClose: () => void }) {
    const terminales = bancos.filter(b => b.tipo === 'tarjeta')
    const { data, setData, post, processing, errors } = useForm({
        banco_caja_id: '',
        numero_lote: '',
        fecha: new Date().toISOString().split('T')[0],
        total_vouchers: '',
    })
    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('bancos.datafast.lote'), {
            onSuccess: () => { notify.ok('Lote registrado correctamente'); onClose() },
            onError: (errs) => notify.error(Object.values(errs).join(' | ')),
        })
    }
    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-sm rounded-xl shadow-2xl p-6"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <div className="flex items-center justify-between mb-5">
                    <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>Nuevo Lote Datafast</h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70"
                        style={{ color: 'var(--text-muted)' }}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Terminal <span className="text-red-400">*</span></Label>
                        <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inp}
                            disabled={terminales.length === 0}>
                            <option value="">— Seleccionar —</option>
                            {terminales.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                        </select>
                        {terminales.length === 0 && (
                            <p className="text-xs mt-1" style={{ color: '#ef4444' }}>
                                No hay terminales Datafast configurados. Crea uno en <strong>Bancos y Cajas</strong> con tipo "Tarjeta".
                            </p>
                        )}
                        {errors.banco_caja_id && <p className="text-red-400 text-xs">{errors.banco_caja_id}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label>N° Lote <span className="text-red-400">*</span></Label>
                        <Input value={data.numero_lote} onChange={e => setData('numero_lote', e.target.value)}
                            error={errors.numero_lote} placeholder="LOT-20260601" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha} onChange={e => setData('fecha', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Total vouchers <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min="0.01" value={data.total_vouchers}
                                onChange={e => setData('total_vouchers', e.target.value)}
                                error={errors.total_vouchers} placeholder="0.00" />
                        </div>
                    </div>
                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50"
                            style={{ background: 'var(--primary)' }}>
                            <Plus className="w-4 h-4" /> Registrar Lote
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

// ─── Modal Liquidar ───────────────────────────────────────────────────────────

function LiquidarModal({ lote, bancos, onClose }: { lote: LoteRow; bancos: Props['bancos']; onClose: () => void }) {
    const bancosDestino = bancos.filter(b => b.tipo === 'banco')
    const { data, setData, patch, processing, errors } = useForm({
        fecha_deposito:    new Date().toISOString().split('T')[0],
        valor_bruto:       String(lote.total_vouchers),
        comision_datafast: '',
        retencion_iva:     '',
        retencion_ir:      '',
        banco_destino_id:  '',
    })

    const valorNeto = useMemo(() =>
        Math.max(0,
            Number(data.valor_bruto || 0) -
            Number(data.comision_datafast || 0) -
            Number(data.retencion_iva || 0) -
            Number(data.retencion_ir || 0)
        ),
    [data.valor_bruto, data.comision_datafast, data.retencion_iva, data.retencion_ir])

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(route('bancos.datafast.liquidar', lote.id), {
            onSuccess: () => { notify.ok(`Lote ${lote.numero_lote} liquidado. Valor neto: ${fmt(valorNeto)}`); onClose() },
            onError: (errs) => notify.error(Object.values(errs).join(' | ')),
        })
    }

    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <div className="sticky top-0 z-10 flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                    <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                        Liquidar Lote {lote.numero_lote}
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70"
                        style={{ color: 'var(--text-muted)' }}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit} className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha depósito <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha_deposito} onChange={e => setData('fecha_deposito', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Valor bruto <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min="0" value={data.valor_bruto}
                                onChange={e => setData('valor_bruto', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="space-y-1.5">
                            <Label>Comisión Datafast <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min="0" value={data.comision_datafast}
                                onChange={e => setData('comision_datafast', e.target.value)} placeholder="0.00" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Ret. IVA</Label>
                            <Input type="number" step="0.01" min="0" value={data.retencion_iva}
                                onChange={e => setData('retencion_iva', e.target.value)} placeholder="0.00" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Ret. IR</Label>
                            <Input type="number" step="0.01" min="0" value={data.retencion_ir}
                                onChange={e => setData('retencion_ir', e.target.value)} placeholder="0.00" />
                        </div>
                    </div>

                    {/* Valor neto en tiempo real */}
                    <div className="rounded-xl p-4 flex items-center justify-between"
                        style={{ background: 'rgba(16,185,129,0.08)', border: '1px solid rgba(16,185,129,0.25)' }}>
                        <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>Valor neto a depositar</span>
                        <span className="text-2xl font-bold text-green-600 dark:text-green-400">{fmt(valorNeto)}</span>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Banco destino <span className="text-red-400">*</span></Label>
                        <select value={data.banco_destino_id} onChange={e => setData('banco_destino_id', e.target.value)}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inp}>
                            <option value="">— Seleccionar banco —</option>
                            {bancosDestino.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                        </select>
                        {errors.banco_destino_id && <p className="text-red-400 text-xs">{errors.banco_destino_id}</p>}
                    </div>

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50"
                            style={{ background: 'var(--primary)' }}>
                            <CheckCircle className="w-4 h-4" /> Liquidar
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

export default function DatafastIndex() {
    const { lotes, bancos, stats, flash } = usePage<Props>().props
    const [showLote, setShowLote] = useState(false)
    const [liquidarLote, setLiquidarLote] = useState<LoteRow | null>(null)

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    return (
        <AppLayout title="Datafast" suppressFlash>
            <Head title="Datafast" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl" style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <CreditCard size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>Datafast</h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Lotes de vouchers y liquidaciones</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <button onClick={() => setShowLote(true)}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                        style={{ background: 'var(--primary)' }}>
                        <Plus size={15} /> Nuevo Lote
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 px-6 py-4">
                <StatCard label="Pendientes" value={stats.pendientes} icon={Clock}
                    cls="bg-orange-500/15 text-orange-600 dark:text-orange-400"
                    valueCls="text-orange-600 dark:text-orange-400" />
                <StatCard label="Liquidados" value={stats.liquidados} icon={CheckCircle}
                    cls="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueCls="text-green-600 dark:text-green-400" />
                <StatCard label="Total Vouchers" value={fmt(stats.total_vouchers)} icon={DollarSign}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-2">N° Lote</span>
                        <span className="col-span-1">Fecha</span>
                        <span className="col-span-2">Terminal</span>
                        <span className="col-span-2 text-right">Total Vouchers</span>
                        <span className="col-span-2">Liquidación</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-2 text-right">Acción</span>
                    </div>

                    {lotes.length === 0 && (
                        <div className="py-20 text-center">
                            <CreditCard className="w-12 h-12 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No hay lotes registrados</p>
                        </div>
                    )}

                    {lotes.map(l => (
                        <div key={l.id}
                            className="group grid grid-cols-12 gap-2 px-4 py-3 border-b items-center text-sm transition-colors"
                            style={{ borderColor: 'var(--border)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                            <div className="col-span-2">
                                <p className="font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>{l.numero_lote}</p>
                            </div>
                            <div className="col-span-1">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{l.fecha}</p>
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-main)' }}>{l.banco ?? '—'}</p>
                            </div>
                            <div className="col-span-2 text-right">
                                <p className="text-sm font-bold" style={{ color: 'var(--text-main)' }}>{fmt(l.total_vouchers)}</p>
                            </div>
                            <div className="col-span-2 min-w-0">
                                {l.liquidacion ? (
                                    <div>
                                        <p className="text-xs text-green-600 dark:text-green-400 font-medium">{fmt(l.liquidacion.valor_neto)}</p>
                                        <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>
                                            Dep. {l.liquidacion.fecha_deposito} · Com. {fmt(l.liquidacion.comision_datafast)}
                                        </p>
                                    </div>
                                ) : (
                                    <span className="text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                )}
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {l.estado === 'pendiente'
                                    ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400">Pendiente</span>
                                    : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Liquidado</span>
                                }
                            </div>
                            <div className="col-span-2 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                {l.estado === 'pendiente' && (
                                    <button onClick={() => setLiquidarLote(l)}
                                        className="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium text-white"
                                        style={{ background: 'var(--primary)' }}>
                                        <CheckCircle className="w-3.5 h-3.5" /> Liquidar
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {showLote && <LoteModal bancos={bancos} onClose={() => setShowLote(false)} />}
            {liquidarLote && <LiquidarModal lote={liquidarLote} bancos={bancos} onClose={() => setLiquidarLote(null)} />}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
