import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Search, Eye, FileText, ChevronLeft, ChevronRight } from 'lucide-react'
import type { PageProps, PaginatedData } from '@/types'

interface RetencionItem {
    id: number
    numero_completo: string
    fecha: string
    factura_numero: string
    cliente_razon: string
    total_retenido: number
    estado_sri: 'pendiente' | 'autorizada' | 'rechazada' | 'anulada'
}

interface Filtros {
    cliente?: string
    fecha_desde?: string
    fecha_hasta?: string
}

interface Props extends PageProps {
    retenciones: PaginatedData<RetencionItem>
    filtros: Filtros
}

const SRI_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    autorizada: { label: 'Autorizada', variant: 'success'   as const },
    rechazada:  { label: 'Rechazada',  variant: 'danger'    as const },
    anulada:    { label: 'Anulada',    variant: 'warning'   as const },
}

export default function Index() {
    const { retenciones, filtros } = usePage<Props>().props

    const [filtro, setFiltro] = useState<Filtros>({
        cliente:     filtros.cliente     ?? '',
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.retenciones.index'), filtro, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { cliente: '', fecha_desde: '', fecha_hasta: '' }
        setFiltro(limpio)
        router.get(route('ventas.retenciones.index'), {}, { preserveState: false })
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    return (
        <AppLayout>
            <Head title="Retenciones" />
            <PageHeader
                title="Retenciones"
                description="Comprobantes de retención emitidos"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Retenciones' }]}
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
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Cliente</label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none" style={{ color: 'var(--text-muted)' }} />
                                <Input
                                    className="pl-8"
                                    placeholder="Nombre o RUC..."
                                    value={filtro.cliente}
                                    onChange={e => setFiltro(p => ({ ...p, cliente: e.target.value }))}
                                    onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                                />
                            </div>
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
                    {retenciones.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron retenciones</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Factura', 'Cliente', 'Total Retenido', 'Estado SRI', 'Acciones'].map(h => (
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
                                    {retenciones.data.map(r => {
                                        const cfg = SRI_CONFIG[r.estado_sri] ?? SRI_CONFIG.pendiente
                                        return (
                                            <tr
                                                key={r.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {r.numero_completo}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(r.fecha)}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {r.factura_numero}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>
                                                    {r.cliente_razon}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold text-red-400">
                                                    {formatMoneda(r.total_retenido)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Link href={route('ventas.retenciones.show', r.id)}>
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

                    {retenciones.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {retenciones.from}–{retenciones.to} de {retenciones.total}</span>
                            <div className="flex gap-1">
                                {retenciones.links.map((link, i) => {
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
