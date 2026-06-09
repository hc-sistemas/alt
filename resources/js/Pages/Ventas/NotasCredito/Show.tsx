import { Head, usePage, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowLeft } from 'lucide-react'
import type { PageProps } from '@/types'

interface NCDetalle {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
}

interface NCFull {
    id: number
    numero_completo: string
    fecha: string
    motivo: string
    total: number
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
    detalles: NCDetalle[]
}

interface Props extends PageProps {
    nota: NCFull
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
    const { nota } = usePage<Props>().props
    const cfg = SRI_CONFIG[nota.estado_sri] ?? SRI_CONFIG.pendiente

    return (
        <AppLayout>
            <Head title={`Nota de Crédito ${nota.numero_completo}`} />
            <PageHeader
                title={`Nota de Crédito ${nota.numero_completo}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Notas de Crédito', href: route('ventas.notas-credito.index') },
                    { label: nota.numero_completo },
                ]}
            />

            <div className="p-6 space-y-6 max-w-5xl">

                <div className="flex items-center gap-3">
                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    <Link href={route('ventas.notas-credito.index')}>
                        <button type="button" className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500" style={{ color: 'var(--text-muted)' }}>
                            <ArrowLeft className="w-3.5 h-3.5" />
                            Volver a Notas de Crédito
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
                        <InfoRow label="Número NC" value={<span className="font-mono">{nota.numero_completo}</span>} />
                        <InfoRow label="Fecha" value={formatFecha(nota.fecha)} />
                        <InfoRow label="Factura origen" value={<span className="font-mono">{nota.factura.numero_completo}</span>} />
                        <InfoRow label="Total NC" value={<span className="font-semibold" style={{ color: 'var(--primary)' }}>{formatMoneda(nota.total)}</span>} />
                    </div>
                </div>

                {/* Cliente */}
                {nota.cliente && (
                    <div
                        className="rounded-xl p-5 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>Cliente</p>
                        <div className="grid grid-cols-2 gap-4">
                            <InfoRow label="Razón Social" value={nota.cliente.razon_social} />
                            <InfoRow label="Identificación" value={nota.cliente.identificacion} />
                        </div>
                    </div>
                )}

                {/* Motivo */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: 'var(--text-muted)' }}>Motivo</p>
                    <p className="text-sm" style={{ color: 'var(--text-main)' }}>{nota.motivo}</p>
                </div>

                {/* Detalle */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Ítems Devueltos</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                    {['Descripción', 'Cantidad', 'Precio', 'Subtotal', 'IVA', 'Total'].map((h, i) => (
                                        <th key={i} className={`px-4 py-3 text-left font-semibold uppercase tracking-wide ${i >= 1 ? 'text-right' : ''}`} style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {nota.detalles.map(d => (
                                    <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>{d.descripcion}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.cantidad}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{formatMoneda(d.precio_unitario)}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-main)' }}>{formatMoneda(d.subtotal)}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{formatMoneda(d.valor_iva)}</td>
                                        <td className="px-4 py-3 text-right font-semibold" style={{ color: 'var(--text-main)' }}>{formatMoneda(d.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={5} className="px-4 py-3 text-right text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                        Total Nota de Crédito:
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <span className="text-base font-bold" style={{ color: 'var(--primary)' }}>{formatMoneda(nota.total)}</span>
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
