import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Search, Plus, ArrowUpDown } from 'lucide-react'
import type { Producto, InventarioSaldo, InventarioMovimiento, Bodega, PaginatedData, PageProps } from '@/types'
import { cn } from "../../../lib/utils";

interface SaldoBodega extends InventarioSaldo {
    bodega?: Bodega
}

interface Props extends PageProps {
    producto: Producto | null
    movimientos: PaginatedData<InventarioMovimiento> | null
    saldosPorBodega: SaldoBodega[]
    bodegas: { id: number; nombre: string }[]
    filters: {
        producto_id?: string
        bodega_id?: string
        fecha_desde?: string
        fecha_hasta?: string
        tipo?: string
    }
}

const TIPO_COLORES: Record<string, string> = {
    entrada:          'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    salida:           'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    traslado_entrada: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    traslado_salida:  'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    ajuste_positivo:  'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    ajuste_negativo:  'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400',
    reserva:          'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    liberacion:       'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
}

const TIPO_LABELS: Record<string, string> = {
    entrada: 'Entrada', salida: 'Salida',
    traslado_entrada: 'Traslado E.', traslado_salida: 'Traslado S.',
    ajuste_positivo: 'Ajuste +', ajuste_negativo: 'Ajuste −',
    reserva: 'Reserva', liberacion: 'Liberación',
}

export default function KardexIndex() {
    const { producto, movimientos, saldosPorBodega, bodegas, filters } = usePage<Props>().props

    const [search, setSearch]         = useState('')
    const [bodegaId, setBodegaId]     = useState(filters.bodega_id ?? '')
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta ?? '')
    const [tipo, setTipo]             = useState(filters.tipo ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    // Buscador de producto (cuando no hay producto seleccionado)
    const [busqueda, setBusqueda] = useState('')
    const [resultados, setResultados] = useState<Producto[]>([])
    const busquedaRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (!producto) return
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.kardex.index'), {
                producto_id: filters.producto_id,
                bodega_id: bodegaId || undefined,
                fecha_desde: fechaDesde || undefined,
                fecha_hasta: fechaHasta || undefined,
                tipo: tipo || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [bodegaId, fechaDesde, fechaHasta, tipo])

    async function buscarProductos(q: string) {
        if (!q.trim()) { setResultados([]); return }
        try {
            const res = await fetch(`/personas/clientes/search?q=${encodeURIComponent(q)}`)
            // Use productos search — fetch directo
        } catch { /* silent */ }
    }

    // Buscar producto directamente a través de router.get
    function seleccionarBusqueda() {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.kardex.index'), {
                producto_id: undefined,
                search: busqueda || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
    }

    const formatNum = (n: number | string | null | undefined) =>
        n !== null && n !== undefined ? Number(n).toFixed(4) : '—'

    const formatFecha = (dt: string) => {
        const d = new Date(dt)
        return d.toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
    }

    return (
        <AppLayout title="Kárdex">
            <Head title="Kárdex" />
            <PageHeader
                title="Kárdex de Movimientos"
                description="Historial de entradas y salidas de stock por producto"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Kárdex' }]}
            />

            <div className="p-6 space-y-6">
                {!producto ? (
                    <div className="max-w-xl">
                        <p className={cn('mb-3', 'text-sm')} style={{ color: 'var(--text-muted)' }}>
                            Selecciona un producto para ver sus movimientos
                        </p>
                        <div className={cn('flex', 'gap-2')}>
                            <div className={cn('relative', 'flex-1')}>
                                <Search className={cn('top-2.5', 'left-3', 'absolute', 'w-4', 'h-4')} style={{ color: 'var(--text-muted)' }} />
                                <Input
                                    value={busqueda}
                                    onChange={e => setBusqueda(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && router.get(
                                        route('inventario.kardex.index'),
                                        { search_producto: busqueda },
                                        { preserveState: false }
                                    )}
                                    placeholder="Buscar por código o nombre..."
                                    className="pl-9"
                                />
                            </div>
                            <Button onClick={() => router.get(
                                route('inventario.kardex.index'),
                                { search_producto: busqueda },
                                { preserveState: false }
                            )}>
                                Buscar
                            </Button>
                        </div>
                        <div className={cn('mt-4', 'text-sm')} style={{ color: 'var(--text-muted)' }}>
                            O ve directamente a{' '}
                            <Link href={route('inventario.kardex.saldos')} className="underline" style={{ color: 'var(--primary)' }}>
                                Saldos de Inventario
                            </Link>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Header producto */}
                        <div className={cn('flex', 'flex-wrap', 'justify-between', 'items-start', 'gap-4')}>
                            <div>
                                <h2 className={cn('font-semibold', 'text-base')} style={{ color: 'var(--text-main)' }}>
                                    {producto.nombre}
                                </h2>
                                <p className={cn('mt-0.5', 'font-mono', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                                    {producto.codigo}
                                </p>
                            </div>
                            <div className={cn('flex', 'gap-2')}>
                                <Link href={route('inventario.kardex.ajuste', { producto_id: producto.id })}>
                                    <Button>
                                        <Plus className={cn('w-4', 'h-4')} />
                                        Registrar Ajuste
                                    </Button>
                                </Link>
                                <Button variant="outline" onClick={() => router.get(route('inventario.kardex.index'))}>
                                    Cambiar producto
                                </Button>
                            </div>
                        </div>

                        {/* Cards de saldos por bodega */}
                        {saldosPorBodega.length > 0 && (
                            <div className={cn('flex', 'flex-wrap', 'gap-3')}>
                                {saldosPorBodega.map(s => (
                                    <div key={s.id} className={cn('px-4', 'py-3', 'border', 'rounded-xl', 'min-w-40')}
                                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                                        <p className={cn('mb-1', 'font-medium', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                                            {s.bodega?.nombre ?? `Bodega #${s.bodega_id}`}
                                        </p>
                                        <p className="text-lg font-bold" style={{ color: 'var(--text-main)' }}>
                                            {Number(s.stock_actual).toFixed(2)}
                                        </p>
                                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                            Reservado: {Number(s.stock_reservado).toFixed(2)}
                                        </p>
                                        <p className="text-xs" style={{ color: 'var(--primary)' }}>
                                            Disponible: {(Number(s.stock_actual) - Number(s.stock_reservado)).toFixed(2)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Filtros */}
                        <div className={cn('flex', 'flex-wrap', 'items-center', 'gap-3')}>
                            <select value={bodegaId} onChange={e => setBodegaId(e.target.value)}
                                className="input-field"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                                <option value="">Todas las bodegas</option>
                                {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                            </select>
                            <input type="date" value={fechaDesde} onChange={e => setFechaDesde(e.target.value)}
                                className="input-field"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                title="Fecha desde" />
                            <input type="date" value={fechaHasta} onChange={e => setFechaHasta(e.target.value)}
                                className="input-field"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                title="Fecha hasta" />
                            <select value={tipo} onChange={e => setTipo(e.target.value)}
                                className="input-field"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                                <option value="">Todos los tipos</option>
                                {Object.entries(TIPO_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>

                        {/* Tabla de movimientos */}
                        {movimientos && (
                            <>
                                <div className={cn('border', 'rounded-xl', 'overflow-x-auto')} style={{ borderColor: 'var(--border)' }}>
                                    <table className={cn('w-full', 'text-xs')}>
                                        <thead>
                                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                                {['Fecha', 'Tipo', 'Bodega', 'Cantidad', 'Costo Unit.', 'Stock Ant.', 'Stock Nuevo', 'Doc.', 'Usuario', 'Notas'].map(h => (
                                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {movimientos.data.length === 0 ? (
                                                <tr>
                                                    <td colSpan={10} className="text-center py-10 text-sm" style={{ color: 'var(--text-muted)' }}>
                                                        No hay movimientos registrados para este producto.
                                                    </td>
                                                </tr>
                                            ) : movimientos.data.map(m => (
                                                <tr key={m.id}
                                                    className={cn('hover:bg-slate-50', 'dark:hover:bg-slate-800/50', 'border-t', 'transition-colors')}
                                                    style={{ borderColor: 'var(--border)' }}>
                                                    <td className={cn('px-3', 'py-2.5', 'whitespace-nowrap')} style={{ color: 'var(--text-muted)' }}>
                                                        {formatFecha(m.created_at)}
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${TIPO_COLORES[m.tipo] ?? ''}`}>
                                                            {TIPO_LABELS[m.tipo] ?? m.tipo}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                        {m.bodega?.nombre ?? (m.bodega_id ? `#${m.bodega_id}` : '—')}
                                                    </td>
                                                    <td className={cn('px-3', 'py-2.5', 'font-mono', 'text-right')} style={{ color: 'var(--text-main)' }}>
                                                        {Number(m.cantidad).toFixed(4)}
                                                    </td>
                                                    <td className={cn('px-3', 'py-2.5', 'font-mono', 'text-right')} style={{ color: 'var(--text-muted)' }}>
                                                        {m.costo_unitario !== null ? Number(m.costo_unitario).toFixed(4) : '—'}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                                        {Number(m.stock_anterior).toFixed(4)}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right font-mono font-medium" style={{ color: 'var(--text-main)' }}>
                                                        {Number(m.stock_nuevo).toFixed(4)}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                        {m.doc_tipo ? `${m.doc_tipo}/${m.doc_id}` : '—'}
                                                    </td>
                                                    <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                        {m.usuario?.nombre ?? (m.usuario_id ? `#${m.usuario_id}` : '—')}
                                                    </td>
                                                    <td className="px-3 py-2.5 max-w-40 truncate" style={{ color: 'var(--text-muted)' }}
                                                        title={m.notas ?? ''}>
                                                        {m.notas ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Paginación */}
                                {movimientos.last_page > 1 && (
                                    <div className={cn('flex', 'justify-between', 'items-center', 'text-sm')}>
                                        <p style={{ color: 'var(--text-muted)' }}>
                                            Mostrando {movimientos.from}–{movimientos.to} de {movimientos.total}
                                        </p>
                                        <div className={cn('flex', 'gap-1')}>
                                            {movimientos.links.map((link, i) => (
                                                link.url ? (
                                                    <Link key={i} href={link.url}
                                                        className={`px-3 py-1 rounded border text-xs transition-colors ${link.active ? 'border-amber-500 bg-amber-500 text-black font-medium' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}
                                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                                    />
                                                ) : (
                                                    <span key={i} className={cn('opacity-40', 'px-3', 'py-1', 'border', 'rounded', 'text-xs')}
                                                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                                )
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    )
}
