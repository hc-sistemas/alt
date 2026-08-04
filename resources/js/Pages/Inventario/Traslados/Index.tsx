import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Plus, Eye, Search, FileSpreadsheet } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import TrasladoDetalleModal from './DetalleModal'
import type { TrasladoBodega, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    traslados: PaginatedData<TrasladoBodega> | null
    bodegas: { id: number; nombre: string }[]
    filters: {
        search?: string
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
    const { puede } = usePermiso('inventario')

    const [search, setSearch]       = useState(filters.search ?? '')
    const [estado, setEstado]       = useState(filters.estado ?? '')
    const [origenId, setOrigenId]   = useState(filters.bodega_origen_id ?? '')
    const [destinoId, setDestinoId] = useState(filters.bodega_destino_id ?? '')
    const [detalleId, setDetalleId] = useState<number | null>(null)

    const haBuscado = traslados !== null

    function buscar() {
        router.get(route('inventario.traslados.index'), {
            search: search || undefined,
            estado: estado || undefined,
            bodega_origen_id: origenId || undefined,
            bodega_destino_id: destinoId || undefined,
            buscado: '1',
        }, { preserveState: false })
    }

    async function exportarExcel() {
        if (!traslados) return
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
        XLSX.utils.book_append_sheet(wb, ws, 'Movimientos')
        XLSX.writeFile(wb, 'movimientos.xlsx')
    }

    const formatFecha = (dt: string) =>
        new Date(dt).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })

    return (
        <AppLayout title="Movimientos">
            <Head title="Movimientos" />
            <PageHeader
                title="Movimientos de productos"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Movimientos' }]}
                actions={
                    puede('crear') ? (
                        <Link href={route('inventario.traslados.create')}>
                            <Button>
                                <Plus className="w-4 h-4" />
                                Nuevo Movimiento
                            </Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-nowrap overflow-x-auto">
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aceptado">Aceptado</option>
                        <option value="rechazado">Rechazado</option>
                    </select>

                    <select value={origenId} onChange={e => setOrigenId(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Bodega origen (todas)</option>
                        {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <select value={destinoId} onChange={e => setDestinoId(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Bodega destino (todas)</option>
                        {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && buscar()}
                                placeholder="Número de traslado..."
                                className="pl-9 w-52 rounded-r-none border-r-0"
                            />
                        </div>
                        <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={buscar}
                            title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>

                    <button className="flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0"
                        style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A', transition: 'background 0.2s' }}
                        onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                        onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                        onClick={exportarExcel}
                        disabled={!traslados}
                        title="Excel">
                        <FileSpreadsheet className="w-4 h-4" />
                    </button>
                </div>

                {/* Estado inicial: aún no se ha buscado */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar los movimientos.
                        </p>
                    </div>
                )}

                {haBuscado && traslados && (
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
                                        No hay movimientos registrados.
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
                                        <Button variant="ghost" size="icon" title="Ver detalle"
                                            onClick={() => setDetalleId(t.id)}>
                                            <Eye className="w-4 h-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}

                {haBuscado && traslados && traslados.last_page > 1 && (
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

            <TrasladoDetalleModal
                trasladoId={detalleId}
                onClose={() => setDetalleId(null)}
                onChanged={() => router.reload({ only: ['traslados'] })}
            />
        </AppLayout>
    )
}
