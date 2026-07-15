import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import { createPortal } from 'react-dom'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { formatFecha, formatMoneda } from '@/lib/utils'
import { toastError } from '@/lib/toast'
import { Save, Search, Plus, Check, X } from 'lucide-react'
import type { PageProps, TallerOrdenTrabajo } from '@/types'

interface Tecnico {
    id: number
    nombre: string
}

interface ProductoOpcion {
    id: number
    codigo: string
    nombre: string
    costo: number
    pvp: number
}

interface Props extends PageProps {
    orden: TallerOrdenTrabajo
    tecnicos: Tecnico[]
    productos: ProductoOpcion[]
}

const ESTADO_CONFIG: Record<string, { label: string; variant: 'info' | 'warning' | 'default' | 'success' | 'secondary' }> = {
    pendiente:  { label: 'Pendiente',  variant: 'warning' },
    en_proceso: { label: 'En proceso', variant: 'info' },
    listo:      { label: 'Listo',      variant: 'success' },
    entregado:  { label: 'Entregado',  variant: 'secondary' },
    facturado:  { label: 'Facturado',  variant: 'success' },
    garantia:   { label: 'Garantía',   variant: 'secondary' },
}

const DIAGNOSTICO_ESTADO_CONFIG: Record<string, { label: string; variant: 'warning' | 'success' | 'danger' }> = {
    pendiente: { label: 'Pendiente', variant: 'warning' },
    aprobado:  { label: 'Aprobado',  variant: 'success' },
    rechazado: { label: 'Rechazado', variant: 'danger' },
}

const REPUESTO_ESTADO_CONFIG: Record<string, { label: string; variant: 'info' | 'success' | 'secondary' }> = {
    reservado: { label: 'Reservado', variant: 'info' },
    entregado: { label: 'Entregado', variant: 'success' },
    devuelto:  { label: 'Devuelto',  variant: 'secondary' },
}

const TIPO_ORDEN_LABELS: Record<number, string> = {
    1: 'Reparación',
    2: 'Garantía',
    3: 'Revisión',
}

// Slot de altura fija debajo de un input (16px), siempre presente con o sin
// texto, para que Cantidad/Precio/N° serie queden a la misma altura entre sí
// — mismo patrón usado en Facturas para el hint de "Stock: N".
const hintSlotCls = "h-4 mt-0.5 text-[11px] font-medium leading-4 whitespace-nowrap overflow-hidden"

async function consultarSaldoDisponible(ordenId: number, productoId: number): Promise<number> {
    const res = await fetch(
        route('taller.ordenes.repuestos.saldo-disponible', { orden: ordenId, producto_id: productoId }),
        { headers: { Accept: 'application/json' } },
    )
    if (!res.ok) {
        const data = await res.json().catch(() => null) as { error?: string } | null
        throw new Error(data?.error || 'No se pudo consultar el stock.')
    }
    const data = await res.json() as { disponible: number }
    return data.disponible
}

export default function OrdenTrabajoShow() {
    const { orden, tecnicos, productos } = usePage<Props>().props
    const cfg = ESTADO_CONFIG[orden.estado] ?? { label: orden.estado, variant: 'secondary' as const }

    const [estado, setEstado] = useState(orden.estado)
    const [tecnicoId, setTecnicoId] = useState(orden.tecnico_id ? String(orden.tecnico_id) : '')
    const [actualizando, setActualizando] = useState(false)

    function actualizarEstado() {
        setActualizando(true)
        router.patch(route('taller.ordenes.cambiar-estado', orden.id), {
            estado,
            tecnico_id: tecnicoId || null,
        }, {
            preserveScroll: true,
            onFinish: () => setActualizando(false),
        })
    }

    // ── Diagnósticos ─────────────────────────────────────────────────────────
    const puedeDiagnosticar = orden.estado === 'pendiente' || orden.estado === 'en_proceso'
    const [aprobandoId, setAprobandoId] = useState<number | null>(null)

    function resolverDiagnostico(diagnosticoId: number, aprueba: boolean) {
        setAprobandoId(diagnosticoId)
        router.patch(route('taller.diagnosticos.aprobar', diagnosticoId), { aprueba }, {
            preserveScroll: true,
            onFinish: () => setAprobandoId(null),
        })
    }

    // ── Repuestos ────────────────────────────────────────────────────────────
    const puedeAgregarRepuesto = orden.estado === 'en_proceso'
    const [busquedaProducto, setBusquedaProducto] = useState('')
    const [matchesModal, setMatchesModal] = useState<ProductoOpcion[] | null>(null)
    const [errorBusqueda, setErrorBusqueda] = useState('')
    const [productoSel, setProductoSel] = useState<ProductoOpcion | null>(null)
    const [cantidad, setCantidad] = useState('1')
    const [precioVenta, setPrecioVenta] = useState('')
    const [numeroSerie, setNumeroSerie] = useState('')
    const [agregando, setAgregando] = useState(false)
    const [disponible, setDisponible] = useState<number | null>(null)
    const [disponibleCargando, setDisponibleCargando] = useState(false)
    const [disponibleError, setDisponibleError] = useState('')

    const cantidadNum = parseInt(cantidad, 10) || 0
    const excedeStock = disponible !== null && cantidadNum > disponible

    function actualizarDisponible(productoId: number) {
        setDisponibleCargando(true)
        setDisponibleError('')
        consultarSaldoDisponible(orden.id, productoId)
            .then(d => {
                setDisponible(d)
                setDisponibleCargando(false)
            })
            .catch((err: unknown) => {
                setDisponible(null)
                setDisponibleCargando(false)
                setDisponibleError(err instanceof Error ? err.message : 'No se pudo consultar el stock.')
            })
    }

    function seleccionarProducto(p: ProductoOpcion) {
        setProductoSel(p)
        setPrecioVenta(String(p.pvp))
        setBusquedaProducto('')
        setMatchesModal(null)
        setErrorBusqueda('')
        actualizarDisponible(p.id)
    }

    function buscarProducto() {
        const q = busquedaProducto.trim().toLowerCase()
        if (!q) return
        const found = productos.filter(p =>
            p.codigo.toLowerCase().includes(q) || p.nombre.toLowerCase().includes(q)
        )
        if (found.length === 1) {
            seleccionarProducto(found[0])
        } else if (found.length > 1) {
            setMatchesModal(found)
            setErrorBusqueda('')
        } else {
            setMatchesModal(null)
            setErrorBusqueda('Producto no encontrado')
        }
    }

    function limpiarProducto() {
        setProductoSel(null)
        setPrecioVenta('')
        setCantidad('1')
        setNumeroSerie('')
        setDisponible(null)
        setDisponibleCargando(false)
        setDisponibleError('')
    }

    function agregarRepuesto() {
        if (!productoSel) { toastError('Seleccione un producto.'); return }
        const cant = parseInt(cantidad, 10)
        if (!cant || cant < 1) { toastError('La cantidad debe ser mayor a 0.'); return }
        const precio = Number(precioVenta)
        if (isNaN(precio) || precio < 0) { toastError('El precio de venta no es válido.'); return }
        if (disponible !== null && cant > disponible) {
            toastError(`Stock insuficiente en Bodega Taller: disponible ${disponible}, solicitado ${cant}.`)
            return
        }

        setAgregando(true)
        router.post(route('taller.ordenes.repuestos.store', orden.id), {
            producto_id: productoSel.id,
            cantidad: cant,
            precio_venta: precio,
            numero_serie: numeroSerie || null,
        }, {
            preserveScroll: true,
            only: ['orden'],
            onSuccess: () => limpiarProducto(),
            onFinish: () => setAgregando(false),
        })
    }

    return (
        <AppLayout title={`Orden de Trabajo ${orden.numero ?? `#${orden.id}`}`}>
            <Head title={`Orden de Trabajo ${orden.numero ?? `#${orden.id}`}`} />
            <PageHeader
                title={`Orden de Trabajo ${orden.numero ?? `#${orden.id}`}`}
                breadcrumbs={[
                    { label: 'Taller' },
                    { label: 'Órdenes de Trabajo', href: route('taller.ordenes.index') },
                    { label: orden.numero ?? `#${orden.id}` },
                ]}
            />

            <div className="p-6 max-w-3xl space-y-6">
                {/* Datos generales */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            {orden.numero ?? `#${orden.id}`}
                        </span>
                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    </div>

                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>N° OT</p>
                            <p style={{ color: 'var(--text-main)' }}>{orden.numero ?? `#${orden.id}`}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha inicio</p>
                            <p style={{ color: 'var(--text-main)' }}>{formatFecha(orden.fecha_inicio)}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Técnico asignado</p>
                            <p style={{ color: 'var(--text-main)' }}>{orden.tecnico?.nombre ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Tipo de orden</p>
                            <p style={{ color: 'var(--text-main)' }}>{TIPO_ORDEN_LABELS[orden.tipo_orden] ?? '—'}</p>
                        </div>
                    </div>
                </div>

                {/* Cliente y Equipo */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                            <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Cliente</h3>
                        </div>
                        <div className="p-4 space-y-3 text-sm">
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Razón social</p>
                                <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.cliente?.razon_social ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Identificación</p>
                                <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.cliente?.identificacion ?? '—'}</p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                            <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Equipo</h3>
                        </div>
                        <div className="p-4 space-y-3 text-sm">
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Tipo</p>
                                <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.equipo?.tipo?.descripcion ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Marca / Modelo</p>
                                <p style={{ color: 'var(--text-main)' }}>
                                    {[orden.ingreso?.equipo?.marca, orden.ingreso?.equipo?.modelo].filter(Boolean).join(' ') || '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Serie</p>
                                <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.equipo?.numero_serie ?? '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Color</p>
                                <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.equipo?.color ?? '—'}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {orden.ingreso?.diagnostico_inicial && (
                    <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                            <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Diagnóstico inicial (recepción)</h3>
                        </div>
                        <div className="p-4 text-sm">
                            <p className="whitespace-pre-wrap" style={{ color: 'var(--text-muted)' }}>
                                {orden.ingreso.diagnostico_inicial}
                            </p>
                        </div>
                    </div>
                )}

                {/* Cambiar estado */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Cambiar estado</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <label className="text-xs" style={{ color: 'var(--text-muted)' }}>Estado</label>
                            <select
                                value={estado}
                                onChange={e => setEstado(e.target.value)}
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            >
                                <option value="pendiente">Pendiente</option>
                                <option value="en_proceso">En proceso</option>
                                <option value="listo">Listo</option>
                                <option value="entregado">Entregado</option>
                                <option value="facturado">Facturado</option>
                                <option value="garantia">Garantía</option>
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-xs" style={{ color: 'var(--text-muted)' }}>Técnico</label>
                            <select
                                value={tecnicoId}
                                onChange={e => setTecnicoId(e.target.value)}
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            >
                                <option value="">Sin asignar</option>
                                {tecnicos.map(t => (
                                    <option key={t.id} value={t.id}>{t.nombre}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <Button onClick={actualizarEstado} loading={actualizando}>
                        <Save className="w-4 h-4" />
                        Actualizar
                    </Button>
                </div>

                {/* Diagnósticos */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3 flex items-center justify-between" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Diagnósticos</h3>
                        <Button
                            size="sm"
                            disabled={!puedeDiagnosticar}
                            title={puedeDiagnosticar ? undefined : 'No disponible en el estado actual de la orden'}
                            onClick={() => router.visit(route('taller.diagnosticos.create', orden.id))}
                        >
                            <Plus className="w-3.5 h-3.5" />
                            Nuevo Diagnóstico
                        </Button>
                    </div>

                    {(orden.diagnosticos ?? []).length === 0 ? (
                        <p className="p-4 text-sm" style={{ color: 'var(--text-muted)' }}>No hay diagnósticos registrados.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                        {['Fecha', 'Técnico', 'Diagnóstico', 'Tiempo estimado', 'Estado', 'Aprobación', ''].map(h => (
                                            <th key={h} className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {(orden.diagnosticos ?? []).map(d => {
                                        const dcfg = DIAGNOSTICO_ESTADO_CONFIG[d.estado] ?? { label: d.estado, variant: 'secondary' as const }
                                        return (
                                            <tr key={d.id} className="border-t align-top" style={{ borderColor: 'var(--border)' }}>
                                                <td className="px-4 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(d.fecha)}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--text-main)' }}>
                                                    {d.tecnico?.nombre ?? '—'}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                    {d.diagnostico}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                    {d.tiempo_estimado ? `${d.tiempo_estimado} ${d.tipo_tiempo === 'dias' ? 'días' : 'horas'}` : '—'}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <Badge variant={dcfg.variant}>{dcfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-2.5 text-xs whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                    {d.cliente_aprueba === null
                                                        ? 'Pendiente'
                                                        : d.cliente_aprueba ? 'Aprobado' : 'Rechazado'}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    {d.estado === 'pendiente' && (
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                loading={aprobandoId === d.id}
                                                                style={{ borderColor: '#10B981', color: '#10B981' }}
                                                                onClick={() => resolverDiagnostico(d.id, true)}
                                                            >
                                                                <Check className="w-3.5 h-3.5" />
                                                                Aprobar
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                loading={aprobandoId === d.id}
                                                                style={{ borderColor: '#EF4444', color: '#EF4444' }}
                                                                onClick={() => resolverDiagnostico(d.id, false)}
                                                            >
                                                                <X className="w-3.5 h-3.5" />
                                                                Rechazar
                                                            </Button>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Repuestos */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Repuestos</h3>
                    </div>

                    {(orden.repuestos ?? []).length === 0 ? (
                        <p className="p-4 text-sm" style={{ color: 'var(--text-muted)' }}>No hay repuestos registrados.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['Producto', 'Serie', 'Cantidad', 'Costo U.', 'Precio Venta', 'Estado'].map(h => (
                                        <th key={h} className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {(orden.repuestos ?? []).map(r => {
                                    const rcfg = REPUESTO_ESTADO_CONFIG[r.estado] ?? { label: r.estado, variant: 'secondary' as const }
                                    return (
                                        <tr key={r.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                {r.producto?.nombre ?? `#${r.producto_id}`}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {r.numero_serie ?? '—'}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                {r.cantidad}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {formatMoneda(r.costo_unitario)}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                {formatMoneda(r.precio_venta)}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <Badge variant={rcfg.variant}>{rcfg.label}</Badge>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    )}

                    {/* Formulario agregar repuesto */}
                    <div className="p-4 border-t space-y-3" style={{ borderColor: 'var(--border)' }}>
                        {!puedeAgregarRepuesto && (
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Solo se pueden agregar repuestos cuando la orden está "En proceso".
                            </p>
                        )}

                        <div className="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                            <div className="sm:col-span-2 space-y-1">
                                <label className="text-xs" style={{ color: 'var(--text-muted)' }}>Producto</label>
                                {productoSel ? (
                                    <div
                                        className="flex items-center gap-1 min-w-0 px-2 py-1.5 rounded"
                                        style={{ border: '1px solid var(--primary)', background: 'rgba(245,158,11,0.06)' }}
                                    >
                                        <span className="font-mono font-semibold text-xs shrink-0" style={{ color: 'var(--primary)' }}>
                                            {productoSel.codigo}
                                        </span>
                                        <span className="text-xs truncate flex-1" style={{ color: 'var(--text-main)' }}>
                                            {' — '}{productoSel.nombre}
                                        </span>
                                        <button type="button" className="shrink-0 p-0.5 rounded hover:bg-red-500/10" onClick={limpiarProducto} title="Quitar producto">
                                            <X className="w-3 h-3 text-red-400" />
                                        </button>
                                    </div>
                                ) : (
                                    <div>
                                        <div className="relative">
                                            <Search className="absolute left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none" style={{ color: 'var(--text-muted)' }} />
                                            <input
                                                type="text"
                                                disabled={!puedeAgregarRepuesto}
                                                className="w-full h-9 pl-7 pr-2 text-xs rounded border focus:outline-none disabled:opacity-50"
                                                style={{ background: 'var(--bg-main)', borderColor: errorBusqueda ? '#ef4444' : 'var(--border)', color: 'var(--text-main)' }}
                                                placeholder="Código o nombre... Enter"
                                                value={busquedaProducto}
                                                onChange={e => { setBusquedaProducto(e.target.value); setErrorBusqueda('') }}
                                                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); buscarProducto() } }}
                                            />
                                        </div>
                                        {errorBusqueda && <p className="text-xs mt-0.5" style={{ color: '#ef4444' }}>{errorBusqueda}</p>}
                                    </div>
                                )}
                                <div className={hintSlotCls} />
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs" style={{ color: 'var(--text-muted)' }}>Cantidad</label>
                                <Input
                                    type="number"
                                    min="1"
                                    step="1"
                                    disabled={!puedeAgregarRepuesto}
                                    value={cantidad}
                                    onChange={e => setCantidad(e.target.value)}
                                />
                                <div
                                    className={hintSlotCls}
                                    style={{
                                        color: disponibleCargando
                                            ? 'var(--text-muted)'
                                            : excedeStock
                                                ? 'var(--color-danger)'
                                                : 'var(--color-warning)',
                                    }}
                                >
                                    {productoSel && (
                                        disponibleCargando
                                            ? 'Consultando...'
                                            : disponibleError
                                                ? disponibleError
                                                : disponible !== null
                                                    ? `Stock: ${disponible}`
                                                    : ''
                                    )}
                                </div>
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs" style={{ color: 'var(--text-muted)' }}>Precio venta</label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    disabled={!puedeAgregarRepuesto}
                                    value={precioVenta}
                                    onChange={e => setPrecioVenta(e.target.value)}
                                />
                                <div className={hintSlotCls} />
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs" style={{ color: 'var(--text-muted)' }}>N° serie (opcional)</label>
                                <Input
                                    type="text"
                                    disabled={!puedeAgregarRepuesto}
                                    value={numeroSerie}
                                    onChange={e => setNumeroSerie(e.target.value)}
                                />
                                <div className={hintSlotCls} />
                            </div>
                        </div>

                        <Button
                            size="sm"
                            disabled={!puedeAgregarRepuesto}
                            loading={agregando}
                            onClick={agregarRepuesto}
                        >
                            <Plus className="w-3.5 h-3.5" />
                            Agregar
                        </Button>
                    </div>
                </div>

                <div className="flex justify-between pt-4 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button variant="outline" onClick={() => router.visit(route('taller.ordenes.index'))}>
                        Volver al Ingreso
                    </Button>
                    {orden.estado === 'listo' && (
                        <Button onClick={() => router.visit(route('taller.liquidacion.show', orden.id))}>
                            Liquidar Orden
                        </Button>
                    )}
                </div>
            </div>

            {matchesModal !== null && createPortal(
                <>
                    <div
                        className="fixed inset-0"
                        style={{ background: 'rgba(0,0,0,0.5)', zIndex: 50 }}
                        onClick={() => setMatchesModal(null)}
                    />
                    <div
                        className="fixed inset-0 flex items-center justify-center p-4"
                        style={{ zIndex: 51 }}
                        onKeyDown={e => { if (e.key === 'Escape') setMatchesModal(null) }}
                    >
                        <div
                            className="w-full rounded-xl shadow-xl flex flex-col"
                            style={{ maxWidth: 600, background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                        >
                            <div
                                className="flex items-center justify-between px-4 py-3 shrink-0"
                                style={{ borderBottom: '1px solid var(--border)' }}
                            >
                                <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Seleccionar Producto
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setMatchesModal(null)}
                                    className="p-1 rounded-md transition-colors"
                                    style={{ color: 'var(--text-muted)' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(0,0,0,0.08)')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>
                            <div style={{ maxHeight: 360, overflowY: 'auto' }}>
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0" style={{ background: 'var(--bg-card)' }}>
                                        <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                            {['Código', 'Nombre', 'PVP'].map(h => (
                                                <th
                                                    key={h}
                                                    className="text-left px-4 py-2 text-xs font-medium"
                                                    style={{ color: 'var(--text-muted)' }}
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {matchesModal.map(p => (
                                            <tr
                                                key={p.id}
                                                onClick={() => seleccionarProducto(p)}
                                                className="cursor-pointer transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                                onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.08)')}
                                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                            >
                                                <td
                                                    className="px-4 py-2.5 font-mono text-xs font-semibold whitespace-nowrap"
                                                    style={{ color: 'var(--primary)' }}
                                                >
                                                    {p.codigo}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                    {p.nombre}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                                    {formatMoneda(p.pvp)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div
                                className="px-4 py-2 text-xs shrink-0"
                                style={{ borderTop: '1px solid var(--border)', color: 'var(--text-muted)' }}
                            >
                                {matchesModal.length} resultado{matchesModal.length !== 1 ? 's' : ''}
                            </div>
                        </div>
                    </div>
                </>,
                document.body
            )}
        </AppLayout>
    )
}
