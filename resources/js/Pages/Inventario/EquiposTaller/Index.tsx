import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { formatFecha } from '@/lib/utils'
import { Search, Wrench, Phone } from 'lucide-react'
import type { PageProps } from '@/types'

interface EquipoListo {
    id: number
    orden_numero: string
    cliente: string | null
    identificacion: string | null
    telefono: string | null
    producto: string
    numero_serie: string | null
    listo_desde: string | null
    dias_bodega: number
}

interface Props extends PageProps {
    equipos: EquipoListo[]
    filtros: { search?: string }
}

// Más de una semana en bodega ya es urgente llamar al cliente.
const colorDias = (d: number) => (d >= 15 ? '#DC2626' : d >= 7 ? '#D97706' : 'var(--text-main)')

export default function EquiposTallerIndex() {
    const { equipos, filtros } = usePage<Props>().props
    const [search, setSearch] = useState(filtros.search ?? '')

    function buscar() {
        router.get(route('inventario.equipos-taller.index'), { search: search || undefined }, { preserveState: true, replace: true })
    }

    return (
        <AppLayout title="Equipos Listos (Taller)">
            <Head title="Equipos Listos (Taller)" />
            <PageHeader
                title="Equipos Listos para Entrega (Taller)"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Equipos Listos (Taller)' }]}
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4">
                    <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                        <strong style={{ color: 'var(--text-main)' }}>{equipos.length}</strong> equipo(s) reparado(s) esperando retiro
                    </p>
                    <div className="relative ml-auto">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input value={search} onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && buscar()}
                            placeholder="Cliente, teléfono, equipo u OT..."
                            className="pl-9 w-72" />
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Cliente', 'Teléfono', 'N° OT', 'Producto', 'Listo desde', 'Días en bodega'].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {equipos.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <Wrench className="w-10 h-10 opacity-20" />
                                            <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                                No hay equipos reparados esperando retiro
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : equipos.map(e => (
                                <tr key={e.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3">
                                        <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{e.cliente ?? '—'}</p>
                                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{e.identificacion}</p>
                                    </td>
                                    <td className="px-4 py-3 text-xs whitespace-nowrap" style={{ color: 'var(--text-main)' }}>
                                        {e.telefono ? (
                                            <span className="inline-flex items-center gap-1.5"><Phone className="w-3 h-3" />{e.telefono}</span>
                                        ) : <span style={{ color: 'var(--text-muted)' }}>Sin teléfono</span>}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>{e.orden_numero}</td>
                                    <td className="px-4 py-3">
                                        <p className="text-xs" style={{ color: 'var(--text-main)' }}>{e.producto}</p>
                                        {e.numero_serie && <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Serie: {e.numero_serie}</p>}
                                    </td>
                                    <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {e.listo_desde ? formatFecha(e.listo_desde) : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm font-semibold" style={{ color: colorDias(e.dias_bodega) }}>
                                        {e.dias_bodega} {e.dias_bodega === 1 ? 'día' : 'días'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    )
}
