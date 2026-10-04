import { useState } from 'react'
import { Head, usePage, Link, router } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { FileMinus2, Mail, Receipt, Truck } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import PdfIcon from '@/Components/shared/PdfIcon'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { ACCION_CLS, accionesFactura, enviarFacturaPorCorreo, enviarFacturaSri } from '@/lib/facturaAcciones'
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
    observacion_sri: string | null
    email_enviado?: boolean
    estado: string
    tipo_identificacion: string | null
    identificacion: string | null
    razon_social: string | null
    email_cliente: string | null
    telefono_cliente: string | null
    direccion_cliente: string | null
    subtotal_0: number
    subtotal_15: number
    descuento_total: number
    total_iva: number
    total: number
    observaciones: string | null
    cliente: {
        id: number
        razon_social: string
        identificacion: string
        agente_retencion: boolean
        email?: string | null
        ciudad?: string | null
        pais?: string | null
    }
    usuario: { id: number; nombre: string } | null
    empresa: { id: number; razon_social: string }
    detalles: FacturaDetalle[]
    pagos: FacturaPago[]
}

interface Props extends PageProps {
    factura: Factura
}

const ESTADO_SRI_CONFIG: Record<string, { label: string; variant: 'secondary' | 'info' | 'success' | 'danger' | 'warning' }> = {
    pendiente:  { label: 'No enviada', variant: 'secondary' },
    recibida:   { label: 'Recibida',   variant: 'info' },
    autorizada: { label: 'Autorizada', variant: 'success' },
    rechazada:  { label: 'Rechazada',  variant: 'danger' },
    anulada:    { label: 'Anulada',    variant: 'warning' },
}

const TIPO_ID_LABEL: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }
const TITULO = 'text-xs font-bold uppercase tracking-wider text-(--primary-hover) dark:text-(--primary)'
const CARD = { background: 'var(--bg-card)', borderColor: 'var(--border)' }
const MUTED = { color: 'var(--text-muted)' }
const MAIN = { color: 'var(--text-main)' }

// Campo de solo lectura con el mismo aspecto que los del formulario de factura
function CampoLectura({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <tr>
            <td className="py-0 px-2 text-xs font-semibold w-28 select-none whitespace-nowrap" style={MUTED}>
                {label}:
            </td>
            <td className="py-px px-1">
                <div
                    className="h-6 w-full rounded-md border px-2 text-xs flex items-center overflow-hidden whitespace-nowrap"
                    style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                >
                    {value || ''}
                </div>
            </td>
        </tr>
    )
}

export default function Show() {
    const { factura } = usePage<Props>().props
    const { puede } = usePermiso('ventas')
    const acc = accionesFactura(factura)
    const [verRide, setVerRide] = useState(false)
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
            />

            <div className="p-4 space-y-4 max-w-7xl">

                {/* Cliente (izquierda) + encabezado y formas de pago (derecha) */}
                <div className="flex flex-col lg:flex-row-reverse lg:items-start gap-4">

                    <div className="flex flex-col flex-1 min-w-0 gap-4">
                        <div className="flex flex-wrap items-center gap-6 px-4 py-2.5 rounded-xl border" style={CARD}>
                            <span className="text-sm" style={MUTED}>
                                Factura N°:{' '}
                                <span className="font-mono font-semibold" style={MAIN}>{numero}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Fecha: <span className="font-medium" style={MAIN}>{formatFecha(factura.fecha_emision)}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Emitido por: <span className="font-medium" style={MAIN}>{factura.usuario?.nombre ?? 'Sistema'}</span>
                            </span>
                            <Badge variant={sriCfg.variant}>SRI: {sriCfg.label}</Badge>
                            {factura.email_enviado && <Badge variant="info">Correo enviado</Badge>}
                            {factura.estado === 'anulada' && <Badge variant="danger">Anulada</Badge>}
                        </div>

                        {factura.observacion_sri && factura.estado_sri !== 'autorizada' && (
                            <div className="rounded-xl px-4 py-2.5 border text-xs" style={{ ...CARD, borderColor: '#F59E0B', color: 'var(--text-main)' }}>
                                <span className="font-semibold">Último intento con el SRI: </span>{factura.observacion_sri}
                            </div>
                        )}

                        <div className="rounded-xl p-3 border" style={CARD}>
                            <p className={`${TITULO} mb-2`}>Formas de Pago</p>
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                        {['Forma', 'Valor', 'Días crédito', 'Vencimiento', 'Estado'].map((h, i) => (
                                            <th key={h} className={`py-1 px-1.5 font-medium ${i === 1 ? 'text-right' : 'text-left'}`} style={MUTED}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {factura.pagos.map(pg => (
                                        <tr key={pg.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="py-1 px-1.5 capitalize" style={MAIN}>{pg.forma_pago}</td>
                                            <td className="py-1 px-1.5 text-right font-semibold" style={MAIN}>{formatMoneda(pg.valor)}</td>
                                            <td className="py-1 px-1.5" style={MUTED}>{pg.dias_credito > 0 ? pg.dias_credito : '—'}</td>
                                            <td className="py-1 px-1.5" style={MUTED}>{pg.fecha_vencimiento ? formatFecha(pg.fecha_vencimiento) : '—'}</td>
                                            <td className="py-1 px-1.5 capitalize" style={MUTED}>{pg.estado ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-xl p-4 border w-full lg:w-xl shrink-0" style={CARD}>
                        <p className={`${TITULO} mb-3`}>Cliente</p>
                        <div style={{ maxWidth: 480 }}>
                            <table className="w-full">
                                <tbody>
                                    <CampoLectura
                                        label={TIPO_ID_LABEL[factura.tipo_identificacion ?? '04'] ?? 'RUC/CC'}
                                        value={factura.identificacion ?? factura.cliente.identificacion}
                                    />
                                    <CampoLectura label="NOMBRE" value={factura.razon_social ?? factura.cliente.razon_social} />
                                    <CampoLectura label="DIRECCIÓN" value={factura.direccion_cliente} />
                                    <CampoLectura label="TELÉFONO" value={factura.telefono_cliente} />
                                    <CampoLectura label="EMAIL" value={factura.email_cliente} />
                                    <CampoLectura label="CIUDAD" value={factura.cliente.ciudad} />
                                    <CampoLectura label="PAÍS" value={factura.cliente.pais ?? 'ECUADOR'} />
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Detalle de productos (V.Tot sin IVA, igual que el formulario) */}
                <div className="rounded-xl p-3 border" style={CARD}>
                    <p className={`${TITULO} mb-2`}>Detalle de Productos</p>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    {[
                                        { l: 'N°', c: 'w-8 text-center' },
                                        { l: 'Producto', c: 'min-w-55 text-left' },
                                        { l: 'Cant', c: 'w-16 text-right' },
                                        { l: 'Precio', c: 'w-24 text-right' },
                                        { l: 'Desc%', c: 'w-20 text-right' },
                                        { l: 'Desc$', c: 'w-20 text-right' },
                                        { l: 'V.Tot', c: 'w-24 text-right' },
                                    ].map(col => (
                                        <th key={col.l} className={`py-1.5 px-1.5 font-medium ${col.c}`} style={MUTED}>{col.l}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {factura.detalles.map((d, idx) => (
                                    <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="py-1 px-1.5 text-center" style={MUTED}>{idx + 1}</td>
                                        <td className="py-1 px-1.5" style={MAIN}>
                                            <span className="font-mono font-semibold" style={{ color: 'var(--primary)' }}>{d.codigo_producto ?? '—'}</span>
                                            {' — '}{d.descripcion}
                                        </td>
                                        <td className="py-1 px-1.5 text-right" style={MAIN}>{Number(d.cantidad)}</td>
                                        <td className="py-1 px-1.5 text-right" style={MAIN}>{formatMoneda(d.precio_unitario)}</td>
                                        <td className="py-1 px-1.5 text-right" style={MUTED}>{Number(d.descuento_pct)}</td>
                                        <td className="py-1 px-1.5 text-right" style={MUTED}>{formatMoneda(d.descuento_valor)}</td>
                                        <td className="py-1 px-1.5 text-right font-semibold" style={MAIN}>{formatMoneda(d.subtotal)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Observaciones + Totales (60 / 40) */}
                <div className="grid grid-cols-5 gap-4">
                    <div className="col-span-3">
                        <p className="text-xs font-semibold uppercase tracking-wider mb-1" style={MUTED}>Observaciones</p>
                        <div
                            className="w-full min-h-18 rounded-md border px-3 py-2 text-sm whitespace-pre-wrap"
                            style={{ borderColor: 'var(--border)', ...MAIN }}
                        >
                            {factura.observaciones || <span style={MUTED}>—</span>}
                        </div>
                    </div>
                    <div className="col-span-2 flex flex-col justify-end">
                        <table className="w-full">
                            <tbody>
                                {[
                                    { label: 'SUBTOTAL 15%:', value: factura.subtotal_15 },
                                    { label: 'SUBTOTAL SIN IMP.:', value: factura.subtotal_0 },
                                    { label: 'TOTAL DESCUENTO:', value: factura.descuento_total },
                                    { label: 'TOTAL IVA:', value: factura.total_iva },
                                ].map(row => (
                                    <tr key={row.label}>
                                        <td className="py-0.5 pr-3 text-right text-xs font-medium" style={MUTED}>{row.label}</td>
                                        <td className="py-0.5 text-right text-xs font-semibold w-28" style={MAIN}>{formatMoneda(row.value)}</td>
                                    </tr>
                                ))}
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td className="pt-2 pr-3 text-right text-sm font-bold" style={MUTED}>TOTAL VALOR:</td>
                                    <td className="pt-2 text-right text-base font-bold w-28" style={{ color: 'var(--primary)' }}>{formatMoneda(factura.total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex justify-between pt-3 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button variant="outline" onClick={() => router.visit(route('ventas.facturas.index'))}>
                        Volver
                    </Button>
                    <div className="flex gap-2">
                        {acc.sri && puede('editar') && (
                            <Button variant="outline" onClick={() => void enviarFacturaSri(factura.id)}>
                                <span className={`text-xs font-bold ${ACCION_CLS.sri.texto}`}>SRI</span>
                                Enviar al SRI
                            </Button>
                        )}
                        {acc.correo && puede('editar') && (
                            <Button
                                variant="outline"
                                onClick={() => void enviarFacturaPorCorreo(factura.id, numero, factura.email_cliente ?? factura.cliente.email)}
                            >
                                <Mail className={`w-4 h-4 ${ACCION_CLS.correo.texto}`} />
                                Enviar por correo
                            </Button>
                        )}
                        <a href={route('ventas.facturas.xml', factura.id)}>
                            <Button variant="outline">
                                <span className={`text-xs font-bold ${ACCION_CLS.xml.texto}`}>XML</span>
                                Descargar XML
                            </Button>
                        </a>
                        <Button variant="outline" onClick={() => setVerRide(true)}>
                            <PdfIcon className={`w-4 h-4 ${ACCION_CLS.ride.texto}`} />
                            RIDE PDF
                        </Button>
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
            <PdfPreviewModal
                abierto={verRide}
                onCerrar={() => setVerRide(false)}
                url={verRide ? route('ventas.facturas.ride', factura.id) : ''}
                titulo={`RIDE — Factura ${numero}`}
                nombreDescarga={`RIDE-${numero}.pdf`}
            />
        </AppLayout>
    )
}
