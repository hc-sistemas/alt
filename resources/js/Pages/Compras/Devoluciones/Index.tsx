import { useState, useEffect, useMemo } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import {
    RotateCcw, Plus, X, CheckCircle, XCircle, Clock, PackageX, Search,
} from 'lucide-react'
import type { PageProps } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ───────────────────────────────────────────────────────────────────

interface Proveedor { id: number; razon_social: string }
interface CompraRef  {
    id: number
    num_documento: string
    proveedor_id: number
    total: number
    saldo_cxp: number | null
}

interface Devolucion {
    id: number
    proveedor: string
    proveedor_id: number
    compra_id: number | null
    num_compra: string | null
    num_documento: string | null
    fecha: string
    motivo: string
    estado: 'pendiente' | 'procesada' | 'anulada'
    subtotal: number
    iva: number
    total: number
}

interface CompraDetalle {
    id: number
    producto_id: number | null
    descripcion: string
    cantidad: number
    precio_unitario: number
    porcentaje_iva: number
}

interface DetalleItem {
    producto_id: number | null
    descripcion: string
    cantidad: number | string
    precio_unitario: number | string
}

interface Filtros {
    estado?: string
    proveedor_id?: string
    fecha_desde?: string
    fecha_hasta?: string
    buscar?: string
}

interface Props extends PageProps {
    devoluciones: Devolucion[] | null
    proveedores:  Proveedor[]
    compras:      CompraRef[]
    filtros:      Filtros
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const notify = {
    ok:    (m: string) => toast.success(m, { style: { background: '#059669', color: '#fff', borderRadius: '12px' } }),
    error: (m: string) => toast.error(m,   { style: { background: '#dc2626', color: '#fff', borderRadius: '12px' } }),
}

const fmt = (n: number) => `$${n.toFixed(2)}`

function EstadoBadge({ estado }: { estado: Devolucion['estado'] }) {
    const cfg = {
        pendiente: { label: 'Pendiente', bg: 'rgba(245,158,11,0.12)', color: '#b45309', icon: Clock },
        procesada: { label: 'Procesada', bg: 'rgba(16,185,129,0.12)', color: '#065f46', icon: CheckCircle },
        anulada:   { label: 'Anulada',   bg: 'rgba(239,68,68,0.12)',  color: '#7f1d1d', icon: XCircle   },
    }[estado] ?? { label: estado, bg: 'rgba(107,114,128,0.12)', color: '#374151', icon: Clock }
    const Icon = cfg.icon
    return (
        <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold"
            style={{ background: cfg.bg, color: cfg.color }}>
            <Icon size={11} /> {cfg.label}
        </span>
    )
}

// ─── Modal Nueva Devolución ───────────────────────────────────────────────────

function NuevaDevolucionModal({ proveedores, compras, onClose }: {
    proveedores: Proveedor[]
    compras:     CompraRef[]
    onClose:     () => void
}) {
    const [proveedorId, setProveedorId]     = useState('')
    const [compraId, setCompraId]           = useState('')
    const [numDoc, setNumDoc]               = useState('')
    const [fecha, setFecha]                 = useState(new Date().toISOString().split('T')[0])
    const [motivo, setMotivo]               = useState('')
    const [pctIva, setPctIva]               = useState(15)
    const [detalles, setDetalles]           = useState<DetalleItem[]>([
        { producto_id: null, descripcion: '', cantidad: 1, precio_unitario: '' }
    ])
    const [processing, setProcessing]       = useState(false)
    const [compraDetalles, setCompraDetalles] = useState<CompraDetalle[]>([])
    const [loadingDetalles, setLoadingDetalles] = useState(false)
    const [selectedDetalles, setSelectedDetalles] = useState<Set<number>>(new Set())

    const comprasDelProveedor = useMemo(() =>
        compras.filter(c => !proveedorId || c.proveedor_id === Number(proveedorId)),
        [compras, proveedorId]
    )

    useEffect(() => {
        if (!compraId) {
            setCompraDetalles([])
            setSelectedDetalles(new Set())
            return
        }
        setLoadingDetalles(true)
        fetch(route('compras.facturas.detalles', compraId))
            .then(r => r.json())
            .then(data => {
                setCompraDetalles(data.detalles ?? [])
                const allIds = new Set<number>((data.detalles ?? []).map((d: CompraDetalle) => d.id))
                setSelectedDetalles(allIds)
            })
            .catch(() => setCompraDetalles([]))
            .finally(() => setLoadingDetalles(false))
    }, [compraId])

    function toggleDetalle(id: number) {
        setSelectedDetalles(prev => {
            const next = new Set(prev)
            if (next.has(id)) next.delete(id)
            else next.add(id)
            return next
        })
    }

    function usarDetallesCompra() {
        const selected = compraDetalles.filter(d => selectedDetalles.has(d.id))
        if (selected.length === 0) return
        setDetalles(selected.map(d => ({
            producto_id:    d.producto_id,
            descripcion:    d.descripcion,
            cantidad:       d.cantidad,
            precio_unitario: d.precio_unitario,
        })))
        setPctIva(compraDetalles[0]?.porcentaje_iva ?? 15)
    }

    function addDetalle() {
        setDetalles(d => [...d, { producto_id: null, descripcion: '', cantidad: 1, precio_unitario: '' }])
    }
    function removeDetalle(i: number) {
        setDetalles(d => d.filter((_, idx) => idx !== i))
    }
    function updateDetalle(i: number, field: keyof DetalleItem, val: string | number) {
        setDetalles(d => d.map((r, idx) => idx === i ? { ...r, [field]: val } : r))
    }

    const subtotal = detalles.reduce((s, d) =>
        s + (Number(d.cantidad) || 0) * (Number(d.precio_unitario) || 0), 0)
    const iva   = subtotal * pctIva / 100
    const total = subtotal + iva

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (!proveedorId || !compraId || !motivo.trim()) return
        setProcessing(true)
        router.post(route('compras.devoluciones.store'), {
            proveedor_id:   proveedorId,
            compra_id:      compraId,
            num_documento:  numDoc || null,
            fecha,
            motivo,
            porcentaje_iva: pctIva,
            detalles: detalles.map(d => ({
                producto_id:     d.producto_id ?? null,
                descripcion:     d.descripcion,
                cantidad:        Number(d.cantidad),
                precio_unitario: Number(d.precio_unitario),
            })),
        }, {
            // El candado (b/c/d) rechaza con back()->with('error', ...), que
            // Inertia trata como redirect exitoso (onSuccess), no como error
            // de validación (onError) — mismo patrón que
            // Compras/Index.tsx::enviarActualizacion() para el candado
            // CxP-02: hay que revisar flash.error dentro de onSuccess.
            onSuccess: (page) => {
                const flash = (page.props as { flash?: { error?: string } }).flash
                if (flash?.error) {
                    notify.error(flash.error)
                } else {
                    notify.ok('Devolución registrada correctamente')
                }
                onClose()
            },
            onError:   (errs) => { notify.error(Object.values(errs).flat().join(' | ') || 'Error al guardar la devolución'); setProcessing(false) },
            onFinish:  () => setProcessing(false),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-2xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2 className="flex items-center gap-2">
                        <RotateCcw className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        Nueva Devolución de Compra
                    </h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>

                <form onSubmit={submit}>
                    <div className="modal-body space-y-4">
                        {/* Fila 1: Proveedor + Compra */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <label className="input-label">Proveedor <span className="text-red-400">*</span></label>
                                <select value={proveedorId} onChange={e => { setProveedorId(e.target.value); setCompraId('') }}
                                    className="input-field select-field" required>
                                    <option value="">— Seleccionar —</option>
                                    {proveedores.map(p => <option key={p.id} value={p.id}>{p.razon_social}</option>)}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <label className="input-label">Compra de origen <span className="text-red-400">*</span></label>
                                <select value={compraId} onChange={e => setCompraId(e.target.value)}
                                    className="input-field select-field" required>
                                    <option value="">— Seleccionar compra —</option>
                                    {comprasDelProveedor.map(c => {
                                        const pagadaCompleto = c.saldo_cxp !== null && c.saldo_cxp <= 0.0001
                                        return (
                                            <option key={c.id} value={c.id} disabled={pagadaCompleto}
                                                title={pagadaCompleto
                                                    ? 'Esta factura ya está pagada al 100% — anule el pago en Bancos antes de poder devolver contra ella'
                                                    : undefined}>
                                                {c.num_documento}{pagadaCompleto ? ' — Pagada al 100% (no disponible)' : ''}
                                            </option>
                                        )
                                    })}
                                </select>
                                {compraId && (() => {
                                    const c = comprasDelProveedor.find(x => String(x.id) === compraId)
                                    return c && c.saldo_cxp !== null ? (
                                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                            Saldo pendiente de esta factura: <strong>${c.saldo_cxp.toFixed(2)}</strong>
                                        </p>
                                    ) : null
                                })()}
                            </div>
                        </div>

                        {/* Detalles de la compra seleccionada */}
                        {compraId && (
                            <div className="rounded-xl border overflow-hidden"
                                style={{ borderColor: 'var(--border)' }}>
                                <div className="flex items-center justify-between px-3 py-2 border-b"
                                    style={{ background: 'rgba(245,158,11,0.07)', borderColor: 'var(--border)' }}>
                                    <span className="text-xs font-semibold flex items-center gap-1.5"
                                        style={{ color: 'var(--text-main)' }}>
                                        <PackageX size={13} style={{ color: 'var(--primary)' }} />
                                        Ítems de la compra
                                    </span>
                                    <button type="button" onClick={usarDetallesCompra}
                                        className="text-xs px-2 py-1 rounded-lg font-medium"
                                        style={{ background: 'var(--primary)', color: '#000' }}>
                                        Usar seleccionados ↓
                                    </button>
                                </div>
                                {loadingDetalles ? (
                                    <p className="text-xs px-3 py-2" style={{ color: 'var(--text-muted)' }}>Cargando...</p>
                                ) : compraDetalles.length === 0 ? (
                                    <p className="text-xs px-3 py-2" style={{ color: 'var(--text-muted)' }}>Sin detalles</p>
                                ) : compraDetalles.map(d => (
                                    <label key={d.id} className="flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-black/5 border-b"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <input type="checkbox" checked={selectedDetalles.has(d.id)}
                                            onChange={() => toggleDetalle(d.id)}
                                            className="rounded w-3.5 h-3.5 accent-amber-500" />
                                        <span className="text-xs flex-1 truncate" style={{ color: 'var(--text-main)' }}>
                                            {d.descripcion}
                                        </span>
                                        <span className="text-xs font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {d.cantidad} × ${d.precio_unitario.toFixed(2)}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        )}

                        {/* Fila 2: N° Nota Crédito + Fecha */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <label className="input-label">N° Nota de Crédito del proveedor</label>
                                <input value={numDoc} onChange={e => setNumDoc(e.target.value)}
                                    className="input-field" placeholder="001-001-000000001" maxLength={30} />
                            </div>
                            <div className="space-y-1.5">
                                <label className="input-label">Fecha <span className="text-red-400">*</span></label>
                                <input type="date" value={fecha} onChange={e => setFecha(e.target.value)}
                                    className="input-field" required />
                            </div>
                        </div>

                        {/* Motivo */}
                        <div className="space-y-1.5">
                            <label className="input-label">Motivo de la devolución <span className="text-red-400">*</span></label>
                            <textarea value={motivo} onChange={e => setMotivo(e.target.value)}
                                rows={2} className="input-field textarea-field"
                                placeholder="Describe el motivo..." minLength={5} required />
                        </div>

                        {/* IVA */}
                        <div className="space-y-1.5">
                            <label className="input-label">% IVA</label>
                            <select value={pctIva} onChange={e => setPctIva(Number(e.target.value))}
                                className="input-field select-field" style={{ width: 'auto' }}>
                                <option value={0}>0% — Exento</option>
                                <option value={5}>5%</option>
                                <option value={8}>8%</option>
                                <option value={12}>12%</option>
                                <option value={15}>15%</option>
                            </select>
                        </div>

                        {/* Detalles */}
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="input-label">Ítems devueltos</label>
                                <button type="button" onClick={addDetalle}
                                    className="text-xs flex items-center gap-1 px-2 py-1 rounded-lg font-medium"
                                    style={{ background: 'rgba(245,158,11,0.12)', color: 'var(--primary)' }}>
                                    <Plus size={12} /> Agregar ítem
                                </button>
                            </div>

                            <div className="rounded-xl border overflow-hidden"
                                style={{ borderColor: 'var(--border)' }}>
                                <div className="grid text-[11px] font-semibold uppercase tracking-wider px-3 py-2"
                                    style={{ gridTemplateColumns: '3fr 1fr 1fr auto', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)', borderBottom: '1px solid var(--border)' }}>
                                    <span>Descripción</span><span>Cantidad</span><span>Precio Unit.</span><span></span>
                                </div>
                                {detalles.map((d, i) => (
                                    <div key={i} className="grid gap-2 px-3 py-2 border-b items-center"
                                        style={{ gridTemplateColumns: '3fr 1fr 1fr auto', borderColor: 'var(--border)' }}>
                                        <input value={d.descripcion}
                                            onChange={e => updateDetalle(i, 'descripcion', e.target.value)}
                                            className="input-field text-xs" placeholder="Descripción del ítem" required />
                                        <input type="number" value={d.cantidad} min={0.001} step="any"
                                            onChange={e => updateDetalle(i, 'cantidad', e.target.value)}
                                            className="input-field text-xs" required />
                                        <input type="number" value={d.precio_unitario} min={0} step="any"
                                            onChange={e => updateDetalle(i, 'precio_unitario', e.target.value)}
                                            className="input-field text-xs" placeholder="0.00" required />
                                        <button type="button" onClick={() => removeDetalle(i)}
                                            disabled={detalles.length === 1}
                                            className="p-1 rounded hover:bg-red-100 disabled:opacity-25">
                                            <X size={14} className="text-red-500" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Totales */}
                        <div className="rounded-xl p-3 space-y-1.5 text-sm"
                            style={{ background: 'rgba(245,158,11,0.05)', border: '1px solid rgba(245,158,11,0.2)' }}>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--text-muted)' }}>Subtotal</span>
                                <span className="font-mono font-semibold" style={{ color: 'var(--text-main)' }}>{fmt(subtotal)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span style={{ color: 'var(--text-muted)' }}>IVA {pctIva}%</span>
                                <span className="font-mono font-semibold" style={{ color: 'var(--text-main)' }}>{fmt(iva)}</span>
                            </div>
                            <div className="flex justify-between border-t pt-1.5 font-bold"
                                style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                <span>TOTAL</span>
                                <span className="font-mono">{fmt(total)}</span>
                            </div>
                        </div>
                    </div>

                    <div className="modal-footer" style={{ justifyContent: 'space-between' }}>
                        <button type="submit" disabled={processing || !proveedorId || !compraId || !motivo.trim()}
                            className="btn-primary flex items-center gap-2"
                            style={{ color: '#000', opacity: (!proveedorId || !compraId || !motivo.trim() || processing) ? 0.6 : 1 }}>
                            <RotateCcw size={15} />
                            {processing ? 'Guardando...' : 'Registrar Devolución'}
                        </button>
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function DevolucionesIndex() {
    const { devoluciones, proveedores, compras, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('compras')

    const [showModal, setShowModal] = useState(false)
    const [buscar, setBuscar]         = useState(filtros.buscar ?? '')
    const [estado, setEstado]         = useState(filtros.estado ?? '')
    const [proveedorId, setProveedorId] = useState(filtros.proveedor_id ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')

    // Cambiar cualquier filtro después de haber buscado marca los
    // resultados como "obsoletos" — la tabla vuelve al estado vacío hasta
    // que se presione Buscar de nuevo (mismo patrón que Cuentas por Pagar/
    // Anticipos Proveedores). Sin esto, cambiar un <select> solo tocaría
    // estado local sin volver a pedir datos, dejando la tabla vieja
    // mezclada con un filtro nuevo todavía sin aplicar.
    const [filtrosSucios, setFiltrosSucios] = useState(false)

    // Carga bajo demanda: `devoluciones` viene null hasta que el usuario
    // presiona Buscar.
    const haBuscado = devoluciones !== null && !filtrosSucios

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash])

    function cambiarBuscar(v: string)      { setBuscar(v);      setFiltrosSucios(true) }
    function cambiarEstado(v: string)      { setEstado(v);      setFiltrosSucios(true) }
    function cambiarProveedorId(v: string) { setProveedorId(v); setFiltrosSucios(true) }
    function cambiarFechaDesde(v: string)  { setFechaDesde(v);  setFiltrosSucios(true) }
    function cambiarFechaHasta(v: string)  { setFechaHasta(v);  setFiltrosSucios(true) }

    function aplicarFiltros() {
        router.get(route('compras.devoluciones.index'), {
            estado, proveedor_id: proveedorId, fecha_desde: fechaDesde, fecha_hasta: fechaHasta, buscar,
            buscado: '1',
        }, {
            preserveState: true,
            replace: true,
            onSuccess: () => setFiltrosSucios(false),
        })
    }

    function limpiar() {
        setEstado(''); setProveedorId(''); setFechaDesde(''); setFechaHasta(''); setBuscar('')
        router.get(route('compras.devoluciones.index'), {}, { preserveState: false })
    }

    const hayFiltros = !!(estado || proveedorId || fechaDesde || fechaHasta || buscar)

    function confirmarAnular(dev: Devolucion) {
        const motivo = window.prompt('Motivo de la anulación (mínimo 5 caracteres):')
        if (!motivo || motivo.trim().length < 5) return
        router.patch(route('compras.devoluciones.anular', dev.id), { motivo }, {
            onSuccess: () => notify.ok(`Devolución #${dev.id} anulada`),
            onError:   () => notify.error('Error al anular'),
        })
    }

    return (
        <AppLayout title="Devoluciones de Compra" suppressFlash>
            <Head title="Devoluciones de Compra" />

            <PageHeader
                title="Devoluciones de Compra"
                breadcrumbs={[{ label: 'Compras' }, { label: 'Devoluciones' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setShowModal(true)}
                            className="flex items-center gap-2 whitespace-nowrap shrink-0 px-4 py-2 rounded-xl font-semibold text-sm text-black transition-all hover:opacity-90"
                            style={{ background: 'var(--primary)' }}>
                            <Plus size={15} /> Nueva Devolución
                        </button>
                    ) : undefined
                }
            />

            <div className="p-4 md:p-6 space-y-5" style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/*
                    Ancho vía `style.width` inline a propósito, NO clases Tailwind: `.input-field`
                    (app.css) declara `width:100%` fuera de cualquier @layer, y las utilidades de
                    Tailwind v4 viven dentro de su @layer utilities interno — por reglas de CSS
                    Cascade Layers, lo no-layereado siempre gana sobre lo layereado sin importar
                    especificidad ni orden, así que un w-XX de Tailwind nunca puede ganarle a
                    `.input-field`. Mismo hallazgo documentado en Asientos/Facturas de Compra/
                    Proveedores/Cuentas por Pagar/Anticipos Proveedores.
                */}
                <div className="overflow-x-auto">
                <div style={{ minWidth: '1100px' }}>
                <FilterToolbar
                    search={{
                        value: buscar,
                        onChange: cambiarBuscar,
                        onSearch: aplicarFiltros,
                        placeholder: 'Proveedor, N° doc...',
                    }}
                    searchWidth="w-[150px]"
                >
                    <select value={estado} onChange={e => cambiarEstado(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '160px' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="procesada">Procesada</option>
                        <option value="anulada">Anulada</option>
                    </select>

                    <select value={proveedorId} onChange={e => cambiarProveedorId(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '190px' }}>
                        <option value="">Todos los proveedores</option>
                        {proveedores.map(p => <option key={p.id} value={p.id}>{p.razon_social}</option>)}
                    </select>

                    {/* Labels "Desde"/"Hasta" apilados sobre el input: el bloque
                        queda más alto que los selects de al lado (sin label), así
                        que se alinean al final de la fila (self-end) en vez de al
                        centro por defecto del FilterToolbar (items-center) — evita
                        que el input de fecha quede descolgado respecto al resto. */}
                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Desde</label>
                        <input type="date" value={fechaDesde} onChange={e => cambiarFechaDesde(e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '150px' }} />
                    </div>
                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                        <input type="date" value={fechaHasta} onChange={e => cambiarFechaHasta(e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '150px' }} />
                    </div>

                    {hayFiltros && (
                        <button type="button" onClick={limpiar} className="text-sm underline shrink-0 self-end" style={{ color: 'var(--text-muted)' }}>
                            Limpiar
                        </button>
                    )}
                </FilterToolbar>
                </div>
                </div>

                {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar las devoluciones.
                        </p>
                    </div>
                )}

                {/* Tabla */}
                {haBuscado && (
                <>
                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    {devoluciones.length} devolución(es) encontrada(s)
                </p>
                <div className="rounded-2xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="grid gap-2 px-4 py-3 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{
                            gridTemplateColumns: '2fr 1.2fr 1fr 1fr 1fr 1fr 1fr auto',
                            borderColor: 'var(--border)',
                            background: 'rgba(245,158,11,0.05)',
                            color: 'var(--text-muted)',
                        }}>
                        <span>Proveedor</span>
                        <span>N° Nota Crédito</span>
                        <span>Compra Ref.</span>
                        <span>Fecha</span>
                        <span className="text-right">Subtotal</span>
                        <span className="text-right">IVA</span>
                        <span>Estado</span>
                        <span>Acc.</span>
                    </div>

                    {devoluciones.length === 0 ? (
                        <div className="py-16 text-center">
                            <RotateCcw className="w-10 h-10 mx-auto mb-3 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No se encontraron devoluciones con estos filtros
                            </p>
                        </div>
                    ) : devoluciones.map((dev, i) => (
                        <div key={dev.id}
                            className="grid gap-2 px-4 py-3 border-b items-center text-sm"
                            style={{
                                gridTemplateColumns: '2fr 1.2fr 1fr 1fr 1fr 1fr 1fr auto',
                                borderColor: 'var(--border)',
                                background: i % 2 === 0 ? 'transparent'
                                    : 'color-mix(in srgb, var(--bg-main) 30%, transparent)',
                            }}>
                            <div>
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                    {dev.proveedor ?? '—'}
                                </p>
                                <p className="text-[11px] truncate" style={{ color: 'var(--text-muted)' }}>
                                    {dev.motivo}
                                </p>
                            </div>
                            <p className="text-xs font-mono" style={{ color: 'var(--text-muted)' }}>
                                {dev.num_documento ?? '—'}
                            </p>
                            <p className="text-xs font-mono" style={{ color: 'var(--text-muted)' }}>
                                {dev.num_compra ?? '—'}
                            </p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{dev.fecha}</p>
                            <p className="text-xs font-mono text-right" style={{ color: 'var(--text-main)' }}>
                                {fmt(dev.subtotal)}
                            </p>
                            <p className="text-xs font-mono text-right" style={{ color: 'var(--text-muted)' }}>
                                {fmt(dev.iva)}
                            </p>
                            <EstadoBadge estado={dev.estado} />
                            <div>
                                {dev.estado !== 'anulada' && puede('anular') && (
                                    <button onClick={() => confirmarAnular(dev)}
                                        title="Anular devolución"
                                        className="p-1.5 rounded-lg transition-colors hover:bg-red-100 dark:hover:bg-red-900/30">
                                        <XCircle size={15} className="text-red-500" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
                </>
                )}
            </div>

            {showModal && (
                <NuevaDevolucionModal
                    proveedores={proveedores}
                    compras={compras}
                    onClose={() => setShowModal(false)}
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
