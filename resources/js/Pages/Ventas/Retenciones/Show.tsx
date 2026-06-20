import { Head, usePage, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowLeft } from 'lucide-react'
import type { PageProps } from '@/types'

interface RetencionDetalleFull {
    id: number
    tipo: 'IR' | 'IVA'
    codigo: string
    descripcion: string
    porcentaje: number
    base_imponible: number
    valor_retenido: number
}

interface RetencionFull {
    id: number
    numero_completo: string
    fecha: string
    total_retenido: number
    estado_sri: 'pendiente' | 'autorizada' | 'rechazada' | 'anulada'
    factura: {
        numero_completo: string
        fecha_emision: string
        total: number
    }
    cliente: {
        razon_social: string
        identificacion: string
    } | null
    detalles: RetencionDetalleFull[]
}

interface Props extends PageProps {
    retencion: RetencionFull
}

const SRI_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    autorizada: { label: 'Autorizada', variant: 'success'   as const },
    rechazada:  { label: 'Rechazada',  variant: 'danger'    as const },
    anulada:    { label: 'Anulada',    variant: 'warning'   as const },
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>{label}</p>
            <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>{value}</p>
        </div>
    )
}

export default function Show() {
    const { retencion } = usePage<Props>().props
    const cfg = SRI_CONFIG[retencion.estado_sri] ?? SRI_CONFIG.pendiente

    return (
        <AppLayout>
            <Head title={`Retención ${retencion.numero_completo}`} />
            <PageHeader
                title={`Retención ${retencion.numero_completo}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Retenciones', href: route('ventas.retenciones.index') },
                    { label: retencion.numero_completo },
                ]}
            />

            <div className="p-6 space-y-6 max-w-5xl">

                <div className="flex items-center gap-3">
                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    <Link href={route('ventas.retenciones.index')}>
                        <button type="button" className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500" style={{ color: 'var(--text-muted)' }}>
                            <ArrowLeft className="w-3.5 h-3.5" />
                            Volver a Retenciones
                        </button>
                    </Link>
                </div>

                {/* Datos del documento */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>Datos del Documento</p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <InfoRow label="Número" value={<span className="font-mono">{retencion.numero_completo}</span>} />
                        <InfoRow label="Fecha" value={formatFecha(retencion.fecha)} />
                        <InfoRow label="Factura origen" value={<span className="font-mono">{retencion.factura.numero_completo}</span>} />
                        <InfoRow label="Total retenido" value={<span className="font-semibold text-red-400">{formatMoneda(retencion.total_retenido)}</span>} />
                    </div>
                </div>

                {/* Cliente */}
                {retencion.cliente && (
                    <div
                        className="rounded-xl p-5 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>Cliente</p>
                        <div className="grid grid-cols-2 gap-4">
                            <InfoRow label="Razón Social" value={retencion.cliente.razon_social} />
                            <InfoRow label="Identificación" value={retencion.cliente.identificacion} />
                        </div>
                    </div>
                )}

                {/* Detalle retenciones */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Detalle de Retenciones</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                    {['Tipo', 'Código', 'Descripción', '% Retención', 'Base Imponible', 'Valor Retenido'].map((h, i) => (
                                        <th key={i} className={`px-4 py-3 text-left font-semibold uppercase tracking-wide ${i >= 3 ? 'text-right' : ''}`} style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {retencion.detalles.map(d => (
                                    <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-3">
                                            <span
                                                className="px-2 py-0.5 rounded text-xs font-semibold"
                                                style={{
                                                    background: d.tipo === 'IR' ? 'rgba(239,68,68,.15)' : 'rgba(59,130,246,.15)',
                                                    color: d.tipo === 'IR' ? 'rgb(252,165,165)' : 'rgb(147,197,253)',
                                                }}
                                            >
                                                {d.tipo}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-mono" style={{ color: 'var(--text-muted)' }}>{d.codigo}</td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>{d.descripcion}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.porcentaje}%</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-main)' }}>{formatMoneda(d.base_imponible)}</td>
                                        <td className="px-4 py-3 text-right font-semibold text-red-400">{formatMoneda(d.valor_retenido)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={5} className="px-4 py-3 text-right text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                        Total Retenido:
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <span className="text-base font-bold text-red-400">{formatMoneda(retencion.total_retenido)}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
