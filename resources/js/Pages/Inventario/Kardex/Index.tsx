import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Search, Plus } from 'lucide-react'
import type { Producto, InventarioSaldo, InventarioMovimiento, Bodega, PaginatedData, PageProps } from '@/types'

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
    entrada:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    salida:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    traslado: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    ajuste:   'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
    reserva:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
}

const TIPO_LABELS: Record<string, string> = {
    entrada: 'Entrada', salida: 'Salida', traslado: 'Traslado',
    ajuste: 'Ajuste', reserva: 'Reserva',
}

export default function KardexIndex() {
    const { producto, movimientos, saldosPorBodega, bodegas, filters } = usePage<Props>().props

    const [bodegaId, setBodegaId]     = useState(filters.bodega_id ?? '')
    const [fechaDesde, setFechaDesde] = useState(filters.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filters.fecha_hasta ?? '')
    const [tipo, setTipo]             = useState(filters.tipo ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    const [busqueda, setBusqueda] = useState('')

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
                        <p className="text-sm mb-3" style={{ color: 'var(--text-muted)' }}>
                            Selecciona un producto para ver sus movimientos
                        </p>
                        <div className="flex gap-2">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
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
                        <div className="mt-4 text-sm" style={{ color: 'var(--text-muted)' }}>
                            O ve directamente a{' '}
                            <Link href={route('inventario.kardex.saldos')} className="underline" style={{ color: 'var(--primary)' }}>
                                Saldos de Inventario
                            </Link>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Header producto */}
                        <div className="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                    {producto.nombre}
                                </h2>
                                <p className="text-xs font-mono mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                    {producto.codigo}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Link href={route('inventario.kardex.ajuste', { producto_id: producto.id })}>
                                    <Button>
                                        <Plus className="w-4 h-4" />
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
                            <div className="flex gap-3 flex-wrap">
                                {saldosPorBodega.map(s => (
                                    <div key={s.id} className="px-4 py-3 rounded-xl border min-w-[160px]"
                                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                                        <p className="text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>
                                            {s.bodega?.nombre ?? `Bodega #${s.bodega_id}`}
                                        </p>
                                        <p className="text-lg font-bold" style={{ color: 'var(--text-main)' }}>
                                            {Number(s.cantidad).toFixed(2)}
                                        </p>
                                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                            Costo prom.: {formatNum(s.costo_promedio)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Filtros */}
                        <div className="flex gap-3 flex-wrap items-center">
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
                                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                                {['Fecha', 'Tipo', 'Bodega', 'Cantidad', 'Costo Unit.', 'Doc.', 'Doc. Nº', 'Usuario', 'Observación'].map(h => (
                                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {movimientos.data.length === 0 ? (
                                                <tr>
                                                    <td colSpan={9} className="text-center py-10 text-sm" style={{ color: 'var(--text-muted)' }}>
                                                        No hay movimientos registrados para este producto.
                                                    </td>
                                                </tr>
                                            ) : movimientos.data.map(m => (
                                                <tr key={m.id}
                                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                                    style={{ borderColor: 'var(--border)' }}>
                                                    <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                        {formatFecha(m.created_at)}
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${TIPO_COLORES[m.tipo_movimiento] ?? ''}`}>
                                                            {TIPO_LABELS[m.tipo_movimiento] ?? m.tipo_movimiento}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                        {m.bodega_origen?.nombre ?? (m.bodega_origen_id ? `#${m.bodega_origen_id}` : '—')}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                                        {Number(m.cantidad).toFixed(4)}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                                        {m.costo_unitario !== null ? Number(m.costo_unitario).toFixed(4) : '—'}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                        {m.documento_tipo ?? '—'}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                        {m.documento_numero ?? (m.documento_id ? `#${m.documento_id}` : '—')}
                                                    </td>
                                                    <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                        {m.usuario_id ? `#${m.usuario_id}` : '—'}
                                                    </td>
                                                    <td className="px-3 py-2.5 max-w-[160px] truncate" style={{ color: 'var(--text-muted)' }}
                                                        title={m.observacion ?? ''}>
                                                        {m.observacion ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {movimientos.last_page > 1 && (
                                    <div className="flex items-center justify-between text-sm">
                                        <p style={{ color: 'var(--text-muted)' }}>
                                            Mostrando {movimientos.from}–{movimientos.to} de {movimientos.total}
                                        </p>
                                        <div className="flex gap-1">
                                            {movimientos.links.map((link, i) => (
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
                            </>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    )
}
