import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowLeft, ArrowRightLeft, Ban } from 'lucide-react'
import type { PageProps } from '@/types'

interface ProformaDetalle {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
}

interface ProformaCliente {
    razon_social: string
    identificacion: string
    email?: string
    telefono?: string
    direccion?: string
}

interface ProformaFull {
    id: number
    numero_completo: string
    fecha: string
    fecha_vencimiento: string
    estado: 'pendiente' | 'facturada' | 'vencida' | 'anulada'
    subtotal: number
    descuento_total: number
    subtotal_iva: number
    iva_total: number
    total: number
    observaciones: string | null
    vendedor_nombre: string | null
    cliente: ProformaCliente | null
    detalles: ProformaDetalle[]
}

interface Props extends PageProps {
    proforma: ProformaFull
}

const ESTADO_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    facturada:  { label: 'Facturada',  variant: 'success'   as const },
    vencida:    { label: 'Vencida',    variant: 'danger'    as const },
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
    const { proforma } = usePage<Props>().props
    const esPendiente = proforma.estado === 'pendiente'
    const cfg = ESTADO_CONFIG[proforma.estado] ?? ESTADO_CONFIG.pendiente

    const handleConvertir = async () => {
        const result = await Swal.fire({
            title: 'Convertir a Factura',
            text: `¿Desea convertir la proforma ${proforma.numero_completo} en una factura?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, convertir',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!result.isConfirmed) return
        router.post(route('ventas.proformas.convertir', proforma.id))
    }

    const handleAnular = async () => {
        const result = await Swal.fire({
            title: 'Anular proforma',
            text: `¿Desea anular la proforma ${proforma.numero_completo}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })
        if (!result.isConfirmed) return
        router.delete(route('ventas.proformas.destroy', proforma.id))
    }

    return (
        <AppLayout>
            <Head title={`Proforma ${proforma.numero_completo}`} />
            <PageHeader
                title={`Proforma ${proforma.numero_completo}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Proformas', href: route('ventas.proformas.index') },
                    { label: proforma.numero_completo },
                ]}
                actions={
                    <div className="flex gap-2">
                        {esPendiente && (
                            <>
                                <Button size="sm" onClick={() => void handleConvertir()}>
                                    <ArrowRightLeft className="w-4 h-4" />
                                    Convertir a Factura
                                </Button>
                                <Button size="sm" variant="destructive" onClick={() => void handleAnular()}>
                                    <Ban className="w-4 h-4" />
                                    Anular
                                </Button>
                            </>
                        )}
                    </div>
                }
            />

            <div className="p-6 space-y-6 max-w-5xl">

                {/* Estado y acciones rápidas */}
                <div className="flex items-center gap-3">
                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    <Link href={route('ventas.proformas.index')}>
                        <button type="button" className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500" style={{ color: 'var(--text-muted)' }}>
                            <ArrowLeft className="w-3.5 h-3.5" />
                            Volver a Proformas
                        </button>
                    </Link>
                </div>

                {/* Datos del documento */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Datos del Documento
                    </p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <InfoRow label="Número" value={<span className="font-mono">{proforma.numero_completo}</span>} />
                        <InfoRow label="Fecha emisión" value={formatFecha(proforma.fecha)} />
                        <InfoRow label="Fecha vencimiento" value={formatFecha(proforma.fecha_vencimiento)} />
                        <InfoRow label="Vendedor" value={proforma.vendedor_nombre ?? '—'} />
                    </div>
                </div>

                {/* Cliente */}
                {proforma.cliente && (
                    <div
                        className="rounded-xl p-5 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                            Cliente
                        </p>
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <InfoRow label="Razón Social" value={proforma.cliente.razon_social} />
                            <InfoRow label="Identificación" value={proforma.cliente.identificacion} />
                            {proforma.cliente.email && <InfoRow label="Email" value={proforma.cliente.email} />}
                            {proforma.cliente.telefono && <InfoRow label="Teléfono" value={proforma.cliente.telefono} />}
                            {proforma.cliente.direccion && <InfoRow label="Dirección" value={proforma.cliente.direccion} />}
                        </div>
                    </div>
                )}

                {/* Detalle */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Detalle de Productos
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                    {['Descripción', 'Cant.', 'Precio', 'Desc%', 'Subtotal', 'IVA', 'Total'].map((h, i) => (
                                        <th key={i} className={`px-4 py-3 text-left font-semibold uppercase tracking-wide ${i >= 1 ? 'text-right' : ''}`} style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {proforma.detalles.map(d => (
                                    <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>{d.descripcion}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.cantidad}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{formatMoneda(d.precio_unitario)}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.descuento_pct > 0 ? `${d.descuento_pct}%` : '—'}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-main)' }}>{formatMoneda(d.subtotal)}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{formatMoneda(d.valor_iva)}</td>
                                        <td className="px-4 py-3 text-right font-semibold" style={{ color: 'var(--text-main)' }}>{formatMoneda(d.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={4} className="px-4 py-3" />
                                    <td className="px-4 py-3 text-right text-xs" style={{ color: 'var(--text-muted)' }}>
                                        Subtotal: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(proforma.subtotal_iva)}</strong><br />
                                        IVA: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(proforma.iva_total)}</strong>
                                    </td>
                                    <td />
                                    <td className="px-4 py-3 text-right">
                                        <span className="text-lg font-bold" style={{ color: 'var(--primary)' }}>{formatMoneda(proforma.total)}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {proforma.observaciones && (
                    <div
                        className="rounded-xl p-5 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <p className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: 'var(--text-muted)' }}>Observaciones</p>
                        <p className="text-sm" style={{ color: 'var(--text-main)' }}>{proforma.observaciones}</p>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
