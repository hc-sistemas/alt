import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, Package, Plane, Anchor, CheckCircle2,
    X, DollarSign, Loader2, Eye,
} from 'lucide-react'
import type { Importacion, Proveedor, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ImportacionRow extends Omit<Importacion, 'proveedor'> {
    proveedor: string | null
}

interface Props extends PageProps {
    importaciones: ImportacionRow[]
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'pais' | 'divisa'>[]
}

type TabKey = 'general' | 'productos' | 'gastos' | 'liquidar'

interface ProductoImportado {
    codigo: string
    nombre: string
    cantidad: number
    precio_unitario: number
    subtotal: number
    costo_actual: number | null
}

interface GastoImportado {
    concepto: string
    num_documento: string
    monto: number
}

interface DetalleData {
    productos: ProductoImportado[]
    gastos: GastoImportado[]
    totales: { fob: number; gastos: number; total: number }
}

interface CostoExtra { descripcion: string; monto: string }

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    edit:  (msg: string) => toast.success(msg, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── Estado Badge ─────────────────────────────────────────────────────────────

const ESTADO_CFG: Record<string, { label: string; cls: string; icon: React.ElementType; pulse?: boolean }> = {
    en_transito: { label: 'En Tránsito', cls: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',        icon: Plane,         pulse: true },
    en_aduana:   { label: 'En Aduana',   cls: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300', icon: Anchor },
    liquidada:   { label: 'Liquidada',   cls: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',     icon: CheckCircle2 },
}

function EstadoBadge({ estado }: { estado: string }) {
    const cfg = ESTADO_CFG[estado] ?? { label: estado, cls: 'bg-gray-100 text-gray-800', icon: Package }
    const Icon = cfg.icon
    return (
        <span className={cn('inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold', cfg.cls)}>
            {cfg.pulse
                ? <span className="relative flex h-2 w-2">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75" />
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-blue-500" />
                  </span>
                : <Icon className="w-3 h-3" />
            }
            {cfg.label}
        </span>
    )
}

// ─── Modal Crear ──────────────────────────────────────────────────────────────

function CrearModal({ proveedores, onClose }: {
    proveedores: Props['proveedores']
    onClose: () => void
}) {
    const { data, setData, post, processing, errors } = useForm({
        nombre:          '',
        proveedor_id:    '' as string | number,
        num_invoice:     '',
        agente_aduanero: '',
        pais_embarque:   '',
        costo_fob:       '' as string | number,
        divisa:          'USD',
        fecha_partida:   '',
        fecha_llegada:   '',
        observaciones:   '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('compras.importaciones.store'), {
            onSuccess: () => { notify.ok(`Importación "${data.nombre}" creada`); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-2xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Nueva importación</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body">
                    <div className="space-y-1.5">
                        <Label>Nombre de la importación <span className="text-red-400">*</span></Label>
                        <Input value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                            error={errors.nombre} placeholder="ej: Importación Q2-2026 China" />
                        {errors.nombre && <p className="text-red-400 text-xs">{errors.nombre}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label>Proveedor internacional</Label>
                        <select value={data.proveedor_id} onChange={e => setData('proveedor_id', e.target.value)}
                            className="input-field select-field">
                            <option value="">— Sin proveedor asignado —</option>
                            {proveedores.map(p => (
                                <option key={p.id} value={p.id}>{p.razon_social} ({p.pais})</option>
                            ))}
                        </select>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>N° Invoice</Label>
                            <Input value={data.num_invoice} onChange={e => setData('num_invoice', e.target.value)}
                                placeholder="INV-2026-001" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Agente aduanero</Label>
                            <Input value={data.agente_aduanero} onChange={e => setData('agente_aduanero', e.target.value)}
                                placeholder="Nombre del agente" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>País de embarque</Label>
                            <Input value={data.pais_embarque} onChange={e => setData('pais_embarque', e.target.value)}
                                placeholder="China, USA, España..." />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Divisa</Label>
                            <Input value={data.divisa} onChange={e => setData('divisa', e.target.value)}
                                placeholder="USD" />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Costo FOB <span className="text-red-400">*</span></Label>
                        <Input type="number" step="0.01" min={0} value={data.costo_fob}
                            onChange={e => setData('costo_fob', e.target.value)}
                            error={errors.costo_fob} placeholder="0.00" />
                        {errors.costo_fob && <p className="text-red-400 text-xs">{errors.costo_fob}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha de partida</Label>
                            <Input type="date" value={data.fecha_partida}
                                onChange={e => setData('fecha_partida', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Fecha estimada llegada</Label>
                            <Input type="date" value={data.fecha_llegada}
                                onChange={e => setData('fecha_llegada', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Observaciones</Label>
                        <textarea value={data.observaciones} onChange={e => setData('observaciones', e.target.value)}
                            rows={3} className="input-field textarea-field" placeholder="Notas adicionales..." />
                    </div>
                </div>
                <div className="modal-footer">
                    <Button type="submit" disabled={processing}>
                        <Plus className="w-4 h-4" /> Crear importación
                    </Button>
                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Detalle (4 tabs) ───────────────────────────────────────────────────

const TABS: { key: TabKey; label: string }[] = [
    { key: 'general',   label: '1. General' },
    { key: 'productos', label: '2. Productos' },
    { key: 'gastos',    label: '3. Costos Extra' },
    { key: 'liquidar',  label: '4. Liquidación' },
]

function DetalleModal({ importacion, initialTab, onClose }: {
    importacion: ImportacionRow
    initialTab: TabKey
    onClose: () => void
}) {
    const [tab,      setTab]      = useState<TabKey>(initialTab)
    const [cargando, setCargando] = useState(true)
    const [detalle,  setDetalle]  = useState<DetalleData | null>(null)

    // ── Tab 1: General ──
    const { data, setData, put, processing, errors } = useForm({
        nombre:          importacion.nombre,
        pais_embarque:   importacion.pais_embarque ?? '',
        costo_fob:       importacion.costo_fob,
        divisa:          importacion.divisa ?? 'USD',
        agente_aduanero: importacion.agente_aduanero ?? '',
        fecha_partida:   importacion.fecha_partida ?? '',
        fecha_llegada:   importacion.fecha_llegada ?? '',
        estado:          importacion.estado,
        observaciones:   importacion.observaciones ?? '',
    })

    // ── Tab 4: Liquidar ──
    const [metodo,             setMetodo]    = useState<'cantidad' | 'precio'>('cantidad')
    const [fechaLiq,           setFechaLiq]  = useState(new Date().toISOString().slice(0, 10))
    const [costos,             setCostos]    = useState<CostoExtra[]>([{ descripcion: '', monto: '' }])
    const [liqProcessing,      setLiqProc]   = useState(false)
    const totalCostosExtra = costos.reduce((acc, c) => acc + (parseFloat(c.monto) || 0), 0)
    const costoTotal       = Number(importacion.costo_fob) + totalCostosExtra

    useEffect(() => {
        fetch(route('compras.importaciones.detalle', importacion.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then((d: DetalleData) => { setDetalle(d); setCargando(false) })
            .catch(() => setCargando(false))
    }, [importacion.id])

    function submitGeneral(e: React.FormEvent) {
        e.preventDefault()
        put(route('compras.importaciones.update', importacion.id), {
            onSuccess: () => { notify.edit('Importación actualizada'); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    function submitLiquidar(e: React.FormEvent) {
        e.preventDefault()
        const costosValidos = costos.filter(c => c.descripcion && parseFloat(c.monto) > 0)
        setLiqProc(true)
        router.patch(route('compras.importaciones.liquidar', importacion.id), {
            metodo_prorrateo:  metodo,
            fecha_liquidacion: fechaLiq,
            costos_extra:      costosValidos as unknown as { [key: string]: string }[],
        }, {
            onSuccess: () => { notify.ok(`Importación "${importacion.nombre}" liquidada`); onClose() },
            onError:   (errs) => { notify.error('Error: ' + Object.values(errs).join(', ')); setLiqProc(false) },
            onFinish:  () => setLiqProc(false),
        })
    }

    const yaLiquidada = importacion.estado === 'liquidada'
    const inputStyle  = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-4xl" style={{ maxHeight: '90vh' }} onClick={e => e.stopPropagation()}>

                {/* ── Header ── */}
                <div className="modal-header shrink-0" style={{ gap: '0.75rem' }}>
                    <div className="min-w-0 flex-1">
                        <h2 className="truncate">{importacion.nombre}</h2>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                            {importacion.num_invoice && `Invoice: ${importacion.num_invoice} · `}
                            FOB: ${Number(importacion.costo_fob).toFixed(2)} {importacion.divisa ?? 'USD'}
                        </p>
                    </div>
                    <EstadoBadge estado={importacion.estado} />
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>

                {/* ── Tab nav ── */}
                <div className="shrink-0 flex gap-0.5 px-4 border-b"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    {TABS.map(t => (
                        <button key={t.key} type="button" onClick={() => setTab(t.key)}
                            className="px-3 py-2.5 text-xs font-semibold transition-colors whitespace-nowrap"
                            style={{
                                color:        tab === t.key ? 'var(--primary)' : 'var(--text-muted)',
                                borderBottom: tab === t.key ? '2px solid var(--primary)' : '2px solid transparent',
                                background:   tab === t.key ? 'rgba(245,158,11,0.06)' : 'transparent',
                            }}>
                            {t.label}
                        </button>
                    ))}
                </div>

                {/* ── Tab content ── */}
                <div className="flex-1 overflow-y-auto">

                    {/* ════ TAB 1: GENERAL ════ */}
                    {tab === 'general' && (
                        <form onSubmit={submitGeneral}>
                            <div className="modal-body">
                                <div className="space-y-1.5">
                                    <Label>Estado</Label>
                                    <select value={data.estado}
                                        onChange={e => setData('estado', e.target.value as ImportacionRow['estado'])}
                                        disabled={yaLiquidada}
                                        className="input-field select-field">
                                        <option value="en_transito">En Tránsito</option>
                                        <option value="en_aduana">En Aduana</option>
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Fecha partida</Label>
                                        <Input type="date" value={data.fecha_partida} disabled={yaLiquidada}
                                            onChange={e => setData('fecha_partida', e.target.value)} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Fecha llegada</Label>
                                        <Input type="date" value={data.fecha_llegada} disabled={yaLiquidada}
                                            onChange={e => setData('fecha_llegada', e.target.value)} />
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Costo FOB</Label>
                                        <Input type="number" step="0.01" min={0} value={data.costo_fob}
                                            disabled={yaLiquidada}
                                            onChange={e => setData('costo_fob', Number(e.target.value))} />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Divisa</Label>
                                        <Input value={data.divisa} disabled={yaLiquidada}
                                            onChange={e => setData('divisa', e.target.value)} />
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>País de embarque</Label>
                                    <Input value={data.pais_embarque} disabled={yaLiquidada}
                                        onChange={e => setData('pais_embarque', e.target.value)}
                                        placeholder="China, USA, España..." />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Agente aduanero</Label>
                                    <Input value={data.agente_aduanero} disabled={yaLiquidada}
                                        onChange={e => setData('agente_aduanero', e.target.value)}
                                        placeholder="Nombre del agente" />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Observaciones</Label>
                                    <textarea value={data.observaciones} disabled={yaLiquidada}
                                        onChange={e => setData('observaciones', e.target.value)}
                                        rows={3} className="input-field textarea-field"
                                        placeholder="Notas..." />
                                </div>
                                {errors.estado && <p className="text-red-400 text-xs">{errors.estado}</p>}
                            </div>
                            {!yaLiquidada && (
                                <div className="modal-footer">
                                    <Button type="submit" disabled={processing}>
                                        <Pencil className="w-4 h-4" /> Guardar cambios
                                    </Button>
                                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                                </div>
                            )}
                        </form>
                    )}

                    {/* ════ TAB 2: PRODUCTOS ════ */}
                    {tab === 'productos' && (
                        <div className="p-5">
                            {cargando ? (
                                <div className="flex items-center justify-center py-16 gap-2"
                                    style={{ color: 'var(--text-muted)' }}>
                                    <Loader2 className="w-5 h-5 animate-spin" />
                                    Cargando productos...
                                </div>
                            ) : !detalle || detalle.productos.length === 0 ? (
                                <div className="py-14 text-center" style={{ color: 'var(--text-muted)' }}>
                                    <Package className="w-10 h-10 mx-auto mb-3 opacity-20" />
                                    <p className="font-semibold text-sm">No hay productos registrados</p>
                                    <p className="text-xs mt-1 max-w-xs mx-auto">
                                        Vincula facturas de compra desde <strong>Compras → Facturas</strong> seleccionando esta importación.
                                    </p>
                                </div>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b" style={{ borderColor: 'var(--border)' }}>
                                                {['Código', 'Producto', 'Cant.', 'P. Unit.', 'Subtotal', 'Costo actual'].map(h => (
                                                    <th key={h} className={cn(
                                                        'pb-2 pt-1 px-2 font-semibold uppercase text-[10px] tracking-wider',
                                                        h === 'P. Unit.' || h === 'Subtotal' || h === 'Costo actual' ? 'text-right' : 'text-left',
                                                        h === 'Cant.' ? 'text-center' : '',
                                                    )} style={{ color: 'var(--text-muted)' }}>
                                                        {h}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detalle.productos.map((p, i) => (
                                                <tr key={i} className="border-b"
                                                    style={{ borderColor: 'var(--border)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                                    <td className="py-2.5 px-2 font-mono font-bold" style={{ color: 'var(--primary)' }}>
                                                        {p.codigo}
                                                    </td>
                                                    <td className="py-2.5 px-2 max-w-55" style={{ color: 'var(--text-main)' }}>
                                                        <span className="block truncate">{p.nombre}</span>
                                                    </td>
                                                    <td className="py-2.5 px-2 text-center tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                        {p.cantidad % 1 === 0 ? p.cantidad.toFixed(0) : p.cantidad.toFixed(2)}
                                                    </td>
                                                    <td className="py-2.5 px-2 text-right tabular-nums" style={{ color: 'var(--text-muted)' }}>
                                                        ${p.precio_unitario.toFixed(2)}
                                                    </td>
                                                    <td className="py-2.5 px-2 text-right tabular-nums font-medium" style={{ color: 'var(--text-main)' }}>
                                                        ${p.subtotal.toFixed(2)}
                                                    </td>
                                                    <td className="py-2.5 px-2 text-right tabular-nums"
                                                        style={{ color: p.costo_actual != null && p.costo_actual > p.precio_unitario ? '#10b981' : 'var(--text-muted)' }}>
                                                        {p.costo_actual != null ? `$${p.costo_actual.toFixed(4)}` : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot>
                                            <tr className="border-t" style={{ borderColor: 'var(--border)' }}>
                                                <td colSpan={4} className="pt-2.5 px-2 text-right font-semibold uppercase text-[10px] tracking-wider"
                                                    style={{ color: 'var(--text-muted)' }}>
                                                    TOTAL FOB
                                                </td>
                                                <td className="pt-2.5 px-2 text-right font-bold tabular-nums"
                                                    style={{ color: 'var(--primary)' }}>
                                                    ${(detalle.totales.fob).toFixed(2)}
                                                </td>
                                                <td />
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ════ TAB 3: COSTOS EXTRA ════ */}
                    {tab === 'gastos' && (
                        <div className="p-5 space-y-4">
                            {cargando ? (
                                <div className="flex items-center justify-center py-16 gap-2"
                                    style={{ color: 'var(--text-muted)' }}>
                                    <Loader2 className="w-5 h-5 animate-spin" />
                                    Cargando...
                                </div>
                            ) : detalle && detalle.gastos.length > 0 ? (
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr className="border-b" style={{ borderColor: 'var(--border)' }}>
                                            <th className="pb-2 px-2 text-left font-semibold uppercase text-[10px] tracking-wider" style={{ color: 'var(--text-muted)' }}>Concepto</th>
                                            <th className="pb-2 px-2 text-left font-semibold uppercase text-[10px] tracking-wider" style={{ color: 'var(--text-muted)' }}>Documento</th>
                                            <th className="pb-2 px-2 text-right font-semibold uppercase text-[10px] tracking-wider" style={{ color: 'var(--text-muted)' }}>Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {detalle.gastos.map((g, i) => (
                                            <tr key={i} className="border-b" style={{ borderColor: 'var(--border)' }}>
                                                <td className="py-2.5 px-2" style={{ color: 'var(--text-main)' }}>{g.concepto}</td>
                                                <td className="py-2.5 px-2 font-mono text-[10px]" style={{ color: 'var(--text-muted)' }}>{g.num_documento}</td>
                                                <td className="py-2.5 px-2 text-right tabular-nums font-medium" style={{ color: 'var(--text-main)' }}>
                                                    ${g.monto.toFixed(2)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t" style={{ borderColor: 'var(--border)' }}>
                                            <td colSpan={2} className="pt-2.5 px-2 text-right font-semibold uppercase text-[10px] tracking-wider"
                                                style={{ color: 'var(--text-muted)' }}>TOTAL COSTOS EXTRA</td>
                                            <td className="pt-2.5 px-2 text-right font-bold tabular-nums" style={{ color: 'var(--primary)' }}>
                                                ${detalle.gastos.reduce((s, g) => s + g.monto, 0).toFixed(2)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            ) : (
                                <div className="py-10 text-center" style={{ color: 'var(--text-muted)' }}>
                                    <DollarSign className="w-8 h-8 mx-auto mb-2 opacity-20" />
                                    <p className="font-semibold text-sm">No hay facturas de gasto vinculadas</p>
                                    <p className="text-xs mt-1">
                                        {Number(importacion.total_costos_extra) > 0
                                            ? 'Los costos extra se ingresaron al liquidar. Ver resumen abajo.'
                                            : 'Registra compras con "Gasto no deducible" asignadas a esta importación.'}
                                    </p>
                                </div>
                            )}

                            {/* Resumen — siempre visible si hay datos */}
                            {(yaLiquidada || Number(importacion.total_costos_extra) > 0) && (
                                <div className="rounded-lg p-3 space-y-1.5 text-sm"
                                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                    <div className="flex justify-between">
                                        <span style={{ color: 'var(--text-muted)' }}>Costo FOB</span>
                                        <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                            ${Number(importacion.costo_fob).toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span style={{ color: 'var(--text-muted)' }}>Costos extra</span>
                                        <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                            ${Number(importacion.total_costos_extra).toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between font-bold border-t pt-1.5"
                                        style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                        <span>Costo total aterrizado</span>
                                        <span className="tabular-nums">${Number(importacion.costo_total).toFixed(2)}</span>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* ════ TAB 4: LIQUIDACIÓN ════ */}
                    {tab === 'liquidar' && (
                        yaLiquidada ? (
                            /* ── Ya liquidada ── */
                            <div className="p-5 space-y-4">
                                <div className="rounded-lg p-5 text-center space-y-2"
                                    style={{ background: 'rgba(16,185,129,0.07)', border: '1px solid rgba(16,185,129,0.3)' }}>
                                    <CheckCircle2 className="w-9 h-9 mx-auto" style={{ color: '#10b981' }} />
                                    <p className="font-bold text-sm" style={{ color: '#10b981' }}>Importación liquidada</p>
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                        Liquidada el {importacion.fecha_liquidacion}
                                        {importacion.metodo_prorrateo && ` · Método: ${importacion.metodo_prorrateo}`}
                                    </p>
                                </div>

                                <div className="rounded-lg p-3 space-y-1.5 text-sm"
                                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                    <div className="flex justify-between">
                                        <span style={{ color: 'var(--text-muted)' }}>Costo FOB</span>
                                        <span className="font-medium tabular-nums">${Number(importacion.costo_fob).toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span style={{ color: 'var(--text-muted)' }}>Costos extra prorrateados</span>
                                        <span className="font-medium tabular-nums">${Number(importacion.total_costos_extra).toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between font-bold border-t pt-1.5"
                                        style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                        <span>Costo total aterrizado</span>
                                        <span className="tabular-nums">${Number(importacion.costo_total).toFixed(2)}</span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    disabled
                                    title="Contacte al administrador para revertir manualmente"
                                    className="w-full py-2 px-4 rounded-lg text-sm font-medium border opacity-40 cursor-not-allowed"
                                    style={{ borderColor: '#ef4444', color: '#ef4444', background: 'transparent' }}>
                                    Revertir liquidación (no disponible)
                                </button>
                            </div>
                        ) : (
                            /* ── Formulario liquidar ── */
                            <form onSubmit={submitLiquidar}>
                                <div className="modal-body">
                                    {/* Costos extra dinámicos */}
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <Label>Costos extra (flete, seguro, aduana…)</Label>
                                            <button type="button"
                                                onClick={() => setCostos(prev => [...prev, { descripcion: '', monto: '' }])}
                                                className="text-xs font-semibold px-2 py-1 rounded-lg transition-colors"
                                                style={{ color: 'var(--primary)', background: 'rgba(245,158,11,0.1)' }}>
                                                + Agregar
                                            </button>
                                        </div>
                                        {costos.map((c, idx) => (
                                            <div key={idx} className="flex gap-2 items-center">
                                                <input
                                                    value={c.descripcion}
                                                    onChange={e => setCostos(prev => prev.map((x, i) => i === idx ? { ...x, descripcion: e.target.value } : x))}
                                                    placeholder="Descripción (flete, seguro…)"
                                                    className="input-field flex-1 text-xs" />
                                                <input
                                                    type="number" step="0.01" min={0}
                                                    value={c.monto}
                                                    onChange={e => setCostos(prev => prev.map((x, i) => i === idx ? { ...x, monto: e.target.value } : x))}
                                                    placeholder="0.00"
                                                    className="input-field text-xs text-right" style={{ width: '6rem' }} />
                                                {costos.length > 1 && (
                                                    <button type="button"
                                                        onClick={() => setCostos(prev => prev.filter((_, i) => i !== idx))}
                                                        className="p-1 rounded hover:text-red-500 transition-colors"
                                                        style={{ color: 'var(--text-muted)' }}>
                                                        <X className="w-3.5 h-3.5" />
                                                    </button>
                                                )}
                                            </div>
                                        ))}
                                    </div>

                                    {/* Resumen costos */}
                                    <div className="rounded-lg p-3 space-y-1.5"
                                        style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                        <div className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--text-muted)' }}>Costo FOB</span>
                                            <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                ${Number(importacion.costo_fob).toFixed(2)} {importacion.divisa ?? 'USD'}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--text-muted)' }}>Costos extra ingresados</span>
                                            <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                ${totalCostosExtra.toFixed(2)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                                            style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                            <span>Costo total estimado</span>
                                            <span className="tabular-nums">${costoTotal.toFixed(2)}</span>
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label>Método de prorrateo <span className="text-red-400">*</span></Label>
                                        <select value={metodo} onChange={e => setMetodo(e.target.value as typeof metodo)}
                                            className="input-field select-field">
                                            <option value="cantidad">Por cantidad (unidades)</option>
                                            <option value="precio">Por precio (valor FOB)</option>
                                        </select>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label>Fecha de liquidación <span className="text-red-400">*</span></Label>
                                        <Input type="date" value={fechaLiq} onChange={e => setFechaLiq(e.target.value)} />
                                    </div>
                                </div>
                                <div className="modal-footer">
                                    <Button type="submit" disabled={liqProcessing || totalCostosExtra <= 0}>
                                        <CheckCircle2 className="w-4 h-4" /> Liquidar
                                    </Button>
                                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                                </div>
                            </form>
                        )
                    )}

                </div>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

type ModalState =
    | { type: 'none' }
    | { type: 'crear' }
    | { type: 'detalle'; importacion: ImportacionRow; tab: TabKey }

export default function ImportacionesIndex() {
    const { importaciones, proveedores, flash } = usePage<Props>().props
    const [modal, setModal] = useState<ModalState>({ type: 'none' })

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function cerrar() { setModal({ type: 'none' }) }

    return (
        <AppLayout title="Importaciones" suppressFlash>
            <Head title="Importaciones" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <Package size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Importaciones COMEX
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Seguimiento de importaciones internacionales y liquidación de costos
                        </p>
                    </div>
                </div>
                <div className="flex items-center justify-between gap-3 mb-6">
                    <button onClick={() => setModal({ type: 'crear' })}
                        className="btn-primary flex items-center gap-2 whitespace-nowrap">
                        <Plus size={15} /> Nueva Importación
                    </button>
                </div>
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-3 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-3">Nombre</span>
                        <span className="col-span-2">Proveedor</span>
                        <span className="col-span-1">Invoice</span>
                        <span className="col-span-1 text-right">FOB</span>
                        <span className="col-span-1 text-right">Total</span>
                        <span className="col-span-1 text-center">Partida</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-2 text-right">Acciones</span>
                    </div>

                    {importaciones.length === 0 && (
                        <div className="py-20 text-center">
                            <Package className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay importaciones registradas
                            </p>
                        </div>
                    )}

                    {importaciones.map(i => (
                        <div key={i.id}
                            className="group grid grid-cols-12 gap-3 px-4 py-3 border-b items-center text-sm transition-colors"
                            style={{ borderColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>

                            <div className="col-span-3 min-w-0">
                                <p className="font-medium truncate" style={{ color: 'var(--text-main)' }}>{i.nombre}</p>
                                {i.pais_embarque && (
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{i.pais_embarque}</p>
                                )}
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>{i.proveedor ?? '—'}</p>
                            </div>
                            <div className="col-span-1">
                                <p className="font-mono text-xs" style={{ color: 'var(--text-main)' }}>{i.num_invoice ?? '—'}</p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                    ${Number(i.costo_fob).toFixed(2)}
                                </p>
                                <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>{i.divisa ?? 'USD'}</p>
                            </div>
                            <div className="col-span-1 text-right">
                                {Number(i.costo_total) > 0
                                    ? <p className="text-xs font-bold" style={{ color: 'var(--primary)' }}>
                                        ${Number(i.costo_total).toFixed(2)}
                                      </p>
                                    : <p className="text-xs" style={{ color: 'var(--text-muted)' }}>—</p>
                                }
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{i.fecha_partida ?? '—'}</p>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                <EstadoBadge estado={i.estado} />
                            </div>
                            <div className="col-span-2 flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                {/* Ver / Editar → siempre visible */}
                                <button
                                    onClick={() => setModal({ type: 'detalle', importacion: i, tab: 'general' })}
                                    title={i.estado === 'liquidada' ? 'Ver detalle' : 'Editar importación'}
                                    className={cn(
                                        'p-1.5 rounded transition-colors',
                                        i.estado === 'liquidada'
                                            ? 'text-gray-500 dark:text-gray-400 hover:bg-gray-500/20'
                                            : 'text-blue-500 dark:text-blue-400 hover:bg-blue-500/20',
                                    )}>
                                    {i.estado === 'liquidada'
                                        ? <Eye className="w-3.5 h-3.5" />
                                        : <Pencil className="w-3.5 h-3.5" />
                                    }
                                </button>
                                {/* Liquidar → solo si no está liquidada */}
                                {i.estado !== 'liquidada' && (
                                    <button
                                        onClick={() => setModal({ type: 'detalle', importacion: i, tab: 'liquidar' })}
                                        title="Liquidar importación"
                                        className="p-1.5 rounded transition-colors text-green-600 dark:text-green-400 hover:bg-green-500/20">
                                        <DollarSign className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modal.type === 'crear' && (
                <CrearModal proveedores={proveedores} onClose={cerrar} />
            )}
            {modal.type === 'detalle' && (
                <DetalleModal
                    importacion={modal.importacion}
                    initialTab={modal.tab}
                    onClose={cerrar} />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
