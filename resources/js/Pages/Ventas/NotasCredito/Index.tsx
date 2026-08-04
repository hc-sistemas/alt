import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Search, Eye, FileText, ChevronLeft, ChevronRight } from 'lucide-react'
import type { PageProps, PaginatedData } from '@/types'

interface NotaCreditoItem {
    id: number
    numero_completo: string
    fecha: string
    factura_numero: string
    cliente_razon: string
    total: number
    estado_sri: 'pendiente' | 'autorizada' | 'rechazada' | 'anulada'
}

interface Filtros {
    cliente?: string
    fecha_desde?: string
    fecha_hasta?: string
    estado?: string
}

interface Props extends PageProps {
    notas: PaginatedData<NotaCreditoItem>
    filtros: Filtros
}

const SRI_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    autorizada: { label: 'Autorizada', variant: 'success'   as const },
    rechazada:  { label: 'Rechazada',  variant: 'danger'    as const },
    anulada:    { label: 'Anulada',    variant: 'warning'   as const },
}

export default function Index() {
    const { notas, filtros } = usePage<Props>().props

    const [filtro, setFiltro] = useState<Filtros>({
        cliente:     filtros.cliente     ?? '',
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
        estado:      filtros.estado      ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.notas-credito.index'), filtro as Record<string, string | undefined>, { preserveState: true })
    }

    return (
        <AppLayout>
            <Head title="Notas de Crédito" />
            <PageHeader
                title="Notas de Crédito"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Notas de Crédito' }]}
            />

            <div className="p-6 space-y-4">
                {/* Barra de filtros */}
                <div className="flex items-center gap-3 mb-4 flex-nowrap overflow-x-auto">
                    <select value={filtro.estado} onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los SRI</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="autorizada">Autorizada</option>
                        <option value="rechazada">Rechazada</option>
                        <option value="anulada">Anulada</option>
                    </select>

                    <div className="flex items-center gap-1.5 shrink-0">
                        <label className="text-xs font-medium whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>DESDE:</label>
                        <input type="date" value={filtro.fecha_desde}
                            onChange={e => setFiltro(p => ({ ...p, fecha_desde: e.target.value }))}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }} />
                    </div>
                    <div className="flex items-center gap-1.5 shrink-0">
                        <label className="text-xs font-medium whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>AL:</label>
                        <input type="date" value={filtro.fecha_hasta}
                            onChange={e => setFiltro(p => ({ ...p, fecha_hasta: e.target.value }))}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }} />
                    </div>

                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={filtro.cliente}
                                onChange={e => setFiltro(p => ({ ...p, cliente: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                                placeholder="Nombre o RUC..."
                                className="pl-9 w-52 rounded-r-none border-r-0"
                            />
                        </div>
                        <button type="button"
                            className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={aplicarFiltros}
                            title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {/* Tabla */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    {notas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron notas de crédito</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Factura origen', 'Cliente', 'Total', 'Estado SRI', 'Acciones'].map(h => (
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
                                    {notas.data.map(n => {
                                        const cfg = SRI_CONFIG[n.estado_sri] ?? SRI_CONFIG.pendiente
                                        return (
                                            <tr
                                                key={n.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {n.numero_completo}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(n.fecha)}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {n.factura_numero}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>
                                                    {n.cliente_razon}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(n.total)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Link href={route('ventas.notas-credito.show', n.id)}>
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

                    {notas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {notas.from}–{notas.to} de {notas.total}</span>
                            <div className="flex gap-1">
                                {notas.links.map((link, i) => {
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
                                                'min-w-7 h-7 px-1.5 rounded text-xs font-medium transition-colors',
                                                link.active ? 'bg-(--primary) text-black' : 'hover:bg-amber-500/10',
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
