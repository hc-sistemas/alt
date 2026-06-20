import { useEffect, useState } from 'react'
import { Head, Link, usePage, useForm } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { ChevronLeft, Ban, ShoppingCart, ExternalLink, X, Printer, XCircle, Download } from 'lucide-react'
import { formatFecha } from '@/utils/contabilidad'
import type {
    Compra, Proveedor, CentroCosto, AsientoContable,
    CuentaPagar, CompraDetalle, PageProps,
} from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CompraShow extends Compra {
    centro_costo?: CentroCosto
    cuenta_pagar?: CuentaPagar
    asiento?: AsientoContable
    creado_por?: { id: number; name: string; email: string }
}

interface Props extends PageProps {
    compra: CompraShow
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const n   = (v: unknown) => parseFloat(String(v ?? 0)) || 0
const fmt = (v: unknown) => '$' + n(v).toFixed(2)

// ─── Modal Anular ─────────────────────────────────────────────────────────────

function AnularModal({ compra, onClose }: { compra: CompraShow; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({ motivo: '' })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(route('compras.facturas.anular', compra.id), {
            onSuccess: () => { notify.ok(`Compra ${compra.num_documento} anulada`); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md rounded-xl shadow-2xl p-6"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <div className="flex items-center justify-between mb-4">
                    <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>Anular compra</h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70"
                        style={{ color: 'var(--text-muted)' }}><X className="w-4 h-4" /></button>
                </div>
                <p className="text-sm mb-4" style={{ color: 'var(--text-muted)' }}>
                    Esta acción anulará <strong style={{ color: 'var(--text-main)' }}>{compra.num_documento}</strong> y no se puede deshacer.
                </p>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Motivo de anulación <span className="text-red-400">*</span></Label>
                        <textarea value={data.motivo}
                            onChange={e => setData('motivo', e.target.value)}
                            rows={3}
                            className="w-full px-3 py-2 border rounded-md text-sm resize-none focus:outline-none focus:ring-1 focus:ring-red-500"
                            style={inp}
                            placeholder="Describe el motivo de la anulación (mínimo 10 caracteres)..." />
                        {errors.motivo && <p className="text-red-400 text-xs">{errors.motivo}</p>}
                    </div>
                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 transition-opacity"
                            style={{ background: '#ef4444' }}>
                            <Ban className="w-3.5 h-3.5" /> Anular compra
                        </button>
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function CompraShow() {
    const { compra, flash } = usePage<Props>().props
    const [showAnular, setShowAnular] = useState(false)
    const [modalPdf,   setModalPdf]   = useState(false)
    const [urlPdf,     setUrlPdf]     = useState('')

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    const puedeAnular       = !compra.tiene_pago
    const confirmarAnulacion = () => setShowAnular(true)

    const detalles    = compra.detalles ?? []
    const subtotal0   = n(compra.subtotal_0)
    const subtotalIva = n(compra.subtotal_iva)
    const totalIva    = n(compra.total_iva)
    const total       = n(compra.total)

    return (
        <AppLayout title={`Compra ${compra.num_documento}`} suppressFlash>
            <Head title={`Compra ${compra.num_documento}`} />

            <div className="px-6 pt-6 pb-8 max-w-5xl">

                {/* ── Header ── */}
                <div className="flex items-start justify-between gap-4 mb-6">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl"
                            style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                            <ShoppingCart size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <div className="flex items-center gap-2 flex-wrap">
                                <h1 className="text-xl font-bold font-mono" style={{ color: 'var(--text-main)' }}>
                                    {compra.num_documento}
                                </h1>
                                {compra.estado === 'activa'
                                    ? <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Activa</span>
                                    : <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Anulada</span>
                                }
                            </div>
                            <p className="text-sm mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                {compra.tipo_documento} · Emitida {formatFecha(compra.fecha_emision)}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                        <Link href={route('compras.facturas.index')}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-all hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                            <ChevronLeft size={15} /> Volver
                        </Link>
                        <button
                            onClick={() => { setUrlPdf(route('compras.facturas.pdf-individual', compra.id)); setModalPdf(true) }}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90"
                            style={{ background: '#ef4444' }}>
                            <Printer size={15} /> PDF
                        </button>
                        {compra.estado === 'activa' && puedeAnular && (
                            <button onClick={confirmarAnulacion}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90"
                                style={{ background: '#ef4444' }}>
                                <XCircle size={15} /> Anular
                            </button>
                        )}
                    </div>
                </div>

                {/* ── Info panel ── */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    {/* Proveedor */}
                    <div className="rounded-xl p-4 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider mb-3"
                            style={{ color: 'var(--text-muted)' }}>Proveedor</p>
                        <p className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                            {(compra.proveedor as Proveedor | undefined)?.razon_social ?? '—'}
                        </p>
                        {(compra.proveedor as Proveedor | undefined)?.identificacion && (
                            <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                {(compra.proveedor as Proveedor).tipo_identificacion?.toUpperCase()}: {(compra.proveedor as Proveedor).identificacion}
                            </p>
                        )}
                        {(compra.proveedor as Proveedor | undefined)?.email && (
                            <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                {(compra.proveedor as Proveedor).email}
                            </p>
                        )}
                        {(compra.proveedor as Proveedor | undefined)?.telefono && (
                            <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                {(compra.proveedor as Proveedor).telefono}
                            </p>
                        )}
                    </div>

                    {/* Documento */}
                    <div className="rounded-xl p-4 border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider mb-3"
                            style={{ color: 'var(--text-muted)' }}>Documento</p>
                        <dl className="space-y-1.5">
                            <div className="flex justify-between text-xs">
                                <dt style={{ color: 'var(--text-muted)' }}>Tipo</dt>
                                <dd className="font-medium" style={{ color: 'var(--text-main)' }}>{compra.tipo_documento}</dd>
                            </div>
                            {compra.num_autorizacion && (
                                <div className="flex justify-between text-xs gap-4">
                                    <dt style={{ color: 'var(--text-muted)' }}>Autorización SRI</dt>
                                    <dd className="font-mono text-[11px] truncate" style={{ color: 'var(--text-main)' }}>{compra.num_autorizacion}</dd>
                                </div>
                            )}
                            <div className="flex justify-between text-xs">
                                <dt style={{ color: 'var(--text-muted)' }}>Fecha emisión</dt>
                                <dd style={{ color: 'var(--text-main)' }}>{formatFecha(compra.fecha_emision)}</dd>
                            </div>
                            <div className="flex justify-between text-xs">
                                <dt style={{ color: 'var(--text-muted)' }}>Fecha registro</dt>
                                <dd style={{ color: 'var(--text-main)' }}>{formatFecha(compra.fecha_registro)}</dd>
                            </div>
                            {compra.fecha_vencimiento && (
                                <div className="flex justify-between text-xs">
                                    <dt style={{ color: 'var(--text-muted)' }}>Vencimiento</dt>
                                    <dd style={{ color: 'var(--text-main)' }}>{formatFecha(compra.fecha_vencimiento)}</dd>
                                </div>
                            )}
                            {compra.dias_credito > 0 && (
                                <div className="flex justify-between text-xs">
                                    <dt style={{ color: 'var(--text-muted)' }}>Días crédito</dt>
                                    <dd style={{ color: 'var(--text-main)' }}>{compra.dias_credito}</dd>
                                </div>
                            )}
                            {compra.centro_costo && (
                                <div className="flex justify-between text-xs">
                                    <dt style={{ color: 'var(--text-muted)' }}>Centro de costo</dt>
                                    <dd style={{ color: 'var(--text-main)' }}>{compra.centro_costo.nombre}</dd>
                                </div>
                            )}
                        </dl>
                    </div>
                </div>

                {/* ── Detalles ── */}
                <div className="rounded-xl border overflow-hidden mb-6"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="px-4 py-3 border-b"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider"
                            style={{ color: 'var(--text-muted)' }}>Detalle de ítems</p>
                    </div>

                    {/* Encabezados */}
                    <div className="grid grid-cols-12 gap-2 px-4 py-2 border-b text-[10px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                        <span className="col-span-4">Descripción</span>
                        <span className="col-span-1 text-right">Cant.</span>
                        <span className="col-span-2 text-right">P. Unit.</span>
                        <span className="col-span-1 text-right">Desc.</span>
                        <span className="col-span-1 text-right">Subtotal</span>
                        <span className="col-span-1 text-center">IVA%</span>
                        <span className="col-span-2 text-right">Total</span>
                    </div>

                    {detalles.length === 0 && (
                        <p className="px-4 py-8 text-sm text-center" style={{ color: 'var(--text-muted)' }}>
                            Sin ítems registrados
                        </p>
                    )}

                    {detalles.map((d: CompraDetalle, i: number) => (
                        <div key={d.id ?? i}
                            className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-xs items-center"
                            style={{ borderColor: 'var(--border)' }}>
                            <div className="col-span-4 min-w-0">
                                <p className="truncate font-medium" style={{ color: 'var(--text-main)' }}>{d.descripcion}</p>
                                {d.cuenta && (
                                    <p className="text-[10px] truncate" style={{ color: 'var(--text-muted)' }}>
                                        {d.cuenta.codigo} – {d.cuenta.nombre}
                                    </p>
                                )}
                            </div>
                            <div className="col-span-1 text-right" style={{ color: 'var(--text-muted)' }}>
                                {n(d.cantidad).toFixed(2)}
                            </div>
                            <div className="col-span-2 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                {fmt(d.precio_unitario)}
                            </div>
                            <div className="col-span-1 text-right" style={{ color: 'var(--text-muted)' }}>
                                {n(d.descuento).toFixed(2)}%
                            </div>
                            <div className="col-span-1 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                {fmt(d.subtotal)}
                            </div>
                            <div className="col-span-1 text-center">
                                <span className="px-1.5 py-0.5 rounded text-[10px] bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    {n(d.porcentaje_iva)}%
                                </span>
                            </div>
                            <div className="col-span-2 text-right font-mono font-semibold" style={{ color: 'var(--primary)' }}>
                                {fmt(d.total)}
                            </div>
                        </div>
                    ))}

                    {/* Totales */}
                    <div className="px-4 py-4 border-t"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.03)' }}>
                        <div className="flex justify-end">
                            <dl className="space-y-1.5 w-52">
                                <div className="flex justify-between text-xs">
                                    <dt style={{ color: 'var(--text-muted)' }}>Subtotal 0%</dt>
                                    <dd className="font-mono" style={{ color: 'var(--text-main)' }}>{fmt(subtotal0)}</dd>
                                </div>
                                <div className="flex justify-between text-xs">
                                    <dt style={{ color: 'var(--text-muted)' }}>Subtotal gravado</dt>
                                    <dd className="font-mono" style={{ color: 'var(--text-main)' }}>{fmt(subtotalIva)}</dd>
                                </div>
                                <div className="flex justify-between text-xs">
                                    <dt style={{ color: 'var(--text-muted)' }}>IVA</dt>
                                    <dd className="font-mono" style={{ color: 'var(--text-main)' }}>{fmt(totalIva)}</dd>
                                </div>
                                <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <dt style={{ color: 'var(--text-main)' }}>TOTAL</dt>
                                    <dd className="font-mono" style={{ color: 'var(--primary)' }}>{fmt(total)}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                {/* ── Cuenta por pagar ── */}
                {compra.cuenta_pagar && (
                    <div className="rounded-xl border p-4 mb-6"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider mb-3"
                            style={{ color: 'var(--text-muted)' }}>Cuenta por pagar</p>
                        <div className="flex flex-wrap gap-6 text-xs">
                            <div>
                                <p style={{ color: 'var(--text-muted)' }}>Monto</p>
                                <p className="font-mono font-semibold mt-0.5" style={{ color: 'var(--text-main)' }}>
                                    {fmt(compra.cuenta_pagar.monto)}
                                </p>
                            </div>
                            <div>
                                <p style={{ color: 'var(--text-muted)' }}>Saldo</p>
                                <p className={cn('font-mono font-semibold mt-0.5',
                                    n(compra.cuenta_pagar.saldo) > 0
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-green-600 dark:text-green-400'
                                )}>
                                    {fmt(compra.cuenta_pagar.saldo)}
                                </p>
                            </div>
                            <div>
                                <p style={{ color: 'var(--text-muted)' }}>Vencimiento</p>
                                <p className="font-semibold mt-0.5" style={{ color: 'var(--text-main)' }}>
                                    {formatFecha(compra.cuenta_pagar.fecha_vencimiento)}
                                </p>
                            </div>
                            <div>
                                <p style={{ color: 'var(--text-muted)' }}>Estado</p>
                                <div className="mt-0.5">
                                    {compra.cuenta_pagar.estado === 'pagada'
                                        ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Pagada</span>
                                        : compra.cuenta_pagar.estado === 'parcial'
                                        ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-600">Parcial</span>
                                        : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Pendiente</span>
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* ── Asiento contable ── */}
                {compra.asiento && (
                    <div className="rounded-xl border p-4"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider mb-2"
                            style={{ color: 'var(--text-muted)' }}>Asiento contable</p>
                        <div className="flex items-center justify-between gap-4">
                            <div className="text-xs min-w-0">
                                <span className="font-mono font-semibold" style={{ color: 'var(--text-main)' }}>
                                    {compra.asiento.numero}
                                </span>
                                <span className="mx-2" style={{ color: 'var(--text-muted)' }}>·</span>
                                <span className="truncate" style={{ color: 'var(--text-muted)' }}>
                                    {compra.asiento.concepto}
                                </span>
                            </div>
                            <Link href={route('contabilidad.asientos.index')}
                                className="flex items-center gap-1 text-xs shrink-0 hover:opacity-80 transition-opacity"
                                style={{ color: 'var(--primary)' }}>
                                Ver asientos <ExternalLink size={12} />
                            </Link>
                        </div>
                    </div>
                )}
            </div>

            {showAnular && (
                <AnularModal compra={compra} onClose={() => setShowAnular(false)} />
            )}

            {/* ── Modal PDF ── */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={() => setModalPdf(false)}>
                    <div className="w-full max-w-4xl rounded-2xl overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)', height: '90vh' }}
                         onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm flex items-center gap-2"
                                style={{ color: 'var(--text-main)' }}>
                                <Printer size={16} style={{ color: '#ef4444' }} />
                                Compra {compra.num_documento}
                            </h3>
                            <div className="flex items-center gap-2">
                                <a href={urlPdf} download target="_blank"
                                   className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-all hover:opacity-90"
                                   style={{ background: '#ef4444' }}>
                                    <Download size={13} /> Descargar
                                </a>
                                <button onClick={() => setModalPdf(false)}
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>
                        <iframe src={urlPdf} className="flex-1 w-full border-0" title="PDF Compra" />
                    </div>
                </div>
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
