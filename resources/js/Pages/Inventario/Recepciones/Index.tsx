import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Eye } from 'lucide-react'
import type { RecepcionBodega, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    recepciones: PaginatedData<RecepcionBodega>
    filtros: {
        estado?: string
        fecha_desde?: string
        fecha_hasta?: string
    }
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    completada: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    parcial:    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
}

const ESTADO_LABELS: Record<string, string> = {
    pendiente: 'Pendiente', completada: 'Completada', parcial: 'Parcial',
}

export default function RecepcionesIndex() {
    const { recepciones, filtros } = usePage<Props>().props

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
        </AppLayout>
    )
}
