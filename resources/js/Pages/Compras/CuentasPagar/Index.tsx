import { useState, useEffect } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Button } from '@/Components/ui/button'
import { cn } from '@/lib/utils'
import { DollarSign, AlertTriangle, Clock, Search, X, FileText, Download, CreditCard } from 'lucide-react'
import type { PageProps, Proveedor, BancoCaja } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CxPRow {
    id: number
    proveedor: string | null
    num_documento: string | null
    monto: number
    saldo: number
    fecha_emision: string | null
    fecha_vencimiento: string | null
    estado: 'pendiente' | 'parcial' | 'pagada'
    urgencia: 'vencida' | 'critica' | 'proxima' | 'normal'
    color_urgencia: string
    dias_vencimiento: number
}

interface Resumen {
    total_pendiente: number
    vencidas: number
    por_vencer: number
}

interface Filtros {
    estado?: string
    proveedor_id?: string
}

interface Props extends PageProps {
    cxp: CxPRow[]
    proveedores: Pick<Proveedor, 'id' | 'razon_social'>[]
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'tipo' | 'saldo_actual'>[]
    filtros: Filtros
    resumen: Resumen
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    success: (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error:   (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── Urgencia helpers ─────────────────────────────────────────────────────────

const URGENCIA_BORDER: Record<string, string> = {
    vencida: 'border-l-4 border-l-red-500',
    critica: 'border-l-4 border-l-orange-500',
    proxima: 'border-l-4 border-l-yellow-500',
    normal:  'border-l-4 border-l-green-500',
}

const URGENCIA_CHIP: Record<string, { bg: string; text: string }> = {
    vencida: { bg: 'bg-red-100 dark:bg-red-900/30',       text: 'text-red-700 dark:text-red-400' },
    critica: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
    proxima: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400' },
    normal:  { bg: 'bg-green-100 dark:bg-green-900/30',   text: 'text-green-700 dark:text-green-400' },
}

function DiasChip({ dias, urgencia }: { dias: number; urgencia: string }) {
    const c = URGENCIA_CHIP[urgencia] ?? URGENCIA_CHIP.normal
    const label = dias < 0 ? `${Math.abs(dias)}d VENCIDA` : dias === 0 ? 'HOY' : `En ${dias}d`
    return (
        <span className={cn('px-2 py-0.5 rounded-full text-xs font-semibold', c.bg, c.text)}>
            {label}
        </span>
    )
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: string | number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div className="min-w-0">
                <p className={cn('text-xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs leading-none" style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
        </div>
    )
}

// ─── Modal Pago ───────────────────────────────────────────────────────────────

interface FormPago {
    monto_pago: string
    banco_caja_id: string
    fecha_pago: string
    referencia: string
}

function ModalPago({ cxp, bancos, onClose }: {
    cxp: CxPRow
    bancos: Props['bancos']
    onClose: () => void
}) {
    const [form, setForm] = useState<FormPago>({
        monto_pago:    Number(cxp.saldo).toFixed(2),
        banco_caja_id: bancos[0]?.id?.toString() ?? '',
        fecha_pago:    new Date().toISOString().slice(0, 10),
        referencia:    '',
    })
    const [processing, setProcessing] = useState(false)

    const bancoBanco = bancos.find(b => b.id.toString() === form.banco_caja_id)
    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setProcessing(true)
        router.post(route('compras.cxp.pagar', cxp.id), form, {
            onSuccess: () => { notify.success('Pago registrado correctamente'); onClose() },
            onError:   (errs) => { notify.error('Error: ' + Object.values(errs).join(', ')); setProcessing(false) },
            onFinish:  () => setProcessing(false),
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md rounded-xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                {/* Header */}
                <div className="flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ borderBottom: '1px solid var(--border)' }}>
                    <div>
                        <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                            Registrar pago
                        </h2>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                            {cxp.proveedor ?? '—'} · {cxp.num_documento ?? '—'}
                        </p>
                    </div>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Info panel */}
                <div className="mx-6 mt-4 rounded-lg p-3 space-y-1.5"
                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.25)' }}>
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--text-muted)' }}>Monto original</span>
                        <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                            ${Number(cxp.monto).toFixed(2)}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm border-t pt-1.5" style={{ borderColor: 'rgba(245,158,11,0.3)' }}>
                        <span className="font-bold" style={{ color: 'var(--primary)' }}>Saldo pendiente</span>
                        <span className="font-bold" style={{ color: 'var(--primary)' }}>
                            ${Number(cxp.saldo).toFixed(2)}
                        </span>
                    </div>
                </div>

                <form onSubmit={submit} className="p-6 space-y-4">
                    <div className="space-y-1.5">
                        <Label>Monto a pagar <span className="text-red-400">*</span></Label>
                        <Input
                            type="number" step="0.01" min={0.01}
                            max={Number(cxp.saldo).toFixed(2)}
                            value={form.monto_pago}
                            onChange={e => setForm(f => ({ ...f, monto_pago: e.target.value }))}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Banco / Caja <span className="text-red-400">*</span></Label>
                        <select value={form.banco_caja_id}
                            onChange={e => setForm(f => ({ ...f, banco_caja_id: e.target.value }))}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inputStyle}>
                            {bancos.map(b => (
                                <option key={b.id} value={b.id}>
                                    {b.nombre} ({b.tipo}) — Saldo: ${Number(b.saldo_actual).toFixed(2)}
                                </option>
                            ))}
                        </select>
                        {bancoBanco && (
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Saldo disponible: <strong style={{ color: 'var(--primary)' }}>
                                    ${Number(bancoBanco.saldo_actual).toFixed(2)}
                                </strong>
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Fecha de pago <span className="text-red-400">*</span></Label>
                        <Input type="date" value={form.fecha_pago}
                            onChange={e => setForm(f => ({ ...f, fecha_pago: e.target.value }))} />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Referencia / N° transferencia</Label>
                        <Input value={form.referencia} placeholder="TRF-001, CHQ-001..."
                            onChange={e => setForm(f => ({ ...f, referencia: e.target.value }))} />
                    </div>

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <Button type="submit" disabled={processing}>
                            <CreditCard className="w-4 h-4" /> Registrar pago
                        </Button>
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function CuentasPagarIndex() {
    const { cxp, proveedores, bancos, filtros, resumen, flash } = usePage<Props>().props

    const [buscar,      setBuscar]      = useState('')
    const [estado,      setEstado]      = useState(filtros.estado ?? '')
    const [proveedorId, setProveedorId] = useState(filtros.proveedor_id ?? '')
    const [modalPago,   setModalPago]   = useState<CxPRow | null>(null)

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function aplicarFiltros() {
        router.get(route('compras.cxp.index'), {
            ...(estado      && { estado }),
            ...(proveedorId && { proveedor_id: proveedorId }),
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setEstado('')
        setProveedorId('')
        router.get(route('compras.cxp.index'), {}, { preserveState: false })
    }

    const hayFiltros = estado || proveedorId

    const filtradas = cxp.filter(c => {
        if (!buscar.trim()) return true
        const q = buscar.toLowerCase()
        return (
            (c.proveedor ?? '').toLowerCase().includes(q) ||
            (c.num_documento ?? '').toLowerCase().includes(q)
        )
    })

    const pdfUrl   = `${route('compras.cxp.pdf')}?estado=${estado}&proveedor_id=${proveedorId}`
    const excelUrl = `${route('compras.cxp.excel')}?estado=${estado}&proveedor_id=${proveedorId}`

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <AppLayout title="Cuentas por Pagar" suppressFlash>
            <Head title="Cuentas por Pagar" />

            {/* Header */}
            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <DollarSign size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Cuentas por Pagar
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Obligaciones pendientes con proveedores ordenadas por vencimiento
                        </p>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <div className="relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2"
                                style={{ color: 'var(--text-muted)' }} />
                        <input type="text" value={buscar}
                            onChange={e => setBuscar(e.target.value)}
                            placeholder="Buscar proveedor o documento…"
                            className="pl-9 pr-3 py-2 text-sm rounded-xl border focus:outline-none focus:ring-2 w-52 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)', color: 'var(--text-main)' }} />
                    </div>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inputStyle}>
                        <option value="">Todas (pendiente + parcial)</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="parcial">Parcial</option>
                        <option value="pagada">Pagada</option>
                    </select>

                    <select value={proveedorId} onChange={e => setProveedorId(e.target.value)}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inputStyle}>
                        <option value="">Todos los proveedores</option>
                        {proveedores.map(p => (
                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                        ))}
                    </select>

                    <button onClick={aplicarFiltros}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white whitespace-nowrap transition-all hover:opacity-90"
                        style={{ background: 'var(--primary)' }}>
                        Filtrar
                    </button>
                    {hayFiltros && (
                        <button onClick={limpiar}
                            className="px-3 py-2 rounded-xl text-sm font-medium border transition-all hover:opacity-80"
                            style={{ color: 'var(--text-muted)', borderColor: 'var(--border)' }}>
                            Limpiar
                        </button>
                    )}

                    <div className="flex-1" />

                    <a href={pdfUrl} target="_blank"
                       className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90"
                       style={{ background: '#ef4444' }}>
                        <FileText size={15} /> PDF
                    </a>
                    <a href={excelUrl}
                       className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90"
                       style={{ background: '#16a34a' }}>
                        <Download size={15} /> Excel
                    </a>
                </div>
            </div>

            {/* Stats resumen */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 px-6 py-4">
                <StatCard
                    label="Total pendiente"
                    value={`$${Number(resumen.total_pendiente).toLocaleString('es-EC', { minimumFractionDigits: 2 })}`}
                    icon={DollarSign}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
                <StatCard
                    label="Facturas vencidas"
                    value={resumen.vencidas}
                    icon={AlertTriangle}
                    cls="bg-red-500/15 text-red-600 dark:text-red-400"
                    valueCls="text-red-600 dark:text-red-400" />
                <StatCard
                    label="Vencen en 15 días"
                    value={resumen.por_vencer}
                    icon={Clock}
                    cls="bg-orange-500/15 text-orange-600 dark:text-orange-400"
                    valueCls="text-orange-600 dark:text-orange-400" />
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-3 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-3">Proveedor</span>
                        <span className="col-span-2">N° Documento</span>
                        <span className="col-span-1 text-right">Monto</span>
                        <span className="col-span-1 text-right">Saldo</span>
                        <span className="col-span-1 text-center">Emisión</span>
                        <span className="col-span-1 text-center">Vencimiento</span>
                        <span className="col-span-1 text-center">Días</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-center">Pagar</span>
                    </div>

                    {filtradas.length === 0 && (
                        <div className="py-20 text-center">
                            <FileText className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay cuentas por pagar
                            </p>
                        </div>
                    )}

                    {filtradas.map(c => (
                        <div key={c.id}
                            className={cn(
                                'group grid grid-cols-12 gap-3 px-4 py-3 border-b items-center text-sm transition-colors',
                                URGENCIA_BORDER[c.urgencia],
                            )}
                            style={{ borderBottomColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <div className="col-span-3 min-w-0">
                                <p className="font-medium truncate" style={{ color: 'var(--text-main)' }}>
                                    {c.proveedor ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-2">
                                <p className="font-mono text-xs" style={{ color: 'var(--text-main)' }}>
                                    {c.num_documento ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="font-medium text-xs" style={{ color: 'var(--text-main)' }}>
                                    ${Number(c.monto).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="font-bold text-xs" style={{ color: 'var(--primary)' }}>
                                    ${Number(c.saldo).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.fecha_emision ?? '—'}</p>
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                    {c.fecha_vencimiento ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                <DiasChip dias={c.dias_vencimiento} urgencia={c.urgencia} />
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {c.estado === 'pendiente' && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        Pendiente
                                    </span>
                                )}
                                {c.estado === 'parcial' && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        Parcial
                                    </span>
                                )}
                                {c.estado === 'pagada' && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        Pagada
                                    </span>
                                )}
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {c.estado !== 'pagada' && bancos.length > 0 && (
                                    <button
                                        onClick={() => setModalPago(c)}
                                        title="Registrar pago"
                                        className="p-1.5 rounded-lg hover:bg-green-500/20 text-green-600 dark:text-green-400 transition-colors">
                                        <CreditCard className="w-4 h-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modalPago && (
                <ModalPago
                    cxp={modalPago}
                    bancos={bancos}
                    onClose={() => setModalPago(null)}
                />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
