import { useState, useEffect, useMemo, useCallback } from 'react'
import { router, usePage, useForm, Head, Link } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Search, X, FileText, ChevronLeft, ChevronRight,
    Lock, Ban, Eye, ShoppingCart, CheckCircle, AlertCircle,
    DollarSign, Trash2, CreditCard,
} from 'lucide-react'
import type { Compra, Proveedor, CentroCosto, PlanCuenta, PageProps, PaginatedData } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CompraStats {
    total: number
    activas: number
    anuladas: number
    con_pago: number
}

interface Filtros {
    buscar?: string
    estado?: string
    fecha_desde?: string
    fecha_hasta?: string
}

interface Props extends PageProps {
    compras: PaginatedData<Compra>
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial' | 'identificacion' | 'tiene_credito' | 'dias_credito' | 'tipo'>[]
    centros: Pick<CentroCosto, 'id' | 'nombre' | 'codigo'>[]
    cuentas: Pick<PlanCuenta, 'id' | 'codigo' | 'nombre'>[]
    filtros: Filtros
    stats: CompraStats
}

interface DetalleItem {
    descripcion: string
    cantidad: number | string
    precio_unitario: number | string
    descuento: number | string
    descuento_pct: string
    porcentaje_iva: number | string
    cuenta_id: string | number
    es_activo_fijo: boolean
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── SweetAlert ───────────────────────────────────────────────────────────────

const SWAL_CSS = `
    .swal-pop { border-radius:20px!important; padding:28px!important; box-shadow:0 25px 60px rgba(0,0,0,.25)!important; max-width:460px!important }
    .swal-title { font-size:1.1rem!important; font-weight:700!important; color:#1f2937!important; margin-bottom:16px!important }
    .swal-confirm { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
    .swal-cancel  { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
`
function injectSwalCss() {
    if (document.getElementById('swal-cmp')) return
    const s = document.createElement('style'); s.id = 'swal-cmp'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const round = (n: number, d: number) => Math.round(n * 10 ** d) / 10 ** d

function calcDetalle(d: DetalleItem) {
    const cant = Number(d.cantidad) || 0
    const precio = Number(d.precio_unitario) || 0
    const desc = Number(d.descuento) || 0
    const porcIva = Number(d.porcentaje_iva) || 0
    const subtotal = Math.round((cant * precio - desc) * 10000) / 10000
    const iva = porcIva > 0 ? Math.round(subtotal * porcIva / 100 * 10000) / 10000 : 0
    return { subtotal, iva, total: subtotal + iva }
}

function calcTotales(detalles: DetalleItem[], gastoNoDeducible: boolean) {
    let sub0 = 0, subIva = 0, totalIva = 0
    for (const d of detalles) {
        const { subtotal, iva } = calcDetalle(d)
        if (Number(d.porcentaje_iva) > 0) subIva += subtotal
        else sub0 += subtotal
        totalIva += iva
    }
    if (gastoNoDeducible) totalIva = 0
    return {
        subtotal0: sub0,
        subtotalIva: subIva,
        totalIva: gastoNoDeducible ? 0 : totalIva,
        total: sub0 + subIva + (gastoNoDeducible ? 0 : totalIva),
    }
}

const TIPO_DOC_LABELS: Record<string, string> = {
    FAC: 'Factura', LIQ: 'Liquidación', TIK: 'Ticket', CON: 'Contrato', EXT: 'Exterior',
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div>
                <p className={cn('text-2xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs leading-none" style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
        </div>
    )
}

// ─── Fila detalle editable ────────────────────────────────────────────────────

interface DetalleRowProps {
    detalle: DetalleItem
    idx: number
    cuentas: Props['cuentas']
    onChange: (idx: number, field: keyof DetalleItem, value: string | number | boolean) => void
    onRemove: (idx: number) => void
}

function DetalleRow({ detalle, idx, cuentas, onChange, onRemove }: DetalleRowProps) {
    const { subtotal, iva, total } = calcDetalle(detalle)
    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <tr className="border-b text-xs" style={{ borderColor: 'var(--border)' }}>
            <td className="px-2 py-1.5">
                <input
                    className="w-full px-2 py-1 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                    value={detalle.descripcion}
                    onChange={e => onChange(idx, 'descripcion', e.target.value)}
                    placeholder="Descripción del bien/servicio"
                />
            </td>
            <td className="px-1 py-1.5 w-20">
                <input
                    type="number"
                    min="1"
                    step="1"
                    pattern="[0-9]*"
                    value={detalle.cantidad}
                    onChange={e => {
                        const val = Math.floor(Math.abs(parseInt(e.target.value) || 1))
                        onChange(idx, 'cantidad', String(val))
                    }}
                    onKeyDown={e => {
                        if (e.key === '.' || e.key === ',') e.preventDefault()
                    }}
                    className="w-full px-2 py-1 border rounded text-xs text-center focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                />
            </td>
            <td className="px-1 py-1.5 w-24">
                <input type="number" step="0.0001" min={0}
                    className="w-full px-2 py-1 border rounded text-xs text-right focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                    value={detalle.precio_unitario}
                    onChange={e => onChange(idx, 'precio_unitario', e.target.value)}
                />
            </td>
            <td className="px-2 py-1">
                <div className="flex flex-col gap-0.5 items-center">
                    <select
                        value={detalle.descuento_pct ?? '0'}
                        onChange={e => {
                            const pct   = parseInt(e.target.value) || 0
                            const base  = (parseFloat(String(detalle.cantidad)) || 0) *
                                          (parseFloat(String(detalle.precio_unitario)) || 0)
                            const monto = parseFloat((base * pct / 100).toFixed(2))
                            onChange(idx, 'descuento_pct', String(pct))
                            onChange(idx, 'descuento',     String(monto))
                        }}
                        className="w-16 rounded px-1 py-1 text-xs border text-center focus:outline-none focus:ring-1 focus:ring-amber-500"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-main)', color: 'var(--text-main)' }}
                    >
                        <option value="0">0%</option>
                        <option value="5">5%</option>
                        <option value="10">10%</option>
                        <option value="15">15%</option>
                        <option value="20">20%</option>
                        <option value="25">25%</option>
                        <option value="30">30%</option>
                        <option value="50">50%</option>
                    </select>
                    <span className="text-xs font-mono" style={{ color: 'var(--primary)' }}>
                        -${parseFloat(String(detalle.descuento) || '0').toFixed(2)}
                    </span>
                </div>
            </td>
            <td className="px-1 py-1.5 w-16">
                <select
                    className="w-full px-1 py-1 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                    value={detalle.porcentaje_iva}
                    onChange={e => onChange(idx, 'porcentaje_iva', e.target.value)}>
                    <option value={0}>0%</option>
                    <option value={15}>15%</option>
                </select>
            </td>
            <td className="px-2 py-1.5 text-right font-medium w-20" style={{ color: 'var(--text-main)' }}>
                {subtotal.toFixed(2)}
            </td>
            <td className="px-2 py-1.5 text-right w-16" style={{ color: 'var(--text-muted)' }}>
                {iva.toFixed(2)}
            </td>
            <td className="px-2 py-1.5 text-right font-bold w-20" style={{ color: 'var(--primary)' }}>
                {total.toFixed(2)}
            </td>
            <td className="px-1 py-1.5 w-8">
                <button onClick={() => onRemove(idx)}
                    className="p-1 rounded hover:bg-red-500/20 text-red-500 transition-colors">
                    <Trash2 className="w-3 h-3" />
                </button>
            </td>
        </tr>
    )
}

// ─── Modal Nueva Compra ───────────────────────────────────────────────────────

interface NuevaCompraModalProps {
    proveedores: Props['proveedores']
    centros: Props['centros']
    cuentas: Props['cuentas']
    onClose: () => void
}

function NuevaCompraModal({ proveedores, centros, cuentas, onClose }: NuevaCompraModalProps) {
    const [tab, setTab] = useState<'datos' | 'detalle' | 'centro'>('datos')

    const { data, setData, post, processing, errors } = useForm<{
        proveedor_id: string | number
        tipo_documento: string
        num_documento: string
        num_autorizacion: string
        fecha_emision: string
        dias_credito: number | string
        iva_asumido: boolean
        gasto_no_deducible: boolean
        sustento_tributario: string
        concepto: string
        centro_costo_id: string | number
        detalles: DetalleItem[]
    }>({
        proveedor_id:        '',
        tipo_documento:      'FAC',
        num_documento:       '',
        num_autorizacion:    '',
        fecha_emision:       new Date().toISOString().slice(0, 10),
        dias_credito:        0,
        iva_asumido:         false,
        gasto_no_deducible:  false,
        sustento_tributario: '',
        concepto:            '',
        centro_costo_id:     '',
        detalles: [{
            descripcion: '', cantidad: 1, precio_unitario: '',
            descuento: 0, descuento_pct: '0', porcentaje_iva: 15,
            cuenta_id: '', es_activo_fijo: false,
        }],
    })

    const totales = useMemo(
        () => calcTotales(data.detalles, data.gasto_no_deducible),
        [data.detalles, data.gasto_no_deducible]
    )

    const proveedorSel = proveedores.find(p => Number(p.id) === Number(data.proveedor_id))

    useEffect(() => {
        if (proveedorSel) {
            setData(prev => ({
                ...prev,
                dias_credito: proveedorSel.tiene_credito ? proveedorSel.dias_credito : 0,
            }))
        }
    }, [data.proveedor_id])

    const updateDetalle = useCallback((idx: number, field: keyof DetalleItem, value: string | number | boolean) => {
        setData(prev => ({
            ...prev,
            detalles: prev.detalles.map((d, i) => {
                if (i !== idx) return d
                const nuevo = { ...d, [field]: value }
                if (field === 'cantidad' || field === 'precio_unitario' || field === 'descuento_pct') {
                    const qty    = parseFloat(String(nuevo.cantidad))        || 0
                    const precio = parseFloat(String(nuevo.precio_unitario)) || 0
                    const pct    = parseFloat(String(nuevo.descuento_pct))   || 0
                    nuevo.descuento = String(parseFloat((qty * precio * pct / 100).toFixed(2)))
                }
                return nuevo
            }),
        }))
    }, [])

    const addDetalle = () => setData(prev => ({
        ...prev,
        detalles: [...prev.detalles, {
            descripcion: '', cantidad: 1, precio_unitario: '',
            descuento: 0, descuento_pct: '0', porcentaje_iva: 15,
            cuenta_id: '', es_activo_fijo: false,
        }],
    }))

    const removeDetalle = (idx: number) => setData(prev => ({
        ...prev,
        detalles: prev.detalles.filter((_, i) => i !== idx),
    }))

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('compras.facturas.store'), {
            onSuccess: () => { notify.ok(`Compra ${data.num_documento} registrada`); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }
    const tabs = [
        { key: 'datos',   label: '1. Datos generales' },
        { key: 'detalle', label: '2. Detalle' },
        { key: 'centro',  label: '3. Centro de costo' },
    ] as const

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-[75vw] max-h-[90vh] flex flex-col rounded-2xl shadow-2xl overflow-hidden"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                {/* Header */}
                <div className="flex items-center justify-between px-6 pt-5 pb-0 shrink-0">
                    <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                        Nueva factura de compra
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Tabs */}
                <div className="flex border-b px-6 mt-4 shrink-0" style={{ borderColor: 'var(--border)' }}>
                    {tabs.map(t => (
                        <button key={t.key} onClick={() => setTab(t.key)}
                            className={cn(
                                'px-4 py-2 text-sm font-medium border-b-2 transition-colors -mb-px',
                                tab === t.key
                                    ? 'border-amber-500'
                                    : 'border-transparent hover:opacity-70'
                            )}
                            style={tab === t.key
                                ? { color: 'var(--primary)', borderBottomColor: 'var(--primary)' }
                                : { color: 'var(--text-muted)' }
                            }
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="flex-1 overflow-y-auto p-6">

                        {/* ── Tab 1: Datos generales ── */}
                        {tab === 'datos' && (
                            <div className="space-y-4 max-w-2xl">
                                {/* Proveedor */}
                                <div className="space-y-1.5">
                                    <Label>Proveedor <span className="text-red-400">*</span></Label>
                                    <select value={data.proveedor_id}
                                        onChange={e => setData('proveedor_id', e.target.value)}
                                        className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        style={inputStyle}>
                                        <option value="">— Seleccionar proveedor —</option>
                                        {proveedores.map(p => (
                                            <option key={p.id} value={p.id}>
                                                {p.razon_social} ({p.identificacion})
                                            </option>
                                        ))}
                                    </select>
                                    {errors.proveedor_id && <p className="text-red-400 text-xs">{errors.proveedor_id}</p>}
                                    {proveedorSel && (
                                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {proveedorSel.tipo === 'internacional' ? '🌎 Internacional' : '🇪🇨 Nacional'}
                                            {proveedorSel.tiene_credito ? ` · Crédito ${proveedorSel.dias_credito}d` : ' · Contado'}
                                        </p>
                                    )}
                                </div>

                                {/* Tipo doc + N° doc */}
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Tipo documento <span className="text-red-400">*</span></Label>
                                        <select value={data.tipo_documento}
                                            onChange={e => setData('tipo_documento', e.target.value)}
                                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                                            style={inputStyle}>
                                            <option value="FAC">Factura</option>
                                            <option value="LIQ">Liquidación</option>
                                            <option value="TIK">Ticket</option>
                                            <option value="CON">Contrato</option>
                                            <option value="EXT">Exterior</option>
                                        </select>
                                    </div>
                                    <div className="col-span-2 space-y-1.5">
                                        <Label>N° Documento <span className="text-red-400">*</span></Label>
                                        <Input value={data.num_documento}
                                            onChange={e => setData('num_documento', e.target.value)}
                                            error={errors.num_documento}
                                            placeholder="001-001-000000001" />
                                        {errors.num_documento && <p className="text-red-400 text-xs">{errors.num_documento}</p>}
                                    </div>
                                </div>

                                {/* Autorización + Fecha */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>N° Autorización SRI</Label>
                                        <Input value={data.num_autorizacion}
                                            onChange={e => setData('num_autorizacion', e.target.value)}
                                            placeholder="Clave de acceso o N° autorización" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Fecha emisión <span className="text-red-400">*</span></Label>
                                        <Input type="date" value={data.fecha_emision}
                                            onChange={e => setData('fecha_emision', e.target.value)}
                                            error={errors.fecha_emision} />
                                    </div>
                                </div>

                                {/* Días crédito + Sustento */}
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Días de crédito</Label>
                                        <Input type="number" min={0} max={365}
                                            value={data.dias_credito}
                                            onChange={e => setData('dias_credito', e.target.value)} />
                                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {Number(data.dias_credito) > 0 ? 'Se creará una CxP automáticamente' : 'Contado — sin CxP'}
                                        </p>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Sustento tributario</Label>
                                        <Input type="number" min={1} max={99}
                                            value={data.sustento_tributario}
                                            onChange={e => setData('sustento_tributario', e.target.value)}
                                            placeholder="01, 02, 03..." />
                                    </div>
                                </div>

                                {/* Toggles IVA */}
                                <div className="grid grid-cols-2 gap-3">
                                    <label className="flex items-center gap-2.5 cursor-pointer rounded-lg border p-3"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <input type="checkbox" checked={data.iva_asumido}
                                            onChange={e => setData('iva_asumido', e.target.checked)}
                                            className="rounded w-4 h-4 accent-amber-500" />
                                        <div>
                                            <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>IVA asumido</p>
                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>El IVA lo asume la empresa</p>
                                        </div>
                                    </label>
                                    <label className="flex items-center gap-2.5 cursor-pointer rounded-lg border p-3"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <input type="checkbox" checked={data.gasto_no_deducible}
                                            onChange={e => setData('gasto_no_deducible', e.target.checked)}
                                            className="rounded w-4 h-4 accent-amber-500" />
                                        <div>
                                            <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>Gasto no deducible</p>
                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>IVA = $0 automáticamente</p>
                                        </div>
                                    </label>
                                </div>

                                {/* Concepto */}
                                <div className="space-y-1.5">
                                    <Label>Concepto / Descripción general</Label>
                                    <textarea value={data.concepto}
                                        onChange={e => setData('concepto', e.target.value)}
                                        rows={2}
                                        className="w-full px-3 py-2 border rounded-md text-sm resize-none focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        style={inputStyle}
                                        placeholder="Descripción general de la compra..." />
                                </div>
                            </div>
                        )}

                        {/* ── Tab 2: Detalle ── */}
                        {tab === 'detalle' && (
                            <div className="space-y-4">
                                <div className="overflow-x-auto rounded-lg border" style={{ borderColor: 'var(--border)' }}>
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b text-[10px] font-semibold uppercase tracking-wider"
                                                style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                                                <th className="px-2 py-2 text-left">Descripción</th>
                                                <th className="px-1 py-2 w-20 text-right">Cantidad</th>
                                                <th className="px-1 py-2 w-24 text-right">P. Unitario</th>
                                                <th className="px-2 py-2 w-24 text-center">Descuento</th>
                                                <th className="px-1 py-2 w-16 text-center">IVA %</th>
                                                <th className="px-2 py-2 w-20 text-right">Subtotal</th>
                                                <th className="px-2 py-2 w-16 text-right">IVA</th>
                                                <th className="px-2 py-2 w-20 text-right">Total</th>
                                                <th className="w-8" />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {data.detalles.map((d, idx) => (
                                                <DetalleRow key={idx} detalle={d} idx={idx}
                                                    cuentas={cuentas}
                                                    onChange={updateDetalle}
                                                    onRemove={removeDetalle} />
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                <button type="button" onClick={addDetalle}
                                    className="flex items-center gap-2 text-sm font-medium px-3 py-2 rounded-lg border transition-colors hover:opacity-80"
                                    style={{ color: 'var(--primary)', borderColor: 'var(--primary)', background: 'rgba(245,158,11,0.08)' }}>
                                    <Plus className="w-4 h-4" /> Agregar línea
                                </button>

                                {/* Totales */}
                                <div className="ml-auto max-w-xs rounded-xl border p-4 space-y-2"
                                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                                    <div className="flex justify-between text-sm">
                                        <span style={{ color: 'var(--text-muted)' }}>Subtotal 0%</span>
                                        <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                                            ${totales.subtotal0.toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span style={{ color: 'var(--text-muted)' }}>Subtotal gravado</span>
                                        <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                                            ${totales.subtotalIva.toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span style={{ color: 'var(--text-muted)' }}>
                                            IVA {data.gasto_no_deducible ? '(no deducible)' : ''}
                                        </span>
                                        <span className={cn('font-medium', data.gasto_no_deducible && 'line-through opacity-50')}
                                            style={{ color: 'var(--text-main)' }}>
                                            ${totales.totalIva.toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm font-bold border-t pt-2"
                                        style={{ borderColor: 'var(--border)', color: 'var(--primary)' }}>
                                        <span>TOTAL</span>
                                        <span>${totales.total.toFixed(2)}</span>
                                    </div>
                                    {Number(data.dias_credito) > 0 && (
                                        <p className="text-xs text-center" style={{ color: 'var(--text-muted)' }}>
                                            CxP: ${totales.total.toFixed(2)} a {data.dias_credito} días
                                        </p>
                                    )}
                                </div>

                                {errors.detalles && (
                                    <p className="text-red-400 text-xs">{errors.detalles as unknown as string}</p>
                                )}
                            </div>
                        )}

                        {/* ── Tab 3: Centro de costo ── */}
                        {tab === 'centro' && (
                            <div className="space-y-4 max-w-md">
                                <div className="space-y-1.5">
                                    <Label>Centro de costo</Label>
                                    <select value={data.centro_costo_id}
                                        onChange={e => setData('centro_costo_id', e.target.value)}
                                        className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                                        style={inputStyle}>
                                        <option value="">— Sin centro de costo —</option>
                                        {centros.map(c => (
                                            <option key={c.id} value={c.id}>{c.codigo} — {c.nombre}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Resumen final */}
                                <div className="rounded-xl border p-4 space-y-3"
                                    style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.04)' }}>
                                    <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                        Resumen de la compra
                                    </h3>
                                    {data.proveedor_id && (
                                        <div className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--text-muted)' }}>Proveedor</span>
                                            <span className="font-medium truncate max-w-48" style={{ color: 'var(--text-main)' }}>
                                                {proveedorSel?.razon_social ?? '—'}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-sm">
                                        <span style={{ color: 'var(--text-muted)' }}>Documento</span>
                                        <span className="font-medium font-mono" style={{ color: 'var(--text-main)' }}>
                                            {TIPO_DOC_LABELS[data.tipo_documento] ?? data.tipo_documento} {data.num_documento || '—'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span style={{ color: 'var(--text-muted)' }}>Líneas de detalle</span>
                                        <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                                            {data.detalles.length}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm font-bold border-t pt-2"
                                        style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                        <span>TOTAL A REGISTRAR</span>
                                        <span>${totales.total.toFixed(2)}</span>
                                    </div>
                                    {Number(data.dias_credito) > 0 && (
                                        <p className="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
                                            <CreditCard className="w-3.5 h-3.5" />
                                            Se creará CxP por ${totales.total.toFixed(2)} a {data.dias_credito} días
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Footer fijo */}
                    <div className="shrink-0 flex items-center justify-between gap-2 px-6 py-4 border-t"
                        style={{ borderColor: 'var(--border)' }}>
                        <div className="flex gap-2">
                            {tab !== 'datos' && (
                                <Button type="button" variant="outline" size="sm"
                                    onClick={() => setTab(tab === 'detalle' ? 'datos' : 'detalle')}>
                                    <ChevronLeft className="w-4 h-4" /> Atrás
                                </Button>
                            )}
                            {tab !== 'centro' && (
                                <Button type="button" size="sm"
                                    onClick={() => setTab(tab === 'datos' ? 'detalle' : 'centro')}>
                                    Siguiente <ChevronRight className="w-4 h-4" />
                                </Button>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                            <Button type="submit" disabled={processing}>
                                <Plus className="w-4 h-4" /> Registrar compra
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Anular ─────────────────────────────────────────────────────────────

function AnularModal({ compra, onClose }: { compra: Compra; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({ motivo: '' })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(route('compras.facturas.anular', compra.id), {
            onSuccess: () => { notify.ok(`Compra ${compra.num_documento} anulada`); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md rounded-xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <div className="flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ borderBottom: '1px solid var(--border)' }}>
                    <h2 className="font-semibold text-base text-red-600 dark:text-red-400">
                        Anular compra
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit} className="p-6 space-y-4">
                    <div className="rounded-lg p-3 border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/10">
                        <p className="text-sm font-medium text-red-800 dark:text-red-400">
                            {compra.num_documento}
                        </p>
                        <p className="text-xs text-red-600 dark:text-red-500 mt-0.5">
                            Total: ${Number(compra.total).toFixed(2)}
                        </p>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Motivo de anulación <span className="text-red-400">*</span></Label>
                        <textarea value={data.motivo}
                            onChange={e => setData('motivo', e.target.value)}
                            rows={3}
                            className="w-full px-3 py-2 border rounded-md text-sm resize-none focus:outline-none focus:ring-1 focus:ring-red-500"
                            style={inputStyle}
                            placeholder="Describe el motivo de la anulación (mínimo 10 caracteres)..." />
                        {errors.motivo && <p className="text-red-400 text-xs">{errors.motivo}</p>}
                    </div>

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <Button type="submit" disabled={processing}
                            className="bg-red-600 hover:bg-red-700 text-white border-0">
                            <Ban className="w-4 h-4" /> Anular compra
                        </Button>
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

type ModalState =
    | { type: 'none' }
    | { type: 'nueva' }
    | { type: 'anular'; compra: Compra }

export default function ComprasIndex() {
    const { compras, proveedores, centros, cuentas, filtros, stats, flash } = usePage<Props>().props

    const [modal, setModal] = useState<ModalState>({ type: 'none' })
    const [buscar, setBuscar]       = useState(filtros.buscar ?? '')
    const [estado, setEstado]       = useState(filtros.estado ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function aplicarFiltros() {
        router.get(route('compras.facturas.index'), {
            ...(buscar     && { buscar }),
            ...(estado     && { estado }),
            ...(fechaDesde && { fecha_desde: fechaDesde }),
            ...(fechaHasta && { fecha_hasta: fechaHasta }),
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setBuscar(''); setEstado(''); setFechaDesde(''); setFechaHasta('')
        router.get(route('compras.facturas.index'), {}, { preserveState: false })
    }

    const hayFiltros = buscar || estado || fechaDesde || fechaHasta
    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <AppLayout title="Facturas de Compra" suppressFlash>
            <Head title="Facturas de Compra" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <ShoppingCart size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Facturas de Compra
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Registro y gestión de facturas, liquidaciones y documentos de compra
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    <button onClick={() => setModal({ type: 'nueva' })}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                        style={{ background: 'var(--primary)' }}
                        onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                        onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}>
                        <Plus size={15} /> Nueva Factura de Compra
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 px-6 py-4">
                <StatCard label="Total registros" value={stats.total} icon={FileText}
                    cls="bg-slate-500/15 text-slate-600 dark:text-slate-400"
                    valueCls="text-slate-600 dark:text-slate-400" />
                <StatCard label="Activas" value={stats.activas} icon={CheckCircle}
                    cls="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueCls="text-green-600 dark:text-green-400" />
                <StatCard label="Anuladas" value={stats.anuladas} icon={AlertCircle}
                    cls="bg-red-500/15 text-red-600 dark:text-red-400"
                    valueCls="text-red-600 dark:text-red-400" />
                <StatCard label="Con pago" value={stats.con_pago} icon={DollarSign}
                    cls="bg-amber-500/15 text-amber-600 dark:text-amber-400"
                    valueCls="text-amber-600 dark:text-amber-400" />
            </div>

            {/* Filtros */}
            <div className="flex flex-wrap items-center gap-2 px-6 pb-4">
                <div className="relative flex-1 min-w-48 max-w-xs">
                    <Search className="absolute top-1/2 left-2.5 w-4 h-4 -translate-y-1/2 pointer-events-none"
                        style={{ color: 'var(--text-muted)' }} />
                    <Input className="pl-8 pr-8" placeholder="Buscar N° doc, proveedor…"
                        value={buscar} onChange={e => setBuscar(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && aplicarFiltros()} />
                    {buscar && (
                        <button onClick={() => setBuscar('')}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 hover:opacity-70 transition-opacity"
                            style={{ color: 'var(--text-muted)' }}>
                            <X className="w-3.5 h-3.5" />
                        </button>
                    )}
                </div>
                <select value={estado} onChange={e => setEstado(e.target.value)}
                    className="h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}>
                    <option value="">Todos los estados</option>
                    <option value="activa">Activa</option>
                    <option value="anulada">Anulada</option>
                </select>
                <Input type="date" className="h-9 w-36" value={fechaDesde}
                    onChange={e => setFechaDesde(e.target.value)} />
                <Input type="date" className="h-9 w-36" value={fechaHasta}
                    onChange={e => setFechaHasta(e.target.value)} />
                <button onClick={aplicarFiltros}
                    className="h-9 px-4 rounded-md text-sm font-medium text-white transition-colors"
                    style={{ background: 'var(--primary)' }}
                    onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                    onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}>
                    Filtrar
                </button>
                {hayFiltros && (
                    <button onClick={limpiar}
                        className="h-9 px-3 rounded-md text-sm font-medium border transition-colors hover:opacity-80"
                        style={{ color: 'var(--text-muted)', borderColor: 'var(--border)' }}>
                        Limpiar
                    </button>
                )}
            </div>

            {/* Tabla */}
            <div className="px-6 pb-6">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-2">N° Documento</span>
                        <span className="col-span-1 text-center">Fecha</span>
                        <span className="col-span-3">Proveedor</span>
                        <span className="col-span-1 text-center">Tipo</span>
                        <span className="col-span-1 text-right">Subtotal</span>
                        <span className="col-span-1 text-right">IVA</span>
                        <span className="col-span-1 text-right">Total</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-right">Acción</span>
                    </div>

                    {compras.data.length === 0 && (
                        <div className="py-20 text-center">
                            <ShoppingCart className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay compras registradas
                            </p>
                        </div>
                    )}

                    {compras.data.map(c => (
                        <div key={c.id}
                            className={cn(
                                'group grid grid-cols-12 gap-2 px-4 py-3 border-b items-center text-sm transition-colors',
                                c.estado === 'anulada' && 'opacity-50',
                            )}
                            style={{ borderColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <div className="col-span-2 min-w-0">
                                <p className="font-mono text-xs font-medium truncate" style={{ color: 'var(--text-main)' }}>
                                    {c.num_documento}
                                </p>
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    {c.fecha_emision ? new Date(c.fecha_emision + 'T00:00:00').toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: '2-digit' }) : '—'}
                                </p>
                            </div>
                            <div className="col-span-3 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-main)' }}>
                                    {(c.proveedor as Proveedor | undefined)?.razon_social ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 text-center">
                                <span className="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    {c.tipo_documento}
                                </span>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    ${Number(c.subtotal_0 + c.subtotal_iva).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    ${Number(c.total_iva).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="text-xs font-bold" style={{ color: 'var(--primary)' }}>
                                    ${Number(c.total).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {c.estado === 'activa'
                                    ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Activa</span>
                                    : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Anulada</span>
                                }
                            </div>
                            <div className="col-span-1 flex justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Link href={route('compras.facturas.show', c.id)}
                                    className="p-1.5 rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors"
                                    title="Ver detalle">
                                    <Eye className="w-3.5 h-3.5" />
                                </Link>
                                {c.estado === 'activa' && (
                                    c.tiene_pago
                                        ? <button disabled title="Tiene pago registrado — anula el pago primero"
                                            className="p-1.5 rounded opacity-30 cursor-not-allowed"
                                            style={{ color: 'var(--text-muted)' }}>
                                            <Lock className="w-3.5 h-3.5" />
                                          </button>
                                        : <button onClick={() => setModal({ type: 'anular', compra: c })}
                                            title="Anular compra"
                                            className="p-1.5 rounded hover:bg-red-500/20 text-red-500 dark:text-red-400 transition-colors">
                                            <Ban className="w-3.5 h-3.5" />
                                          </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Paginación */}
            {compras.meta && compras.meta.last_page > 1 && (
                <div className="flex items-center justify-between px-6 pb-6">
                    <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                        Mostrando {compras.meta.from}–{compras.meta.to} de {compras.meta.total} registros
                    </p>
                    <div className="flex gap-1">
                        {compras.links.map((link, idx) => (
                            link.url
                                ? <Link key={idx} href={link.url}
                                    className={cn(
                                        'px-3 py-1.5 rounded text-sm border transition-colors',
                                        link.active
                                            ? 'text-white border-transparent'
                                            : 'hover:opacity-80'
                                    )}
                                    style={link.active
                                        ? { background: 'var(--primary)' }
                                        : { color: 'var(--text-muted)', borderColor: 'var(--border)', background: 'var(--bg-card)' }
                                    }
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                  />
                                : <span key={idx}
                                    className="px-3 py-1.5 rounded text-sm border opacity-40"
                                    style={{ color: 'var(--text-muted)', borderColor: 'var(--border)' }}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                  />
                        ))}
                    </div>
                </div>
            )}

            {modal.type === 'nueva' && (
                <NuevaCompraModal
                    proveedores={proveedores}
                    centros={centros}
                    cuentas={cuentas}
                    onClose={() => setModal({ type: 'none' })}
                />
            )}
            {modal.type === 'anular' && (
                <AnularModal
                    compra={modal.compra}
                    onClose={() => setModal({ type: 'none' })}
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
