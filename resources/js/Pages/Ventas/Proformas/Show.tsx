import { useState } from 'react'
import { createPortal } from 'react-dom'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowLeft, ArrowRightLeft, Ban, X, Plus, Trash2 } from 'lucide-react'
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
    fecha_emision: string
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

    const [modalConvertir, setModalConvertir] = useState(false)
    const [formasPago, setFormasPago] = useState<FormaPago[]>([
        { forma: 'efectivo', monto: proforma.total }
    ])
    const [convirtiendo, setConvirtiendo] = useState(false)
    const [errorPago, setErrorPago] = useState('')

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
                        <InfoRow label="Fecha emisión" value={formatFecha(proforma.fecha_emision)} />
                        <InfoRow label="Fecha vencimiento" value={proforma.fecha_vencimiento ? formatFecha(proforma.fecha_vencimiento) : '—'} />
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
