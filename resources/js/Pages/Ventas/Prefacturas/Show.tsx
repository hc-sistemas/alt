import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowLeft, Plus, X, DollarSign, FileText } from 'lucide-react'
import type { PageProps } from '@/types'

interface PrefacturaDetalle {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
}

interface PrefacturaAbono {
    id: number
    fecha: string
    forma_pago: string
    valor: number
    num_comprobante: string | null
    usuario_nombre: string | null
}

interface PrefacturaCliente {
    razon_social: string
    identificacion: string
}

interface PrefacturaFull {
    id: number
    numero_completo: string
    fecha: string
    estado: 'pendiente' | 'parcial' | 'liquidada' | 'anulada'
    total: number
    total_abonado: number
    saldo_pendiente: number
    observaciones: string | null
    cliente: PrefacturaCliente | null
    detalles: PrefacturaDetalle[]
    abonos: PrefacturaAbono[]
}

interface Props extends PageProps {
    prefactura: PrefacturaFull
}

const ESTADO_CONFIG = {
    pendiente: { label: 'Pendiente', variant: 'warning'   as const },
    parcial:   { label: 'Parcial',   variant: 'info'      as const },
    liquidada: { label: 'Liquidada', variant: 'success'   as const },
    anulada:   { label: 'Anulada',   variant: 'secondary' as const },
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
    const { prefactura } = usePage<Props>().props
    const cfg = ESTADO_CONFIG[prefactura.estado] ?? ESTADO_CONFIG.pendiente
    const puedeAbonar = prefactura.estado !== 'liquidada' && prefactura.estado !== 'anulada'
    const puedeConvertir = prefactura.saldo_pendiente === 0 && prefactura.estado !== 'anulada'

    const [modalAbono, setModalAbono] = useState(false)
    const [abonoValor, setAbonoValor] = useState('')
    const [abonoFormaPago, setAbonoFormaPago] = useState('efectivo')
    const [abonoBanco, setAbonoBanco] = useState('')
    const [abonoComprobante, setAbonoComprobante] = useState('')
    const [abonando, setAbonando] = useState(false)

    const abrirModal = () => {
        setAbonoValor(String(prefactura.saldo_pendiente))
        setAbonoFormaPago('efectivo')
        setAbonoBanco('')
        setAbonoComprobante('')
        setModalAbono(true)
    }

    const cerrarModal = () => {
        setModalAbono(false)
    }

    const handleAbonar = () => {
        const valor = parseFloat(abonoValor)
        if (!valor || valor <= 0 || valor > prefactura.saldo_pendiente) return
        setAbonando(true)
        router.post(
            route('ventas.prefacturas.abonar', prefactura.id),
            {
                valor,
                forma_pago: abonoFormaPago,
                banco: abonoBanco || null,
                num_comprobante: abonoComprobante || null,
            },
            {
                onSuccess: () => { cerrarModal(); setAbonando(false) },
                onError: () => setAbonando(false),
                preserveState: false,
            }
        )
    }

    const handleConvertir = async () => {
        const result = await Swal.fire({
            title: 'Crear Factura',
            text: 'La prefactura está liquidada. ¿Desea generar la factura correspondiente?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear factura',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!result.isConfirmed) return
        router.post(route('ventas.prefacturas.convertir', prefactura.id))
    }

    const requiereBanco = abonoFormaPago === 'transferencia' || abonoFormaPago === 'cheque'

    return (
        <AppLayout>
            <Head title={`Prefactura ${prefactura.numero_completo}`} />
            <PageHeader
                title={`Prefactura ${prefactura.numero_completo}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Prefacturas', href: route('ventas.prefacturas.index') },
                    { label: prefactura.numero_completo },
                ]}
                actions={
                    <div className="flex gap-2">
                        {puedeAbonar && (
                            <Button size="sm" onClick={abrirModal}>
                                <Plus className="w-4 h-4" />
                                Registrar Abono
                            </Button>
                        )}
                        {puedeConvertir && (
                            <Button size="sm" variant="secondary" onClick={() => void handleConvertir()}>
                                <FileText className="w-4 h-4" />
                                Crear Factura
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="p-6 space-y-6 max-w-5xl">

                <div className="flex items-center gap-3">
                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    <Link href={route('ventas.prefacturas.index')}>
                        <button type="button" className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500" style={{ color: 'var(--text-muted)' }}>
                            <ArrowLeft className="w-3.5 h-3.5" />
                            Volver a Prefacturas
                        </button>
                    </Link>
                </div>

                {/* Resumen financiero */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Total', valor: prefactura.total, color: 'var(--text-main)' },
                        { label: 'Total Abonado', valor: prefactura.total_abonado, color: 'rgb(52,211,153)' },
                        { label: 'Saldo Pendiente', valor: prefactura.saldo_pendiente, color: prefactura.saldo_pendiente > 0 ? 'var(--primary)' : 'var(--text-muted)' },
                    ].map(item => (
                        <div
                            key={item.label}
                            className="rounded-xl p-4 border"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                        >
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>{item.label}</p>
                            <p className="text-xl font-bold" style={{ color: item.color }}>{formatMoneda(item.valor)}</p>
                        </div>
                    ))}
                </div>

                {/* Datos */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>Información</p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <InfoRow label="Número" value={<span className="font-mono">{prefactura.numero_completo}</span>} />
                        <InfoRow label="Fecha" value={formatFecha(prefactura.fecha)} />
                        <InfoRow label="Cliente" value={prefactura.cliente?.razon_social ?? '—'} />
                        <InfoRow label="Identificación" value={prefactura.cliente?.identificacion ?? '—'} />
                    </div>
                </div>

                {/* Detalle productos */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Productos</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                    {['Descripción', 'Cant.', 'Precio', 'Subtotal', 'IVA', 'Total'].map((h, i) => (
                                        <th key={i} className={`px-4 py-3 text-left font-semibold uppercase tracking-wide ${i >= 1 ? 'text-right' : ''}`} style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {prefactura.detalles.map(d => (
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
                        </table>
                    </div>
                </div>

                {/* Historial de abonos */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Historial de Abonos
                        </p>
                    </div>
                    {prefactura.abonos.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-center" style={{ color: 'var(--text-muted)' }}>Sin abonos registrados</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Fecha', 'Forma de pago', 'N° Comprobante', 'Usuario', 'Valor'].map(h => (
                                            <th key={h} className="px-4 py-3 text-left font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {prefactura.abonos.map(a => (
                                        <tr key={a.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{formatFecha(a.fecha)}</td>
                                            <td className="px-4 py-3 capitalize" style={{ color: 'var(--text-main)' }}>{a.forma_pago}</td>
                                            <td className="px-4 py-3 font-mono" style={{ color: 'var(--text-muted)' }}>{a.num_comprobante ?? '—'}</td>
                                            <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{a.usuario_nombre ?? '—'}</td>
                                            <td className="px-4 py-3 font-semibold text-emerald-400">{formatMoneda(a.valor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Abono */}
            {modalAbono && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                    <div
                        className="w-full max-w-md rounded-2xl p-6 shadow-2xl"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                    >
                        <div className="flex items-center justify-between mb-5">
                            <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>Registrar Abono</h3>
                            <button type="button" onClick={cerrarModal}>
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>

                        <p className="text-xs mb-4" style={{ color: 'var(--text-muted)' }}>
                            Saldo pendiente: <strong style={{ color: 'var(--primary)' }}>{formatMoneda(prefactura.saldo_pendiente)}</strong>
                        </p>

                        <div className="space-y-4">
                            <div>
                                <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Valor del abono *</label>
                                <Input
                                    type="number"
                                    min="0.01"
                                    max={prefactura.saldo_pendiente}
                                    step="0.01"
                                    value={abonoValor}
                                    onChange={e => setAbonoValor(e.target.value)}
                                    autoFocus
                                />
                            </div>
                            <div>
                                <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Forma de pago *</label>
                                <select
                                    className="w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={abonoFormaPago}
                                    onChange={e => setAbonoFormaPago(e.target.value)}
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="datafast">Datafast</option>
                                </select>
                            </div>
                            {requiereBanco && (
                                <div>
                                    <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Banco</label>
                                    <Input placeholder="Nombre del banco..." value={abonoBanco} onChange={e => setAbonoBanco(e.target.value)} />
                                </div>
                            )}
                            <div>
                                <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>N° Comprobante</label>
                                <Input placeholder="Opcional..." value={abonoComprobante} onChange={e => setAbonoComprobante(e.target.value)} />
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 mt-6">
                            <Button type="button" variant="outline" onClick={cerrarModal} disabled={abonando}>
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                onClick={handleAbonar}
                                loading={abonando}
                                disabled={!abonoValor || parseFloat(abonoValor) <= 0}
                            >
                                <DollarSign className="w-4 h-4" />
                                Registrar Abono
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
