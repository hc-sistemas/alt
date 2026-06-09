import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, Ban, FileText, ChevronLeft, ChevronRight, ArrowRightLeft } from 'lucide-react'
import type { PageProps, PaginatedData } from '@/types'

interface ProformaCliente {
    razon_social: string
    identificacion: string
}

interface Proforma {
    id: number
    numero_completo: string
    fecha: string
    fecha_vencimiento: string
    total: number
    estado: 'pendiente' | 'facturada' | 'vencida' | 'anulada'
    cliente: ProformaCliente | null
}

interface Filtros {
    estado?: string
    cliente?: string
    fecha_desde?: string
    fecha_hasta?: string
}

interface Props extends PageProps {
    proformas: PaginatedData<Proforma>
    filtros: Filtros
}

const ESTADO_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    facturada:  { label: 'Facturada',  variant: 'success'   as const },
    vencida:    { label: 'Vencida',    variant: 'danger'    as const },
    anulada:    { label: 'Anulada',    variant: 'warning'   as const },
}

export default function Index() {
    const { proformas, filtros } = usePage<Props>().props

    const [filtro, setFiltro] = useState<Filtros>({
        estado:      filtros.estado      ?? '',
        cliente:     filtros.cliente     ?? '',
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.proformas.index'), filtro, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { estado: '', cliente: '', fecha_desde: '', fecha_hasta: '' }
        setFiltro(limpio)
        router.get(route('ventas.proformas.index'), {}, { preserveState: false })
    }

    const handleConvertir = async (p: Proforma) => {
        const result = await Swal.fire({
            title: 'Convertir a Factura',
            text: `¿Desea convertir la proforma ${p.numero_completo} en una factura?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, convertir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!result.isConfirmed) return
        router.post(route('ventas.proformas.convertir', p.id), {}, { preserveState: true })
    }

    const handleAnular = async (p: Proforma) => {
        const result = await Swal.fire({
            title: 'Anular proforma',
            text: `¿Desea anular la proforma ${p.numero_completo}? Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })
        if (!result.isConfirmed) return
        router.delete(route('ventas.proformas.destroy', p.id), { preserveState: true })
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    return (
        <AppLayout>
            <Head title="Proformas" />
            <PageHeader
                title="Proformas"
                description="Gestión de proformas y cotizaciones"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Proformas' }]}
                actions={
                    <Link href={route('ventas.proformas.create')}>
                        <Button size="sm">
                            <Plus className="w-4 h-4" />
                            Nueva Proforma
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
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
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
                                <option value="facturada">Facturada</option>
                                <option value="vencida">Vencida</option>
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
                    {proformas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron proformas</p>
                            <Link href={route('ventas.proformas.create')}>
                                <Button size="sm">
                                    <Plus className="w-4 h-4" />
                                    Nueva Proforma
                                </Button>
                            </Link>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Vencimiento', 'Cliente', 'Total', 'Estado', 'Acciones'].map(h => (
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
                                    {proformas.data.map(p => {
                                        const cfg = ESTADO_CONFIG[p.estado] ?? ESTADO_CONFIG.pendiente
                                        const esPendiente = p.estado === 'pendiente'
                                        return (
                                            <tr
                                                key={p.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {p.numero_completo}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(p.fecha)}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(p.fecha_vencimiento)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {p.cliente ? (
                                                        <>
                                                            <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{p.cliente.razon_social}</p>
                                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{p.cliente.identificacion}</p>
                                                        </>
                                                    ) : (
                                                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(p.total)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={route('ventas.proformas.show', p.id)}>
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                                style={{ color: 'var(--primary)' }}
                                                            >
                                                                <Eye className="w-3.5 h-3.5" />
                                                                Ver
                                                            </button>
                                                        </Link>
                                                        {esPendiente && (
                                                            <>
                                                                <button
                                                                    type="button"
                                                                    className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-emerald-500/10 text-emerald-400"
                                                                    onClick={() => void handleConvertir(p)}
                                                                >
                                                                    <ArrowRightLeft className="w-3.5 h-3.5" />
                                                                    Convertir
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-red-500/10 text-red-400"
                                                                    onClick={() => void handleAnular(p)}
                                                                >
                                                                    <Ban className="w-3.5 h-3.5" />
                                                                    Anular
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Paginación */}
                    {proformas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {proformas.from}–{proformas.to} de {proformas.total}</span>
                            <div className="flex gap-1">
                                {proformas.links.map((link, i) => {
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
