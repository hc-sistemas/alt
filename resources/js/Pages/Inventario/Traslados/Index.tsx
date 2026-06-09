import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Plus, Eye, FileSpreadsheet } from 'lucide-react'
import type { TrasladoBodega, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    traslados: PaginatedData<TrasladoBodega>
    bodegas: { id: number; nombre: string }[]
    filters: {
        estado?: string
        bodega_origen_id?: string
        bodega_destino_id?: string
    }
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    aceptado:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    rechazado: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

const ESTADO_LABELS: Record<string, string> = {
    pendiente: 'Pendiente', aceptado: 'Aceptado', rechazado: 'Rechazado',
}

export default function TrasladosIndex() {
    const { traslados, bodegas, filters } = usePage<Props>().props

    const [estado, setEstado]       = useState(filters.estado ?? '')
    const [origenId, setOrigenId]   = useState(filters.bodega_origen_id ?? '')
    const [destinoId, setDestinoId] = useState(filters.bodega_destino_id ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.traslados.index'), {
                estado: estado || undefined,
                bodega_origen_id: origenId || undefined,
                bodega_destino_id: destinoId || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [estado, origenId, destinoId])

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = traslados.data.map(t => ({
            'ID':             `#${t.id}`,
            'Número':         t.numero ?? '—',
            'Fecha':          t.fecha,
            'Bodega Origen':  t.bodega_origen?.nombre ?? `#${t.bodega_origen_id}`,
            'Bodega Destino': t.bodega_destino?.nombre ?? `#${t.bodega_destino_id}`,
            'Estado':         ESTADO_LABELS[t.estado] ?? t.estado,
            'Items':          t.detalles?.length ?? 0,
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Traslados')
        XLSX.writeFile(wb, 'traslados.xlsx')
    }

    const formatFecha = (dt: string) =>
        new Date(dt).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })

    return (
        <AppLayout title="Traslados">
            <Head title="Traslados" />
            <PageHeader
                title="Traslados de Inventario"
                description="Movimiento de stock entre bodegas"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Traslados' }]}
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <Link href={route('inventario.traslados.create')}>
                        <Button>
                            <Plus className="w-4 h-4" />
                            Nuevo Traslado
                        </Button>
                    </Link>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aceptado">Aceptado</option>
                        <option value="rechazado">Rechazado</option>
                    </select>

                    <select value={origenId} onChange={e => setOrigenId(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Bodega origen (todas)</option>
                        {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <select value={destinoId} onChange={e => setDestinoId(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Bodega destino (todas)</option>
                        {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <div className="flex items-center gap-2 ml-auto">
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#16A34A', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                            onClick={exportarExcel}>
                            <FileSpreadsheet className="w-4 h-4" />
                            Excel
                        </button>
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['#', 'Número', 'Fecha', 'Origen', 'Destino', 'Items', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {traslados.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-16 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay traslados registrados.
                                    </td>
                                </tr>
                            ) : traslados.data.map(t => (
                                <tr key={t.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                        #{t.id}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {t.numero ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {formatFecha(t.fecha)}
                                    </td>
                                    <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {t.bodega_origen?.nombre ?? `#${t.bodega_origen_id}`}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>
                                        {t.bodega_destino?.nombre ?? `#${t.bodega_destino_id}`}
                                    </td>
                                    <td className="px-4 py-3 text-center text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {t.detalles?.length ?? 0}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium capitalize ${ESTADO_COLORES[t.estado] ?? ''}`}>
                                            {ESTADO_LABELS[t.estado] ?? t.estado}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link href={route('inventario.traslados.show', t.id)}>
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

                {traslados.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {traslados.from}–{traslados.to} de {traslados.total}
                        </p>
                        <div className="flex gap-1">
                            {traslados.links.map((link, i) => (
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
