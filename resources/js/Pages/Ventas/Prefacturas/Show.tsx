import { useEffect, useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import PdfIcon from '@/Components/shared/PdfIcon'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, X, DollarSign, FileText } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import { toastError } from '@/lib/toast'
import type { PageProps } from '@/types'

interface PrefacturaDetalle {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    total: number
    producto?: { codigo: string } | null
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
    tipo_identificacion?: string | null
    direccion?: string | null
    telefono?: string | null
    email?: string | null
    ciudad?: string | null
    pais?: string | null
}

interface PrefacturaFull {
    id: number
    numero: string
    fecha_emision: string
    estado: 'pendiente' | 'parcial' | 'liquidada' | 'anulada'
    total: number
    total_abonado: number
    saldo_pendiente: number
    observaciones: string | null
    cliente: PrefacturaCliente | null
    usuario?: { nombre: string } | null
    detalles: PrefacturaDetalle[]
    abonos: PrefacturaAbono[]
}

interface Props extends PageProps {
    prefactura: PrefacturaFull
}

const ESTADO_CONFIG = {
    pendiente: { label: 'Pendiente', variant: 'warning' as const },
    parcial: { label: 'Parcial', variant: 'info' as const },
    liquidada: { label: 'Liquidada', variant: 'success' as const },
    anulada: { label: 'Anulada', variant: 'secondary' as const },
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
    const { prefactura } = usePage<Props>().props
    const { puede } = usePermiso('ventas')
    const cfg = ESTADO_CONFIG[prefactura.estado] ?? ESTADO_CONFIG.pendiente
    const puedeAbonar = prefactura.estado !== 'liquidada' && prefactura.estado !== 'anulada' && puede('editar')
    const puedeConvertir = Number(prefactura.saldo_pendiente) === 0 && prefactura.estado !== 'anulada' && puede('editar')
    const cliente = prefactura.cliente

    const [verPdf, setVerPdf] = useState(false)
    const [modalAbono, setModalAbono] = useState(false)
    const [abonoValor, setAbonoValor] = useState('')
    const [abonoFormaPago, setAbonoFormaPago] = useState('efectivo')
    const [abonoBanco, setAbonoBanco] = useState('')
    const [abonoComprobante, setAbonoComprobante] = useState('')
    const [abonando, setAbonando] = useState(false)

    // Líneas con valores derivados (el detalle solo guarda cantidad, precio, % y total con IVA)
    const lineas = prefactura.detalles.map(d => {
        const base = Number(d.cantidad) * Number(d.precio_unitario)
        const descuento = redondear(base * (Number(d.descuento_pct) / 100))
        const neto = redondear(base - descuento)
        const grava = Number(d.total) > neto + 0.001
        return { d, descuento, neto, grava }
    })
    const subtotal15 = redondear(lineas.filter(l => l.grava).reduce((a, l) => a + l.neto, 0))
    const subtotal0 = redondear(lineas.filter(l => !l.grava).reduce((a, l) => a + l.neto, 0))
    const descTotal = redondear(lineas.reduce((a, l) => a + l.descuento, 0))
    const iva = redondear(subtotal15 * 0.15)

    const abrirModal = () => {
        setAbonoValor(String(prefactura.saldo_pendiente))
        setAbonoFormaPago('efectivo')
        setAbonoBanco('')
        setAbonoComprobante('')
        setModalAbono(true)
    }

    // Desde el listado, el icono de abono llega con ?abonar=1
    useEffect(() => {
        if (puedeAbonar && new URLSearchParams(window.location.search).get('abonar') === '1') abrirModal()
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [])

    const cerrarModal = () => {
        setModalAbono(false)
    }

    const handleAbonar = () => {
        const valor = parseFloat(abonoValor)
        if (!valor || valor <= 0 || valor > prefactura.saldo_pendiente) return
        if (descTotal > 0 && (abonoFormaPago === 'tarjeta' || abonoFormaPago === 'datafast')) {
            toastError('Con tarjeta de crédito no hay descuento de ningún tipo. Esta prefactura tiene descuento.')
            return
        }
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
            <Head title={`Prefactura ${prefactura.numero}`} />
            <PageHeader
                title={`Prefactura ${prefactura.numero}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Prefacturas', href: route('ventas.prefacturas.index') },
                    { label: prefactura.numero },
                ]}
            />

            <div className="p-4 space-y-4 max-w-7xl">

                {/* Cliente (izquierda) + encabezado y abonos (derecha) */}
                <div className="flex flex-col lg:flex-row-reverse lg:items-start gap-4">

                    <div className="flex flex-col flex-1 min-w-0 gap-4">
                        <div className="flex flex-wrap items-center gap-6 px-4 py-2.5 rounded-xl border" style={CARD}>
                            <span className="text-sm" style={MUTED}>
                                Prefactura N°:{' '}
                                <span className="font-mono font-semibold" style={MAIN}>{prefactura.numero}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Fecha: <span className="font-medium" style={MAIN}>{formatFecha(prefactura.fecha_emision)}</span>
                            </span>
                            <span className="text-sm" style={MUTED}>
                                Emitido por: <span className="font-medium" style={MAIN}>{prefactura.usuario?.nombre ?? 'Sistema'}</span>
                            </span>
                            <Badge variant={cfg.variant}>{cfg.label}</Badge>
                        </div>

                        <div className="rounded-xl p-3 border" style={CARD}>
                            <div className="flex items-center justify-between mb-2">
                                <p className={TITULO}>Abonos</p>
                                <span className="text-xs" style={MUTED}>
                                    Abonado: <b className="text-emerald-400">{formatMoneda(prefactura.total_abonado)}</b>
                                    {' · '}Saldo: <b style={{ color: Number(prefactura.saldo_pendiente) > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>{formatMoneda(prefactura.saldo_pendiente)}</b>
                                </span>
                            </div>
                            {prefactura.abonos.length === 0 ? (
                                <p className="py-3 text-xs text-center" style={MUTED}>Sin abonos registrados</p>
                            ) : (
                                <table className="w-full text-xs">
                                    <thead>
                                        <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                            {['Fecha', 'Forma', 'Comprobante', 'Usuario', 'Valor'].map((h, i) => (
                                                <th key={h} className={`py-1 px-1.5 font-medium ${i === 4 ? 'text-right' : 'text-left'}`} style={MUTED}>{h}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {prefactura.abonos.map(a => (
                                            <tr key={a.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                                <td className="py-1 px-1.5" style={MUTED}>{a.fecha ? formatFecha(a.fecha) : '—'}</td>
                                                <td className="py-1 px-1.5 capitalize" style={MAIN}>{a.forma_pago}</td>
                                                <td className="py-1 px-1.5 font-mono" style={MUTED}>{a.num_comprobante ?? '—'}</td>
                                                <td className="py-1 px-1.5" style={MUTED}>{a.usuario_nombre ?? '—'}</td>
                                                <td className="py-1 px-1.5 text-right font-semibold text-emerald-400">{formatMoneda(a.valor)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
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
                                {lineas.map(({ d, descuento, neto }, idx) => (
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
                                        <td className="py-1 px-1.5 text-right font-semibold" style={MAIN}>{formatMoneda(neto)}</td>
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
                            {prefactura.observaciones || <span style={MUTED}>—</span>}
                        </div>
                    </div>
                    <div className="col-span-2 flex flex-col justify-end">
                        <table className="w-full">
                            <tbody>
                                {[
                                    { label: 'SUBTOTAL 15%:', value: subtotal15 },
                                    { label: 'SUBTOTAL SIN IMP.:', value: subtotal0 },
                                    { label: 'TOTAL DESCUENTO:', value: descTotal },
                                    { label: 'TOTAL IVA:', value: iva },
                                ].map(row => (
                                    <tr key={row.label}>
                                        <td className="py-0.5 pr-3 text-right text-xs font-medium" style={MUTED}>{row.label}</td>
                                        <td className="py-0.5 text-right text-xs font-semibold w-28" style={MAIN}>{formatMoneda(row.value)}</td>
                                    </tr>
                                ))}
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td className="pt-2 pr-3 text-right text-sm font-bold" style={MUTED}>TOTAL VALOR:</td>
                                    <td className="pt-2 text-right text-base font-bold w-28" style={{ color: 'var(--primary)' }}>{formatMoneda(prefactura.total)}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex justify-between pt-3 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Link href={route('ventas.prefacturas.index')}>
                        <Button variant="outline">Volver</Button>
                    </Link>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setVerPdf(true)}>
                            <PdfIcon className="w-4 h-4 text-red-500" />
                            PDF
                        </Button>
                        {puedeAbonar && (
                            <Button onClick={abrirModal}>
                                <Plus className="w-4 h-4" />
                                Registrar Abono
                            </Button>
                        )}
                        {puedeConvertir && (
                            <Button variant="secondary" onClick={() => void handleConvertir()}>
                                <FileText className="w-4 h-4" />
                                Crear Factura
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <PdfPreviewModal
                abierto={verPdf}
                onCerrar={() => setVerPdf(false)}
                url={verPdf ? route('ventas.prefacturas.pdf', prefactura.id) : ''}
                titulo={`Prefactura ${prefactura.numero}`}
                nombreDescarga={`Prefactura-${prefactura.numero}.pdf`}
            />

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
