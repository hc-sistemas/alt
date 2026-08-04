import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, FileText, ChevronLeft, ChevronRight } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, PaginatedData } from '@/types'

interface PrefacturaCliente {
    razon_social: string
    identificacion: string
}

interface Prefactura {
    id: number
    numero: string
    fecha_emision: string
    total: number
    total_abonado: number
    saldo_pendiente: number
    estado: 'pendiente' | 'parcial' | 'liquidada' | 'anulada'
    cliente: PrefacturaCliente | null
}

interface Filtros {
    estado?: string
    cliente?: string
}

interface Props extends PageProps {
    prefacturas: PaginatedData<Prefactura>
    filtros: Filtros
}

const ESTADO_CONFIG = {
    pendiente: { label: 'Pendiente', variant: 'warning'   as const },
    parcial:   { label: 'Parcial',   variant: 'info'      as const },
    liquidada: { label: 'Liquidada', variant: 'success'   as const },
    anulada:   { label: 'Anulada',   variant: 'secondary' as const },
}

export default function Index() {
    const { prefacturas, filtros } = usePage<Props>().props
    const { puede } = usePermiso('ventas')

    const [filtro, setFiltro] = useState<Filtros>({
        estado:  filtros.estado  ?? '',
        cliente: filtros.cliente ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.prefacturas.index'), filtro as Record<string, string | undefined>, { preserveState: true })
    }

    return (
        <AppLayout>
            <Head title="Prefacturas" />
            <PageHeader
                title="Prefacturas / Reservas"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Prefacturas' }]}
                actions={
                    puede('crear') ? (
                        <Link href={route('ventas.prefacturas.create')}>
                            <Button>
                                <Plus className="w-4 h-4" />
                                Nueva Prefactura
                            </Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="p-6 space-y-4">
                {/* Barra de filtros */}
                <div className="flex items-center gap-3 mb-4 flex-nowrap overflow-x-auto">
                    <select value={filtro.estado} onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="parcial">Parcial</option>
                        <option value="liquidada">Liquidada</option>
                        <option value="anulada">Anulada</option>
                    </select>

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
                    {prefacturas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron prefacturas</p>
                            {puede('crear') && (
                                <Link href={route('ventas.prefacturas.create')}>
                                    <Button size="sm">
                                        <Plus className="w-4 h-4" />
                                        Nueva Prefactura
                                    </Button>
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Cliente', 'Total', 'Abonado', 'Saldo', 'Estado', 'Acciones'].map(h => (
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
                                    {prefacturas.data.map(pf => {
                                        const cfg = ESTADO_CONFIG[pf.estado] ?? ESTADO_CONFIG.pendiente
                                        return (
                                            <tr
                                                key={pf.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {pf.numero}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(pf.fecha_emision)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {pf.cliente ? (
                                                        <>
                                                            <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{pf.cliente.razon_social}</p>
                                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{pf.cliente.identificacion}</p>
                                                        </>
                                                    ) : (
                                                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(pf.total)}
                                                </td>
                                                <td className="px-4 py-3 text-xs text-emerald-400 font-medium">
                                                    {formatMoneda(pf.total_abonado)}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: pf.saldo_pendiente > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>
                                                    {formatMoneda(pf.saldo_pendiente)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={route('ventas.prefacturas.show', pf.id)}>
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                                style={{ color: 'var(--primary)' }}
                                                            >
                                                                <Eye className="w-3.5 h-3.5" />
                                                                Ver
                                                            </button>
                                                        </Link>
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {prefacturas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {prefacturas.from}–{prefacturas.to} de {prefacturas.total}</span>
                            <div className="flex gap-1">
                                {prefacturas.links.map((link, i) => {
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
