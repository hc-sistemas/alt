import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import ConfirmModal from '@/Components/shared/ConfirmModal'
import axios from '@/lib/axios'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, Package, Plane, Anchor, CheckCircle2,
    X, DollarSign, Loader2, Eye, ExternalLink, AlertCircle, Copy, Search,
} from 'lucide-react'
import type { Importacion, Proveedor, PageProps } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ImportacionRow extends Omit<Importacion, 'proveedor'> {
    proveedor: string | null
}

interface Filtros {
    estado?: string
    proveedor_id?: string
    fecha_desde?: string
    fecha_hasta?: string
    buscar?: string
}

interface Props extends PageProps {
    importaciones: ImportacionRow[] | null
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'pais' | 'divisa' | 'tipo'>[]
    filtros: Filtros
}

const CONCEPTOS_COSTO = [
    'ISD',
    'IVA 15%',
    'Seguro Transporte Internacional',
    'Advalorem',
    'FODINFA',
    'ICE',
    'Flete Marítimo',
    'Gastos Destino Ecuador',
    'Honorarios Aduanero',
    'Almacenaje',
    'Honorarios Banco',
    'Transporte Nacional',
    'Otro',
] as const

type TabKey = 'general' | 'productos' | 'gastos' | 'liquidar' | 'resultado'

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

// ─── Previsualización de Liquidación (auditoría 2026-07-29, Parte 3.1) ────────

interface ProductoPreview {
    producto_id: number
    codigo: string
    nombre: string
    cantidad: number
    pct_peso: number | null
    costo_actual: number
    costo_nuevo: number
    pvd_sugerido: number | null
    pvp_sugerido: number | null
}

interface PreviewLiquidacionData {
    metodo_prorrateo: string
    costo_fob: number
    costos_extra_total: number
    costos_extra_detalle: { concepto: string; num_documento: string; monto: number }[]
    costo_total_estimado: number
    factor_importacion: number | null
    suma_pct_peso: number | null
    comision_pct: number
    margen_pvd_pct: number
    margen_pvp_pct: number
    productos: ProductoPreview[]
}

// ─── Resultado de Liquidación ─────────────────────────────────────────────────

interface ProductoResultadoLiquidacion {
    producto_id: number
    codigo: string
    nombre: string
    cantidad: number
    costo_anterior: number | null
    costo_nuevo: number
    pvp: number
    pvd: number
    pvp_sugerido: number | null
    pvd_sugerido: number | null
}

interface ResultadoLiquidacionData {
    metodo_prorrateo: string | null
    costo_total: number
    cantidad_productos: number
    factor_importacion: number | null
    productos: ProductoResultadoLiquidacion[]
}

const METODO_LABEL: Record<string, string> = {
    cantidad: 'Cantidad',
    precio: 'Precio Unitario',
    peso: 'Peso',
    factor_importacion: 'Factor de Importación',
}

function calcularMargen(precio: number, costo: number): number | null {
    if (!costo || costo <= 0) return null
    return ((precio - costo) / costo) * 100
}

function colorMargen(margen: number | null): string {
    if (margen === null) return 'var(--text-muted)'
    if (margen < 0) return '#ef4444'
    if (margen < 20) return '#f59e0b'
    return '#10b981'
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    edit:  (msg: string) => toast.success(msg, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

function mensajeError(err: unknown, fallback: string): string {
    if (typeof err === 'object' && err !== null && 'response' in err) {
        const data = (err as { response?: { data?: { message?: string } } }).response?.data
        if (data?.message) return data.message
    }
    return fallback
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

const TABS: { key: TabKey; label: string; soloLiquidada?: boolean }[] = [
    { key: 'general',   label: '1. General' },
    { key: 'productos', label: '2. Productos' },
    { key: 'gastos',    label: '3. Costos Extra' },
    { key: 'liquidar',  label: '4. Liquidación' },
    { key: 'resultado', label: 'Resultado de Liquidación', soloLiquidada: true },
]

function DetalleModal({ importacion, initialTab, proveedores, onClose }: {
    importacion: ImportacionRow
    initialTab: TabKey
    proveedores: Props['proveedores']
    onClose: () => void
}) {
    const { puede } = usePermiso('compras')
    const [tab,      setTab]      = useState<TabKey>(initialTab)
    const [cargando, setCargando] = useState(true)
    const [detalle,  setDetalle]  = useState<DetalleData | null>(null)
    const yaLiquidada = importacion.estado === 'liquidada'

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

    // ── Tab 2: Crear factura exterior ──
    const [creandoFact,  setCreandoFact]  = useState(false)
    const [yaExisteWarn, setYaExisteWarn] = useState(false)

    async function crearFacturaExterior() {
        setCreandoFact(true)
        setYaExisteWarn(false)
        try {
            const { data: datos } = await axios.post<{ ya_existe: boolean }>(
                route('compras.importaciones.crear-factura', importacion.id)
            )
            if (datos.ya_existe) {
                setYaExisteWarn(true)
            } else {
                router.get(route('compras.facturas.index'), { iniciar_exterior: String(importacion.id) })
            }
        } catch (err) {
            notify.error(mensajeError(err, 'Error al verificar factura'))
        } finally {
            setCreandoFact(false)
        }
    }

    // ── Tab 3: Costos extra ──
    const [showFormCosto, setShowFormCosto] = useState(false)
    const [costoSaving,   setCostoSaving]   = useState(false)
    const [formCosto, setFormCosto] = useState({
        concepto:     CONCEPTOS_COSTO[0] as string,
        conceptoLibre: '',
        proveedor_id:  '',
        monto:         '',
        num_factura:   '',
    })

    function refetchDetalle(initial = false) {
        fetch(route('compras.importaciones.detalle', importacion.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then((d: DetalleData) => { setDetalle(d); if (initial) setCargando(false) })
            .catch(() => { if (initial) setCargando(false) })
    }

    async function submitCosto(e: React.FormEvent) {
        e.preventDefault()
        const conceptoFinal = formCosto.concepto === 'Otro' ? formCosto.conceptoLibre.trim() : formCosto.concepto
        if (!conceptoFinal) { notify.error('Ingresa el concepto del gasto'); return }
        const monto = parseFloat(formCosto.monto)
        if (!monto || monto <= 0) { notify.error('Ingresa un monto válido'); return }

        setCostoSaving(true)
        try {
            const { data: json } = await axios.post<{ success?: boolean; message?: string }>(
                route('compras.importaciones.agregar-costo', importacion.id),
                {
                    concepto:     conceptoFinal,
                    proveedor_id: formCosto.proveedor_id || null,
                    monto,
                    num_factura:  formCosto.num_factura || null,
                }
            )
            if (json.success) {
                notify.ok(json.message ?? 'Costo registrado')
                setFormCosto({ concepto: CONCEPTOS_COSTO[0], conceptoLibre: '', proveedor_id: '', monto: '', num_factura: '' })
                setShowFormCosto(false)
                refetchDetalle()
            } else {
                notify.error(json.message ?? 'Error al guardar el costo')
            }
        } catch (err) {
            notify.error(mensajeError(err, 'Error al guardar el costo'))
        } finally {
            setCostoSaving(false)
        }
    }

    // ── Tab 4: Liquidar ──
    const [metodo,        setMetodo]    = useState<'cantidad' | 'precio' | 'peso' | 'factor_importacion'>('cantidad')
    const [fechaLiq,      setFechaLiq]  = useState(new Date().toISOString().slice(0, 10))
    const [liqProcessing, setLiqProc]   = useState(false)
    const [comisionPct,  setComisionPct]  = useState('3')
    const [margenPvdPct, setMargenPvdPct] = useState('20')
    const [margenPvpPct, setMargenPvpPct] = useState('35')

    // ── Previsualización antes de liquidar (auditoría 2026-07-29, Parte 3.1) ──
    // `previewData` es null hasta que el usuario pide previsualizar; a partir
    // de ahí se muestra la vista de solo lectura (con % de peso y PVD/PVP
    // editables) en vez del formulario, y "Confirmar y Liquidar" hace el
    // submit real. Cambiar cualquier parámetro (método, comisión, márgenes)
    // invalida la previsualización — hay que volver a generarla, para no
    // liquidar con una vista vieja que ya no corresponde a los parámetros
    // actuales del formulario.
    const [previewData,      setPreviewData]      = useState<PreviewLiquidacionData | null>(null)
    const [cargandoPreview,  setCargandoPreview]  = useState(false)
    const [mostrarDetalleCostos, setMostrarDetalleCostos] = useState(false)
    // % de peso por producto, editable solo con método "peso" — se
    // inicializa con el % natural que devuelve la previsualización la
    // primera vez, y desde ahí el usuario puede ajustarlo a mano.
    const [pesosManual, setPesosManual] = useState<Record<number, string>>({})
    // Override individual de PVD/PVP sobre el sugerido "masivo" (comisión +
    // margen aplicado a TODOS los ítems) — mismo patrón ya usado en el tab
    // "Resultado de Liquidación" (variable `precios` / `actualizarPrecio`).
    const [preciosPreview, setPreciosPreview] = useState<Record<number, { pvd: string; pvp: string }>>({})

    function invalidarPreview() {
        if (previewData) setPreviewData(null)
    }

    async function fetchPreview() {
        setCargandoPreview(true)
        try {
            const params: Record<string, string | Record<string, string>> = {
                metodo_prorrateo: metodo,
                comision_pct:     comisionPct,
                margen_pvd_pct:   margenPvdPct,
                margen_pvp_pct:   margenPvpPct,
            }
            if (metodo === 'peso' && Object.keys(pesosManual).length > 0) {
                params.pesos_manual = pesosManual
            }
            const { data } = await axios.get<PreviewLiquidacionData>(
                route('compras.importaciones.previsualizar-liquidacion', importacion.id),
                { params }
            )
            setPreviewData(data)
            const preciosIniciales: Record<number, { pvd: string; pvp: string }> = {}
            const pesosIniciales: Record<number, string> = { ...pesosManual }
            data.productos.forEach(p => {
                preciosIniciales[p.producto_id] = {
                    pvd: p.pvd_sugerido !== null ? p.pvd_sugerido.toString() : '',
                    pvp: p.pvp_sugerido !== null ? p.pvp_sugerido.toString() : '',
                }
                if (metodo === 'peso' && p.pct_peso !== null && pesosIniciales[p.producto_id] === undefined) {
                    pesosIniciales[p.producto_id] = p.pct_peso.toString()
                }
            })
            setPreciosPreview(preciosIniciales)
            if (metodo === 'peso') setPesosManual(pesosIniciales)
        } catch (err) {
            notify.error(mensajeError(err, 'No se pudo generar la previsualización'))
        } finally {
            setCargandoPreview(false)
        }
    }

    function actualizarPesoManual(productoId: number, valor: string) {
        setPesosManual(prev => ({ ...prev, [productoId]: valor }))
    }

    function actualizarPrecioPreview(productoId: number, campo: 'pvd' | 'pvp', valor: string) {
        setPreciosPreview(prev => ({ ...prev, [productoId]: { ...prev[productoId], [campo]: valor } }))
    }

    const sumaPesosManual = Object.values(pesosManual).reduce((s, v) => s + (parseFloat(v) || 0), 0)
    const pesosCuadran = metodo !== 'peso' || Object.keys(pesosManual).length === 0 || Math.abs(sumaPesosManual - 100) <= 0.1

    // ── Tab 4: Revertir ──
    const [confirmRevertir, setConfirmRevertir] = useState(false)
    const [revProcessing,   setRevProc]         = useState(false)

    function ejecutarRevertir() {
        setRevProc(true)
        router.patch(route('compras.importaciones.revertir', importacion.id), {}, {
            // back()->with('error', ...) llega como respuesta "exitosa" para Inertia —
            // hay que mirar el flash para saber si en realidad falló el candado de reversión.
            onSuccess: (page) => {
                const flash = page.props.flash as { success?: string; error?: string } | undefined
                if (flash?.error) {
                    setConfirmRevertir(false)
                } else {
                    onClose()
                }
            },
            onFinish: () => setRevProc(false),
        })
    }

    // ── Tab Resultado de Liquidación ──
    const [resultado,        setResultado]        = useState<ResultadoLiquidacionData | null>(null)
    const [cargandoResultado, setCargandoResultado] = useState(true)
    const [precios,          setPrecios]          = useState<Record<number, { pvp: string; pvd: string }>>({})
    const [guardandoPrecios, setGuardandoPrecios]  = useState(false)

    function fetchResultado() {
        setCargandoResultado(true)
        fetch(route('compras.importaciones.resultado-liquidacion', importacion.id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then((d: ResultadoLiquidacionData) => {
                setResultado(d)
                const inicial: Record<number, { pvp: string; pvd: string }> = {}
                d.productos.forEach(p => {
                    // Con el método "Factor de Importación" se precargan los precios
                    // SUGERIDOS por la fórmula (comisión + margen + IVA) en vez del
                    // precio anterior — el usuario los puede seguir editando aquí mismo.
                    inicial[p.producto_id] = {
                        pvp: (p.pvp_sugerido ?? p.pvp).toString(),
                        pvd: (p.pvd_sugerido ?? p.pvd).toString(),
                    }
                })
                setPrecios(inicial)
            })
            .finally(() => setCargandoResultado(false))
    }

    function actualizarPrecio(productoId: number, campo: 'pvp' | 'pvd', valor: string) {
        setPrecios(prev => ({ ...prev, [productoId]: { ...prev[productoId], [campo]: valor } }))
    }

    async function guardarPrecios() {
        if (!resultado) return
        setGuardandoPrecios(true)
        try {
            const payload = {
                precios: resultado.productos.map(p => ({
                    producto_id: p.producto_id,
                    pvp: parseFloat(precios[p.producto_id]?.pvp ?? String(p.pvp)) || 0,
                    pvd: parseFloat(precios[p.producto_id]?.pvd ?? String(p.pvd)) || 0,
                })),
            }
            const { data: json } = await axios.patch<{ success?: boolean; message?: string }>(
                route('compras.importaciones.actualizar-precios-lote', importacion.id),
                payload
            )
            if (json.success) {
                notify.ok(json.message ?? 'Precios actualizados')
                fetchResultado()
            } else {
                notify.error(json.message ?? 'Error al guardar los precios')
            }
        } catch (err) {
            notify.error(mensajeError(err, 'Error al guardar los precios'))
        } finally {
            setGuardandoPrecios(false)
        }
    }

    useEffect(() => { refetchDetalle(true) }, [importacion.id])
    useEffect(() => { if (yaLiquidada) fetchResultado() }, [importacion.id])

    function submitGeneral(e: React.FormEvent) {
        e.preventDefault()
        put(route('compras.importaciones.update', importacion.id), {
            onSuccess: () => { notify.edit('Importación actualizada'); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    function confirmarLiquidar() {
        if (metodo === 'peso' && !pesosCuadran) return
        setLiqProc(true)
        router.patch(route('compras.importaciones.liquidar', importacion.id), {
            metodo_prorrateo:  metodo,
            fecha_liquidacion: fechaLiq,
            comision_pct:      comisionPct,
            margen_pvd_pct:    margenPvdPct,
            margen_pvp_pct:    margenPvpPct,
            ...(metodo === 'peso' && Object.keys(pesosManual).length > 0 ? { pesos_manual: pesosManual } : {}),
        }, {
            onSuccess: () => { notify.ok(`Importación "${importacion.nombre}" liquidada`); onClose() },
            onError:   (errs) => { notify.error('Error: ' + Object.values(errs).join(', ')); setLiqProc(false) },
            onFinish:  () => setLiqProc(false),
        })
    }

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
                    {TABS.filter(t => !t.soloLiquidada || yaLiquidada).map(t => (
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
                            {!yaLiquidada && puede('editar') && (
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
                                <div className="py-12 text-center" style={{ color: 'var(--text-muted)' }}>
                                    <Package className="w-10 h-10 mx-auto mb-3 opacity-20" />
                                    <p className="font-semibold text-sm mb-1">No hay factura de productos vinculada</p>
                                    <p className="text-xs mb-4 max-w-xs mx-auto">
                                        Crea una factura de compra exterior pre-llenada con los datos de esta importación.
                                    </p>
                                    {puede('crear') && (
                                        <button
                                            type="button"
                                            onClick={crearFacturaExterior}
                                            disabled={creandoFact}
                                            className="btn-primary inline-flex items-center gap-2 text-sm">
                                            {creandoFact
                                                ? <Loader2 className="w-4 h-4 animate-spin" />
                                                : <ExternalLink className="w-4 h-4" />
                                            }
                                            {creandoFact ? 'Verificando...' : 'Crear factura de compra exterior'}
                                        </button>
                                    )}

                                    {/* Aviso cuando ya existe una factura vinculada */}
                                    {yaExisteWarn && (
                                        <div className="mt-4 mx-auto max-w-sm rounded-xl border p-4 text-left"
                                            style={{ borderColor: 'rgba(245,158,11,0.4)', background: 'rgba(245,158,11,0.07)' }}>
                                            <p className="text-sm font-semibold mb-1" style={{ color: 'var(--primary)' }}>
                                                Ya existe una factura vinculada
                                            </p>
                                            <p className="text-xs mb-3" style={{ color: 'var(--text-muted)' }}>
                                                Esta importación ya tiene una factura de productos registrada.
                                            </p>
                                            <div className="flex flex-col gap-1.5">
                                                <button
                                                    type="button"
                                                    onClick={() => router.get(route('compras.facturas.index'), { importacion_id: String(importacion.id) })}
                                                    className="btn-secondary text-xs w-full">
                                                    Ver facturas existentes
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setYaExisteWarn(false)
                                                        router.get(route('compras.facturas.index'), { iniciar_exterior: String(importacion.id) })
                                                    }}
                                                    className="text-xs w-full py-1.5 px-3 rounded-lg border transition-colors"
                                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                                    Crear otra factura igualmente
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setYaExisteWarn(false)}
                                                    className="text-xs"
                                                    style={{ color: 'var(--text-muted)' }}>
                                                    Cancelar
                                                </button>
                                            </div>
                                        </div>
                                    )}
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
                            {/* Botón + formulario inline */}
                            {!yaLiquidada && puede('editar') && (
                                <div>
                                    {!showFormCosto ? (
                                        <button type="button"
                                            onClick={() => setShowFormCosto(true)}
                                            className="btn-primary flex items-center gap-2 text-sm">
                                            <Plus className="w-4 h-4" /> Agregar costo extra
                                        </button>
                                    ) : (
                                        <form onSubmit={submitCosto}
                                            className="rounded-xl border p-4 space-y-3"
                                            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                                            <div className="flex items-center justify-between mb-1">
                                                <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                                    Nuevo costo extra
                                                </p>
                                                <button type="button" onClick={() => setShowFormCosto(false)}
                                                    style={{ color: 'var(--text-muted)' }}>
                                                    <X className="w-4 h-4" />
                                                </button>
                                            </div>

                                            {/* Concepto */}
                                            <div className="space-y-1">
                                                <label className="input-label">Concepto <span className="text-red-400">*</span></label>
                                                <select
                                                    value={formCosto.concepto}
                                                    onChange={e => setFormCosto(p => ({ ...p, concepto: e.target.value }))}
                                                    className="input-field select-field text-sm">
                                                    {CONCEPTOS_COSTO.map(c => (
                                                        <option key={c} value={c}>{c}</option>
                                                    ))}
                                                </select>
                                                {formCosto.concepto === 'Otro' && (
                                                    <input
                                                        value={formCosto.conceptoLibre}
                                                        onChange={e => setFormCosto(p => ({ ...p, conceptoLibre: e.target.value }))}
                                                        placeholder="Descripción del gasto"
                                                        className="input-field text-sm mt-1" />
                                                )}
                                            </div>

                                            {/* Proveedor */}
                                            <div className="space-y-1">
                                                <label className="input-label">Proveedor</label>
                                                <select
                                                    value={formCosto.proveedor_id}
                                                    onChange={e => setFormCosto(p => ({ ...p, proveedor_id: e.target.value }))}
                                                    className="input-field select-field text-sm">
                                                    <option value="">— Usar proveedor de importación —</option>
                                                    {proveedores.map(p => (
                                                        <option key={p.id} value={p.id}>{p.razon_social}</option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3">
                                                {/* Monto */}
                                                <div className="space-y-1">
                                                    <label className="input-label">Monto <span className="text-red-400">*</span></label>
                                                    <input
                                                        type="number" step="0.01" min="0.01"
                                                        value={formCosto.monto}
                                                        onChange={e => setFormCosto(p => ({ ...p, monto: e.target.value }))}
                                                        placeholder="0.00"
                                                        className="input-field text-sm text-right" />
                                                </div>
                                                {/* N° Factura */}
                                                <div className="space-y-1">
                                                    <label className="input-label">N° Factura</label>
                                                    <input
                                                        value={formCosto.num_factura}
                                                        onChange={e => setFormCosto(p => ({ ...p, num_factura: e.target.value }))}
                                                        placeholder="001-001-000001"
                                                        className="input-field text-sm" />
                                                </div>
                                            </div>

                                            <div className="flex gap-2 pt-1">
                                                <button type="submit" disabled={costoSaving}
                                                    className="btn-primary flex items-center gap-2 text-sm">
                                                    {costoSaving
                                                        ? <><Loader2 className="w-3.5 h-3.5 animate-spin" /> Guardando…</>
                                                        : <><DollarSign className="w-3.5 h-3.5" /> Guardar costo</>
                                                    }
                                                </button>
                                                <button type="button" onClick={() => setShowFormCosto(false)}
                                                    className="btn-secondary text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            )}

                            {/* Lista de costos registrados */}
                            {cargando ? (
                                <div className="flex items-center justify-center py-10 gap-2"
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
                                !showFormCosto && (
                                    <div className="py-10 text-center" style={{ color: 'var(--text-muted)' }}>
                                        <DollarSign className="w-8 h-8 mx-auto mb-2 opacity-20" />
                                        <p className="font-semibold text-sm">Sin costos extra registrados</p>
                                        <p className="text-xs mt-1">
                                            Usa el botón de arriba para agregar flete, aduana, seguros, etc.
                                        </p>
                                    </div>
                                )
                            )}

                            {/* Resumen — visible cuando hay gastos o ya está liquidada */}
                            {detalle && (detalle.gastos.length > 0 || yaLiquidada) && (
                                <div className="rounded-lg p-3 space-y-1.5 text-sm"
                                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                    <div className="flex justify-between">
                                        <span style={{ color: 'var(--text-muted)' }}>Costo FOB</span>
                                        <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                            ${Number(importacion.costo_fob).toFixed(2)} {importacion.divisa ?? 'USD'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span style={{ color: 'var(--text-muted)' }}>Costos extra</span>
                                        <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                            ${detalle.gastos.reduce((s, g) => s + g.monto, 0).toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between font-bold border-t pt-1.5"
                                        style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                        <span>Costo total estimado</span>
                                        <span className="tabular-nums">
                                            ${(Number(importacion.costo_fob) + detalle.gastos.reduce((s, g) => s + g.monto, 0)).toFixed(2)}
                                        </span>
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

                                {puede('editar') && (
                                    <button
                                        type="button"
                                        onClick={() => setConfirmRevertir(true)}
                                        className="w-full py-2 px-4 rounded-lg text-sm font-medium border transition-colors hover:bg-red-500/10"
                                        style={{ borderColor: '#ef4444', color: '#ef4444', background: 'transparent' }}>
                                        Revertir liquidación
                                    </button>
                                )}
                                <p className="text-xs text-center" style={{ color: 'var(--text-muted)' }}>
                                    Restaura el costo anterior de los productos y el estado previo a la liquidación.
                                    Se bloqueará si ya se vendió o movió stock con el costo actual.
                                </p>

                                <ConfirmModal
                                    open={confirmRevertir}
                                    title="¿Revertir esta liquidación?"
                                    message={`Se restaurará el costo anterior de los productos de "${importacion.nombre}" y, si hubo cruce de anticipo, se generará un asiento de reversión. Esta acción no se puede deshacer.`}
                                    confirmLabel="Sí, revertir"
                                    variant="danger"
                                    loading={revProcessing}
                                    onConfirm={ejecutarRevertir}
                                    onCancel={() => setConfirmRevertir(false)}
                                />
                            </div>
                        ) : (
                            /* ── Formulario liquidar ── */
                            (() => {
                                const tieneProductos = (detalle?.productos.length ?? 0) > 0
                                const tieneCostos    = (detalle?.gastos.length   ?? 0) > 0
                                const puedeRender    = !cargando

                                if (!puedeRender) return (
                                    <div className="flex items-center justify-center py-20 gap-2"
                                        style={{ color: 'var(--text-muted)' }}>
                                        <Loader2 className="w-5 h-5 animate-spin" /> Cargando...
                                    </div>
                                )

                                if (!tieneProductos || !tieneCostos) return (
                                    <div className="p-5 space-y-4">
                                        <div className="rounded-xl border p-5 space-y-3"
                                            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                                            <p className="text-sm font-semibold mb-3" style={{ color: 'var(--text-main)' }}>
                                                Prerrequisitos para liquidar
                                            </p>
                                            <div className="flex items-center gap-3">
                                                {tieneProductos
                                                    ? <CheckCircle2 className="w-5 h-5 shrink-0" style={{ color: '#10b981' }} />
                                                    : <AlertCircle  className="w-5 h-5 shrink-0" style={{ color: '#ef4444' }} />
                                                }
                                                <div>
                                                    <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                                        Factura de productos vinculada
                                                    </p>
                                                    {!tieneProductos && (
                                                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                                            Ve al Tab "2. Productos" y crea la factura exterior.
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                {tieneCostos
                                                    ? <CheckCircle2 className="w-5 h-5 shrink-0" style={{ color: '#10b981' }} />
                                                    : <AlertCircle  className="w-5 h-5 shrink-0" style={{ color: '#ef4444' }} />
                                                }
                                                <div>
                                                    <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                                        Al menos un costo extra registrado
                                                    </p>
                                                    {!tieneCostos && (
                                                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                                            Ve al Tab "3. Costos Extra" y agrega flete, aduana, etc.
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        <p className="text-xs text-center" style={{ color: 'var(--text-muted)' }}>
                                            Completa los pasos anteriores para habilitar la liquidación.
                                        </p>
                                    </div>
                                )

                                if (previewData) return (
                                    <div>
                                        <div className="modal-body space-y-4">
                                            {/* Resumen — costos extra colapsados en un solo total, con detalle expandible */}
                                            <div className="rounded-lg p-3 space-y-1.5"
                                                style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                                <div className="flex justify-between text-sm">
                                                    <span style={{ color: 'var(--text-muted)' }}>Costo FOB (Mercadería)</span>
                                                    <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                        ${previewData.costo_fob.toFixed(2)}
                                                    </span>
                                                </div>
                                                <button type="button"
                                                    onClick={() => setMostrarDetalleCostos(v => !v)}
                                                    className="w-full flex justify-between text-sm hover:opacity-80">
                                                    <span style={{ color: 'var(--text-muted)' }}>
                                                        Costos extra ({previewData.costos_extra_detalle.length}) {mostrarDetalleCostos ? '▲' : '▼'}
                                                    </span>
                                                    <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                        ${previewData.costos_extra_total.toFixed(2)}
                                                    </span>
                                                </button>
                                                {mostrarDetalleCostos && (
                                                    <div className="pl-3 space-y-1 border-l-2" style={{ borderColor: 'rgba(245,158,11,0.3)' }}>
                                                        {previewData.costos_extra_detalle.map((g, i) => (
                                                            <div key={i} className="flex justify-between text-xs">
                                                                <span style={{ color: 'var(--text-muted)' }}>
                                                                    {g.concepto}{g.num_documento ? ` — ${g.num_documento}` : ''}
                                                                </span>
                                                                <span className="tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                                    ${g.monto.toFixed(2)}
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                                <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                                                    style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                                    <span>Costo total estimado</span>
                                                    <span className="tabular-nums">${previewData.costo_total_estimado.toFixed(2)}</span>
                                                </div>
                                                {previewData.factor_importacion !== null && (
                                                    <div className="flex justify-between text-sm font-bold">
                                                        <span style={{ color: 'var(--text-muted)' }}>Factor de Importación</span>
                                                        <span className="tabular-nums" style={{ color: 'var(--primary)' }}>
                                                            {previewData.factor_importacion.toFixed(6)}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>

                                            {metodo === 'peso' && (
                                                <div className={cn('rounded-lg p-2.5 text-xs font-semibold text-center',
                                                    pesosCuadran ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400')}
                                                    style={{ background: pesosCuadran ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)' }}>
                                                    Total % de peso: {sumaPesosManual.toFixed(2)}% {pesosCuadran ? '✓' : '— debe sumar 100%'}
                                                </div>
                                            )}

                                            {/* Tabla resumen por ítem — solo lectura salvo % peso y PVD/PVP */}
                                            <div className="overflow-x-auto">
                                                <table className="w-full text-xs">
                                                    <thead>
                                                        <tr className="border-b" style={{ borderColor: 'var(--border)' }}>
                                                            {[
                                                                'Producto', 'Cant.',
                                                                ...(metodo === 'peso' ? ['% Peso'] : []),
                                                                'Costo Actual', 'Costo Nuevo', 'PVD sugerido', 'PVP sugerido',
                                                            ].map(h => (
                                                                <th key={h} className="pb-2 pt-1 px-2 font-semibold uppercase text-[10px] tracking-wider text-left whitespace-nowrap"
                                                                    style={{ color: 'var(--text-muted)' }}>
                                                                    {h}
                                                                </th>
                                                            ))}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {previewData.productos.map(p => (
                                                            <tr key={p.producto_id} className="border-b" style={{ borderColor: 'var(--border)' }}>
                                                                <td className="py-2 px-2 max-w-40">
                                                                    <p className="font-mono font-bold" style={{ color: 'var(--primary)' }}>{p.codigo}</p>
                                                                    <p className="truncate" style={{ color: 'var(--text-main)' }}>{p.nombre}</p>
                                                                </td>
                                                                <td className="py-2 px-2 tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                                    {p.cantidad % 1 === 0 ? p.cantidad.toFixed(0) : p.cantidad.toFixed(2)}
                                                                </td>
                                                                {metodo === 'peso' && (
                                                                    <td className="py-2 px-2">
                                                                        <input type="number" step="0.01" min="0" max="100"
                                                                            className="input-field text-xs" style={{ width: '5.5rem' }}
                                                                            value={pesosManual[p.producto_id] ?? ''}
                                                                            onChange={e => actualizarPesoManual(p.producto_id, e.target.value)} />
                                                                    </td>
                                                                )}
                                                                <td className="py-2 px-2 tabular-nums" style={{ color: 'var(--text-muted)' }}>
                                                                    ${p.costo_actual.toFixed(4)}
                                                                </td>
                                                                <td className="py-2 px-2 tabular-nums font-medium" style={{ color: 'var(--text-main)' }}>
                                                                    ${p.costo_nuevo.toFixed(4)}
                                                                </td>
                                                                <td className="py-2 px-2">
                                                                    <input type="number" step="0.01" min="0"
                                                                        className="input-field text-xs" style={{ width: '6rem' }}
                                                                        value={preciosPreview[p.producto_id]?.pvd ?? ''}
                                                                        onChange={e => actualizarPrecioPreview(p.producto_id, 'pvd', e.target.value)} />
                                                                </td>
                                                                <td className="py-2 px-2">
                                                                    <input type="number" step="0.01" min="0"
                                                                        className="input-field text-xs" style={{ width: '6rem' }}
                                                                        value={preciosPreview[p.producto_id]?.pvp ?? ''}
                                                                        onChange={e => actualizarPrecioPreview(p.producto_id, 'pvp', e.target.value)} />
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>

                                            {metodo === 'peso' && (
                                                <button type="button" onClick={fetchPreview} disabled={cargandoPreview}
                                                    className="text-xs underline" style={{ color: 'var(--primary)' }}>
                                                    {cargandoPreview ? 'Recalculando…' : 'Recalcular con estos % de peso'}
                                                </button>
                                            )}
                                        </div>
                                        <div className="modal-footer">
                                            {puede('editar') && (
                                                <Button type="button" onClick={confirmarLiquidar}
                                                    disabled={liqProcessing || (metodo === 'peso' && !pesosCuadran)}>
                                                    <CheckCircle2 className="w-4 h-4" /> Confirmar y Liquidar
                                                </Button>
                                            )}
                                            <Button type="button" variant="outline" onClick={() => setPreviewData(null)}>
                                                Editar parámetros
                                            </Button>
                                            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                                        </div>
                                    </div>
                                )

                                return (
                                    <form onSubmit={e => { e.preventDefault(); fetchPreview() }}>
                                        <div className="modal-body">
                                            {/* Resumen previo */}
                                            <div className="rounded-lg p-3 space-y-1.5"
                                                style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                                <div className="flex justify-between text-sm">
                                                    <span style={{ color: 'var(--text-muted)' }}>Costo FOB</span>
                                                    <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                        ${Number(importacion.costo_fob).toFixed(2)} {importacion.divisa ?? 'USD'}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span style={{ color: 'var(--text-muted)' }}>Costos extra registrados</span>
                                                    <span className="font-medium tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                        ${detalle!.gastos.reduce((s, g) => s + g.monto, 0).toFixed(2)}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                                                    style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                                                    <span>Costo total estimado</span>
                                                    <span className="tabular-nums">
                                                        ${(Number(importacion.costo_fob) + detalle!.gastos.reduce((s, g) => s + g.monto, 0)).toFixed(2)}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label>Método de prorrateo <span className="text-red-400">*</span></Label>
                                                <select value={metodo}
                                                    onChange={e => { setMetodo(e.target.value as typeof metodo); invalidarPreview(); setPesosManual({}) }}
                                                    className="input-field select-field">
                                                    <option value="cantidad">Por cantidad (unidades)</option>
                                                    <option value="precio">Por precio (valor FOB)</option>
                                                    <option value="peso">Por peso (kg)</option>
                                                    <option value="factor_importacion">Factor de Importación</option>
                                                </select>
                                            </div>

                                            {metodo === 'factor_importacion' && (
                                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    Factor = (Mercadería + todos los costos extra) / Mercadería FOB.
                                                    Se aplica directamente al costo unitario original de cada producto.
                                                </p>
                                            )}

                                            {/* Ganancia sugerida (comisión + márgenes PVD/PVP) — aplica de forma MASIVA
                                                a todos los ítems de la previsualización, sin importar el método de
                                                prorrateo elegido (auditoría 2026-07-29, Parte 3.3). Cada ítem se puede
                                                sobreescribir individualmente ya en la vista de previsualización. */}
                                            <div className="rounded-lg p-3 space-y-3"
                                                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    Ganancia sugerida (aplica a todos los ítems — editable uno por uno en la previsualización):
                                                </p>
                                                <div className="grid grid-cols-3 gap-3">
                                                    <div className="space-y-1">
                                                        <label className="input-label">% Comisión</label>
                                                        <input type="number" step="0.01" min="0" className="input-field text-sm"
                                                            value={comisionPct} onChange={e => { setComisionPct(e.target.value); invalidarPreview() }} />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <label className="input-label">% Margen PVD</label>
                                                        <input type="number" step="0.01" min="0" max="99.99" className="input-field text-sm"
                                                            value={margenPvdPct} onChange={e => { setMargenPvdPct(e.target.value); invalidarPreview() }} />
                                                    </div>
                                                    <div className="space-y-1">
                                                        <label className="input-label">% Margen PVP</label>
                                                        <input type="number" step="0.01" min="0" max="99.99" className="input-field text-sm"
                                                            value={margenPvpPct} onChange={e => { setMargenPvpPct(e.target.value); invalidarPreview() }} />
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="space-y-1.5">
                                                <Label>Fecha de liquidación <span className="text-red-400">*</span></Label>
                                                <Input type="date" value={fechaLiq} onChange={e => setFechaLiq(e.target.value)} />
                                            </div>
                                        </div>
                                        <div className="modal-footer">
                                            <Button type="submit" disabled={cargandoPreview}>
                                                {cargandoPreview
                                                    ? <Loader2 className="w-4 h-4 animate-spin" />
                                                    : <Eye className="w-4 h-4" />
                                                }
                                                Previsualizar
                                            </Button>
                                            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                                        </div>
                                    </form>
                                )
                            })()
                        )
                    )}

                    {/* ════ TAB: RESULTADO DE LIQUIDACIÓN ════ */}
                    {tab === 'resultado' && (
                        cargandoResultado ? (
                            <div className="flex items-center justify-center py-20 gap-2"
                                style={{ color: 'var(--text-muted)' }}>
                                <Loader2 className="w-5 h-5 animate-spin" /> Cargando resultado de liquidación...
                            </div>
                        ) : !resultado ? (
                            <div className="py-12 text-center" style={{ color: 'var(--text-muted)' }}>
                                <AlertCircle className="w-10 h-10 mx-auto mb-3 opacity-20" />
                                <p className="text-sm">No se pudo cargar el resultado de la liquidación.</p>
                            </div>
                        ) : (
                            <div className="p-5 space-y-4">
                                {/* Resumen */}
                                <div className={cn('rounded-lg p-3 grid gap-3 text-sm', resultado.factor_importacion !== null ? 'grid-cols-4' : 'grid-cols-3')}
                                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                                    <div>
                                        <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                            Costo total liquidado
                                        </p>
                                        <p className="font-bold tabular-nums" style={{ color: 'var(--primary)' }}>
                                            ${resultado.costo_total.toFixed(2)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                            Productos afectados
                                        </p>
                                        <p className="font-bold tabular-nums" style={{ color: 'var(--text-main)' }}>
                                            {resultado.cantidad_productos}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                            Método de prorrateo
                                        </p>
                                        <p className="font-bold" style={{ color: 'var(--text-main)' }}>
                                            {resultado.metodo_prorrateo
                                                ? (METODO_LABEL[resultado.metodo_prorrateo] ?? resultado.metodo_prorrateo)
                                                : '—'}
                                        </p>
                                    </div>
                                    {resultado.factor_importacion !== null && (
                                        <div>
                                            <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                                Factor de Importación
                                            </p>
                                            <p className="font-bold tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                {resultado.factor_importacion.toFixed(6)}
                                            </p>
                                        </div>
                                    )}
                                </div>

                                {/* Tabla editable */}
                                <div className="overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="border-b" style={{ borderColor: 'var(--border)' }}>
                                                {['Producto', 'Cant.', 'Costo Anterior', 'Costo Nuevo', 'PVP', 'Margen PVP', 'PVD', 'Margen PVD'].map(h => (
                                                    <th key={h}
                                                        className="pb-2 pt-1 px-2 font-semibold uppercase text-[10px] tracking-wider text-left whitespace-nowrap"
                                                        style={{ color: 'var(--text-muted)' }}>
                                                        {h}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {resultado.productos.map(p => {
                                                const pvpActual  = parseFloat(precios[p.producto_id]?.pvp ?? String(p.pvp)) || 0
                                                const pvdActual  = parseFloat(precios[p.producto_id]?.pvd ?? String(p.pvd)) || 0
                                                const margenPvp  = calcularMargen(pvpActual, p.costo_nuevo)
                                                const margenPvd  = calcularMargen(pvdActual, p.costo_nuevo)
                                                return (
                                                    <tr key={p.producto_id} className="border-b"
                                                        style={{ borderColor: 'var(--border)' }}>
                                                        <td className="py-2 px-2 max-w-40">
                                                            <p className="font-mono font-bold" style={{ color: 'var(--primary)' }}>{p.codigo}</p>
                                                            <p className="truncate" style={{ color: 'var(--text-main)' }}>{p.nombre}</p>
                                                        </td>
                                                        <td className="py-2 px-2 tabular-nums" style={{ color: 'var(--text-main)' }}>
                                                            {p.cantidad % 1 === 0 ? p.cantidad.toFixed(0) : p.cantidad.toFixed(2)}
                                                        </td>
                                                        <td className="py-2 px-2 tabular-nums" style={{ color: 'var(--text-muted)' }}>
                                                            {p.costo_anterior !== null ? `$${p.costo_anterior.toFixed(4)}` : '—'}
                                                        </td>
                                                        <td className="py-2 px-2 tabular-nums font-medium" style={{ color: 'var(--text-main)' }}>
                                                            ${p.costo_nuevo.toFixed(4)}
                                                        </td>
                                                        <td className="py-2 px-2">
                                                            <input type="number" step="0.01" min="0"
                                                                className="input-field text-xs"
                                                                style={{ width: '6.5rem' }}
                                                                value={precios[p.producto_id]?.pvp ?? ''}
                                                                onChange={e => actualizarPrecio(p.producto_id, 'pvp', e.target.value)} />
                                                        </td>
                                                        <td className="py-2 px-2 font-semibold tabular-nums"
                                                            style={{ color: colorMargen(margenPvp) }}>
                                                            {margenPvp !== null ? `${margenPvp.toFixed(1)}%` : '—'}
                                                        </td>
                                                        <td className="py-2 px-2">
                                                            <input type="number" step="0.01" min="0"
                                                                className="input-field text-xs"
                                                                style={{ width: '6.5rem' }}
                                                                value={precios[p.producto_id]?.pvd ?? ''}
                                                                onChange={e => actualizarPrecio(p.producto_id, 'pvd', e.target.value)} />
                                                        </td>
                                                        <td className="py-2 px-2 font-semibold tabular-nums"
                                                            style={{ color: colorMargen(margenPvd) }}>
                                                            {margenPvd !== null ? `${margenPvd.toFixed(1)}%` : '—'}
                                                        </td>
                                                    </tr>
                                                )
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                {puede('editar') && (
                                    <div className="flex justify-start">
                                        <button type="button" onClick={guardarPrecios} disabled={guardandoPrecios}
                                            className="btn-primary flex items-center gap-2 text-sm">
                                            {guardandoPrecios
                                                ? <Loader2 className="w-4 h-4 animate-spin" />
                                                : <DollarSign className="w-4 h-4" />
                                            }
                                            Guardar cambios de precios
                                        </button>
                                    </div>
                                )}
                            </div>
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
    const { importaciones, proveedores, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('compras')
    const [modal, setModal] = useState<ModalState>({ type: 'none' })

    const [buscar,      setBuscar]      = useState(filtros.buscar       ?? '')
    const [estado,      setEstado]      = useState(filtros.estado       ?? '')
    const [proveedorId, setProveedorId] = useState(filtros.proveedor_id ?? '')
    const [fechaDesde,  setFechaDesde]  = useState(filtros.fecha_desde  ?? '')
    const [fechaHasta,  setFechaHasta]  = useState(filtros.fecha_hasta  ?? '')

    // Cambiar cualquier filtro después de haber buscado marca los
    // resultados como "obsoletos" — la tabla vuelve al estado vacío hasta
    // que se presione Buscar de nuevo (mismo patrón que Cuentas por
    // Pagar/Anticipos/Devoluciones).
    const [filtrosSucios, setFiltrosSucios] = useState(false)

    // Carga bajo demanda: `importaciones` viene null hasta que el usuario
    // presiona Buscar.
    const haBuscado = importaciones !== null && !filtrosSucios

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function cambiarBuscar(v: string)      { setBuscar(v);      setFiltrosSucios(true) }
    function cambiarEstado(v: string)      { setEstado(v);      setFiltrosSucios(true) }
    function cambiarProveedorId(v: string) { setProveedorId(v); setFiltrosSucios(true) }
    function cambiarFechaDesde(v: string)  { setFechaDesde(v);  setFiltrosSucios(true) }
    function cambiarFechaHasta(v: string)  { setFechaHasta(v);  setFiltrosSucios(true) }

    function aplicarFiltros() {
        router.get(route('compras.importaciones.index'), {
            estado, proveedor_id: proveedorId, fecha_desde: fechaDesde, fecha_hasta: fechaHasta, buscar,
            buscado: '1',
        }, {
            preserveState: true,
            replace: true,
            onSuccess: () => setFiltrosSucios(false),
        })
    }

    function cerrar() { setModal({ type: 'none' }) }

    function copiarImportacion(i: ImportacionRow) {
        router.post(route('compras.importaciones.copiar', i.id), {}, {
            preserveScroll: true,
        })
    }

    return (
        <AppLayout title="Importaciones" suppressFlash>
            <Head title="Importaciones" />

            <PageHeader
                title="Importaciones COMEX"
                breadcrumbs={[{ label: 'Compras' }, { label: 'Importaciones' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setModal({ type: 'crear' })}
                            className="flex items-center gap-2 whitespace-nowrap px-4 py-2 rounded-xl font-semibold text-sm text-black transition-all hover:opacity-90"
                            style={{ background: 'var(--primary)' }}>
                            <Plus size={15} /> Nueva Importación
                        </button>
                    ) : undefined
                }
            />

            <div className="px-6 pt-6 mb-2">
                {/*
                    Ancho vía `style.width` inline a propósito, NO clases Tailwind: `.input-field`
                    (app.css) declara `width:100%` fuera de cualquier @layer, y las utilidades de
                    Tailwind v4 viven dentro de su @layer utilities interno — por reglas de CSS
                    Cascade Layers, lo no-layereado siempre gana sobre lo layereado sin importar
                    especificidad ni orden, así que un w-XX de Tailwind nunca puede ganarle a
                    `.input-field`. Mismo hallazgo documentado en Asientos/Facturas de Compra/
                    Proveedores/Cuentas por Pagar/Anticipos/Devoluciones.
                */}
                <div className="overflow-x-auto">
                <div style={{ minWidth: '1050px' }}>
                <FilterToolbar
                    search={{
                        value: buscar,
                        onChange: cambiarBuscar,
                        onSearch: aplicarFiltros,
                        placeholder: 'Nombre, proveedor, invoice...',
                    }}
                    searchWidth="w-[170px]"
                >
                    <select value={estado} onChange={e => cambiarEstado(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '160px' }}>
                        <option value="">Todos los estados</option>
                        <option value="en_transito">En Tránsito</option>
                        <option value="en_aduana">En Aduana</option>
                        <option value="liquidada">Liquidada</option>
                    </select>

                    <select value={proveedorId} onChange={e => cambiarProveedorId(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '190px' }}>
                        <option value="">Todos los proveedores</option>
                        {proveedores.map(p => (
                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                        ))}
                    </select>

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
                </FilterToolbar>
                </div>
                </div>
            </div>

            {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
            {!haBuscado && (
                <div className="px-6 pb-8">
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar las importaciones.
                        </p>
                    </div>
                </div>
            )}

            {/* Tabla */}
            {haBuscado && (
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
                                No se encontraron importaciones con estos filtros
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
                                {/* Ver / Editar → siempre visible si es solo ver; editar requiere permiso */}
                                {(i.estado === 'liquidada' || puede('editar')) && (
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
                                )}
                                {/* Liquidar → solo si no está liquidada */}
                                {i.estado !== 'liquidada' && puede('editar') && (
                                    <button
                                        onClick={() => setModal({ type: 'detalle', importacion: i, tab: 'liquidar' })}
                                        title="Liquidar importación"
                                        className="p-1.5 rounded transition-colors text-green-600 dark:text-green-400 hover:bg-green-500/20">
                                        <DollarSign className="w-3.5 h-3.5" />
                                    </button>
                                )}
                                {/* Copiar → siempre disponible, como plantilla de una nueva */}
                                {puede('crear') && (
                                    <button
                                        onClick={() => copiarImportacion(i)}
                                        title="Copiar como plantilla de una nueva importación"
                                        className="p-1.5 rounded transition-colors text-amber-600 dark:text-amber-400 hover:bg-amber-500/20">
                                        <Copy className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            )}

            {modal.type === 'crear' && (
                <CrearModal proveedores={proveedores} onClose={cerrar} />
            )}
            {modal.type === 'detalle' && (
                <DetalleModal
                    importacion={modal.importacion}
                    initialTab={modal.tab}
                    proveedores={proveedores}
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
