import { useMemo, useState } from 'react'
import { usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Search, AlertTriangle } from 'lucide-react'
import type { PageProps } from '@/types'

interface Evento {
    id: string
    seccion: string
    evento: string
    debe: string
    haber: string
    estado: string
    sin_cuenta: boolean
}

interface Props extends PageProps {
    eventos: Evento[]
}

const COLOR_ESTADO: Record<string, string> = {
    correcto:         '#10b981',
    incorrecto:       '#ef4444',
    incompleto:       '#f59e0b',
    'sin configurar': '#8b5cf6',
    pendiente:        '#3b82f6',
}

export default function EventosIndex({ eventos }: Props) {
    usePage<Props>()

    const [busqueda, setBusqueda] = useState('')
    const [seccion, setSeccion]   = useState('')
    const [estado, setEstado]     = useState('')

    const secciones = useMemo(() => Array.from(new Set(eventos.map(e => e.seccion))), [eventos])
    const estados   = useMemo(() => Array.from(new Set(eventos.map(e => e.estado))), [eventos])

    const filtrados = useMemo(() => {
        const q = busqueda.toLowerCase().trim()
        return eventos.filter(e =>
            (!seccion || e.seccion === seccion) &&
            (!estado || e.estado === estado) &&
            (!q || `${e.id} ${e.evento} ${e.debe} ${e.haber}`.toLowerCase().includes(q))
        )
    }, [eventos, busqueda, seccion, estado])

    const sinCuenta = eventos.filter(e => e.sin_cuenta).length

    return (
        <AppLayout>
            <PageHeader
                title="Eventos Contables"
                breadcrumbs={[{ label: 'Contabilidad' }, { label: 'Eventos Contables' }]}
            />

            <div className="rounded-xl p-4 mb-4 text-sm"
                 style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                Cuenta de debe y de haber de cada evento, según el documento 01 de Parámetros Contables.
                Es solo de consulta: las cuentas se configuran en Parámetros Contables.
                {sinCuenta > 0 && (
                    <span className="flex items-center gap-2 mt-2" style={{ color: '#f59e0b' }}>
                        <AlertTriangle size={15} />
                        {sinCuenta} eventos no tienen cuenta definida todavía (pendientes de decisión).
                    </span>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-3 mb-4">
                <div className="relative">
                    <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--text-muted)' }} />
                    <Input
                        value={busqueda}
                        onChange={e => setBusqueda(e.target.value)}
                        placeholder="Buscar evento o cuenta…"
                        className="pl-9 w-72"
                    />
                </div>
                <select value={seccion} onChange={e => setSeccion(e.target.value)}
                        className="rounded-lg px-3 py-2 text-sm"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-main)' }}>
                    <option value="">Todos los módulos</option>
                    {secciones.map(s => <option key={s} value={s}>{s}</option>)}
                </select>
                <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="rounded-lg px-3 py-2 text-sm"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-main)' }}>
                    <option value="">Todos los estados</option>
                    {estados.map(s => <option key={s} value={s}>{s}</option>)}
                </select>
                <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    {filtrados.length} de {eventos.length} eventos
                </span>
            </div>

            <div className="rounded-xl overflow-x-auto"
                 style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <table className="w-full text-sm">
                    <thead>
                        <tr style={{ borderBottom: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                            <th className="text-left px-3 py-2 font-medium">ID</th>
                            <th className="text-left px-3 py-2 font-medium">Módulo</th>
                            <th className="text-left px-3 py-2 font-medium">Evento</th>
                            <th className="text-left px-3 py-2 font-medium">Debe</th>
                            <th className="text-left px-3 py-2 font-medium">Haber</th>
                            <th className="text-left px-3 py-2 font-medium">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtrados.map(e => (
                            <tr key={e.id} style={{ borderBottom: '1px solid var(--border)', color: 'var(--text-main)' }}>
                                <td className="px-3 py-2 whitespace-nowrap font-mono text-xs">{e.id}</td>
                                <td className="px-3 py-2 whitespace-nowrap">{e.seccion}</td>
                                <td className="px-3 py-2">{e.evento}</td>
                                <td className="px-3 py-2" style={e.debe === 'No documentado' ? { color: '#f59e0b' } : undefined}>
                                    {e.debe}
                                </td>
                                <td className="px-3 py-2" style={e.haber === 'No documentado' ? { color: '#f59e0b' } : undefined}>
                                    {e.haber}
                                </td>
                                <td className="px-3 py-2 whitespace-nowrap">
                                    <span className="px-2 py-0.5 rounded-full text-xs font-semibold"
                                          style={{
                                              color: COLOR_ESTADO[e.estado] ?? 'var(--text-muted)',
                                              background: `${COLOR_ESTADO[e.estado] ?? '#888888'}22`,
                                          }}>
                                        {e.estado}
                                    </span>
                                </td>
                            </tr>
                        ))}
                        {filtrados.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-3 py-6 text-center" style={{ color: 'var(--text-muted)' }}>
                                    Sin resultados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    )
}
