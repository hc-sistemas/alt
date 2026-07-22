import { Head, usePage, router } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatFecha } from '@/lib/utils'
import type { PageProps } from '@/types'

interface GuiaItemFull {
    id: number
    descripcion: string
    cantidad: number
}

interface GuiaFull {
    id: number
    numero_completo: string
    fecha_emision: string
    estado_sri: 'pendiente' | 'autorizada' | 'rechazada' | 'anulada'
    fecha_inicio_transporte: string
    fecha_fin_transporte: string | null
    origen: string | null
    destino: string
    ruta: string | null
    motivo: string
    transportista: { razon_social: string; identificacion: string | null; placa: string | null } | null
    factura: { numero_completo: string } | null
    detalles: GuiaItemFull[]
}

interface Props extends PageProps {
    guia: GuiaFull
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
            <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>{value ?? '—'}</p>
        </div>
    )
}

export default function Show() {
    const { guia } = usePage<Props>().props
    const cfg = SRI_CONFIG[guia.estado_sri] ?? SRI_CONFIG.pendiente

    return (
        <AppLayout>
            <Head title={`Guía de Remisión ${guia.numero_completo}`} />
            <PageHeader
                title={`Guía de Remisión ${guia.numero_completo}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Guías de Remisión', href: route('ventas.guias-remision.index') },
                    { label: guia.numero_completo },
                ]}
            />

            <div className="p-6 space-y-6 max-w-5xl">

                <div className="flex items-center gap-3">
                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                </div>

                {/* Datos del transporte */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>Datos del Transporte</p>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <InfoRow label="Número" value={<span className="font-mono">{guia.numero_completo}</span>} />
                        <InfoRow label="Fecha emisión" value={formatFecha(guia.fecha_emision)} />
                        <InfoRow label="Factura origen" value={guia.factura?.numero_completo ? <span className="font-mono">{guia.factura.numero_completo}</span> : '—'} />
                        <InfoRow label="Transportista" value={guia.transportista?.razon_social} />
                        <InfoRow label="Identificación" value={guia.transportista?.identificacion} />
                        <InfoRow label="Placa" value={guia.transportista?.placa ?? '—'} />
                        <InfoRow label="Fecha inicio transporte" value={formatFecha(guia.fecha_inicio_transporte)} />
                        <InfoRow label="Fecha fin transporte" value={guia.fecha_fin_transporte ? formatFecha(guia.fecha_fin_transporte) : '—'} />
                        <InfoRow label="Motivo" value={guia.motivo} />
                        <InfoRow label="Origen" value={guia.origen} />
                        <InfoRow label="Destino" value={guia.destino} />
                        <InfoRow label="Ruta" value={guia.ruta} />
                    </div>
                </div>

                {/* Ítems */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Ítems Transportados</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                    <th className="px-4 py-3 text-left font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>Descripción</th>
                                    <th className="px-4 py-3 text-right font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                {guia.detalles.map(item => (
                                    <tr key={item.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>{item.descripcion}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{item.cantidad}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex gap-3 pt-4 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button variant="outline" onClick={() => router.visit(route('ventas.guias-remision.index'))}>
                        Volver
                    </Button>
                </div>
            </div>
        </AppLayout>
    )
}
