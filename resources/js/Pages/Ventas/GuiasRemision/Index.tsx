import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, FileText, ChevronLeft, ChevronRight } from 'lucide-react'
import type { PageProps, PaginatedData } from '@/types'

interface GuiaRemisionItem {
    id: number
    numero_completo: string
    fecha: string
    factura_numero: string | null
    transportista_nombre: string | null
    destino: string
    estado_sri: 'pendiente' | 'autorizada' | 'rechazada' | 'anulada'
}

interface Filtros {
    fecha_desde?: string
    fecha_hasta?: string
    estado?: string
}

interface Props extends PageProps {
    guias: PaginatedData<GuiaRemisionItem>
    filtros: Filtros
}

const SRI_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    autorizada: { label: 'Autorizada', variant: 'success'   as const },
    rechazada:  { label: 'Rechazada',  variant: 'danger'    as const },
    anulada:    { label: 'Anulada',    variant: 'warning'   as const },
}

export default function Index() {
    const { guias, filtros } = usePage<Props>().props

    const [filtro, setFiltro] = useState<Filtros>({
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
        estado:      filtros.estado      ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.guias-remision.index'), filtro, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { fecha_desde: '', fecha_hasta: '', estado: '' }
        setFiltro(limpio)
        router.get(route('ventas.guias-remision.index'), {}, { preserveState: false })
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    return (
        <AppLayout>
            <Head title="Guías de Remisión" />
            <PageHeader
                title="Guías de Remisión"
                description="Gestión de guías de remisión"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Guías de Remisión' }]}
                actions={
                    <Link href={route('ventas.guias-remision.create')}>
                        <Button size="sm">
                            <Plus className="w-4 h-4" />
                            Nueva Guía
                        </Button>
                    </Link>
                }
            />

            <div className="p-6 space-y-4">
                {/* Filtros */}
                <div
                    className="rounded-xl p-4 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Desde</label>
                            <Input
                                type="date"
                                value={filtro.fecha_desde}
                                onChange={e => setFiltro(p => ({ ...p, fecha_desde: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            />
                        </div>
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                            <Input
                                type="date"
                                value={filtro.fecha_hasta}
                                onChange={e => setFiltro(p => ({ ...p, fecha_hasta: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            />
                        </div>
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Estado SRI</label>
                            <select
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={filtro.estado}
                                onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                            >
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="autorizada">Autorizada</option>
                                <option value="rechazada">Rechazada</option>
                                <option value="anulada">Anulada</option>
                            </select>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 mt-3">
                        {hayFiltros && (
                            <Button type="button" variant="ghost" size="sm" onClick={limpiarFiltros}>
                                Limpiar
                            </Button>
                        )}
                        <Button type="button" size="sm" onClick={aplicarFiltros}>
                            <Search className="w-4 h-4" />
                            Buscar
                        </Button>
                    </div>
                </div>

                {/* Tabla */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    {guias.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron guías de remisión</p>
                            <Link href={route('ventas.guias-remision.create')}>
                                <Button size="sm">
                                    <Plus className="w-4 h-4" />
                                    Nueva Guía
                                </Button>
                            </Link>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Factura', 'Transportista', 'Destino', 'Estado SRI', 'Acciones'].map(h => (
                                            <th
                                                key={h}
                                                className="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide"
                                                style={{ color: 'var(--text-muted)' }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {guias.data.map(g => {
                                        const cfg = SRI_CONFIG[g.estado_sri] ?? SRI_CONFIG.pendiente
                                        return (
                                            <tr
                                                key={g.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {g.numero_completo}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(g.fecha)}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {g.factura_numero ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>
                                                    {g.transportista_nombre ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {g.destino}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Link href={route('ventas.guias-remision.show', g.id)}>
                                                        <button
                                                            type="button"
                                                            className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                            style={{ color: 'var(--primary)' }}
                                                        >
                                                            <Eye className="w-3.5 h-3.5" />
                                                            Ver
                                                        </button>
                                                    </Link>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {guias.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {guias.from}–{guias.to} de {guias.total}</span>
                            <div className="flex gap-1">
                                {guias.links.map((link, i) => {
                                    const isPrev = link.label.includes('Prev') || link.label === '&laquo; Previous'
                                    const isNext = link.label.includes('Next') || link.label === 'Next &raquo;'
                                    const label = isPrev ? <ChevronLeft className="w-3.5 h-3.5" /> :
                                                  isNext ? <ChevronRight className="w-3.5 h-3.5" /> :
                                                  link.label
                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.visit(link.url)}
                                            className={cn(
                                                'min-w-[28px] h-7 px-1.5 rounded text-xs font-medium transition-colors',
                                                link.active ? 'bg-[var(--primary)] text-black' : 'hover:bg-amber-500/10',
                                                !link.url && 'opacity-40 cursor-not-allowed',
                                            )}
                                            style={!link.active ? { color: 'var(--text-muted)' } : {}}
                                        >
                                            {label}
                                        </button>
                                    )
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    )
}
