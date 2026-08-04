import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, FileText, Wrench } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, PaginatedData, TallerIngreso } from '@/types'

interface Props extends PageProps {
    ingresos: PaginatedData<TallerIngreso>
    filtros: { search?: string; estado?: string }
}

const ESTADO_CONFIG: Record<number, { label: string; variant: 'info' | 'warning' | 'default' | 'success' | 'secondary' }> = {
    0: { label: 'Ingresado',      variant: 'secondary' },
    1: { label: 'En diagnóstico', variant: 'warning' },
    2: { label: 'En proceso',     variant: 'info' },
    3: { label: 'Listo',          variant: 'success' },
    4: { label: 'Entregado',      variant: 'secondary' },
}

export default function IngresosIndex() {
    const { ingresos, filtros } = usePage<Props>().props
    const { puede } = usePermiso('taller')
    const [search, setSearch] = useState(filtros.search ?? '')
    const [estado, setEstado] = useState(filtros.estado ?? '')
    const [pdfIngresoId, setPdfIngresoId] = useState<number | null>(null)

    function buscar() {
        router.get(route('taller.ingresos.index'), {
            search: search || undefined,
            estado: estado || undefined,
        }, { preserveState: true, replace: true })
    }

    return (
        <AppLayout title="Ingresos">
            <Head title="Ingresos" />
            <PageHeader
                title="Ingresos al Taller"
                breadcrumbs={[{ label: 'Taller' }, { label: 'Ingresos' }]}
                actions={
                    puede('crear') ? (
                        <Link href={route('taller.ingresos.create')}>
                            <Button>
                                <Plus className="w-4 h-4" />
                                Nuevo Ingreso
                            </Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="p-6">
                {/* Barra de filtros */}
                <div className="flex items-center gap-3 mb-4 flex-nowrap overflow-x-auto">
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los estados</option>
                        <option value="0">Ingresado</option>
                        <option value="1">En diagnóstico</option>
                        <option value="2">En proceso</option>
                        <option value="3">Listo</option>
                        <option value="4">Entregado</option>
                    </select>

                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && buscar()}
                                placeholder="Cliente o identificación..."
                                className="pl-9 w-60 rounded-r-none border-r-0"
                            />
                        </div>
                        <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={buscar}
                            title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['N° OT', 'Fecha', 'Cliente', 'Equipo', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {ingresos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <Wrench className="w-10 h-10 opacity-20" />
                                            <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                                No hay ingresos registrados
                                            </p>
                                            <p>Crea el primero con "Nuevo Ingreso"</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : ingresos.data.map(ingreso => {
                                const cfg = ESTADO_CONFIG[ingreso.estado] ?? ESTADO_CONFIG[0]
                                const ot = ingreso.ordenes_trabajo?.[0]
                                return (
                                    <tr key={ingreso.id}
                                        className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                            {ot?.numero ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {formatFecha(ingreso.fecha)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {ingreso.cliente ? (
                                                <>
                                                    <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{ingreso.cliente.razon_social}</p>
                                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{ingreso.cliente.identificacion}</p>
                                                </>
                                            ) : (
                                                <span style={{ color: 'var(--text-muted)' }}>—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>
                                            {[ingreso.equipo?.marca, ingreso.equipo?.modelo].filter(Boolean).join(' ') || '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={route('taller.ingresos.show', ingreso.id)}>
                                                    <Button variant="ghost" size="icon" title="Ver">
                                                        <Eye className="w-4 h-4" />
                                                    </Button>
                                                </Link>
                                                <Button variant="ghost" size="icon" title="Ver PDF" onClick={() => setPdfIngresoId(ingreso.id)}>
                                                    <FileText className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {ingresos.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {ingresos.from}–{ingresos.to} de {ingresos.total}
                        </p>
                        <div className="flex gap-1">
                            {ingresos.links.map((link, i) => (
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

            <PdfPreviewModal
                abierto={pdfIngresoId !== null}
                onCerrar={() => setPdfIngresoId(null)}
                url={pdfIngresoId !== null ? route('taller.ingresos.pdf', pdfIngresoId) : ''}
                titulo={`Orden de Trabajo — Ingreso #${pdfIngresoId}`}
                nombreDescarga={`orden-trabajo-ingreso-${pdfIngresoId}.pdf`}
            />
        </AppLayout>
    )
}
