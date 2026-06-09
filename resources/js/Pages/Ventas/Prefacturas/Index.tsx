import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, FileText, ChevronLeft, ChevronRight } from 'lucide-react'
import type { PageProps, PaginatedData } from '@/types'

interface PrefacturaCliente {
    razon_social: string
    identificacion: string
}

interface Prefactura {
    id: number
    numero_completo: string
    fecha: string
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

    const [filtro, setFiltro] = useState<Filtros>({
        estado:  filtros.estado  ?? '',
        cliente: filtros.cliente ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.prefacturas.index'), filtro, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { estado: '', cliente: '' }
        setFiltro(limpio)
        router.get(route('ventas.prefacturas.index'), {}, { preserveState: false })
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    return (
        <AppLayout>
            <Head title="Prefacturas" />
            <PageHeader
                title="Prefacturas / Reservas"
                description="Gestión de prefacturas y anticipos"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Prefacturas' }]}
                actions={
                    <Link href={route('ventas.prefacturas.create')}>
                        <Button size="sm">
                            <Plus className="w-4 h-4" />
                            Nueva Prefactura
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
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Estado</label>
                            <select
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={filtro.estado}
                                onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                            >
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="parcial">Parcial</option>
                                <option value="liquidada">Liquidada</option>
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
                    {prefacturas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron prefacturas</p>
                            <Link href={route('ventas.prefacturas.create')}>
                                <Button size="sm">
                                    <Plus className="w-4 h-4" />
                                    Nueva Prefactura
                                </Button>
                            </Link>
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
                                                    {pf.numero_completo}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(pf.fecha)}
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
