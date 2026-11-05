import { Head, router, usePage } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda } from '@/lib/utils'
import { AlertTriangle, FileText, X } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import { estadoOrden } from '@/lib/tallerEstados'
import type { PageProps, TallerOrdenTrabajo } from '@/types'

interface BancoOpcion {
    id: number
    nombre: string
    tipo: string
}

interface Props extends PageProps {
    orden: TallerOrdenTrabajo
    iva_servicio: number
    centro_costo: string | null
    empresa_factura: { razon_social: string; ruc: string } | null
    bancos: BancoOpcion[]
}

const FORMAS_PAGO = [
    { value: 'efectivo', label: 'Efectivo' },
    { value: 'transferencia', label: 'Transferencia' },
    { value: 'tarjeta', label: 'Tarjeta' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'datafast', label: 'Datafast' },
]

export default function LiquidacionShow() {
    const { orden, errors, iva_servicio, centro_costo, empresa_factura, bancos } = usePage<Props>().props
    const { puede } = usePermiso('taller')
    const cfg = estadoOrden(orden.estado)

    const [costoManoObra, setCostoManoObra] = useState(String(orden.costo_mano_obra ?? 0))
    const [formaPago, setFormaPago] = useState('efectivo')
    const [bancoCajaId, setBancoCajaId] = useState('')
    const requiereBanco = formaPago === 'transferencia'   // el cheque queda en cartera: no elige banco
    const [referencia, setReferencia] = useState('')
    const [numCheque, setNumCheque] = useState('')
    const [fechaCheque, setFechaCheque] = useState('')
    const [observaciones, setObservaciones] = useState('')
    const [enviando, setEnviando] = useState(false)

    const repuestos = orden.repuestos ?? []
    // Una orden en garantía no se factura al cliente (02-10 §2.8): aquí solo se cierra y se registra el costo.
    const esGarantia = orden.estado === 'garantia'
    const costoGarantia = repuestos.reduce((t, r) => t + r.costo_unitario * r.cantidad, 0)

    const totales = useMemo(() => {
        let subtotalRepuestos = 0
        let subtotal0 = 0
        let subtotal15 = 0
        let totalIva = 0

        for (const r of repuestos) {
            const subtotal = r.precio_venta * r.cantidad
            const pctIva = r.producto?.porcentaje_iva ?? 15
            const iva = subtotal * (pctIva / 100)
            subtotalRepuestos += subtotal
            if (pctIva > 0) subtotal15 += subtotal
            else subtotal0 += subtotal
            totalIva += iva
        }

        const manoObra = Number(costoManoObra) || 0
        const ivaServicio = manoObra * (iva_servicio / 100)
        totalIva += ivaServicio
        const total = subtotal0 + subtotal15 + manoObra + totalIva

        return { subtotalRepuestos, manoObra, totalIva, total }
    }, [repuestos, costoManoObra, iva_servicio])

    async function cerrarGarantia() {
        const result = await Swal.fire({
            title: 'Cerrar garantía',
            text: `La orden ${orden.numero ?? `#${orden.id}`} no se factura al cliente. Los repuestos salen del inventario y su costo (${formatMoneda(costoGarantia)}) queda registrado. ¿Continuar?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar garantía',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!result.isConfirmed) return
        setEnviando(true)
        router.post(route('taller.liquidacion.garantia', orden.id), {}, {
            onError: () => setEnviando(false),
            onFinish: () => setEnviando(false),
        })
    }

    async function confirmarLiquidacion() {
        const result = await Swal.fire({
            title: 'Generar factura',
            text: `¿Confirmar la liquidación de la orden ${orden.numero ?? `#${orden.id}`} por ${formatMoneda(totales.total)}? Esta acción no se puede deshacer.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar factura',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!result.isConfirmed) return

        setEnviando(true)
        router.post(route('taller.liquidacion.liquidar', orden.id), {
            costo_mano_obra: Number(costoManoObra) || 0,
            forma_pago: formaPago,
            banco_caja_id: requiereBanco && bancoCajaId ? Number(bancoCajaId) : null,
            referencia: formaPago === 'transferencia' ? referencia : null,
            num_cheque: formaPago === 'cheque' ? numCheque : null,
            fecha_cheque: formaPago === 'cheque' && fechaCheque ? fechaCheque : null,
            observaciones: observaciones || null,
        }, {
            onError: () => setEnviando(false),
            onFinish: () => setEnviando(false),
        })
    }

    return (
        <AppLayout title={`Liquidar Orden ${orden.numero ?? `#${orden.id}`}`}>
            <Head title={`Liquidar Orden ${orden.numero ?? `#${orden.id}`}`} />
            <PageHeader
                title={`Liquidar Orden ${orden.numero ?? `#${orden.id}`}`}
                breadcrumbs={[
                    { label: 'Taller' },
                    { label: 'Órdenes de Trabajo', href: route('taller.ordenes.index') },
                    { label: orden.numero ?? `#${orden.id}`, href: route('taller.ordenes.show', orden.id) },
                    { label: 'Liquidar' },
                ]}
            />

            <div className="p-6 max-w-3xl space-y-6">
                {errors?.error && (
                    <div
                        className="rounded-lg p-4 border"
                        style={{ background: 'rgba(239,68,68,.1)', borderColor: 'rgba(239,68,68,.3)' }}
                    >
                        <p className="text-sm text-red-400 flex items-center gap-2">
                            <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                            {errors.error}
                        </p>
                    </div>
                )}

                {/* Resumen de la OT */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            {orden.numero ?? `#${orden.id}`}
                        </span>
                        <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Cliente</p>
                            <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.cliente?.razon_social ?? '—'}</p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{orden.ingreso?.cliente?.identificacion ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Equipo</p>
                            <p style={{ color: 'var(--text-main)' }}>
                                {[orden.ingreso?.equipo?.marca, orden.ingreso?.equipo?.modelo].filter(Boolean).join(' ') || '—'}
                            </p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Serie: {orden.ingreso?.equipo?.numero_serie ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Técnico asignado</p>
                            <p style={{ color: 'var(--text-main)' }}>{orden.tecnico?.nombre ?? '—'}</p>
                        </div>
                    </div>
                </div>

                {/* Repuestos (readonly) */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Repuestos</h3>
                    </div>

                    {repuestos.length === 0 ? (
                        <p className="p-4 text-sm" style={{ color: 'var(--text-muted)' }}>No hay repuestos registrados en esta orden.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                        {['Producto', 'Serie', 'Cantidad', esGarantia ? 'Costo unitario' : 'Precio Venta', 'Subtotal'].map(h => (
                                            <th key={h} className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {repuestos.map(r => (
                                        <tr key={r.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                {r.producto?.nombre ?? `#${r.producto_id}`}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {r.numero_serie ?? '—'}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                {r.cantidad}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                {formatMoneda(esGarantia ? r.costo_unitario : r.precio_venta)}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                {formatMoneda((esGarantia ? r.costo_unitario : r.precio_venta) * r.cantidad)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ borderTop: '2px solid var(--border)' }}>
                                        <td colSpan={4} className="px-4 py-2.5 text-right text-xs font-semibold" style={{ color: 'var(--text-muted)' }}>
                                            Subtotal repuestos
                                        </td>
                                        <td className="px-4 py-2.5 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                            {formatMoneda(totales.subtotalRepuestos)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </div>

                {esGarantia ? (
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Cierre de garantía</h3>
                    <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                        Una orden en garantía no se factura al cliente. Al cerrarla, los repuestos salen del inventario y el costo
                        queda registrado en la orden. La cuenta contable de la garantía y la recuperación con el proveedor
                        están pendientes de definir (DEC-047), por eso este cierre no genera asiento.
                    </p>
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--text-muted)' }}>Costo referencial de repuestos (el costo real sale del kárdex al cerrar)</span>
                        <span className="font-semibold" style={{ color: 'var(--text-main)' }}>{formatMoneda(costoGarantia)}</span>
                    </div>
                    <div className="flex items-center gap-3">
                        {puede('editar') && !orden.garantia_cerrada_at && (
                            <Button onClick={cerrarGarantia} loading={enviando}>
                                <FileText className="w-4 h-4" />
                                Cerrar garantía (sin factura)
                            </Button>
                        )}
                        <Button variant="ghost" onClick={() => router.visit(route('taller.ordenes.show', orden.id))}>
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </div>
                </div>
                ) : (
                /* Formulario de facturación */
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Facturación</h3>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        Se emite con el RUC {empresa_factura?.ruc ?? '—'} ({empresa_factura?.razon_social ?? '—'}) y el centro de costo{' '}
                        {centro_costo ?? 'Altamira FIX'}. Líneas: servicio técnico y repuestos.
                    </p>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label style={{ color: 'var(--text-main)' }}>Costo de mano de obra</Label>
                            <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={costoManoObra}
                                onChange={e => setCostoManoObra(e.target.value)}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label style={{ color: 'var(--text-main)' }}>Forma de pago</Label>
                            <select
                                value={formaPago}
                                onChange={e => setFormaPago(e.target.value)}
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            >
                                {FORMAS_PAGO.map(f => (
                                    <option key={f.value} value={f.value}>{f.label}</option>
                                ))}
                            </select>
                        </div>
                        {formaPago === 'transferencia' && (
                            <div className="space-y-1.5">
                                <Label style={{ color: 'var(--text-main)' }}>Referencia (N° de comprobante) *</Label>
                                <input value={referencia} onChange={e => setReferencia(e.target.value)} placeholder="N° de transferencia"
                                    className="w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }} />
                            </div>
                        )}
                        {formaPago === 'cheque' && (
                            <>
                                <div className="space-y-1.5">
                                    <Label style={{ color: 'var(--text-main)' }}>N° de cheque *</Label>
                                    <input value={numCheque} onChange={e => setNumCheque(e.target.value)} placeholder="Número del cheque"
                                        className="w-full h-9 rounded-md border px-3 text-sm"
                                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label style={{ color: 'var(--text-main)' }}>Fecha del cheque (post fechado si es posterior)</Label>
                                    <input type="date" value={fechaCheque} onChange={e => setFechaCheque(e.target.value)}
                                        className="w-full h-9 rounded-md border px-3 text-sm"
                                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }} />
                                </div>
                            </>
                        )}
                        {requiereBanco && (
                            <div className="space-y-1.5">
                                <Label style={{ color: 'var(--text-main)' }}>Banco donde se recibe *</Label>
                                <select
                                    value={bancoCajaId}
                                    onChange={e => setBancoCajaId(e.target.value)}
                                    className="w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                >
                                    <option value="">Seleccione…</option>
                                    {bancos.map(b => (
                                        <option key={b.id} value={b.id}>{b.nombre}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label style={{ color: 'var(--text-main)' }}>Observaciones</Label>
                        <textarea
                            rows={3}
                            className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-(--primary) transition-shadow"
                            style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            placeholder="Observaciones adicionales para la factura..."
                            value={observaciones}
                            onChange={e => setObservaciones(e.target.value)}
                        />
                    </div>

                    {/* Resumen de totales */}
                    <div className="rounded-lg p-4 space-y-1.5 text-sm" style={{ background: 'var(--bg-main)', border: '1px solid var(--border)' }}>
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--text-muted)' }}>Repuestos</span>
                            <span style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.subtotalRepuestos)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--text-muted)' }}>Mano de obra</span>
                            <span style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.manoObra)}</span>
                        </div>
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--text-muted)' }}>IVA (servicio {iva_servicio}%)</span>
                            <span style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.totalIva)}</span>
                        </div>
                        <div className="flex justify-between pt-1.5 mt-1.5 border-t" style={{ borderColor: 'var(--border)' }}>
                            <span className="font-semibold" style={{ color: 'var(--text-main)' }}>Total</span>
                            <span className="text-base font-bold" style={{ color: 'var(--primary)' }}>{formatMoneda(totales.total)}</span>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        {puede('editar') && (
                            <Button onClick={confirmarLiquidacion} loading={enviando}>
                                <FileText className="w-4 h-4" />
                                Generar Factura
                            </Button>
                        )}
                        <Button variant="ghost" onClick={() => router.visit(route('taller.ordenes.show', orden.id))}>
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </div>
                </div>
                )}

                <div className="flex justify-between pt-4 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button variant="outline" onClick={() => router.visit(route('taller.ordenes.show', orden.id))}>
                        Volver a la Orden
                    </Button>
                </div>
            </div>
        </AppLayout>
    )
}
