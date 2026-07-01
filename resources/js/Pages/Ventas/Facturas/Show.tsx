import { Head, usePage, Link, router } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { FileMinus2, Receipt, Truck } from 'lucide-react'
import type { PageProps } from '@/types'

interface FacturaDetalle {
    id: number
    producto_id: number | null
    codigo_producto: string | null
    descripcion: string
    unidad: string | null
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    descuento_valor: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    numero_serie: string | null
}

interface FacturaPago {
    id: number
    forma_pago: string
    valor: number
    dias_credito: number
    fecha_vencimiento: string | null
    banco: string | null
    estado: string
}

interface Factura {
    id: number
    numero_completo: string | null
    fecha_emision: string
    estado_sri: string
    estado: string
    tipo_identificacion: string | null
    identificacion: string | null
    razon_social: string | null
    email_cliente: string | null
    subtotal_0: number
    subtotal_15: number
    descuento_total: number
    total_iva: number
    total: number
    observaciones: string | null
    cliente: { id: number; razon_social: string; identificacion: string; agente_retencion: boolean }
    usuario: { id: number; nombre: string } | null
    empresa: { id: number; razon_social: string }
    detalles: FacturaDetalle[]
    pagos: FacturaPago[]
}

interface Props extends PageProps {
    factura: Factura
}

const ESTADO_SRI_CONFIG: Record<string, { label: string; variant: 'secondary' | 'success' | 'danger' | 'warning' }> = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' },
    autorizada: { label: 'Autorizada', variant: 'success' },
    rechazada:  { label: 'Rechazada',  variant: 'danger' },
    anulada:    { label: 'Anulada',    variant: 'warning' },
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
    const { factura } = usePage<Props>().props
    const numero = factura.numero_completo ?? 'Sin número'
    const sriCfg = ESTADO_SRI_CONFIG[factura.estado_sri] ?? ESTADO_SRI_CONFIG.pendiente

    return (
        <AppLayout title={`Factura ${numero}`}>
            <Head title={`Factura ${numero}`} />
            <PageHeader
                title={`Factura ${numero}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Facturas', href: route('ventas.facturas.index') },
                    { label: numero },
                ]}
                actions={
                    factura.estado === 'activa'
                        ? <Badge variant="warning">Modo prueba — sin SRI</Badge>
                        : undefined
                }
            />

            <div className="p-6 space-y-6 max-w-5xl">

                {/* Estado */}
                <div className="flex items-center gap-3">
                    <Badge variant={sriCfg.variant}>{sriCfg.label}</Badge>
                </div>

                {/* Datos generales */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Datos Generales
                    </p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <InfoRow
                            label="Cliente"
                            value={`${factura.cliente.razon_social} (${factura.cliente.identificacion})`}
                        />
                        <InfoRow label="Fecha de Emisión" value={formatFecha(factura.fecha_emision)} />
                        <InfoRow label="Emitido por" value={factura.usuario?.nombre ?? 'Sistema'} />
                        <InfoRow label="Empresa" value={factura.empresa.razon_social} />
                    </div>
                </div>

                {/* Tabla detalles */}
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
                                    {['#', 'Código', 'Descripción', 'Unidad', 'Cantidad', 'Precio Unit.', 'Desc.%', 'IVA%', 'Total'].map((h, i) => (
                                        <th key={i} className={`px-4 py-3 font-semibold uppercase tracking-wide ${i >= 4 ? 'text-right' : 'text-left'}`} style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {factura.detalles.map((d, idx) => (
                                    <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{idx + 1}</td>
                                        <td className="px-4 py-3 font-mono" style={{ color: 'var(--text-muted)' }}>{d.codigo_producto ?? '—'}</td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-main)' }}>{d.descripcion}</td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{d.unidad ?? '—'}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.cantidad}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{formatMoneda(d.precio_unitario)}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.descuento_pct > 0 ? `${d.descuento_pct}%` : '—'}</td>
                                        <td className="px-4 py-3 text-right" style={{ color: 'var(--text-muted)' }}>{d.porcentaje_iva}%</td>
                                        <td className="px-4 py-3 text-right font-semibold" style={{ color: 'var(--text-main)' }}>{formatMoneda(d.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Tabla formas de pago */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Formas de Pago
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                    {['Forma de Pago', 'Valor', 'Días Crédito', 'Fecha Vencimiento', 'Estado'].map((h, i) => (
                                        <th key={i} className={`px-4 py-3 font-semibold uppercase tracking-wide ${i === 1 ? 'text-right' : 'text-left'}`} style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {factura.pagos.map(p => (
                                    <tr key={p.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-3 capitalize" style={{ color: 'var(--text-main)' }}>{p.forma_pago}</td>
                                        <td className="px-4 py-3 text-right font-semibold" style={{ color: 'var(--text-main)' }}>{formatMoneda(p.valor)}</td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{p.dias_credito > 0 ? p.dias_credito : '—'}</td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{p.fecha_vencimiento ? formatFecha(p.fecha_vencimiento) : '—'}</td>
                                        <td className="px-4 py-3 capitalize" style={{ color: 'var(--text-muted)' }}>{p.estado ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Totales */}
                <div className="flex justify-end">
                    <div
                        className="rounded-xl p-5 border w-72"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between" style={{ color: 'var(--text-muted)' }}>
                                <span>Subtotal 0%:</span>
                                <span style={{ color: 'var(--text-main)' }}>{formatMoneda(factura.subtotal_0)}</span>
                            </div>
                            <div className="flex justify-between" style={{ color: 'var(--text-muted)' }}>
                                <span>Subtotal 15%:</span>
                                <span style={{ color: 'var(--text-main)' }}>{formatMoneda(factura.subtotal_15)}</span>
                            </div>
                            <div className="flex justify-between" style={{ color: 'var(--text-muted)' }}>
                                <span>Descuento:</span>
                                <span style={{ color: 'var(--text-main)' }}>{formatMoneda(factura.descuento_total)}</span>
                            </div>
                            <div className="flex justify-between" style={{ color: 'var(--text-muted)' }}>
                                <span>IVA (15%):</span>
                                <span style={{ color: 'var(--text-main)' }}>{formatMoneda(factura.total_iva)}</span>
                            </div>
                            <div className="border-t pt-2 mt-2 flex justify-between" style={{ borderColor: 'var(--border)' }}>
                                <span className="font-bold text-base" style={{ color: 'var(--text-main)' }}>TOTAL:</span>
                                <span className="font-bold text-base" style={{ color: 'var(--primary)' }}>{formatMoneda(factura.total)}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Observaciones */}
                {factura.observaciones && (
                    <div
                        className="rounded-xl p-5 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <p className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: 'var(--text-muted)' }}>Observaciones</p>
                        <p className="text-sm" style={{ color: 'var(--text-main)' }}>{factura.observaciones}</p>
                    </div>
                )}

                {/* Acciones */}
                <div className="flex justify-between pt-4 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button variant="outline" onClick={() => router.visit(route('ventas.facturas.index'))}>
                        Volver
                    </Button>
                    <div className="flex gap-2">
                        {factura.estado === 'activa' && (
                            <>
                                <Link href={route('ventas.notas-credito.create', { factura_id: factura.id })}>
                                    <Button variant="outline">
                                        <FileMinus2 className="w-4 h-4" />
                                        Generar Nota de Crédito
                                    </Button>
                                </Link>
                                <Link href={route('ventas.guias-remision.create', { factura_id: factura.id })}>
                                    <Button variant="outline">
                                        <Truck className="w-4 h-4" />
                                        Generar Guía de Remisión
                                    </Button>
                                </Link>
                            </>
                        )}
                        {factura.cliente.agente_retencion === true && (
                            <Link href={route('ventas.retenciones.create', { factura_id: factura.id })}>
                                <Button variant="outline">
                                    <Receipt className="w-4 h-4" />
                                    Generar Retención
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
