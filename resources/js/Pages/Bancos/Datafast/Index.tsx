import { useState, useEffect, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { Plus, X, CreditCard, CheckCircle } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { BancoCaja, DatafastLote, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface LoteRow {
    id: number; numero_lote: string; fecha: string; banco: string | null
    banco_caja_id: number; total_vouchers: number; estado: 'pendiente' | 'liquidado'
    liquidacion: null | {
        fecha_deposito: string; valor_bruto: number
        comision_datafast: number; valor_neto: number
    }
}

interface Filtros {
    banco_caja_id?: string
    estado?:        string
    fecha_desde?:   string
    fecha_hasta?:   string
    buscar?:        string
}

interface Props extends PageProps {
    lotes:   LoteRow[]
    bancos:  Pick<BancoCaja, 'id' | 'nombre' | 'tipo'>[]
    filtros: Filtros
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })

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
    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-sm" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Nuevo Lote Datafast</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <div className="space-y-1.5">
                        <Label>Terminal <span className="text-red-400">*</span></Label>
                        <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                            className="input-field select-field"
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
                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing} className="btn-primary">
                        <Plus className="w-4 h-4" /> Registrar Lote
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
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

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Liquidar Lote {lote.numero_lote}</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
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
                            className="input-field select-field">
                            <option value="">— Seleccionar banco —</option>
                            {bancosDestino.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                        </select>
                        {errors.banco_destino_id && <p className="text-red-400 text-xs">{errors.banco_destino_id}</p>}
                    </div>

                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing} className="btn-primary">
                        <CheckCircle className="w-4 h-4" /> Liquidar
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function DatafastIndex() {
    const { lotes, bancos, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('bancos')
    const [showLote, setShowLote] = useState(false)
    const [liquidarLote, setLiquidarLote] = useState<LoteRow | null>(null)
    const [bancoId,    setBancoId]    = useState(filtros.banco_caja_id ?? '')
    const [estado,     setEstado]     = useState(filtros.estado        ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde   ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta   ?? '')
    const [buscar,     setBuscar]     = useState(filtros.buscar        ?? '')

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    function aplicarFiltros() {
        router.get(route('bancos.datafast.index'), {
            banco_caja_id: bancoId, estado, fecha_desde: fechaDesde, fecha_hasta: fechaHasta, buscar,
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setBancoId(''); setEstado(''); setFechaDesde(''); setFechaHasta(''); setBuscar('')
        router.get(route('bancos.datafast.index'), {}, { preserveState: false })
    }

    const hayFiltros = !!(bancoId || estado || fechaDesde || fechaHasta || buscar)

    const lotesFiltrados = useMemo(() => {
        if (!buscar.trim()) return lotes
        const q = buscar.toLowerCase()
        return lotes.filter(l => l.numero_lote.toLowerCase().includes(q))
    }, [lotes, buscar])

    return (
        <AppLayout title="Datafast" suppressFlash>
            <Head title="Datafast" />

            <PageHeader
                title="Datafast"
                description="Lotes de vouchers y liquidaciones"
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Datafast' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setShowLote(true)}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-black whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5 shrink-0"
                            style={{ background: 'var(--primary)' }}>
                            <Plus size={15} /> Nuevo Lote
                        </button>
                    ) : undefined
                }
            />

            <div className="px-6 pt-6 mb-2">
                <FilterToolbar
                    search={{
                        value: buscar,
                        onChange: setBuscar,
                        onSearch: aplicarFiltros,
                        placeholder: 'N° lote...',
                    }}
                >
                    <select value={bancoId} onChange={e => setBancoId(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los terminales</option>
                        {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="liquidado">Liquidado</option>
                    </select>

                    <input type="date" value={fechaDesde} onChange={e => setFechaDesde(e.target.value)}
                        className="input-field shrink-0 w-36"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }} />
                    <input type="date" value={fechaHasta} onChange={e => setFechaHasta(e.target.value)}
                        className="input-field shrink-0 w-36"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }} />

                    {hayFiltros && (
                        <button type="button" onClick={limpiar} className="text-sm underline shrink-0" style={{ color: 'var(--text-muted)' }}>
                            Limpiar
                        </button>
                    )}
                </FilterToolbar>
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

                    {lotesFiltrados.length === 0 && (
                        <div className="py-20 text-center">
                            <CreditCard className="w-12 h-12 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No hay lotes registrados</p>
                        </div>
                    )}

                    {lotesFiltrados.map(l => (
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
                                {l.estado === 'pendiente' && puede('editar') && (
                                    <button onClick={() => setLiquidarLote(l)}
                                        className="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium text-black"
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
