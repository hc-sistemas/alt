import { useState, useEffect, useMemo, useCallback } from 'react'
import { router, usePage, useForm, Head, Link } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { formatFecha } from '@/utils/contabilidad'
import {
    Plus, X, FileText, Download, ChevronLeft, ChevronRight, ChevronDown,
    Eye, ShoppingCart, Trash2, CreditCard, Pencil, Search, Loader2,
    Barcode, CheckCircle, XCircle, RefreshCw, Upload, AlertTriangle,
} from 'lucide-react'
import type { Compra, Importacion, Proveedor, CentroCosto, PlanCuenta, Bodega, PageProps, PaginatedData, Producto, EtiquetaDetalleData, EtiquetaGrupoProducto } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

type ProductoRow = Pick<Producto, 'id' | 'codigo' | 'nombre' | 'unidad' | 'costo' | 'porcentaje_iva' | 'tipo' | 'pvp'>

interface Filtros {
    buscar?: string
    estado?: string
    fecha_desde?: string
    fecha_hasta?: string
}

type ImportacionResumen = Pick<Importacion, 'id' | 'nombre' | 'pais_embarque' | 'costo_fob' | 'divisa'>

interface PrefillExterior {
    tipo_documento: string
    proveedor_id: number | null
    num_documento: string
    fecha_emision: string
    importacion_id: number
    sustento_tributario: string
    dias_credito: number
    metodo_envio: string
    divisa: string
    concepto: string
}

interface Props extends PageProps {
    compras: PaginatedData<Compra> | null
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'nombre_comercial' | 'identificacion' | 'tiene_credito' | 'dias_credito' | 'tipo'>[]
    centros: Pick<CentroCosto, 'id' | 'nombre' | 'codigo'>[]
    cuentas: Pick<PlanCuenta, 'id' | 'codigo' | 'nombre'>[]
    bodegas: Pick<Bodega, 'id' | 'nombre' | 'tipo'>[]
    productos: ProductoRow[]
    importacionesActivas: ImportacionResumen[]
    prefillExterior: PrefillExterior | null
    filtros: Filtros
}

interface DetalleEdicion {
    id: number
    producto_id: number | null
    cuenta_id: number | null
    descripcion: string
    cantidad: number
    peso: number | null
    precio_unitario: number
    descuento: number
    porcentaje_iva: number
    es_activo_fijo: boolean
}

interface DetalleItem {
    producto_id: number | null
    codigo: string
    descripcion: string
    cantidad: number | string
    /** Peso real (kg) de esta línea — opcional, no bloquea el guardado si está
     *  vacío. Alimenta el método de prorrateo "Peso" en Importaciones como
     *  override del estimado (cantidad × peso unitario del producto) cuando
     *  el usuario ingresa el peso real facturado/medido de esa línea. */
    peso: string
    precio_unitario: number | string
    descuento: number | string
    descuento_pct: string
    porcentaje_iva: number | string
    cuenta_id: string | number
    es_activo_fijo: boolean
    /** Unidad del producto seleccionado ('unidad', 'kg', etc. — ver Productos/Form.tsx)
     *  solo para mostrar junto a Cantidad; no afecta el cálculo (cantidad × precio_unitario
     *  funciona igual sin importar la unidad, solo cambia qué representa "cantidad"). */
    unidad: string
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

// ─── Fila detalle editable ────────────────────────────────────────────────────

const DETALLE_COLS = '130px 1fr 70px 85px 90px 80px 70px 80px 70px 80px 36px'
const ROW_INPUT_HEIGHT = '32px'

interface DetalleRowProps {
    detalle: DetalleItem
    idx: number
    cuentas: Props['cuentas']
    onChange: (idx: number, field: keyof DetalleItem, value: string | number | boolean | null) => void
    onRemove: (idx: number) => void
    onAbrirModal: (idx: number) => void
    tipoDocumento: string
}

function DetalleRow({ detalle, idx, cuentas, onChange, onRemove, onAbrirModal, tipoDocumento }: DetalleRowProps) {
    const { subtotal, iva, total } = calcDetalle(detalle)
    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)', height: ROW_INPUT_HEIGHT }

    return (
        <div className="border-b text-xs"
            style={{ display: 'grid', gridTemplateColumns: DETALLE_COLS, alignItems: 'center', borderColor: 'var(--border)' }}
            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.03)')}
            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>

            {/* ── Código ── */}
            <div className="px-2 py-2">
                <button
                    type="button"
                    onClick={() => onAbrirModal(idx)}
                    title={detalle.codigo ? `${detalle.codigo} — clic para cambiar` : 'Clic para buscar producto'}
                    className="w-full flex items-center justify-between gap-1 px-2 border rounded text-xs transition-all"
                    style={{
                        background: detalle.producto_id
                            ? 'color-mix(in srgb, #10b981 10%, var(--bg-main))'
                            : 'var(--bg-main)',
                        borderColor: detalle.producto_id ? '#10b981' : 'var(--border)',
                        cursor: 'pointer',
                        height: ROW_INPUT_HEIGHT,
                    }}>
                    <span style={{
                        fontFamily: detalle.codigo ? 'monospace' : 'inherit',
                        fontWeight: detalle.codigo ? 'bold' : 'normal',
                        color: detalle.codigo ? 'var(--primary)' : 'var(--text-muted)',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                    }}>
                        {detalle.codigo || 'Buscar…'}
                    </span>
                    {detalle.producto_id ? (
                        <span style={{ color: '#10b981', fontSize: '11px', flexShrink: 0 }}>✓</span>
                    ) : (
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" strokeWidth="2"
                             style={{ flexShrink: 0, color: 'var(--text-muted)' }}>
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    )}
                </button>
            </div>

            {/* ── Descripción ── */}
            <div className="px-2 py-2">
                <input
                    className="w-full px-2 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                    value={detalle.descripcion}
                    onChange={e => onChange(idx, 'descripcion', e.target.value)}
                    placeholder="Descripción del bien/servicio"
                />
            </div>

            {/* ── Cantidad ──
                Antes forzaba enteros (step=1, bloqueaba '.'/',' con Math.floor) aunque el
                backend YA acepta decimales (CompraController::store(), 'detalles.*.cantidad'
                => 'required|numeric|min:0.0001') — necesario para productos por peso (kg) u
                otras unidades fraccionables (metro, hora). El cálculo de subtotal (calcDetalle,
                arriba) ya funciona igual con decimales, no dependía de que fuera entero.

                type="text" en vez de type="number": los inputs number nativos aplican
                agrupación de miles dependiente de la configuración regional del SO/navegador
                (p.ej. Windows/Chrome en español-Ecuador puede mostrar "2,070" al escribir
                "2.07"), lo que corrompe el valor mientras se edita. Con texto + regex se
                controla el formato explícitamente: solo dígitos y un único punto decimal,
                nunca comas ni separadores de miles. */}
            <div className="px-1 py-2">
                <input
                    type="text" inputMode="decimal"
                    value={detalle.cantidad}
                    onChange={e => {
                        const raw = e.target.value
                        if (raw === '' || /^\d*\.?\d*$/.test(raw)) {
                            onChange(idx, 'cantidad', raw)
                        }
                    }}
                    placeholder="0.00"
                    className="w-full px-1.5 border rounded text-xs text-center focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                />
                <p className="text-center mt-1 truncate" style={{ fontSize: '9px', color: 'var(--text-muted)' }} title={detalle.unidad || undefined}>
                    {detalle.unidad || '—'}
                </p>
            </div>

            {/* ── Peso (kg) — opcional, alimenta el prorrateo "Peso" en Importaciones.
                Columna ensanchada a 85px (antes 70px) porque a 70px el placeholder
                "Opcional" se truncaba visualmente ("Opdona"); el texto del placeholder
                se mantiene completo a propósito — el ancho es lo que se ajustó. ── */}
            <div className="px-1 py-2">
                <input
                    type="text" inputMode="decimal"
                    value={detalle.peso}
                    onChange={e => {
                        const raw = e.target.value
                        if (raw === '' || /^\d*\.?\d*$/.test(raw)) {
                            onChange(idx, 'peso', raw)
                        }
                    }}
                    placeholder="Opcional"
                    title="Peso real de esta línea (kg) — opcional"
                    className="w-full px-1.5 border rounded text-xs text-center focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                />
            </div>

            {/* ── P. Unitario ── */}
            <div className="px-1 py-2">
                <input type="number" step="0.0001" min={0}
                    className="w-full px-2 border rounded text-xs text-right focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}
                    value={detalle.precio_unitario}
                    onChange={e => onChange(idx, 'precio_unitario', e.target.value)}
                />
            </div>

            {/* ── Descuento ── */}
            <div className="px-1 py-2 flex flex-col gap-1 items-center">
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
                    className="w-full rounded px-1 text-xs border text-center focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-main)', color: 'var(--text-main)', height: ROW_INPUT_HEIGHT }}
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
                <span className="text-[10px] font-mono" style={{ color: 'var(--primary)' }}>
                    -{parseFloat(String(detalle.descuento) || '0').toFixed(2)}
                </span>
            </div>

            {/* ── IVA % ── */}
            <div className="px-1 py-2">
                {tipoDocumento === 'EXT' ? (
                    <div className="w-full px-1 border rounded text-xs text-center cursor-not-allowed opacity-60 flex items-center justify-center"
                        style={{ ...inputStyle, background: 'var(--bg-main)' }}
                        title="Facturas del exterior no generan IVA local">
                        0%
                    </div>
                ) : (
                    <select
                        className="w-full px-1 border rounded text-xs focus:outline-none focus:ring-1 focus:ring-amber-500"
                        style={inputStyle}
                        value={detalle.porcentaje_iva}
                        onChange={e => onChange(idx, 'porcentaje_iva', e.target.value)}>
                        <option value={0}>0%</option>
                        <option value={15}>15%</option>
                    </select>
                )}
            </div>

            {/* ── Subtotal ── */}
            <div className="px-2 py-2 text-right font-medium tabular-nums"
                style={{ color: 'var(--text-main)' }}>
                {subtotal.toFixed(2)}
            </div>

            {/* ── IVA ── */}
            <div className="px-2 py-2 text-right tabular-nums"
                style={{ color: 'var(--text-muted)' }}>
                {iva.toFixed(2)}
            </div>

            {/* ── Total ── */}
            <div className="px-2 py-2 text-right font-bold tabular-nums"
                style={{ color: 'var(--primary)' }}>
                {total.toFixed(2)}
            </div>

            {/* ── Eliminar ── */}
            <div className="flex items-center justify-center">
                <button type="button" onClick={() => onRemove(idx)}
                    className="p-1 rounded hover:bg-red-500/20 text-red-500 transition-colors">
                    <Trash2 className="w-3 h-3" />
                </button>
            </div>
        </div>
    )
}

// ─── Modal Nueva Compra ───────────────────────────────────────────────────────

interface NuevaCompraModalProps {
    proveedores: Props['proveedores']
    centros: Props['centros']
    cuentas: Props['cuentas']
    bodegas: Props['bodegas']
    productos: ProductoRow[]
    importacionesActivas: ImportacionResumen[]
    centroMatrizId: number | null
    bodegaDefaultId: number | null
    initialValues?: Partial<PrefillExterior>
    editando?: Compra
    detallesEdicion?: DetalleEdicion[]
    onClose: () => void
}

function NuevaCompraModal({ proveedores, centros, cuentas, bodegas, productos, importacionesActivas, centroMatrizId, bodegaDefaultId, initialValues, editando, detallesEdicion, onClose }: NuevaCompraModalProps) {
    const [tab, setTab] = useState<'datos' | 'detalle' | 'centro'>('datos')

    const isExt = (editando?.tipo_documento ?? initialValues?.tipo_documento) === 'EXT'

    const { data, setData, post, put, processing, errors } = useForm<{
        proveedor_id: string | number
        tipo_documento: string
        num_documento: string
        num_autorizacion: string
        fecha_emision: string
        dias_credito: number | string
        iva_asumido: boolean
        gasto_no_deducible: boolean
        retencion_ir: string | number
        retencion_iva: string | number
        sustento_tributario: string
        concepto: string
        centro_costo_id: string | number
        bodega_id: string | number
        detalles: DetalleItem[]
        importacion_id: string | number
        metodo_envio: string
        divisa: string
        tipo_cambio: string | number
        num_orden_compra: string
        num_contrato: string
        vigencia_desde: string
        vigencia_hasta: string
    }>({
        proveedor_id:        editando?.proveedor_id ?? initialValues?.proveedor_id ?? '',
        tipo_documento:      editando?.tipo_documento ?? initialValues?.tipo_documento ?? 'FAC',
        num_documento:       editando?.num_documento ?? initialValues?.num_documento ?? '',
        num_autorizacion:    editando?.num_autorizacion ?? '',
        fecha_emision:       editando?.fecha_emision ?? initialValues?.fecha_emision ?? new Date().toISOString().slice(0, 10),
        dias_credito:        editando?.dias_credito ?? initialValues?.dias_credito ?? 0,
        iva_asumido:         editando?.iva_asumido ?? false,
        gasto_no_deducible:  editando?.gasto_no_deducible ?? false,
        retencion_ir:        editando?.retencion_ir ?? 0,
        retencion_iva:       editando?.retencion_iva ?? 0,
        sustento_tributario: editando?.sustento_tributario != null ? String(editando.sustento_tributario) : (initialValues?.sustento_tributario ?? '01'),
        concepto:            editando?.concepto ?? initialValues?.concepto ?? '',
        centro_costo_id:     editando?.centro_costo_id ? String(editando.centro_costo_id) : (centroMatrizId ? String(centroMatrizId) : ''),
        bodega_id:           editando?.bodega_id ? String(editando.bodega_id) : (bodegaDefaultId ? String(bodegaDefaultId) : ''),
        detalles: detallesEdicion && detallesEdicion.length > 0
            ? detallesEdicion.map(d => {
                const base = d.cantidad * d.precio_unitario
                const pct  = base > 0 ? String(parseFloat(((d.descuento / base) * 100).toFixed(2))) : '0'
                const prod = d.producto_id ? productos.find(p => p.id === d.producto_id) : null
                return {
                    producto_id:     d.producto_id,
                    codigo:          prod?.codigo ?? '',
                    descripcion:     d.descripcion,
                    cantidad:        d.cantidad,
                    peso:            d.peso != null ? String(d.peso) : '',
                    precio_unitario: String(d.precio_unitario),
                    descuento:       d.descuento,
                    descuento_pct:   pct,
                    porcentaje_iva:  d.porcentaje_iva,
                    cuenta_id:       d.cuenta_id ?? '',
                    es_activo_fijo:  d.es_activo_fijo,
                    unidad:          prod?.unidad ?? '',
                }
            })
            : [{
                producto_id: null, codigo: '',
                descripcion: '', cantidad: 1, peso: '', precio_unitario: '',
                descuento: 0, descuento_pct: '0', porcentaje_iva: isExt ? 0 : 15,
                cuenta_id: '', es_activo_fijo: false, unidad: '',
            }],
        importacion_id:   editando?.importacion_id ?? initialValues?.importacion_id ?? '',
        metodo_envio:     editando?.metodo_envio ?? initialValues?.metodo_envio ?? 'FOB',
        divisa:           editando?.divisa ?? initialValues?.divisa ?? 'USD',
        tipo_cambio:      editando?.tipo_cambio ?? '',
        num_orden_compra: editando?.num_orden_compra ?? '',
        num_contrato:     editando?.num_contrato ?? '',
        vigencia_desde:   editando?.vigencia_desde ?? '',
        vigencia_hasta:   editando?.vigencia_hasta ?? '',
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

    const SUSTENTO_DEFAULT: Record<string, string> = {
        FAC: '01', LIQ: '01', TIK: '07', CON: '02', EXT: '06',
    }

    function handleTipoChange(tipo: string) {
        setData(prev => ({
            ...prev,
            tipo_documento:      tipo,
            sustento_tributario: SUSTENTO_DEFAULT[tipo] ?? '01',
            num_autorizacion:    ['EXT', 'TIK'].includes(tipo) ? '' : prev.num_autorizacion,
            dias_credito:        tipo === 'TIK' ? 0 : prev.dias_credito,
            importacion_id:      ['EXT', 'LIQ'].includes(tipo) ? prev.importacion_id : '',
            metodo_envio:        tipo === 'EXT' ? prev.metodo_envio : 'FOB',
            divisa:              tipo === 'EXT' ? prev.divisa : 'USD',
            tipo_cambio:         tipo === 'EXT' ? prev.tipo_cambio : '',
            num_orden_compra:    tipo === 'EXT' ? prev.num_orden_compra : '',
            num_contrato:        tipo === 'CON' ? prev.num_contrato : '',
            vigencia_desde:      tipo === 'CON' ? prev.vigencia_desde : '',
            vigencia_hasta:      tipo === 'CON' ? prev.vigencia_hasta : '',
            // IVA 0 obligatorio en Exterior; restaurar 15 al cambiar a otro tipo
            detalles: prev.detalles.map(d => ({
                ...d,
                porcentaje_iva: tipo === 'EXT' ? 0
                    : (Number(d.porcentaje_iva) === 0 ? 15 : d.porcentaje_iva),
            })),
        }))
    }

    const updateDetalle = useCallback((idx: number, field: keyof DetalleItem, value: string | number | boolean | null) => {
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
            producto_id: null, codigo: '',
            descripcion: '', cantidad: 1, peso: '', precio_unitario: '',
            descuento: 0, descuento_pct: '0',
            porcentaje_iva: prev.tipo_documento === 'EXT' ? 0 : 15,
            cuenta_id: '', es_activo_fijo: false, unidad: '',
        }],
    }))

    const handleSelectProducto = useCallback((idx: number, p: ProductoRow | null) => {
        setData(prev => ({
            ...prev,
            detalles: prev.detalles.map((d, i) => {
                if (i !== idx) return d
                if (!p) return { ...d, producto_id: null, unidad: '' }
                return {
                    ...d,
                    producto_id:     p.id,
                    codigo:          p.codigo,
                    descripcion:     p.nombre,
                    precio_unitario: String(p.costo),
                    porcentaje_iva:  prev.tipo_documento === 'EXT' ? 0 : Number(p.porcentaje_iva),
                    unidad:          p.unidad || 'unidad',
                }
            }),
        }))
    }, [])

    const removeDetalle = (idx: number) => setData(prev => ({
        ...prev,
        detalles: prev.detalles.filter((_, i) => i !== idx),
    }))

    // ── Modal búsqueda de producto ──────────────────────────
    // Carga bajo demanda: no se muestra ni filtra nada del catálogo hasta que
    // el usuario escriba al menos 2 caracteres (mismo patrón que Productos de
    // Inventario / Asientos Contables) — con debounce para no recalcular en
    // cada pulsación mientras el usuario sigue escribiendo.
    const [modalProductos,   setModalProductos]   = useState(false)
    const [idxDetalleActivo, setIdxDetalleActivo] = useState(0)
    const [busquedaProducto, setBusquedaProducto] = useState('')
    const [busquedaDebounced, setBusquedaDebounced] = useState('')

    useEffect(() => {
        const t = setTimeout(() => setBusquedaDebounced(busquedaProducto), 250)
        return () => clearTimeout(t)
    }, [busquedaProducto])

    const productosFiltrados = useMemo(() => {
        const q = busquedaDebounced.toLowerCase().trim()
        if (q.length < 2) return []
        return productos.filter(p =>
            p.codigo.toLowerCase().includes(q) ||
            p.nombre.toLowerCase().includes(q) ||
            p.tipo.toLowerCase().includes(q)
        ).slice(0, 30)
    }, [busquedaDebounced, productos])

    const abrirModalProductos = (idx: number) => {
        setIdxDetalleActivo(idx)
        setBusquedaProducto('')
        setModalProductos(true)
    }

    const seleccionarProducto = (p: ProductoRow) => {
        handleSelectProducto(idxDetalleActivo, p)
        setModalProductos(false)
        setBusquedaProducto('')
    }

    function enviarActualizacion() {
        put(route('compras.facturas.update', editando!.id), {
            onSuccess: (page) => {
                const flash = (page as any).props?.flash
                if (flash?.error) {
                    notify.error(flash.error)
                } else {
                    notify.ok(`Compra ${data.num_documento} actualizada`)
                }
                onClose()
            },
            onError: (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    async function submit(e: React.FormEvent) {
        e.preventDefault()

        if (editando) {
            if (editando.estado === 'activa') {
                const result = await Swal.fire({
                    ...swalBase,
                    icon: 'warning',
                    title: 'Editar factura activa',
                    html: `<p style="color:#374151;font-size:13px;line-height:1.6">
                               Esta factura ya generó movimientos contables y de inventario.
                               Editar revertirá y regenerará esos efectos con los datos corregidos.
                           </p>`,
                    confirmButtonText: 'Sí, editar y regenerar',
                    cancelButtonText:  'Cancelar',
                    confirmButtonColor: '#f59e0b',
                    cancelButtonColor:  '#6b7280',
                })
                if (!result.isConfirmed) return
            }
            enviarActualizacion()
            return
        }

        post(route('compras.facturas.store'), {
            onSuccess: (page) => {
                const flash = (page as any).props?.flash
                if (flash?.error) {
                    notify.error(flash.error)
                } else {
                    notify.ok(`Compra ${data.num_documento} registrada`)
                }
                onClose()
            },
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
        <>
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-5xl max-h-[90vh]" onClick={e => e.stopPropagation()}>

                {/* Header */}
                <div className="modal-header shrink-0">
                    <h2>{editando ? `Editar factura ${editando.num_documento}` : 'Nueva factura de compra'}</h2>
                    <button className="modal-close" onClick={onClose}>
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

                                {/* Proveedor — para EXT mostrar solo internacionales */}
                                <div className="space-y-1.5">
                                    <Label>
                                        Proveedor{' '}
                                        {data.tipo_documento !== 'TIK' && <span className="text-red-400">*</span>}
                                        {data.tipo_documento === 'EXT' && (
                                            <span className="ml-2 text-xs font-normal" style={{ color: 'var(--text-muted)' }}>
                                                (solo internacionales)
                                            </span>
                                        )}
                                    </Label>
                                    <select value={data.proveedor_id}
                                        onChange={e => setData('proveedor_id', e.target.value)}
                                        className="input-field select-field">
                                        <option value="">— Seleccionar proveedor —</option>
                                        {(data.tipo_documento === 'EXT'
                                            ? proveedores.filter(p => p.tipo === 'internacional')
                                            : proveedores
                                        ).map(p => (
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
                                            onChange={e => handleTipoChange(e.target.value)}
                                            className="input-field select-field">
                                            <option value="FAC">Factura</option>
                                            <option value="LIQ">Liquidación</option>
                                            <option value="TIK">Ticket</option>
                                            <option value="CON">Contrato</option>
                                            <option value="EXT">Exterior</option>
                                        </select>
                                    </div>
                                    <div className="col-span-2 space-y-1.5">
                                        <Label># Documento <span className="text-red-400">*</span></Label>
                                        <Input value={data.num_documento}
                                            onChange={e => setData('num_documento', e.target.value)}
                                            error={errors.num_documento}
                                            placeholder={data.tipo_documento === 'EXT' ? 'Invoice Number' : '001-001-000000001'} />
                                        {errors.num_documento && <p className="text-red-400 text-xs">{errors.num_documento}</p>}
                                    </div>
                                </div>

                                {/* Autorización + Fecha (FAC, LIQ, CON) */}
                                {!['EXT', 'TIK'].includes(data.tipo_documento) && (
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
                                )}

                                {/* Solo fecha (EXT, TIK) */}
                                {['EXT', 'TIK'].includes(data.tipo_documento) && (
                                    <div className="space-y-1.5">
                                        <Label>
                                            {data.tipo_documento === 'EXT' ? 'Fecha de la invoice' : 'Fecha emisión'}
                                            {' '}<span className="text-red-400">*</span>
                                        </Label>
                                        <Input type="date" value={data.fecha_emision}
                                            onChange={e => setData('fecha_emision', e.target.value)}
                                            error={errors.fecha_emision} />
                                    </div>
                                )}

                                {/* Campos específicos Exterior */}
                                {data.tipo_documento === 'EXT' && (
                                    <>
                                        <div className="grid grid-cols-2 gap-3">
                                            <div className="space-y-1.5">
                                                <Label>Método de envío</Label>
                                                <select value={data.metodo_envio}
                                                    onChange={e => setData('metodo_envio', e.target.value)}
                                                    className="input-field select-field">
                                                    <option value="FOB">FOB — Free On Board</option>
                                                    <option value="CIF">CIF — Cost Insurance Freight</option>
                                                    <option value="EXW">EXW — Ex Works</option>
                                                    <option value="DAP">DAP — Delivered At Place</option>
                                                </select>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label>Divisa</Label>
                                                <select value={data.divisa}
                                                    onChange={e => setData('divisa', e.target.value)}
                                                    className="input-field select-field">
                                                    <option value="USD">USD — Dólar</option>
                                                    <option value="EUR">EUR — Euro</option>
                                                    <option value="CNY">CNY — Yuan Chino</option>
                                                    <option value="JPY">JPY — Yen Japonés</option>
                                                </select>
                                            </div>
                                        </div>
                                        {data.divisa !== 'USD' && (
                                            <div className="space-y-1.5">
                                                <Label>Tipo de cambio (1 {data.divisa} = ? USD)</Label>
                                                <Input type="number" step="0.0001" min={0.0001}
                                                    value={data.tipo_cambio}
                                                    onChange={e => setData('tipo_cambio', e.target.value)}
                                                    placeholder="Ej: 1.0850" />
                                            </div>
                                        )}
                                        <div className="space-y-1.5">
                                            <Label>
                                                N° Orden de compra{' '}
                                                <span className="text-xs font-normal" style={{ color: 'var(--text-muted)' }}>(opcional)</span>
                                            </Label>
                                            <Input value={data.num_orden_compra}
                                                onChange={e => setData('num_orden_compra', e.target.value)}
                                                placeholder="Purchase Order Number" />
                                        </div>
                                    </>
                                )}

                                {/* Importación vinculada (EXT y LIQ) */}
                                {['EXT', 'LIQ'].includes(data.tipo_documento) && (
                                    <div className="space-y-1.5">
                                        <Label>Importación vinculada</Label>
                                        <select value={data.importacion_id}
                                            onChange={e => setData('importacion_id', e.target.value)}
                                            className="input-field select-field">
                                            <option value="">— Sin importación —</option>
                                            {importacionesActivas.map(imp => (
                                                <option key={imp.id} value={imp.id}>
                                                    {imp.nombre} · {imp.pais_embarque ?? '—'} · ${Number(imp.costo_fob).toLocaleString()} {imp.divisa}
                                                </option>
                                            ))}
                                        </select>
                                        {importacionesActivas.length === 0 && (
                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                                No hay importaciones activas.{' '}
                                                <a href={route('compras.importaciones.index')}
                                                    className="underline hover:no-underline"
                                                    style={{ color: 'var(--primary)' }}>
                                                    Crear importación
                                                </a>
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Campos específicos Contrato */}
                                {data.tipo_documento === 'CON' && (
                                    <div className="grid grid-cols-3 gap-3">
                                        <div className="space-y-1.5">
                                            <Label>N° Contrato</Label>
                                            <Input value={data.num_contrato}
                                                onChange={e => setData('num_contrato', e.target.value)}
                                                placeholder="CONT-2024-001" />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Vigencia desde</Label>
                                            <Input type="date" value={data.vigencia_desde}
                                                onChange={e => setData('vigencia_desde', e.target.value)} />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Vigencia hasta</Label>
                                            <Input type="date" value={data.vigencia_hasta}
                                                onChange={e => setData('vigencia_hasta', e.target.value)} />
                                        </div>
                                    </div>
                                )}

                                {/* Días crédito (oculto para TIK) + Sustento */}
                                {data.tipo_documento !== 'TIK' ? (
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
                                        {data.tipo_documento !== 'EXT' ? (
                                            <div className="space-y-1.5">
                                                <Label>Sustento tributario</Label>
                                                <Input type="number" min={1} max={99}
                                                    value={data.sustento_tributario}
                                                    onChange={e => setData('sustento_tributario', e.target.value)}
                                                    placeholder="01, 02, 03..." />
                                            </div>
                                        ) : (
                                            <div className="space-y-1.5">
                                                <Label>Sustento tributario</Label>
                                                <p className="text-sm font-medium px-3 py-2 rounded-lg border"
                                                    style={{ borderColor: 'var(--border)', color: 'var(--primary)' }}>
                                                    06 — Importación de bienes
                                                </p>
                                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Asignado automáticamente</p>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-1.5">
                                        <Label>Sustento tributario</Label>
                                        <Input type="number" min={1} max={99}
                                            value={data.sustento_tributario}
                                            onChange={e => setData('sustento_tributario', e.target.value)}
                                            placeholder="07 — Contribuyente RISE" />
                                    </div>
                                )}

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

                                {/* CxP-03: nota visual cuando gasto no deducible */}
                                {data.gasto_no_deducible && (
                                    <div className="rounded-lg border px-3 py-2.5 text-xs"
                                        style={{ borderColor: '#fbbf24', background: 'rgba(245,158,11,0.08)', color: '#92400e' }}>
                                        <span className="font-bold">Gasto No Deducible activo:</span> El egreso se registrará al 100% en la cuenta 5.4.1.01 (Gastos No Deducibles Locales), sin crédito tributario de IVA y sin generación de retenciones.
                                    </div>
                                )}

                                {/* Retenciones — ocultas si gasto no deducible (CxP-03) */}
                                {data.tipo_documento !== 'EXT' && !data.gasto_no_deducible && (
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label>Ret. IR ($)</Label>
                                            <Input type="number" min={0} step="0.01"
                                                value={data.retencion_ir}
                                                onChange={e => setData('retencion_ir', e.target.value)}
                                                placeholder="0.00" />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Ret. IVA ($)</Label>
                                            <Input type="number" min={0} step="0.01"
                                                value={data.retencion_iva}
                                                onChange={e => setData('retencion_iva', e.target.value)}
                                                placeholder="0.00" />
                                        </div>
                                    </div>
                                )}

                                {/* Concepto */}
                                <div className="space-y-1.5">
                                    <Label>Concepto / Descripción general</Label>
                                    <textarea value={data.concepto}
                                        onChange={e => setData('concepto', e.target.value)}
                                        rows={2}
                                        className="input-field textarea-field"
                                        placeholder="Descripción general de la compra..." />
                                </div>
                            </div>
                        )}

                        {/* ── Tab 2: Detalle ── */}
                        {tab === 'detalle' && (
                            <div className="space-y-5">
                                <div className="rounded-lg border overflow-x-auto" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                                    {/* Header — mismo padding horizontal/vertical que las filas (DetalleRow)
                                        para que encabezado y columnas queden perfectamente alineados. */}
                                    <div className="border-b text-xs font-semibold uppercase tracking-wide"
                                        style={{
                                            display: 'grid', gridTemplateColumns: DETALLE_COLS,
                                            alignItems: 'center',
                                            borderColor: 'var(--border)',
                                            background: 'rgba(245,158,11,0.05)',
                                            color: 'var(--text-muted)',
                                        }}>
                                        <div className="px-2 py-2.5">Código</div>
                                        <div className="px-2 py-2.5">Descripción</div>
                                        <div className="px-1 py-2.5 text-right">Cant.</div>
                                        <div className="px-1 py-2.5 text-right">Peso (kg)</div>
                                        <div className="px-1 py-2.5 text-right">P. Unit.</div>
                                        <div className="px-1 py-2.5 text-center">Desc.</div>
                                        <div className="px-1 py-2.5 text-center">IVA%</div>
                                        <div className="px-2 py-2.5 text-right">Subtotal</div>
                                        <div className="px-2 py-2.5 text-right">IVA</div>
                                        <div className="px-2 py-2.5 text-right">Total</div>
                                        <div />
                                    </div>
                                    {/* Rows */}
                                    <div>
                                        {data.detalles.map((d, idx) => (
                                            <DetalleRow key={idx} detalle={d} idx={idx}
                                                cuentas={cuentas}
                                                onChange={updateDetalle}
                                                onRemove={removeDetalle}
                                                onAbrirModal={abrirModalProductos}
                                                tipoDocumento={data.tipo_documento} />
                                        ))}
                                    </div>
                                </div>

                                <button type="button" onClick={addDetalle}
                                    className="flex items-center gap-2 text-sm font-medium px-3 py-2 rounded-lg border transition-colors hover:opacity-80"
                                    style={{ color: 'var(--primary)', borderColor: 'var(--primary)', background: 'rgba(245,158,11,0.08)' }}>
                                    <Plus className="w-4 h-4" /> Agregar línea
                                </button>

                                {/* Totales */}
                                <div className="ml-auto max-w-sm rounded-xl border overflow-hidden"
                                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                                    <div className="px-4 py-2.5 border-b"
                                        style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}>
                                        <p className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>
                                            Resumen
                                        </p>
                                    </div>
                                    <div className="p-4 space-y-2.5">
                                        <div className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--text-muted)' }}>Subtotal 0%</span>
                                            <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                ${totales.subtotal0.toFixed(2)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--text-muted)' }}>Subtotal gravado</span>
                                            <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                ${totales.subtotalIva.toFixed(2)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span style={{ color: 'var(--text-muted)' }}>
                                                IVA {data.gasto_no_deducible ? '(no deducible)' : ''}
                                            </span>
                                            <span className={cn('font-medium tabular-nums', data.gasto_no_deducible && 'line-through opacity-50')}
                                                style={{ color: 'var(--text-main)' }}>
                                                ${totales.totalIva.toFixed(2)}
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-base font-bold border-t pt-3 mt-1 tabular-nums"
                                            style={{ borderColor: 'var(--border)', color: 'var(--primary)' }}>
                                            <span>TOTAL</span>
                                            <span>${totales.total.toFixed(2)}</span>
                                        </div>
                                        {Number(data.dias_credito) > 0 && (
                                            <p className="text-xs text-center pt-1" style={{ color: 'var(--text-muted)' }}>
                                                CxP: ${totales.total.toFixed(2)} a {data.dias_credito} días
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {errors.detalles && (
                                    <p className="text-red-400 text-xs">{errors.detalles as unknown as string}</p>
                                )}
                            </div>
                        )}

                        {/* ── Tab 3: Centro de costo y Bodega ── */}
                        {tab === 'centro' && (
                            <div className="space-y-4 max-w-md">
                                <div className="space-y-1.5">
                                    <Label>Centro de costo</Label>
                                    <select value={data.centro_costo_id}
                                        onChange={e => setData('centro_costo_id', e.target.value)}
                                        className="input-field select-field">
                                        <option value="">— Sin centro de costo —</option>
                                        {centros.map(c => (
                                            <option key={c.id} value={c.id}>{c.codigo} — {c.nombre}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Bodega destino</Label>
                                    <select value={data.bodega_id}
                                        onChange={e => setData('bodega_id', e.target.value)}
                                        className="input-field select-field">
                                        <option value="">— Sin ingreso a inventario —</option>
                                        {bodegas.map(b => (
                                            <option key={b.id} value={b.id}>{b.nombre}</option>
                                        ))}
                                    </select>
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                        Si seleccionas una bodega, los detalles con producto ingresarán al stock automáticamente.
                                    </p>
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
                    <div className="modal-footer shrink-0 justify-between">
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
                                <Plus className="w-4 h-4" /> {editando ? 'Guardar cambios' : 'Registrar compra'}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {/* ═══ MODAL BÚSQUEDA DE PRODUCTOS ═══ */}
        {modalProductos && (
            <div className="modal-overlay" style={{ zIndex: 60 }}
                 onClick={() => setModalProductos(false)}>
                <div className="modal-card"
                     style={{ maxWidth: '680px', maxHeight: '85vh',
                              display: 'flex', flexDirection: 'column' }}
                     onClick={e => e.stopPropagation()}>

                    {/* Header */}
                    <div className="modal-header">
                        <h2 style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <svg width="18" height="18" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" strokeWidth="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            Buscar Producto
                        </h2>
                        <button className="modal-close"
                                onClick={() => setModalProductos(false)}>
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    {/* Buscador */}
                    <div style={{ padding: '16px 20px',
                                  borderBottom: '1px solid var(--border)' }}>
                        <div className="input-with-icon">
                            <svg className="input-icon" width="16" height="16"
                                 viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" strokeWidth="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input
                                autoFocus
                                type="text"
                                value={busquedaProducto}
                                onChange={e => setBusquedaProducto(e.target.value)}
                                placeholder="Buscar por código, nombre o tipo..."
                                className="input-field"
                            />
                        </div>
                        {busquedaProducto.trim().length >= 2 && (
                            <p style={{ fontSize: '12px', color: 'var(--text-muted)',
                                        marginTop: '6px' }}>
                                {productosFiltrados.length} producto(s) encontrado(s) para "{busquedaProducto}"
                            </p>
                        )}
                    </div>

                    {/* Lista */}
                    <div style={{ overflowY: 'auto', flex: 1 }}>
                        {busquedaProducto.trim().length < 2 ? (
                            <div style={{ textAlign: 'center', padding: '40px 20px',
                                          color: 'var(--text-muted)' }}>
                                <svg width="40" height="40" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" strokeWidth="1.5"
                                     style={{ margin: '0 auto 12px', display: 'block',
                                              opacity: 0.3 }}>
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <p style={{ fontWeight: 600 }}>Escribe para buscar</p>
                                <p style={{ fontSize: '12px', marginTop: '4px' }}>
                                    Ingresa al menos 2 caracteres (código, nombre o tipo)
                                </p>
                            </div>
                        ) : productosFiltrados.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: '40px 20px',
                                          color: 'var(--text-muted)' }}>
                                <svg width="40" height="40" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" strokeWidth="1.5"
                                     style={{ margin: '0 auto 12px', display: 'block',
                                              opacity: 0.3 }}>
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                <p style={{ fontWeight: 600 }}>Sin resultados</p>
                                <p style={{ fontSize: '12px', marginTop: '4px' }}>
                                    Intenta con otro término
                                </p>
                            </div>
                        ) : (
                            <table style={{ width: '100%', borderCollapse: 'collapse',
                                            fontSize: '13px' }}>
                                <thead>
                                    <tr style={{ background: 'var(--bg-main)',
                                                 position: 'sticky', top: 0, zIndex: 1 }}>
                                        {['Código','Nombre','Tipo','Unidad',
                                          'Costo','PVP','IVA%',''].map(h => (
                                            <th key={h} style={{
                                                padding: '8px 12px',
                                                textAlign: h === 'Costo' || h === 'PVP'
                                                    ? 'right' : 'left',
                                                fontWeight: 600, fontSize: '11px',
                                                textTransform: 'uppercase',
                                                letterSpacing: '0.5px',
                                                color: 'var(--text-muted)',
                                                borderBottom: '1px solid var(--border)',
                                                whiteSpace: 'nowrap',
                                            }}>
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {productosFiltrados.map((p, i) => (
                                        <tr key={p.id}
                                            onClick={() => seleccionarProducto(p)}
                                            style={{
                                                cursor: 'pointer',
                                                borderBottom: '1px solid var(--border)',
                                                background: i % 2 === 0
                                                    ? 'transparent'
                                                    : 'color-mix(in srgb, var(--bg-main) 50%, transparent)',
                                            }}
                                            onMouseEnter={e => {
                                                (e.currentTarget as HTMLElement).style.background =
                                                    'color-mix(in srgb, var(--primary) 8%, transparent)'
                                            }}
                                            onMouseLeave={e => {
                                                (e.currentTarget as HTMLElement).style.background =
                                                    i % 2 === 0 ? 'transparent'
                                                    : 'color-mix(in srgb, var(--bg-main) 50%, transparent)'
                                            }}>
                                            <td style={{ padding: '9px 12px',
                                                         fontFamily: 'monospace',
                                                         fontWeight: 'bold',
                                                         color: 'var(--primary)',
                                                         whiteSpace: 'nowrap' }}>
                                                {p.codigo}
                                            </td>
                                            <td style={{ padding: '9px 12px',
                                                         color: 'var(--text-main)',
                                                         fontWeight: 500,
                                                         maxWidth: '220px' }}>
                                                <div style={{ overflow: 'hidden',
                                                              textOverflow: 'ellipsis',
                                                              whiteSpace: 'nowrap' }}>
                                                    {p.nombre}
                                                </div>
                                            </td>
                                            <td style={{ padding: '9px 12px' }}>
                                                <span style={{
                                                    display: 'inline-block',
                                                    padding: '2px 7px',
                                                    borderRadius: '6px',
                                                    fontSize: '11px', fontWeight: 600,
                                                    background: p.tipo === 'servicio'
                                                        ? '#dbeafe'
                                                        : p.tipo === 'repuesto'
                                                            ? '#fef3c7' : '#d1fae5',
                                                    color: p.tipo === 'servicio'
                                                        ? '#1e40af'
                                                        : p.tipo === 'repuesto'
                                                            ? '#92400e' : '#065f46',
                                                }}>
                                                    {p.tipo}
                                                </span>
                                            </td>
                                            <td style={{ padding: '9px 12px',
                                                         color: 'var(--text-muted)',
                                                         fontSize: '12px' }}>
                                                {p.unidad}
                                            </td>
                                            <td style={{ padding: '9px 12px',
                                                         textAlign: 'right',
                                                         fontFamily: 'monospace',
                                                         fontWeight: 600,
                                                         color: '#10b981',
                                                         whiteSpace: 'nowrap' }}>
                                                ${Number(p.costo).toFixed(2)}
                                            </td>
                                            <td style={{ padding: '9px 12px',
                                                         textAlign: 'right',
                                                         fontFamily: 'monospace',
                                                         color: 'var(--text-muted)',
                                                         fontSize: '12px',
                                                         whiteSpace: 'nowrap' }}>
                                                ${Number(p.pvp).toFixed(2)}
                                            </td>
                                            <td style={{ padding: '9px 12px',
                                                         textAlign: 'center',
                                                         whiteSpace: 'nowrap' }}>
                                                <span style={{
                                                    display: 'inline-block',
                                                    padding: '1px 5px',
                                                    borderRadius: '4px',
                                                    fontSize: '11px', fontWeight: 600,
                                                    background: '#dbeafe', color: '#1e40af',
                                                }}>
                                                    {p.porcentaje_iva}%
                                                </span>
                                            </td>
                                            <td style={{ padding: '9px 10px',
                                                         textAlign: 'center' }}>
                                                <span style={{
                                                    display: 'inline-block',
                                                    padding: '3px 10px',
                                                    borderRadius: '6px',
                                                    fontSize: '11px', fontWeight: 600,
                                                    background: 'var(--primary)',
                                                    color: 'white',
                                                }}>
                                                    Seleccionar
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="modal-footer"
                         style={{ justifyContent: 'space-between',
                                  alignItems: 'center' }}>
                        <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>
                            Clic en una fila para seleccionar el producto
                        </p>
                        <button className="btn-secondary"
                                onClick={() => setModalProductos(false)}>
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        )}
        </>
    )
}

// ─── Modal Etiquetas ──────────────────────────────────────────────────────────

interface EtiquetasModalProps {
    compra: Compra
    onClose: () => void
    abrirPdf: (url: string) => void
    onGenerada: (compraId: number) => void
}

function EtiquetasModal({ compra, onClose, abrirPdf, onGenerada }: EtiquetasModalProps) {
    const [cargando,  setCargando]  = useState(true)
    const [detalles,  setDetalles]  = useState<EtiquetaDetalleData[]>([])
    const [generando, setGenerando] = useState(false)

    useEffect(() => {
        fetch(route('compras.facturas.etiquetas-data', compra.id), {
            headers: { 'Accept': 'application/json' },
        })
            .then(async r => {
                const json = await r.json() as { detalles?: EtiquetaDetalleData[]; error?: string }
                if (!r.ok || json.error) throw new Error(json.error ?? `Error ${r.status}`)
                setDetalles(json.detalles ?? [])
                setCargando(false)
            })
            .catch((err: unknown) => {
                const msg = err instanceof Error ? err.message : 'Error al cargar datos'
                notify.error(msg)
                onClose()
            })
    }, [compra.id])

    async function generar() {
        setGenerando(true)
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
            const res  = await fetch(route('compras.facturas.etiquetas-pdf', compra.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    productos: detalles.map(d => ({
                        producto_id:   d.producto_id,
                        detalle_id:    d.id,
                        codigo:        d.codigo,
                        descripcion:   d.descripcion,
                        num_etiquetas: d.num_etiquetas,
                    })),
                }),
            })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Error desconocido' })) as { message?: string }
                notify.error(err.message ?? 'Error al generar PDF')
                return
            }
            const blob = await res.blob()
            const url  = URL.createObjectURL(blob)
            abrirPdf(url)
            onGenerada(compra.id)
            onClose()
        } catch {
            notify.error('Error al conectar con el servidor')
        } finally {
            setGenerando(false)
        }
    }

    const totalEtiquetas = detalles.reduce((sum, d) => sum + d.num_etiquetas, 0)

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-lg" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2 className="flex items-center gap-2" style={{ color: 'var(--text-main)' }}>
                        <Barcode className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        Generar Etiquetas — {compra.num_documento}
                    </h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>

                <div className="modal-body space-y-4">
                    {cargando ? (
                        <p className="text-center py-8 text-sm" style={{ color: 'var(--text-muted)' }}>Cargando datos…</p>
                    ) : detalles.length === 0 ? (
                        <p className="text-center py-8 text-sm" style={{ color: 'var(--text-muted)' }}>
                            Esta factura no tiene productos con código registrado.
                        </p>
                    ) : (
                        <>
                            {/* Tabla: CÓDIGO | PRODUCTO | CANT. | ÚLT. ETIQUETA */}
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wider mb-2"
                                    style={{ color: 'var(--text-muted)' }}>
                                    Productos en esta factura
                                </p>
                                <div className="border rounded-xl overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr style={{ background: 'rgba(245,158,11,0.05)', borderBottom: '1px solid var(--border)' }}>
                                                <th className="px-3 py-2 font-semibold uppercase tracking-wider text-left"
                                                    style={{ color: 'var(--text-muted)' }}>Código</th>
                                                <th className="px-3 py-2 font-semibold uppercase tracking-wider text-left"
                                                    style={{ color: 'var(--text-muted)' }}>Producto</th>
                                                <th className="px-2 py-2 font-semibold uppercase tracking-wider text-center"
                                                    style={{ color: 'var(--text-muted)' }}>Cant.</th>
                                                <th className="px-2 py-2 font-semibold uppercase tracking-wider text-center"
                                                    style={{ color: 'var(--text-muted)' }}>Últ. etiq.</th>
                                                <th className="px-2 py-2 font-semibold uppercase tracking-wider text-center"
                                                    style={{ color: 'var(--text-muted)' }}>Desde</th>
                                                <th className="px-2 py-2 font-semibold uppercase tracking-wider text-center"
                                                    style={{ color: 'var(--text-muted)' }}>Hasta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {detalles.map(d => (
                                                <tr key={d.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                                    <td className="px-3 py-2 font-mono font-bold text-[11px]"
                                                        style={{ color: 'var(--primary)' }}>
                                                        {d.codigo}
                                                    </td>
                                                    <td className="px-3 py-2 text-[11px] truncate"
                                                        style={{ color: 'var(--text-main)', maxWidth: '160px' }}>
                                                        {d.nombre}
                                                    </td>
                                                    <td className="px-2 py-2 font-bold text-center text-[11px]"
                                                        style={{ color: 'var(--text-main)' }}>
                                                        {d.num_etiquetas}
                                                    </td>
                                                    <td className="px-2 py-2 text-center font-mono text-[11px]"
                                                        style={{ color: 'var(--text-muted)' }}>
                                                        {d.ultima_etiqueta_prefijo > 0 ? d.ultima_etiqueta_prefijo : '—'}
                                                    </td>
                                                    <td className="px-2 py-2 text-center font-mono text-[11px]"
                                                        style={{ color: 'var(--text-main)' }}>
                                                        {d.desde}
                                                    </td>
                                                    <td className="px-2 py-2 text-center font-mono text-[11px]"
                                                        style={{ color: 'var(--text-main)' }}>
                                                        {d.hasta}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Total */}
                            <div className="flex justify-between items-center text-sm pt-1"
                                style={{ borderTop: '1px solid var(--border)', paddingTop: '0.75rem' }}>
                                <span style={{ color: 'var(--text-muted)' }}>Total de etiquetas a generar</span>
                                <span className="font-bold text-base" style={{ color: 'var(--primary)' }}>
                                    {totalEtiquetas}
                                </span>
                            </div>
                        </>
                    )}
                </div>

                <div className="modal-footer">
                    {!cargando && detalles.length > 0 && (
                        <button onClick={generar} disabled={generando} className="btn-primary flex items-center gap-2">
                            <Barcode className="w-4 h-4" />
                            {generando ? 'Generando…' : 'Generar'}
                        </button>
                    )}
                    <button type="button" className="btn-secondary" onClick={onClose}>Cancelar</button>
                </div>
            </div>
        </div>
    )
}

// ─── Tipo escenario anulación ─────────────────────────────────────────────────

type EscenarioAnulacion =
    | { escenario: 'A';       mensaje: string }
    | { escenario: 'B';       mensaje: string; monto: number; banco: string }
    | { escenario: 'C';       mensaje: string; productos_vendidos: Array<{ nombre: string; cantidad_salida: number }> }
    | { escenario: 'ANULADA'; mensaje: string }

interface VerificacionEdicion {
    puede: boolean
    motivo: string | null
    es_activa?: boolean
}

// ─── Página principal ─────────────────────────────────────────────────────────

// ─── Modal Cargar XML SRI ─────────────────────────────────────────────────────

function CargarXmlModal({ onParsed, onClose }: {
    onParsed: (data: Partial<PrefillExterior>) => void
    onClose: () => void
}) {
    const [archivo, setArchivo] = useState<File | null>(null)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    async function submit(e: React.FormEvent) {
        e.preventDefault()
        if (!archivo) return
        setLoading(true)
        setError('')
        const form = new FormData()
        form.append('archivo', archivo)
        form.append('_token', (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '')
        try {
            const res = await fetch(route('compras.facturas.parsear-xml'), { method: 'POST', body: form })
            const json = await res.json()
            if (!json.ok) { setError(json.message ?? 'Error al procesar el XML'); setLoading(false); return }
            onParsed({
                proveedor_id:   json.proveedor_id ?? null,
                num_documento:  json.num_documento ?? '',
                fecha_emision:  json.fecha_emision ?? '',
                tipo_documento: 'FAC',
                sustento_tributario: '01',
                dias_credito: 0,
            })
        } catch {
            setError('No se pudo conectar al servidor')
            setLoading(false)
        }
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2 className="flex items-center gap-2">
                        <Upload className="w-5 h-5" style={{ color: '#0891b2' }} />
                        Cargar Factura XML del SRI
                    </h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                    <div className="modal-body space-y-4">
                        <div className="rounded-lg p-3 text-xs"
                            style={{ background: 'rgba(8,145,178,0.07)', border: '1px solid rgba(8,145,178,0.2)', color: 'var(--text-muted)' }}>
                            <p>Sube el XML de la factura del proveedor emitido por el SRI. Se pre-llenarán automáticamente el proveedor, número de documento y fecha.</p>
                        </div>
                        <div className="space-y-1.5">
                            <label className="input-label">Archivo XML <span className="text-red-400">*</span></label>
                            <input type="file" accept=".xml"
                                onChange={e => { setArchivo(e.target.files?.[0] ?? null); setError('') }}
                                className="input-field" style={{ padding: '6px 10px' }} />
                        </div>
                        {error && (
                            <div className="rounded-lg p-3 text-xs"
                                style={{ background: 'rgba(239,68,68,0.07)', border: '1px solid rgba(239,68,68,0.2)', color: '#dc2626' }}>
                                {error}
                            </div>
                        )}
                    </div>
                    <div className="modal-footer" style={{ justifyContent: 'space-between' }}>
                        <button type="submit" disabled={!archivo || loading}
                            className="btn-primary flex items-center gap-2"
                            style={{ background: '#0891b2', color: '#fff', opacity: (!archivo || loading) ? 0.6 : 1 }}>
                            <Upload size={15} />
                            {loading ? 'Procesando...' : 'Cargar XML'}
                        </button>
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    )
}

type ModalState =
    | { type: 'none' }
    | { type: 'nueva' }
    | { type: 'editar'; compra: Compra; detalles: DetalleEdicion[] }
    | { type: 'etiquetas'; compra: Compra }
    | { type: 'reimprimir-etiquetas'; compra: Compra }

// ─── Modal Reimprimir Etiquetas ───────────────────────────────────────────────

interface ReimprimirEtiquetasModalProps {
    compra: Compra
    onClose: () => void
    abrirPdf: (url: string) => void
}

function ReimprimirEtiquetasModal({ compra, onClose, abrirPdf }: ReimprimirEtiquetasModalProps) {
    const [cargando,      setCargando]      = useState(true)
    const [grupos,        setGrupos]        = useState<EtiquetaGrupoProducto[]>([])
    const [expandidos,    setExpandidos]    = useState<Set<string>>(new Set())
    const [seleccionadas, setSeleccionadas] = useState<Set<string>>(new Set())
    const [enviando,      setEnviando]      = useState(false)

    useEffect(() => {
        fetch(route('compras.facturas.etiquetas-listado', compra.id), {
            headers: { Accept: 'application/json' },
        })
            .then(async r => {
                const json = await r.json() as { productos?: EtiquetaGrupoProducto[]; error?: string }
                if (!r.ok || json.error) throw new Error(json.error ?? `Error ${r.status}`)
                const prods = json.productos ?? []
                setGrupos(prods)
                if (prods.length > 0) setExpandidos(new Set([prods[0].codigo]))
                setCargando(false)
            })
            .catch((err: unknown) => {
                notify.error(err instanceof Error ? err.message : 'Error al cargar etiquetas')
                onClose()
            })
    }, [compra.id])

    const totalEtiquetas    = grupos.reduce((s, g) => s + g.etiquetas.length, 0)
    const cantSeleccionadas = seleccionadas.size

    function toggleExpand(codigo: string) {
        setExpandidos(prev => {
            const next = new Set(prev)
            next.has(codigo) ? next.delete(codigo) : next.add(codigo)
            return next
        })
    }

    function toggleEtiqueta(cod: string) {
        setSeleccionadas(prev => {
            const next = new Set(prev)
            next.has(cod) ? next.delete(cod) : next.add(cod)
            return next
        })
    }

    function seleccionarGrupo(grupo: EtiquetaGrupoProducto) {
        setSeleccionadas(prev => {
            const next = new Set(prev)
            grupo.etiquetas.forEach(e => next.add(e))
            return next
        })
    }

    function limpiarGrupo(grupo: EtiquetaGrupoProducto) {
        setSeleccionadas(prev => {
            const next = new Set(prev)
            grupo.etiquetas.forEach(e => next.delete(e))
            return next
        })
    }

    async function enviar() {
        if (cantSeleccionadas === 0 || enviando) return
        setEnviando(true)
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''
            const res  = await fetch(route('compras.facturas.etiquetas-reimprimir-seleccion', compra.id), {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body:    JSON.stringify({ etiquetas: [...seleccionadas] }),
            })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Error desconocido' })) as { message?: string }
                notify.error(err.message ?? 'Error al generar PDF')
                return
            }
            const blob = await res.blob()
            abrirPdf(URL.createObjectURL(blob))
            onClose()
        } catch {
            notify.error('Error al conectar con el servidor')
        } finally {
            setEnviando(false)
        }
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2 className="flex items-center gap-2" style={{ color: 'var(--text-main)' }}>
                        <RefreshCw className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        Reimprimir Etiquetas — {compra.num_documento}
                    </h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>

                <div className="modal-body" style={{ maxHeight: '60vh', overflowY: 'auto' }}>
                    {cargando ? (
                        <p className="text-center py-8 text-sm" style={{ color: 'var(--text-muted)' }}>Cargando etiquetas…</p>
                    ) : grupos.length === 0 ? (
                        <p className="text-center py-8 text-sm" style={{ color: 'var(--text-muted)' }}>
                            No hay etiquetas generadas para esta factura.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {grupos.map(grupo => {
                                const expandido  = expandidos.has(grupo.codigo)
                                const todosSelec = grupo.etiquetas.every(e => seleccionadas.has(e))
                                return (
                                    <div key={grupo.codigo} className="border rounded-xl overflow-hidden"
                                        style={{ borderColor: 'var(--border)' }}>

                                        {/* Header accordion */}
                                        <button
                                            onClick={() => toggleExpand(grupo.codigo)}
                                            className="w-full flex items-center gap-2 px-4 py-2.5 text-left text-sm font-semibold transition-colors hover:opacity-80"
                                            style={{ background: 'rgba(245,158,11,0.06)', color: 'var(--text-main)' }}>
                                            <ChevronDown
                                                className={cn('w-4 h-4 shrink-0 transition-transform', !expandido && '-rotate-90')}
                                                style={{ color: 'var(--primary)' }} />
                                            <span className="font-mono" style={{ color: 'var(--primary)' }}>{grupo.codigo}</span>
                                            <span className="font-normal truncate" style={{ color: 'var(--text-muted)' }}>
                                                — {grupo.nombre}
                                            </span>
                                            <span className="ml-auto shrink-0 text-xs font-normal"
                                                style={{ color: 'var(--text-muted)' }}>
                                                {grupo.etiquetas.length} etiq.
                                            </span>
                                        </button>

                                        {expandido && (
                                            <div className="px-4 py-3 space-y-3"
                                                style={{ borderTop: '1px solid var(--border)' }}>

                                                {/* Grid de checkboxes */}
                                                <div className="grid grid-cols-3 gap-x-2 gap-y-1.5">
                                                    {grupo.etiquetas.map(cod => (
                                                        <label key={cod}
                                                            className="flex items-center gap-1.5 cursor-pointer group">
                                                            <input
                                                                type="checkbox"
                                                                checked={seleccionadas.has(cod)}
                                                                onChange={() => toggleEtiqueta(cod)}
                                                                className="w-3.5 h-3.5 shrink-0 accent-amber-500"
                                                            />
                                                            <span className="font-mono text-[10px] truncate group-hover:opacity-70"
                                                                style={{ color: 'var(--text-main)' }}>
                                                                {cod}
                                                            </span>
                                                        </label>
                                                    ))}
                                                </div>

                                                {/* Botones de grupo */}
                                                <div className="flex gap-2 pt-1"
                                                    style={{ borderTop: '1px solid var(--border)' }}>
                                                    <button
                                                        onClick={() => seleccionarGrupo(grupo)}
                                                        disabled={todosSelec}
                                                        className="btn-secondary text-xs px-2.5 py-1 disabled:opacity-40">
                                                        Seleccionar todos
                                                    </button>
                                                    <button
                                                        onClick={() => limpiarGrupo(grupo)}
                                                        className="btn-secondary text-xs px-2.5 py-1">
                                                        Quitar selección
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )
                            })}
                        </div>
                    )}
                </div>

                <div className="modal-footer flex items-center justify-between">
                    <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                        Seleccionadas:{' '}
                        <strong style={{ color: cantSeleccionadas > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>
                            {cantSeleccionadas}
                        </strong>
                        {' '}de {totalEtiquetas}
                    </span>
                    <div className="flex gap-2">
                        <button className="btn-secondary" onClick={onClose}>Cancelar</button>
                        <button
                            className="btn-primary"
                            disabled={cantSeleccionadas === 0 || enviando}
                            onClick={enviar}>
                            {enviando ? 'Generando…' : 'Reimprimir Seleccionadas'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function ComprasIndex() {
    const { compras, proveedores, centros, cuentas, bodegas, productos, importacionesActivas, filtros, flash, prefillExterior } = usePage<Props>().props
    const { puede } = usePermiso('compras')

    // Estado local de filas — permite actualizar una fila sin recargar la página
    const [comprasData, setComprasData] = useState(compras?.data ?? [])
    const actualizarCompra = (id: number, cambios: Partial<Compra>) =>
        setComprasData(prev => prev.map(c => c.id === id ? { ...c, ...cambios } : c))

    // Sincronizar cuando Inertia actualiza los props (filtros, paginación)
    useEffect(() => { setComprasData(compras?.data ?? []) }, [compras])

    // Carga bajo demanda: mismo patrón que Asientos Contables — `compras` es
    // `null` hasta que el usuario presiona Buscar (aplicarFiltros manda `buscado=1`).
    const haBuscado = compras !== null

    const [modal, setModal] = useState<ModalState>(prefillExterior ? { type: 'nueva' } : { type: 'none' })
    const [modalXml, setModalXml] = useState(false)
    const [xmlPrefill, setXmlPrefill] = useState<Partial<PrefillExterior> | null>(null)
    const [buscar, setBuscar]       = useState(filtros.buscar ?? '')
    const [estado, setEstado]       = useState(filtros.estado ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')
    const [modalPdf, setModalPdf] = useState(false)
    const [urlPdf,   setUrlPdf]   = useState('')
    const [cargandoPdf, setCargandoPdf] = useState(false)

    // Se trae el PDF como blob (fetch) en vez de apuntar el <iframe> directo a la
    // URL del backend: aunque el backend ya responde con Content-Disposition:
    // inline, algunos navegadores igual fuerzan la descarga o dejan el iframe en
    // blanco según su propia configuración de manejo de PDF. Un blob: URL siempre
    // se muestra embebido — mismo patrón que Asientos Contables.
    // Si `url` ya es un blob: (algunos llamadores, como EtiquetasModal, generan el
    // PDF vía POST con body y ya convierten a blob ellos mismos), se usa directo.
    const abrirPdf = async (url: string) => {
        setModalPdf(true)
        if (url.startsWith('blob:')) {
            setUrlPdf(url)
            return
        }
        setCargandoPdf(true)
        setUrlPdf('')
        try {
            const res = await fetch(url, { headers: { Accept: 'application/pdf' } })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: null })) as { message?: string | null }
                throw new Error(err.message ?? 'No se pudo generar el PDF.')
            }
            const blob = await res.blob()
            setUrlPdf(URL.createObjectURL(blob))
        } catch (e) {
            notify.error(e instanceof Error ? e.message : 'No se pudo generar el PDF. Intenta de nuevo.')
            setModalPdf(false)
        } finally {
            setCargandoPdf(false)
        }
    }

    const cerrarModalPdf = () => {
        if (urlPdf) URL.revokeObjectURL(urlPdf)
        setModalPdf(false)
        setUrlPdf('')
    }

    // ── Reporte PDF grande (> MAX_FILAS_PDF): ofrecer generarlo en segundo
    //    plano en vez de solo bloquear — mismo patrón que Asientos Contables
    //    (contar-exportables -> Swal "Reporte grande" -> Job en cola ->
    //    notificación campana cuando termina). ──────────────────────────────
    const [verificandoExportPdf, setVerificandoExportPdf] = useState(false)
    const [exportandoFondo, setExportandoFondo] = useState<{ desde: number } | null>(null)

    const construirUrlPdf = () =>
        `${route('compras.facturas.pdf')}?estado=${estado}&fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`

    const confirmarExportacionSegundoPlano = () => {
        router.post(route('compras.facturas.pdf-segundo-plano'), {
            estado, fecha_desde: fechaDesde, fecha_hasta: fechaHasta,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setExportandoFondo({ desde: Date.now() }),
        })
    }

    const iniciarExportacionPdf = async () => {
        setVerificandoExportPdf(true)
        try {
            const params = new URLSearchParams({ estado, fecha_desde: fechaDesde, fecha_hasta: fechaHasta })
            const res = await fetch(route('compras.facturas.contar-pdf') + '?' + params)
            if (!res.ok) throw new Error()
            const data = await res.json() as { total: number; limite: number; excede: boolean }

            if (!data.excede) {
                abrirPdf(construirUrlPdf())
                return
            }

            const { isConfirmed } = await Swal.fire({
                ...swalBase,
                title: 'Reporte grande',
                html: `
                    <div style="text-align:left;color:#374151;font-size:0.875rem;line-height:1.5">
                        <p>Este reporte tiene <strong>${data.total.toLocaleString('es-EC')}</strong> facturas con
                        estos filtros — muy grande para generarse al instante (límite: ${data.limite.toLocaleString('es-EC')}).</p>
                        <p style="margin-top:8px">Se procesará en segundo plano y te avisaremos por notificación
                        (campanita) cuando esté listo para descargar.</p>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#F59E0B',
                confirmButtonText: 'Procesar en segundo plano',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            })

            if (isConfirmed) confirmarExportacionSegundoPlano()
        } catch {
            notify.error('No se pudo verificar el tamaño del reporte. Intenta de nuevo.')
        } finally {
            setVerificandoExportPdf(false)
        }
    }

    // Sin websockets/polling en el backend — se consulta el mismo endpoint que
    // ya usa la campana de notificaciones (notificaciones.index) cada 15s,
    // mientras haya una exportación en curso, hasta encontrarla o 10 minutos.
    useEffect(() => {
        if (!exportandoFondo) return
        const intervalo = setInterval(async () => {
            if (Date.now() - exportandoFondo.desde > 10 * 60 * 1000) {
                setExportandoFondo(null)
                return
            }
            try {
                const res = await fetch(route('notificaciones.index'))
                if (!res.ok) return
                const data = await res.json() as { notificaciones: { tipo: string; created_at: string }[] }
                const lista = data.notificaciones.some(n =>
                    (n.tipo === 'exportacion_compras' || n.tipo === 'exportacion_compras_error') &&
                    new Date(n.created_at).getTime() >= exportandoFondo.desde
                )
                if (lista) {
                    notify.ok('Tu reporte terminó de procesarse — revisa la campana de notificaciones para descargarlo.')
                    setExportandoFondo(null)
                }
            } catch { /* red momentáneamente caída — se reintenta en el próximo tick */ }
        }, 15000)
        return () => clearInterval(intervalo)
    }, [exportandoFondo])

    function reimprimir(c: Compra) {
        setModal({ type: 'reimprimir-etiquetas', compra: c })
    }

    function confirmarRecepcion(c: Compra) {
        if ((c as any).recepcion_bodega) {
            router.visit(route('inventario.recepciones.show', (c as any).recepcion_bodega.id) + '?redirect_to=' + encodeURIComponent(route('compras.facturas.index')))
        } else {
            notify.error('Esta compra no tiene una recepción de bodega asociada')
        }
    }

    async function iniciarAnulacion(c: Compra) {
        let escData: EscenarioAnulacion
        try {
            const res  = await fetch(route('compras.facturas.verificar-anulacion', c.id), {
                headers: { Accept: 'application/json' },
            })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Error del servidor' })) as { message?: string }
                notify.error(err.message ?? 'Error al verificar la compra')
                return
            }
            escData = await res.json() as EscenarioAnulacion
        } catch {
            notify.error('Error al verificar el estado de la compra.')
            return
        }

        if (escData.escenario === 'ANULADA') {
            notify.error(escData.mensaje)
            return
        }

        // ── Escenario C: bloqueado — productos con ventas ───────────────────────
        if (escData.escenario === 'C') {
            const listaHtml = escData.productos_vendidos
                .map(d => `<li><b>${d.nombre}</b> — ${d.cantidad_salida} unid. vendidas</li>`)
                .join('')
            void Swal.fire({
                icon: 'error',
                title: 'No se puede anular',
                html: `<p style="color:#6b7280;font-size:13px;margin-bottom:12px">${escData.mensaje}</p>
                       <ul style="text-align:left;font-size:13px;color:#374151;line-height:1.8;padding-left:16px">
                           ${listaHtml}
                       </ul>`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#ef4444',
                showCancelButton: false,
                customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm' },
                didOpen: injectSwalCss,
            })
            return
        }

        // ── Escenario A: sin pago ───────────────────────────────────────────────
        if (escData.escenario === 'A') {
            const result = await Swal.fire({
                ...swalBase,
                title: 'Anular compra',
                html: `<p style="color:#6b7280;font-size:14px;margin-bottom:10px">
                           <strong>${c.num_documento}</strong> — $${Number(c.total).toFixed(2)}
                       </p>
                       <p style="color:#374151;font-size:13px">${escData.mensaje}</p>`,
                input: 'textarea',
                inputPlaceholder: 'Motivo de la anulación (mínimo 10 caracteres)…',
                inputAttributes: { rows: '3', style: 'font-size:13px' },
                confirmButtonText: 'Anular',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor:  '#6b7280',
                inputValidator: (v) => (!v || v.trim().length < 10) ? 'El motivo debe tener al menos 10 caracteres.' : null,
            })
            if (!result.isConfirmed) return
            router.patch(route('compras.facturas.anular', c.id), { motivo: result.value as string }, {
                onSuccess: () => {
                    notify.ok(`Compra ${c.num_documento} anulada correctamente.`)
                    actualizarCompra(c.id, { estado: 'anulada' })
                },
                onError: (e) => notify.error(Object.values(e)[0] ?? 'Error al anular'),
            })
            return
        }

        // ── Escenario B: tiene pago — debe anularse el pago primero ────────────
        if (escData.escenario === 'B') {
            void Swal.fire({
                icon: 'warning',
                title: 'Anula el pago primero',
                html: `<p style="color:#6b7280;font-size:13px;margin-bottom:12px">
                           <strong>${c.num_documento}</strong> — $${Number(c.total).toFixed(2)}
                       </p>
                       <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:10px 14px;margin-bottom:12px;text-align:left">
                           <p style="font-size:12px;color:#92400e;font-weight:600;margin-bottom:4px">Pago registrado</p>
                           <p style="font-size:12px;color:#78350f">
                               Banco/Caja: <b>${escData.banco}</b><br>
                               Monto: <b>$${escData.monto.toFixed(2)}</b>
                           </p>
                       </div>
                       <p style="color:#374151;font-size:13px">
                           Usa el botón <b style="color:#f59e0b">Anular Pago</b> (ícono amarillo) y luego podrás anular la factura.
                       </p>`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#f59e0b',
                showCancelButton: false,
                customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm' },
                didOpen: injectSwalCss,
            })
        }
    }

    async function anularPago(c: Compra) {
        const result = await Swal.fire({
            ...swalBase,
            title: 'Anular pago registrado',
            html: `<p style="color:#6b7280;font-size:14px;margin-bottom:12px">
                       <strong>${c.num_documento}</strong> — $${Number(c.total).toFixed(2)}
                   </p>
                   <p style="color:#374151;font-size:13px;line-height:1.6">
                       Se revertirá el movimiento bancario y la deuda volverá a
                       <strong>Cuentas por Pagar</strong> como pendiente.<br><br>
                       <strong>La factura seguirá activa.</strong>
                   </p>`,
            confirmButtonText: 'Anular pago',
            cancelButtonText:  'Cancelar',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor:  '#6b7280',
        })
        if (!result.isConfirmed) return
        router.post(route('compras.facturas.anular-pago', c.id), {}, {
            onSuccess: () => {
                notify.ok(`Pago de ${c.num_documento} anulado. Factura sigue activa.`)
                actualizarCompra(c.id, { tiene_pago: false })
            },
            onError: (e) => notify.error(Object.values(e)[0] ?? 'Error al anular pago'),
        })
    }

    async function verificarEdicionCompra(c: Compra): Promise<VerificacionEdicion | null> {
        try {
            const res = await fetch(route('compras.facturas.verificar-edicion', c.id), {
                headers: { Accept: 'application/json' },
            })
            return await res.json() as VerificacionEdicion
        } catch {
            notify.error('Error al verificar el estado de la compra.')
            return null
        }
    }

    function mostrarBloqueoEdicion(titulo: string, motivo: string) {
        void Swal.fire({
            icon: 'error',
            title: titulo,
            html: `<p style="color:#374151;font-size:13px">${motivo}</p>`,
            confirmButtonText: 'Entendido',
            confirmButtonColor: '#ef4444',
            showCancelButton: false,
            customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm' },
            didOpen: injectSwalCss,
        })
    }

    async function iniciarEdicion(c: Compra) {
        const verif = await verificarEdicionCompra(c)
        if (!verif) return
        if (!verif.puede) {
            mostrarBloqueoEdicion('No se puede editar', verif.motivo ?? 'No se puede editar esta factura.')
            return
        }

        try {
            const res  = await fetch(route('compras.facturas.detalles', c.id), { headers: { Accept: 'application/json' } })
            const json = await res.json() as { detalles?: DetalleEdicion[] }
            setModal({ type: 'editar', compra: c, detalles: json.detalles ?? [] })
        } catch {
            notify.error('Error al cargar el detalle de la compra.')
        }
    }

    async function iniciarEliminacion(c: Compra) {
        const verif = await verificarEdicionCompra(c)
        if (!verif) return
        if (!verif.puede) {
            mostrarBloqueoEdicion('No se puede eliminar', verif.motivo ?? 'No se puede eliminar esta factura.')
            return
        }

        const result = await Swal.fire({
            ...swalBase,
            icon: 'warning',
            title: 'Eliminar factura',
            html: `<p style="color:#6b7280;font-size:14px;margin-bottom:10px">
                       <strong>${c.num_documento}</strong> — $${Number(c.total).toFixed(2)}
                   </p>
                   <p style="color:#374151;font-size:13px;line-height:1.6">
                       ${verif.es_activa
                            ? verif.motivo
                            : 'Esta factura está pendiente de recepción; se eliminará directamente.'}
                   </p>`,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText:  'Cancelar',
            confirmButtonColor: '#ef4444',
            cancelButtonColor:  '#6b7280',
        })
        if (!result.isConfirmed) return

        router.delete(route('compras.facturas.destroy', c.id), {
            onSuccess: () => {
                notify.ok(`Factura ${c.num_documento} eliminada correctamente.`)
                setComprasData(prev => prev.filter(x => x.id !== c.id))
            },
            onError: (e) => notify.error(Object.values(e)[0] ?? 'Error al eliminar'),
        })
    }

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    // Abrir edición automáticamente al llegar desde Show.tsx con ?editar=<id>
    useEffect(() => {
        const editarId = new URLSearchParams(window.location.search).get('editar')
        if (!editarId) return

        const url = new URL(window.location.href)
        url.searchParams.delete('editar')
        window.history.replaceState({}, '', url.toString())

        void (async () => {
            try {
                const res = await fetch(route('compras.facturas.show', editarId), {
                    headers: { Accept: 'application/json', 'X-Inertia': 'true' },
                })
                const json = await res.json() as { props?: { compra?: Compra } }
                const c = json?.props?.compra
                if (c) await iniciarEdicion(c)
            } catch {
                notify.error('No se pudo cargar la factura para editar.')
            }
        })()
    }, [])

    function aplicarFiltros() {
        router.get(route('compras.facturas.index'), {
            ...(buscar     && { buscar }),
            ...(estado     && { estado }),
            ...(fechaDesde && { fecha_desde: fechaDesde }),
            ...(fechaHasta && { fecha_hasta: fechaHasta }),
            buscado: '1',
        }, { preserveState: true, replace: true })
    }

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <AppLayout title="Facturas de Compra" suppressFlash>
            <Head title="Facturas de Compra" />

            <PageHeader
                title="Facturas de Compra"
                breadcrumbs={[{ label: 'Compras' }, { label: 'Facturas' }]}
                actions={
                    <div className="flex items-center gap-2 flex-wrap shrink-0">
                        {puede('crear') && (
                            <button onClick={() => setModal({ type: 'nueva' })}
                                className="flex items-center gap-2 whitespace-nowrap px-4 py-2 rounded-xl font-semibold text-sm text-black transition-all hover:opacity-90"
                                style={{ background: 'var(--primary)' }}>
                                <Plus size={15} /> Nueva Factura
                            </button>
                        )}
                        {puede('crear') && (
                            <button onClick={() => setModalXml(true)}
                                className="btn-primary flex items-center gap-2 whitespace-nowrap"
                                style={{ background: '#0891b2', color: '#fff' }}>
                                <Upload size={15} /> Cargar XML SRI
                            </button>
                        )}
                    </div>
                }
            />

            {exportandoFondo && (
                <div className="flex items-center gap-2 text-xs rounded-lg px-3 py-2 mx-6 mt-4"
                    style={{ background: 'color-mix(in srgb, var(--primary) 12%, var(--bg-main))', color: 'var(--text-main)' }}>
                    <Loader2 className="w-3.5 h-3.5 animate-spin shrink-0" style={{ color: 'var(--primary)' }} />
                    <span>
                        Tu PDF se está procesando en segundo plano — te avisaremos por notificación cuando esté listo.
                    </span>
                </div>
            )}

            <div className="px-6 pt-6 mb-2">
                {/*
                    Ancho vía `style.width` inline a propósito, NO clases Tailwind (w-36 etc.):
                    `.input-field` (app.css) declara `width:100%` fuera de cualquier @layer, y
                    las utilidades de Tailwind v4 viven dentro de su @layer utilities interno —
                    por reglas de CSS Cascade Layers, lo no-layereado siempre gana sobre lo
                    layereado sin importar especificidad ni orden, así que un w-36 de Tailwind
                    nunca puede ganarle a `.input-field`. Solo un estilo inline (fuera de la
                    cascada) lo puede sobreescribir de forma confiable — mismo hallazgo y mismo
                    fix que en Asientos Contables.

                    Presupuesto (1 solo select aquí, no 3 como en Asientos, así que hay bastante
                    margen; sin botón de "Limpiar" — eliminado por completo, mismo criterio que
                    quedó en Asientos Contables; searchWidth="w-[130px]" para el mismo buscador
                    compacto que Asientos, en vez del w-52 default):
                      Estado 200 + Desde 136 + Hasta 136 + buscador (130+36)
                      + Excel 36 + PDF 36 = 710px + gaps (12px×5=60) = ~770px
                    Cabe cómodo en el presupuesto de ~1100px con sidebar abierto. Igual se
                    envuelve en overflow-x-auto + minWidth como red de seguridad ante cambios
                    futuros, mismo patrón que Asientos.
                */}
                <div className="overflow-x-auto">
                <div style={{ minWidth: '900px' }}>
                <FilterToolbar
                    search={{
                        value: buscar,
                        onChange: setBuscar,
                        onSearch: aplicarFiltros,
                        placeholder: 'N° doc, proveedor...',
                    }}
                    searchWidth="w-[130px]"
                    onExport={() => window.location.href = `${route('compras.facturas.excel')}?estado=${estado}&fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`}
                    extraActions={
                        <button
                            onClick={iniciarExportacionPdf}
                            disabled={verificandoExportPdf}
                            title={verificandoExportPdf ? 'Verificando tamaño…' : 'PDF'}
                            className="flex items-center justify-center w-9 h-9 rounded-md border text-sm font-medium shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                            style={{ background: '#ef4444', color: 'white', borderColor: '#ef4444' }}>
                            <FileText className="w-4 h-4" />
                        </button>
                    }
                >
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '200px' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="activa">Activa</option>
                        <option value="anulada">Anulada</option>
                    </select>
                    <div className="flex flex-col gap-0.5 shrink-0">
                        <span className="text-[11px] leading-none" style={{ color: 'var(--text-muted)' }}>Desde</span>
                        <input type="date" value={fechaDesde} onChange={e => setFechaDesde(e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '136px' }} />
                    </div>
                    <div className="flex flex-col gap-0.5 shrink-0">
                        <span className="text-[11px] leading-none" style={{ color: 'var(--text-muted)' }}>Hasta</span>
                        <input type="date" value={fechaHasta} onChange={e => setFechaHasta(e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '136px' }} />
                    </div>
                </FilterToolbar>
                </div>
                </div>
            </div>

            {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
            {!haBuscado && (
                <div className="px-6 pb-6">
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar las facturas.
                        </p>
                    </div>
                </div>
            )}

            {/* Tabla */}
            {haBuscado && (
            <div className="px-6 pb-6">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-2">N° Documento</span>
                        <span className="col-span-1 text-center">Fecha</span>
                        <span className="col-span-2">Proveedor</span>
                        <span className="col-span-1 text-center">Tipo</span>
                        <span className="col-span-1 text-right">Subtotal</span>
                        <span className="col-span-1 text-right">IVA</span>
                        <span className="col-span-1 text-right">Total</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-2 text-right">Acción</span>
                    </div>

                    {comprasData.length === 0 && (
                        <div className="py-20 text-center">
                            <ShoppingCart className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay compras registradas
                            </p>
                        </div>
                    )}

                    {comprasData.map(c => (
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
                                <p className="font-mono text-xs font-medium truncate flex items-center gap-1" style={{ color: 'var(--text-main)' }}>
                                    {c.num_documento}
                                    {!c.asiento_id && c.asiento_error && (
                                        <span title={`Sin asiento contable: ${c.asiento_error}`} className="shrink-0 cursor-help">
                                            <AlertTriangle size={12} className="text-orange-500" />
                                        </span>
                                    )}
                                </p>
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    {formatFecha(c.fecha_emision)}
                                </p>
                            </div>
                            <div className="col-span-2 min-w-0">
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
                                    ${(Number(c.subtotal_0) + Number(c.subtotal_iva)).toFixed(2)}
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
                                {c.estado === 'pendiente' && (
                                    <span className="inline-flex items-center justify-center w-20 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Pendiente</span>
                                )}
                                {c.estado === 'activa' && (
                                    <span className="inline-flex items-center justify-center w-20 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Activa</span>
                                )}
                                {c.estado === 'anulada' && (
                                    <span className="inline-flex items-center justify-center w-20 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Anulada</span>
                                )}
                            </div>
                            <div className="col-span-2 flex justify-end items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                {c.estado === 'pendiente' && (
                                    <>
                                        {c.tiene_productos_codificados > 0 && (
                                            <>
                                                {!c.has_etiquetas ? (
                                                    <button
                                                        onClick={() => setModal({ type: 'etiquetas', compra: c })}
                                                        title="Generar etiquetas"
                                                        className="h-7 w-7 flex items-center justify-center rounded hover:bg-amber-500/20 transition-colors"
                                                        style={{ color: 'var(--primary)' }}>
                                                        <Barcode className="w-4 h-4" />
                                                    </button>
                                                ) : (
                                                    <button
                                                        onClick={() => reimprimir(c)}
                                                        title="Reimprimir etiquetas"
                                                        className="h-7 w-7 flex items-center justify-center rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                                        <RefreshCw className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </>
                                        )}
                                        {puede('editar') && (
                                            <button
                                                onClick={() => confirmarRecepcion(c)}
                                                title="Confirmar recepción manual"
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-green-500/20 text-green-600 dark:text-green-400 transition-colors">
                                                <CheckCircle className="w-4 h-4" />
                                            </button>
                                        )}
                                        <Link href={route('compras.facturas.show', c.id)}
                                            title="Ver detalle"
                                            className="h-7 w-7 flex items-center justify-center rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                            <Eye className="w-4 h-4" />
                                        </Link>
                                        {puede('editar') && (
                                            <button
                                                onClick={() => iniciarEdicion(c)}
                                                title="Editar factura"
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-amber-500/20 transition-colors"
                                                style={{ color: 'var(--primary)' }}>
                                                <Pencil className="w-4 h-4" />
                                            </button>
                                        )}
                                        {puede('eliminar') && (
                                            <button
                                                onClick={() => iniciarEliminacion(c)}
                                                title="Eliminar factura"
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-red-500/20 text-red-500 dark:text-red-400 transition-colors">
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        )}
                                        {puede('anular') && (
                                            <button
                                                onClick={() => iniciarAnulacion(c)}
                                                title="Anular compra"
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-red-500/20 text-red-500 dark:text-red-400 transition-colors">
                                                <XCircle className="w-4 h-4" />
                                            </button>
                                        )}
                                    </>
                                )}
                                {c.estado === 'activa' && (
                                    <>
                                        {c.tiene_productos_codificados > 0 && (
                                            <button
                                                onClick={() => reimprimir(c)}
                                                disabled={!c.has_etiquetas}
                                                title={c.has_etiquetas ? 'Reimprimir etiquetas' : 'No hay etiquetas generadas'}
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                                <RefreshCw className="w-4 h-4" />
                                            </button>
                                        )}
                                        <Link href={route('compras.facturas.show', c.id)}
                                            title="Ver detalle"
                                            className="h-7 w-7 flex items-center justify-center rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                            <Eye className="w-4 h-4" />
                                        </Link>
                                        {c.tiene_pago && puede('anular') && (
                                            <button
                                                onClick={() => anularPago(c)}
                                                title="Anular pago registrado"
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-amber-500/20 transition-colors"
                                                style={{ color: '#f59e0b' }}>
                                                <CreditCard className="w-4 h-4" />
                                            </button>
                                        )}
                                        {puede('editar') && (
                                            <button
                                                onClick={() => !c.tiene_pago && iniciarEdicion(c)}
                                                disabled={c.tiene_pago}
                                                title={c.tiene_pago
                                                    ? 'No se puede editar: tiene un pago registrado. Anule el pago primero.'
                                                    : 'Editar factura (revertirá y regenerará asiento/inventario/CxP)'}
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-amber-500/20 transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                                                style={{ color: 'var(--primary)' }}>
                                                <Pencil className="w-4 h-4" />
                                            </button>
                                        )}
                                        {puede('eliminar') && (
                                            <button
                                                onClick={() => !c.tiene_pago && iniciarEliminacion(c)}
                                                disabled={c.tiene_pago}
                                                title={c.tiene_pago
                                                    ? 'No se puede eliminar: tiene un pago registrado. Anule el pago primero.'
                                                    : 'Eliminar factura (revertirá asiento/inventario/CxP)'}
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-red-500/20 text-red-500 dark:text-red-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        )}
                                        {puede('anular') && (
                                            <button
                                                onClick={() => !c.tiene_pago && iniciarAnulacion(c)}
                                                disabled={c.tiene_pago}
                                                title={c.tiene_pago
                                                    ? 'Factura con pago registrado — anula el pago primero (candado CxP-02)'
                                                    : 'Anular factura'}
                                                className="h-7 w-7 flex items-center justify-center rounded hover:bg-red-500/20 text-red-500 dark:text-red-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-transparent">
                                                <XCircle className="w-4 h-4" />
                                            </button>
                                        )}
                                    </>
                                )}
                                {c.estado === 'anulada' && (
                                    <Link href={route('compras.facturas.show', c.id)}
                                        title="Ver detalle"
                                        className="h-7 w-7 flex items-center justify-center rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                        <Eye className="w-4 h-4" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            )}

            {/* Paginación */}
            {compras && compras.meta && compras.meta.last_page > 1 && (
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

            {modalXml && (
                <CargarXmlModal
                    onParsed={(data) => {
                        setXmlPrefill(data)
                        setModalXml(false)
                        setModal({ type: 'nueva' })
                    }}
                    onClose={() => setModalXml(false)}
                />
            )}

            {modal.type === 'nueva' && (
                <NuevaCompraModal
                    key={xmlPrefill ? JSON.stringify(xmlPrefill) : 'default'}
                    proveedores={proveedores}
                    centros={centros}
                    cuentas={cuentas}
                    bodegas={bodegas}
                    productos={productos}
                    importacionesActivas={importacionesActivas}
                    centroMatrizId={centros.find(c => c.codigo === 'MATRIZ')?.id ?? centros[0]?.id ?? null}
                    bodegaDefaultId={bodegas.find(b => b.tipo === 'general')?.id ?? bodegas[0]?.id ?? null}
                    initialValues={xmlPrefill ?? prefillExterior ?? undefined}
                    onClose={() => { setModal({ type: 'none' }); setXmlPrefill(null) }}
                />
            )}
            {modal.type === 'editar' && (
                <NuevaCompraModal
                    key={`editar-${modal.compra.id}`}
                    proveedores={proveedores}
                    centros={centros}
                    cuentas={cuentas}
                    bodegas={bodegas}
                    productos={productos}
                    importacionesActivas={importacionesActivas}
                    centroMatrizId={centros.find(c => c.codigo === 'MATRIZ')?.id ?? centros[0]?.id ?? null}
                    bodegaDefaultId={bodegas.find(b => b.tipo === 'general')?.id ?? bodegas[0]?.id ?? null}
                    editando={modal.compra}
                    detallesEdicion={modal.detalles}
                    onClose={() => setModal({ type: 'none' })}
                />
            )}
            {modal.type === 'etiquetas' && (
                <EtiquetasModal
                    compra={modal.compra}
                    onClose={() => setModal({ type: 'none' })}
                    abrirPdf={abrirPdf}
                    onGenerada={(id) => actualizarCompra(id, { has_etiquetas: true })}
                />
            )}
            {modal.type === 'reimprimir-etiquetas' && (
                <ReimprimirEtiquetasModal
                    compra={modal.compra}
                    onClose={() => setModal({ type: 'none' })}
                    abrirPdf={abrirPdf}
                />
            )}

            {/* ── Modal PDF ── */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={cerrarModalPdf}>
                    <div className="w-full max-w-5xl rounded-2xl overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)', height: '90vh' }}
                         onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm flex items-center gap-2"
                                style={{ color: 'var(--text-main)' }}>
                                <FileText size={16} style={{ color: '#ef4444' }} />
                                Reporte de Facturas de Compra
                            </h3>
                            <div className="flex items-center gap-2">
                                {urlPdf && (
                                    <a href={urlPdf} download={`facturas-compra-${new Date().toISOString().slice(0, 10)}.pdf`}
                                       className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-all hover:opacity-90"
                                       style={{ background: '#ef4444' }}>
                                        <Download size={13} /> Descargar
                                    </a>
                                )}
                                <button onClick={cerrarModalPdf}
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>
                        {cargandoPdf ? (
                            <div className="flex-1 flex items-center justify-center text-sm" style={{ color: 'var(--text-muted)' }}>
                                Generando PDF…
                            </div>
                        ) : (
                            <iframe src={urlPdf} className="flex-1 w-full border-0" title="Reporte PDF Compras" />
                        )}
                    </div>
                </div>
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
