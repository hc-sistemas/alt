import { Head, Link, router, usePage } from '@inertiajs/react'
import { useCallback, useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Eye, Plus, Search, X, Package, ChevronRight, ChevronLeft, Loader2,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { RecepcionBodega, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    recepciones: PaginatedData<RecepcionBodega>
    filtros: {
        estado?: string
        fecha_desde?: string
        fecha_hasta?: string
    }
    bodegas: { id: number; nombre: string }[]
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    completada: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    parcial:    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
}

const ESTADO_LABELS: Record<string, string> = {
    pendiente: 'Pendiente', completada: 'Completada', parcial: 'Parcial',
}

// ─── Types for modal ──────────────────────────────────────────────────────────

interface CompraLinea {
    id: number
    producto_id: number
    cantidad: number
    producto: { id: number; codigo: string; nombre: string }
}

interface CompraResultado {
    id: number
    num_documento: string
    fecha_emision: string
    proveedor: { razon_social: string }
    detalles: CompraLinea[]
}

// ─── Modal Nueva Recepción ────────────────────────────────────────────────────

interface NuevaRecepcionModalProps {
    bodegas: Props['bodegas']
    onClose: () => void
}

function NuevaRecepcionModal({ bodegas, onClose }: NuevaRecepcionModalProps) {
    const [paso, setPaso] = useState<'buscar' | 'confirmar'>('buscar')
    const [busqueda, setBusqueda] = useState('')
    const [resultados, setResultados] = useState<CompraResultado[]>([])
    const [buscando, setBuscando] = useState(false)
    const [compraSeleccionada, setCompraSeleccionada] = useState<CompraResultado | null>(null)
    const [bodegaId, setBodegaId] = useState('')
    const [cantidades, setCantidades] = useState<Record<number, number>>({})
    const [guardando, setGuardando] = useState(false)
    const [errorMsg, setErrorMsg] = useState('')

    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    const buscar = useCallback((q: string) => {
        if (!q.trim()) { setResultados([]); return }
        setBuscando(true)
        fetch(route('inventario.recepciones.buscarCompra') + '?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then((data: CompraResultado[]) => setResultados(data))
            .catch(() => setResultados([]))
            .finally(() => setBuscando(false))
    }, [])

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => buscar(busqueda), 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [busqueda, buscar])

    function seleccionarCompra(compra: CompraResultado) {
        setCompraSeleccionada(compra)
        const init: Record<number, number> = {}
        for (const d of compra.detalles) {
            init[d.id] = Number(d.cantidad) || 1
        }
        setCantidades(init)
        setErrorMsg('')
        setPaso('confirmar')
    }

    function crearRecepcion() {
        if (!compraSeleccionada || !bodegaId) return
        setGuardando(true)
        setErrorMsg('')

        const detalles = compraSeleccionada.detalles.map(d => ({
            compra_detalle_id: d.id,
            producto_id: d.producto_id,
            cantidad_esperada: cantidades[d.id] ?? (Number(d.cantidad) || 1),
        }))

        router.post(route('inventario.recepciones.store'), {
            compra_id: compraSeleccionada.id,
            bodega_id: Number(bodegaId),
            detalles,
        }, {
            onSuccess: () => onClose(),
            onError: (errs) => {
                setErrorMsg(Object.values(errs).join(', '))
                setGuardando(false)
            },
            onFinish: () => setGuardando(false),
        })
    }

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl shadow-2xl overflow-hidden"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                {/* Header */}
                <div className="flex items-center justify-between px-6 pt-5 pb-4 shrink-0"
                    style={{ borderBottom: '1px solid var(--border)' }}>
                    <div className="flex items-center gap-2">
                        <Package className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                            Nueva recepción de bodega
                        </h2>
                    </div>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6">

                    {/* ── Paso 1: Buscar compra ── */}
                    {paso === 'buscar' && (
                        <div className="space-y-4">
                            <div className="space-y-1.5">
                                <Label>Buscar factura de compra por N° documento</Label>
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-2.5 w-4 h-4 -translate-y-1/2 pointer-events-none"
                                        style={{ color: 'var(--text-muted)' }} />
                                    <Input
                                        className="pl-8 pr-8"
                                        placeholder="Ej: 001-001-000000123"
                                        value={busqueda}
                                        onChange={e => setBusqueda(e.target.value)}
                                        autoFocus
                                    />
                                    {busqueda && (
                                        <button onClick={() => setBusqueda('')}
                                            className="absolute top-1/2 right-2.5 -translate-y-1/2 hover:opacity-70 transition-opacity"
                                            style={{ color: 'var(--text-muted)' }}>
                                            <X className="w-3.5 h-3.5" />
                                        </button>
                                    )}
                                </div>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Solo aparecen compras activas con al menos un producto asociado
                                </p>
                            </div>

                            {buscando && (
                                <div className="flex items-center justify-center py-8 gap-2"
                                    style={{ color: 'var(--text-muted)' }}>
                                    <Loader2 className="w-4 h-4 animate-spin" />
                                    <span className="text-sm">Buscando...</span>
                                </div>
                            )}

                            {!buscando && busqueda && resultados.length === 0 && (
                                <div className="text-center py-8">
                                    <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No se encontraron compras con productos para "{busqueda}"
                                    </p>
                                </div>
                            )}

                            {!buscando && resultados.length > 0 && (
                                <div className="space-y-2">
                                    {resultados.map(c => (
                                        <button key={c.id}
                                            onClick={() => seleccionarCompra(c)}
                                            className="w-full text-left rounded-lg border p-3 transition-colors hover:shadow-sm"
                                            style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}
                                            onMouseEnter={e => (e.currentTarget.style.borderColor = 'var(--primary)')}
                                            onMouseLeave={e => (e.currentTarget.style.borderColor = 'var(--border)')}>
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <p className="font-mono text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                                        {c.num_documento}
                                                    </p>
                                                    <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                                        {c.proveedor?.razon_social ?? '—'}
                                                        {' · '}
                                                        {c.fecha_emision
                                                            ? new Date(c.fecha_emision + 'T00:00:00').toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' })
                                                            : '—'}
                                                        {' · '}
                                                        {c.detalles.length} producto{c.detalles.length !== 1 ? 's' : ''}
                                                    </p>
                                                </div>
                                                <ChevronRight className="w-4 h-4 shrink-0" style={{ color: 'var(--text-muted)' }} />
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {/* ── Paso 2: Confirmar recepción ── */}
                    {paso === 'confirmar' && compraSeleccionada && (
                        <div className="space-y-4">
                            {/* Compra seleccionada */}
                            <div className="rounded-lg border p-3"
                                style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.04)' }}>
                                <p className="text-xs uppercase tracking-wider font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>
                                    Compra seleccionada
                                </p>
                                <p className="font-mono text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                    {compraSeleccionada.num_documento}
                                </p>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    {compraSeleccionada.proveedor?.razon_social ?? '—'}
                                </p>
                            </div>

                            {/* Bodega destino */}
                            <div className="space-y-1.5">
                                <Label>Bodega destino <span className="text-red-400">*</span></Label>
                                <select value={bodegaId}
                                    onChange={e => setBodegaId(e.target.value)}
                                    className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    style={inputStyle}>
                                    <option value="">— Seleccionar bodega —</option>
                                    {bodegas.map(b => (
                                        <option key={b.id} value={b.id}>{b.nombre}</option>
                                    ))}
                                </select>
                            </div>

                            {/* Tabla de líneas */}
                            <div className="space-y-1.5">
                                <Label>Productos a recibir</Label>
                                <div className="rounded-lg border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-[10px] font-semibold uppercase tracking-wider"
                                                style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                                                <th className="px-3 py-2 text-left">Código</th>
                                                <th className="px-3 py-2 text-left">Producto</th>
                                                <th className="px-3 py-2 text-right w-32">Cantidad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {compraSeleccionada.detalles.map(d => (
                                                <tr key={d.id} className="border-b" style={{ borderColor: 'var(--border)' }}>
                                                    <td className="px-3 py-2 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                                        {d.producto?.codigo ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2 text-xs" style={{ color: 'var(--text-main)' }}>
                                                        {d.producto?.nombre ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2 w-32 text-right font-mono text-xs" style={{ color: 'var(--text-main)' }}>
                                                        {Number(d.cantidad)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {errorMsg && (
                                <div className="rounded-lg border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/10 p-3">
                                    <p className="text-xs text-red-600 dark:text-red-400">{errorMsg}</p>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="shrink-0 flex items-center justify-between gap-2 px-6 py-4 border-t"
                    style={{ borderColor: 'var(--border)' }}>
                    <div>
                        {paso === 'confirmar' && (
                            <Button type="button" variant="outline" size="sm"
                                onClick={() => { setPaso('buscar'); setCompraSeleccionada(null); setErrorMsg('') }}>
                                <ChevronLeft className="w-4 h-4" /> Cambiar compra
                            </Button>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                        {paso === 'confirmar' && (
                            <Button
                                type="button"
                                disabled={guardando || !bodegaId}
                                onClick={crearRecepcion}>
                                {guardando
                                    ? <><Loader2 className="w-4 h-4 animate-spin" /> Creando...</>
                                    : <><Plus className="w-4 h-4" /> Crear recepción</>
                                }
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function RecepcionesIndex() {
    const { recepciones, filtros, bodegas } = usePage<Props>().props
    const { puede } = usePermiso('inventario')

    const [modalAbierto, setModalAbierto] = useState(false)
    const [estado, setEstado]         = useState(filtros.estado ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
    const isFirstRender = useRef(true)

    useEffect(() => {
        if (isFirstRender.current) { isFirstRender.current = false; return }
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.recepciones.index'), {
                estado: estado || undefined,
                fecha_desde: fechaDesde || undefined,
                fecha_hasta: fechaHasta || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [estado, fechaDesde, fechaHasta])

    const formatFecha = (dt: string | null) =>
        dt ? new Date(dt).toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'

    return (
        <AppLayout title="Recepciones">
            <Head title="Recepciones de Bodega" />
            <PageHeader
                title="Recepciones de Bodega"
                description="Confirmación de ingreso físico de productos desde facturas de compra"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Recepciones' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setModalAbierto(true)}
                            className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                            style={{ background: 'var(--primary)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}>
                            <Plus size={15} /> Nueva Recepción
                        </button>
                    ) : undefined
                }
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="completada">Completada</option>
                        <option value="parcial">Parcial</option>
                    </select>

                    <input type="date" value={fechaDesde} onChange={e => setFechaDesde(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        placeholder="Desde" />

                    <input type="date" value={fechaHasta} onChange={e => setFechaHasta(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        placeholder="Hasta" />
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['#', 'Factura Compra', 'Proveedor', 'Bodega', 'Estado', 'Fecha Recepción', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {recepciones.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-16 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay recepciones registradas.
                                    </td>
                                </tr>
                            ) : recepciones.data.map(r => (
                                <tr key={r.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                        #{r.id}
                                    </td>
                                    <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {r.compra?.num_documento ?? '—'}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>
                                        {r.compra?.proveedor?.razon_social ?? '—'}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>
                                        {r.bodega?.nombre ?? `#${r.bodega_id}`}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize ${ESTADO_COLORES[r.estado] ?? ''}`}>
                                            {ESTADO_LABELS[r.estado] ?? r.estado}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {formatFecha(r.fecha_recepcion)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={route('inventario.recepciones.show', r.id)}>
                                            <Button variant="ghost" size="icon" title="Ver detalle">
                                                <Eye className="w-4 h-4" />
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {recepciones.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {recepciones.from}–{recepciones.to} de {recepciones.total}
                        </p>
                        <div className="flex gap-1">
                            {recepciones.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`px-3 py-1 rounded border text-xs transition-colors ${link.active ? 'border-amber-500 bg-amber-500 text-black font-medium' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}
                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 rounded border text-xs opacity-40"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {modalAbierto && (
                <NuevaRecepcionModal
                    bodegas={bodegas}
                    onClose={() => setModalAbierto(false)}
                />
            )}
        </AppLayout>
    )
}
