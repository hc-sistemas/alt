import { useState } from 'react'
import { createPortal } from 'react-dom'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import PdfIcon from '@/Components/shared/PdfIcon'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowRightLeft, Ban, X, Plus, Trash2 } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import { anularConPin } from '@/lib/ventasDocumentos'
import type { PageProps } from '@/types'

interface ProformaDetalle {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    subtotal: number
    total: number
    producto?: { codigo: string } | null
}

interface ProformaCliente {
    razon_social: string
    identificacion: string
    tipo_identificacion?: string | null
    email?: string | null
    telefono?: string | null
    direccion?: string | null
    ciudad?: string | null
    pais?: string | null
}

interface ProformaFull {
    id: number
    numero: string
    fecha_emision: string
    fecha_vencimiento: string
    estado: 'pendiente' | 'facturada' | 'vencida' | 'anulada'
    subtotal: number
    descuento_total: number
    total_iva: number
    total: number
    observaciones: string | null
    usuario?: { nombre: string } | null
    cliente: ProformaCliente | null
    detalles: ProformaDetalle[]
}

interface Props extends PageProps {
    proforma: ProformaFull
}

interface FormaPago {
    [key: string]: string | number
    forma: string
    monto: number
}

const FORMAS_PAGO = ['efectivo', 'transferencia', 'tarjeta', 'cheque', 'credito']

const ESTADO_CONFIG = {
    pendiente: { label: 'Pendiente', variant: 'secondary' as const },
    facturada: { label: 'Facturada', variant: 'success' as const },
    vencida: { label: 'Vencida', variant: 'danger' as const },
    anulada: { label: 'Anulada', variant: 'warning' as const },
}

const TIPO_ID_LABEL: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }
const TITULO = 'text-xs font-bold uppercase tracking-wider text-(--primary-hover) dark:text-(--primary)'
const CARD = { background: 'var(--bg-card)', borderColor: 'var(--border)' }
const MUTED = { color: 'var(--text-muted)' }
const MAIN = { color: 'var(--text-main)' }

const redondear = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100

// Campo de solo lectura con el mismo aspecto que los del formulario
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
    const { proforma } = usePage<Props>().props
    const { puede } = usePermiso('ventas')
    const esPendiente = proforma.estado === 'pendiente'
    const cfg = ESTADO_CONFIG[proforma.estado] ?? ESTADO_CONFIG.pendiente
    const cliente = proforma.cliente

    const [verPdf, setVerPdf] = useState(false)
    const [modalConvertir, setModalConvertir] = useState(false)
    const [formasPago, setFormasPago] = useState<FormaPago[]>([
        { forma: 'efectivo', monto: proforma.total }
    ])
    const [convirtiendo, setConvirtiendo] = useState(false)
    const [errorPago, setErrorPago] = useState('')

    // Base gravada = IVA / 15%; el resto de la base es 0%
    const iva = Number(proforma.total_iva)
    const subtotal15 = iva > 0 ? redondear(iva / 0.15) : 0
    const subtotal0 = Math.max(0, redondear(Number(proforma.subtotal) - subtotal15))

    const handleConvertir = () => {
        setFormasPago([{ forma: 'efectivo', monto: proforma.total }])
        setErrorPago('')
        setModalConvertir(true)
    }

    const handleConfirmarConversion = () => {
        const totalPagos = formasPago.reduce((sum, p) => sum + p.monto, 0)
        if (Math.abs(totalPagos - proforma.total) > 0.01) {
            setErrorPago(`Las formas de pago deben sumar ${formatMoneda(proforma.total)}. Actual: ${formatMoneda(totalPagos)}`)
            return
        }
        if (formasPago.some(p => !p.forma || p.monto <= 0)) {
            setErrorPago('Todas las formas de pago deben tener forma y monto válido.')
            return
        }
        if (Number(proforma.descuento_total) > 0 && formasPago.some(p => p.forma === 'tarjeta' || p.forma === 'datafast')) {
            setErrorPago('Con tarjeta de crédito no hay descuento de ningún tipo. Esta proforma tiene descuento.')
            return
        }
        setErrorPago('')
        setConvirtiendo(true)
        router.post(
            route('ventas.proformas.convertir', proforma.id),
            { formas_pago: formasPago },
            {
                onError: () => setConvirtiendo(false),
                onFinish: () => setConvirtiendo(false),
            }
        )
        setModalConvertir(false)
    }

    const handleAnular = () =>
        anularConPin({ url: route('ventas.proformas.anular', proforma.id), etiqueta: 'proforma', numero: proforma.numero })

    return (
        <AppLayout>
            <Head title={`Proforma ${proforma.numero}`} />
            <PageHeader
                title={`Proforma ${proforma.numero}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Proformas', href: route('ventas.proformas.index') },
                    { label: proforma.numero },
                ]}
            />

            <div className="p-4 space-y-4 max-w-7xl">

                {/* Cliente (izquierda) + encabezado (derecha) */}
                <div className="flex flex-col lg:flex-row-reverse lg:items-start gap-4">

                    <div className="flex flex-col flex-1 min-w-0 gap-4">
                        <div className="flex flex-wrap items-center gap-6 px-4 py-2.5 rounded-xl border" style={CARD}>
                            <span className="text-sm" style={MUTED}>
                                Proforma N°:{' '}
                                <span className="font-mono font-semibold" style={MAIN}>{proforma.numero}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Fecha: <span className="font-medium" style={MAIN}>{formatFecha(proforma.fecha_emision)}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Vence: <span className="font-medium" style={MAIN}>{proforma.fecha_vencimiento ? formatFecha(proforma.fecha_vencimiento) : '—'}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Vendedor: <span className="font-medium" style={MAIN}>{proforma.usuario?.nombre ?? '—'}</span>
                            </span>
                            <Badge variant={cfg.variant}>{cfg.label}</Badge>
                        </div>
                    </div>

                    <div className="rounded-xl p-4 border w-full lg:w-xl shrink-0" style={CARD}>
                        <p className={`${TITULO} mb-3`}>Cliente</p>
                        <div style={{ maxWidth: 480 }}>
                            <table className="w-full">
                                <tbody>
                                    <CampoLectura label={TIPO_ID_LABEL[cliente?.tipo_identificacion ?? '04'] ?? 'RUC/CC'} value={cliente?.identificacion} />
                                    <CampoLectura label="NOMBRE" value={cliente?.razon_social} />
                                    <CampoLectura label="DIRECCIÓN" value={cliente?.direccion} />
                                    <CampoLectura label="TELÉFONO" value={cliente?.telefono} />
                                    <CampoLectura label="EMAIL" value={cliente?.email} />
                                    <CampoLectura label="CIUDAD" value={cliente?.ciudad} />
                                    <CampoLectura label="PAÍS" value={cliente?.pais ?? 'ECUADOR'} />
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
                                {proforma.detalles.map((d, idx) => {
                                    const descuento = redondear(Number(d.cantidad) * Number(d.precio_unitario) * (Number(d.descuento_pct) / 100))
                                    return (
                                        <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="py-1 px-1.5 text-center" style={MUTED}>{idx + 1}</td>
                                            <td className="py-1 px-1.5" style={MAIN}>
                                                <span className="font-mono font-semibold" style={{ color: 'var(--primary)' }}>{d.producto?.codigo ?? '—'}</span>
                                                {' — '}{d.descripcion}
                                            </td>
                                            <td className="py-1 px-1.5 text-right" style={MAIN}>{Number(d.cantidad)}</td>
                                            <td className="py-1 px-1.5 text-right" style={MAIN}>{formatMoneda(d.precio_unitario)}</td>
                                            <td className="py-1 px-1.5 text-right" style={MUTED}>{Number(d.descuento_pct)}</td>
                                            <td className="py-1 px-1.5 text-right" style={MUTED}>{formatMoneda(descuento)}</td>
                                            <td className="py-1 px-1.5 text-right font-semibold" style={MAIN}>{formatMoneda(d.subtotal)}</td>
                                        </tr>
                                    )
                                })}
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
                            {proforma.observaciones || <span style={MUTED}>—</span>}
                        </div>
                    </div>
                    <div className="col-span-2 flex flex-col justify-end">
                        <table className="w-full">
                            <tbody>
                                {[
                                    { label: 'SUBTOTAL 15%:', value: subtotal15 },
                                    { label: 'SUBTOTAL SIN IMP.:', value: subtotal0 },
                                    { label: 'TOTAL DESCUENTO:', value: proforma.descuento_total },
                                    { label: 'TOTAL IVA:', value: proforma.total_iva },
                                ].map(row => (
                                    <tr key={row.label}>
                                        <td className="py-0.5 pr-3 text-right text-xs font-medium" style={MUTED}>{row.label}</td>
                                        <td className="py-0.5 text-right text-xs font-semibold w-28" style={MAIN}>{formatMoneda(row.value)}</td>
                                    </tr>
                                ))}
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td className="pt-2 pr-3 text-right text-sm font-bold" style={MUTED}>TOTAL VALOR:</td>
                                    <td className="pt-2 text-right text-base font-bold w-28" style={{ color: 'var(--primary)' }}>{formatMoneda(proforma.total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex justify-between pt-3 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Link href={route('ventas.proformas.index')}>
                        <Button variant="outline">Volver</Button>
                    </Link>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setVerPdf(true)}>
                            <PdfIcon className="w-4 h-4 text-red-500" />
                            PDF
                        </Button>
                        {esPendiente && puede('editar') && (
                            <Button onClick={() => handleConvertir()}>
                                <ArrowRightLeft className="w-4 h-4" />
                                Convertir a Factura
                            </Button>
                        )}
                        {esPendiente && puede('anular') && (
                            <Button variant="destructive" onClick={() => void handleAnular()}>
                                <Ban className="w-4 h-4" />
                                Anular
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <PdfPreviewModal
                abierto={verPdf}
                onCerrar={() => setVerPdf(false)}
                url={verPdf ? route('ventas.proformas.pdf', proforma.id) : ''}
                titulo={`Proforma ${proforma.numero}`}
                nombreDescarga={`Proforma-${proforma.numero}.pdf`}
            />

            {modalConvertir && createPortal(
                <>
                    <div
                        className="fixed inset-0"
                        style={{ background: 'rgba(0,0,0,0.5)', zIndex: 50 }}
                        onClick={() => !convirtiendo && setModalConvertir(false)}
                    />
                    <div
                        className="fixed inset-0 flex items-center justify-center p-4"
                        style={{ zIndex: 51 }}
                    >
                        <div
                            className="w-full rounded-xl shadow-xl flex flex-col gap-4 p-5"
                            style={{ maxWidth: 480, background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Formas de Pago
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setModalConvertir(false)}
                                    className="p-1 rounded-md transition-colors"
                                    style={{ color: 'var(--text-muted)' }}
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <div
                                className="rounded-lg px-3 py-2 text-sm"
                                style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}
                            >
                                <span style={{ color: 'var(--text-muted)' }}>Total a cobrar: </span>
                                <span className="font-bold" style={{ color: 'var(--primary)' }}>{formatMoneda(proforma.total)}</span>
                            </div>

                            <div className="space-y-2">
                                {formasPago.map((p, idx) => (
                                    <div key={idx} className="flex items-center gap-2">
                                        <select
                                            className="h-8 rounded-md border px-2 text-sm flex-1"
                                            style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                            value={p.forma}
                                            onChange={e => {
                                                const next = [...formasPago]
                                                next[idx] = { ...next[idx], forma: e.target.value }
                                                setFormasPago(next)
                                            }}
                                        >
                                            {FORMAS_PAGO.map(f => (
                                                <option key={f} value={f}>{f}</option>
                                            ))}
                                        </select>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className="h-8 rounded-md border px-2 text-sm w-28 text-right"
                                            style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                            value={p.monto}
                                            onChange={e => {
                                                const next = [...formasPago]
                                                next[idx] = { ...next[idx], monto: Number(e.target.value) }
                                                setFormasPago(next)
                                            }}
                                        />
                                        {formasPago.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => setFormasPago(prev => prev.filter((_, i) => i !== idx))}
                                                className="p-1 rounded hover:bg-red-500/10 transition-colors"
                                            >
                                                <Trash2 className="w-4 h-4 text-red-400" />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <button
                                type="button"
                                onClick={() => setFormasPago(prev => [...prev, { forma: 'efectivo', monto: 0 }])}
                                className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500 w-fit"
                                style={{ color: 'var(--text-muted)' }}
                            >
                                <Plus className="w-3.5 h-3.5" />
                                Agregar forma de pago
                            </button>

                            {errorPago && (
                                <p className="text-xs" style={{ color: '#ef4444' }}>{errorPago}</p>
                            )}

                            <div className="flex justify-end gap-2 pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setModalConvertir(false)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    loading={convirtiendo}
                                    onClick={handleConfirmarConversion}
                                >
                                    Convertir a Factura
                                </Button>
                            </div>
                        </div>
                    </div>
                </>,
                document.body
            )}
        </AppLayout>
    )
}
