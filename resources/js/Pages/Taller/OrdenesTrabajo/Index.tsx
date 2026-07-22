import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { formatFecha } from '@/lib/utils'
import { Search, Eye, Wrench } from 'lucide-react'
import type { PageProps, PaginatedData, TallerOrdenTrabajo } from '@/types'

interface Tecnico {
    id: number
    nombre: string
}

interface Props extends PageProps {
    ordenes: PaginatedData<TallerOrdenTrabajo>
    filtros: { search?: string; estado?: string; tecnico_id?: string }
    tecnicos: Tecnico[]
}

const ESTADO_CONFIG: Record<string, { label: string; variant: 'info' | 'warning' | 'default' | 'success' | 'secondary' }> = {
    pendiente:  { label: 'Pendiente',  variant: 'warning' },
    en_proceso: { label: 'En proceso', variant: 'info' },
    listo:      { label: 'Listo',      variant: 'success' },
    entregado:  { label: 'Entregado',  variant: 'secondary' },
    facturado:  { label: 'Facturado',  variant: 'success' },
    garantia:   { label: 'Garantía',   variant: 'secondary' },
}

export default function OrdenesTrabajoIndex() {
    const { ordenes, filtros, tecnicos } = usePage<Props>().props
    const [search, setSearch] = useState(filtros.search ?? '')
    const [estado, setEstado] = useState(filtros.estado ?? '')
    const [tecnicoId, setTecnicoId] = useState(filtros.tecnico_id ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
    const isFirstRender = useRef(true)

    useEffect(() => {
        if (isFirstRender.current) { isFirstRender.current = false; return }
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('taller.ordenes.index'), {
                search: search || undefined,
                estado: estado || undefined,
                tecnico_id: tecnicoId || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, estado, tecnicoId])

    return (
        <AppLayout title="Órdenes de Trabajo">
            <Head title="Órdenes de Trabajo" />
            <PageHeader
                title="Órdenes de Trabajo"
                description="Seguimiento de las reparaciones en curso"
                breadcrumbs={[{ label: 'Taller' }, { label: 'Órdenes de Trabajo' }]}
            />

            <div className="p-6">
                {/* Barra de filtros */}
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Cliente o identificación..."
                            className="pl-9 w-60"
                        />
                    </div>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_proceso">En proceso</option>
                        <option value="listo">Listo</option>
                        <option value="entregado">Entregado</option>
                        <option value="facturado">Facturado</option>
                        <option value="garantia">Garantía</option>
                    </select>

                    <select value={tecnicoId} onChange={e => setTecnicoId(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los técnicos</option>
                        {tecnicos.map(t => (
                            <option key={t.id} value={t.id}>{t.nombre}</option>
                        ))}
                    </select>
                </div>

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['N° OT', 'Fecha inicio', 'Cliente', 'Equipo', 'Técnico', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {ordenes.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <Wrench className="w-10 h-10 opacity-20" />
                                            <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                                No hay órdenes de trabajo registradas
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : ordenes.data.map(orden => {
                                const cfg = ESTADO_CONFIG[orden.estado] ?? { label: orden.estado, variant: 'secondary' as const }
                                const cliente = orden.ingreso?.cliente
                                const equipo = orden.ingreso?.equipo
                                return (
                                    <tr key={orden.id}
                                        className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                            {orden.numero ?? `#${orden.id}`}
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {formatFecha(orden.fecha_inicio)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {cliente ? (
                                                <>
                                                    <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{cliente.razon_social}</p>
                                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{cliente.identificacion}</p>
                                                </>
                                            ) : (
                                                <span style={{ color: 'var(--text-muted)' }}>—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>
                                            {[equipo?.marca, equipo?.modelo].filter(Boolean).join(' ') || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>
                                            {orden.tecnico?.nombre ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={route('taller.ordenes.show', orden.id)}>
                                                    <Button variant="ghost" size="icon" title="Ver">
                                                        <Eye className="w-4 h-4" />
                                                    </Button>
                                                </Link>
                                            </div>
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {ordenes.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {ordenes.from}–{ordenes.to} de {ordenes.total}
                        </p>
                        <div className="flex gap-1">
                            {ordenes.links.map((link, i) => (
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
