import { useState } from 'react'
import { Head, router, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import DesgloseHorasExtraModal from '@/Components/shared/DesgloseHorasExtraModal'
import type { Nomina, NominaDetalle, PageProps } from '@/types'
import { cn } from '@/lib/utils'
import {
    FileText, Download, CheckCircle, CreditCard,
    Pencil, X, Save, ChevronLeft, AlertTriangle, Eye,
} from 'lucide-react'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function fmt(n: number | string) {
    return new Intl.NumberFormat('es-EC', { minimumFractionDigits: 2 }).format(Number(n))
}

function badgeEstado(estado: Nomina['estado']) {
    const map = {
        borrador:  'bg-gray-100 text-gray-600 border-gray-300',
        procesado: 'bg-blue-50 text-blue-700 border-blue-200',
        pagado:    'bg-green-50 text-green-700 border-green-200',
    } as const
    const labels = { borrador: 'Borrador', procesado: 'Procesado', pagado: 'Pagado' }
    return (
        <span className={cn('inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border', map[estado])}>
            {labels[estado]}
        </span>
    )
}

// ─────────────────────────────────────────────────────────────────────────────
// Modal Edición Manual
// ─────────────────────────────────────────────────────────────────────────────

interface EditarModalProps {
    nominaId: number
    detalle: NominaDetalle
    onClose: () => void
}

type EditForm = {
    sueldo_base: string
    horas_extras_50: string
    horas_extras_100: string
    comisiones: string
    otros_ingresos: string
    aporte_personal_iess: string
    descuento_atrasos: string
    descuento_prestamos: string
    descuento_anticipos: string
    otros_egresos: string
    tipo_pago: string
    num_cuenta: string
    banco: string
}

function EditarModal({ nominaId, detalle, onClose }: EditarModalProps) {
    const col = detalle.colaborador
    const [form, setForm] = useState<EditForm>({
        sueldo_base:          String(detalle.sueldo_base),
        horas_extras_50:      String(detalle.horas_extras_50),
        horas_extras_100:     String(detalle.horas_extras_100),
        comisiones:           String(detalle.comisiones),
        otros_ingresos:       String(detalle.otros_ingresos),
        aporte_personal_iess: String(detalle.aporte_personal_iess),
        descuento_atrasos:    String(detalle.descuento_atrasos),
        descuento_prestamos:  String(detalle.descuento_prestamos),
        descuento_anticipos:  String(detalle.descuento_anticipos),
        otros_egresos:        String(detalle.otros_egresos),
        tipo_pago:            detalle.tipo_pago ?? '',
        num_cuenta:           detalle.num_cuenta ?? '',
        banco:                detalle.banco ?? '',
    })
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    function num(k: keyof EditForm) { return parseFloat(form[k] || '0') || 0 }

    const totalIngresos = num('sueldo_base') + num('horas_extras_50') + num('horas_extras_100') + num('comisiones') + num('otros_ingresos')
    const totalEgresos  = num('aporte_personal_iess') + num('descuento_atrasos') + num('descuento_prestamos') + num('descuento_anticipos') + num('otros_egresos')
    const netoPagar     = totalIngresos - totalEgresos

    function setField(k: keyof EditForm, v: string) {
        setForm(f => ({ ...f, [k]: v }))
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setLoading(true)
        setError('')
        router.put(
            route('rrhh.nomina.update', { id: nominaId, did: detalle.id }),
            form,
            {
                onSuccess: onClose,
                onError: (errors) => setError(Object.values(errors)[0] as string ?? 'Error al guardar.'),
                onFinish: () => setLoading(false),
            }
        )
    }

    const Campo = ({ label, k }: { label: string; k: keyof EditForm }) => (
        <div>
            <label className="input-label">{label}</label>
            <input
                type="number" step="0.01" min="0"
                className="input-field"
                value={form[k]}
                onChange={e => setField(k, e.target.value)}
            />
        </div>
    )

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div className="modal-card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div className="modal-header flex items-center justify-between">
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                        Editar — {col?.apellidos} {col?.nombres}
                    </h3>
                    <button onClick={onClose}><X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} /></button>
                </div>
                <form onSubmit={submit}>
                    <div className="modal-body space-y-4">
                        {error && (
                            <div className="px-3 py-2 rounded text-sm bg-red-50 text-red-700 border border-red-200">{error}</div>
                        )}

                        <p className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>Ingresos</p>
                        <div className="grid grid-cols-2 gap-3">
                            <Campo label="Sueldo Base" k="sueldo_base" />
                            <Campo label="H. Extras Supl. 50%" k="horas_extras_50" />
                            <Campo label="H. Extras Extr. 100%" k="horas_extras_100" />
                            <Campo label="Comisiones" k="comisiones" />
                            <Campo label="Otros Ingresos" k="otros_ingresos" />
                        </div>

                        <p className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>Egresos</p>
                        <div className="grid grid-cols-2 gap-3">
                            <Campo label="IESS Personal 9.45%" k="aporte_personal_iess" />
                            <Campo label="Desc. Atrasos" k="descuento_atrasos" />
                            <Campo label="Desc. Préstamos" k="descuento_prestamos" />
                            <Campo label="Desc. Anticipos" k="descuento_anticipos" />
                            <Campo label="Otros Egresos" k="otros_egresos" />
                        </div>

                        <p className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>Datos de pago</p>
                        <div className="grid grid-cols-3 gap-3">
                            <div>
                                <label className="input-label">Tipo Pago</label>
                                <select className="input-field" value={form.tipo_pago} onChange={e => setField('tipo_pago', e.target.value)}>
                                    <option value="">—</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="efectivo">Efectivo</option>
                                </select>
                            </div>
                            <div>
                                <label className="input-label">N° Cuenta</label>
                                <input className="input-field" value={form.num_cuenta} onChange={e => setField('num_cuenta', e.target.value)} />
                            </div>
                            <div>
                                <label className="input-label">Banco</label>
                                <input className="input-field" value={form.banco} onChange={e => setField('banco', e.target.value)} />
                            </div>
                        </div>

                        {/* Resumen en tiempo real */}
                        <div className="rounded-lg p-3 grid grid-cols-3 gap-3 text-center" style={{ background: 'var(--bg-main)', border: '1px solid var(--border)' }}>
                            <div>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Ingresos</p>
                                <p className="text-base font-bold font-mono" style={{ color: 'var(--text-main)' }}>$ {fmt(totalIngresos)}</p>
                            </div>
                            <div>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Egresos</p>
                                <p className="text-base font-bold font-mono" style={{ color: 'var(--text-muted)' }}>$ {fmt(totalEgresos)}</p>
                            </div>
                            <div>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Neto a Pagar</p>
                                <p className="text-base font-bold font-mono text-green-600">$ {fmt(netoPagar)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn-secondary" onClick={onClose}>Cancelar</button>
                        <button type="submit" className="btn-primary flex items-center gap-1.5" disabled={loading}>
                            <Save className="w-4 h-4" />
                            {loading ? 'Guardando…' : 'Guardar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─────────────────────────────────────────────────────────────────────────────
// Modal Registrar Pago
// ─────────────────────────────────────────────────────────────────────────────

interface PagarModalProps { nominaId: number; onClose: () => void }

function PagarModal({ nominaId, onClose }: PagarModalProps) {
    const hoy = new Date().toISOString().split('T')[0]
    const [form, setForm] = useState({
        fecha_pago:       hoy,
        tipo_comprobante: 'transferencia_masiva',
        num_comprobante:  '',
    })
    const [loading, setLoading] = useState(false)

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setLoading(true)
        router.post(route('rrhh.nomina.pagar', nominaId), form, {
            onFinish: () => { setLoading(false); onClose() },
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="modal-card w-full max-w-md">
                <div className="modal-header">
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Registrar Pago</h3>
                </div>
                <form onSubmit={submit}>
                    <div className="modal-body space-y-4">
                        <div>
                            <label className="input-label">Fecha de pago</label>
                            <input type="date" className="input-field" value={form.fecha_pago}
                                onChange={e => setForm(f => ({ ...f, fecha_pago: e.target.value }))} required />
                        </div>
                        <div>
                            <label className="input-label">Tipo de comprobante</label>
                            <select className="input-field" value={form.tipo_comprobante}
                                onChange={e => setForm(f => ({ ...f, tipo_comprobante: e.target.value }))}>
                                <option value="transferencia_masiva">Transferencia masiva</option>
                                <option value="individual">Comprobantes individuales</option>
                            </select>
                        </div>
                        <div>
                            <label className="input-label">N° Comprobante bancario</label>
                            <input type="text" className="input-field" placeholder="TRF-2026-001"
                                value={form.num_comprobante}
                                onChange={e => setForm(f => ({ ...f, num_comprobante: e.target.value }))} required />
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn-secondary" onClick={onClose}>Cancelar</button>
                        <button type="submit" className="btn-primary" disabled={loading}>
                            {loading ? 'Registrando…' : 'Confirmar Pago'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─────────────────────────────────────────────────────────────────────────────
// Página Show
// ─────────────────────────────────────────────────────────────────────────────

interface Props extends PageProps {
    nomina: Nomina
}

export default function NominaShow() {
    const { nomina, flash } = usePage<Props>().props

    const [editando, setEditando] = useState<NominaDetalle | null>(null)
    const [showPagar, setShowPagar] = useState(false)
    const [procesando, setProcesando] = useState(false)
    const [modalPdfUrl, setModalPdfUrl] = useState<string | null>(null)
    const [detalleHorasExtra, setDetalleHorasExtra] = useState<NominaDetalle | null>(null)

    const detalles = nomina.detalles ?? []

    function procesar() {
        if (!confirm('¿Procesar la nómina? Se generará el asiento contable automáticamente.')) return
        setProcesando(true)
        router.post(route('rrhh.nomina.procesar', nomina.id), {}, {
            onFinish: () => setProcesando(false),
        })
    }

    return (
        <AppLayout>
            <Head title={`Nómina — ${nomina.periodo_label}`} />

            {editando && (
                <EditarModal
                    nominaId={nomina.id}
                    detalle={editando}
                    onClose={() => setEditando(null)}
                />
            )}
            {detalleHorasExtra && (() => {
                const sueldoBase = detalleHorasExtra.colaborador?.sueldo_base ?? 0
                const valorHora  = sueldoBase / 240
                // Los subtotales ya vienen agregados (posiblemente de varios días
                // aprobados en el período); se recupera la cantidad de horas a partir
                // del subtotal guardado y el mismo VH usado al aprobar cada solicitud.
                const horas50  = valorHora > 0 ? Number(detalleHorasExtra.horas_extras_50)  / (valorHora * 1.5) : 0
                const horas100 = valorHora > 0 ? Number(detalleHorasExtra.horas_extras_100) / (valorHora * 2.0) : 0
                return (
                    <DesgloseHorasExtraModal
                        sueldoBase={sueldoBase}
                        horas50={horas50}
                        horas100={horas100}
                        nota="Total de horas extras aprobadas en el período de este rol de pagos."
                        onClose={() => setDetalleHorasExtra(null)}
                    />
                )
            })()}
            {showPagar && (
                <PagarModal nominaId={nomina.id} onClose={() => setShowPagar(false)} />
            )}

            <PageHeader
                title={`Nómina — ${nomina.periodo_label ?? ''}`}
                breadcrumbs={[
                    { label: 'RRHH' },
                    { label: 'Nómina', href: route('rrhh.nomina.index') },
                    { label: nomina.periodo_label ?? '' },
                ]}
            />

            <div className="px-6 py-4">
                {/* Flash */}
                {flash?.success && (
                    <div className="mb-4 px-4 py-2 rounded text-sm font-medium bg-green-50 text-green-700 border border-green-200">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 px-4 py-2 rounded text-sm font-medium bg-red-50 text-red-700 border border-red-200 flex items-center gap-2">
                        <AlertTriangle className="w-4 h-4 shrink-0" />
                        {flash.error}
                    </div>
                )}

                {/* Header de nómina */}
                <div className="rounded-lg border p-4 mb-5 flex flex-wrap items-center justify-between gap-4"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="flex flex-wrap items-center gap-4">
                        {badgeEstado(nomina.estado)}
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {nomina.periodo_tipo === 'quincenal' ? 'Quincenal' : 'Mensual'}
                            &nbsp;·&nbsp;{detalles.length} empleados
                        </span>
                        <div className="flex gap-6 text-sm">
                            <span>
                                <span style={{ color: 'var(--text-muted)' }}>Ingresos: </span>
                                <span className="font-semibold font-mono" style={{ color: 'var(--text-main)' }}>
                                    $ {fmt(nomina.total_ingresos)}
                                </span>
                            </span>
                            <span>
                                <span style={{ color: 'var(--text-muted)' }}>Egresos: </span>
                                <span className="font-semibold font-mono" style={{ color: 'var(--text-muted)' }}>
                                    $ {fmt(nomina.total_egresos)}
                                </span>
                            </span>
                            <span>
                                <span style={{ color: 'var(--text-muted)' }}>Neto: </span>
                                <span className="font-bold font-mono text-green-600">
                                    $ {fmt(nomina.total_neto)}
                                </span>
                            </span>
                        </div>
                    </div>

                    {/* Acciones principales */}
                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => router.visit(route('rrhh.nomina.index'))}
                            className="btn-secondary flex items-center gap-1.5"
                        >
                            <ChevronLeft className="w-4 h-4" />
                            Volver
                        </button>

                        {nomina.estado === 'borrador' && (
                            <button
                                className="btn-primary flex items-center gap-1.5"
                                onClick={procesar}
                                disabled={procesando}
                            >
                                <CheckCircle className="w-4 h-4" />
                                {procesando ? 'Procesando…' : 'Procesar Nómina'}
                            </button>
                        )}

                        {nomina.estado === 'procesado' && (
                            <button
                                className="btn-primary flex items-center gap-1.5"
                                onClick={() => setShowPagar(true)}
                            >
                                <CreditCard className="w-4 h-4" />
                                Registrar Pago
                            </button>
                        )}

                        {/* ZIP descarga masiva */}
                        <a
                            href={route('rrhh.nomina.pdf-masivo', nomina.id)}
                            className="btn-secondary flex items-center gap-1.5 text-sm no-underline"
                            target="_blank"
                        >
                            <Download className="w-4 h-4" />
                            Descargar todos los roles (ZIP)
                        </a>
                    </div>
                </div>

                {/* Tabla de detalles */}
                <div className="rounded-lg border overflow-x-auto" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <table className="w-full text-sm min-w-275">
                        <thead>
                            <tr style={{ background: 'var(--bg-main)', borderBottom: '1px solid var(--border)' }}>
                                {['Empleado', 'Sueldo', 'H.Ext', 'Otros Ing.', 'IESS Pers.', 'Desc. Atr.', 'Prést.', 'Total Ing.', 'Total Egr.', 'Neto', 'Tipo Pago', 'Cta. Banco', 'Acciones'].map(h => (
                                    <th key={h} className="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.length === 0 ? (
                                <tr>
                                    <td colSpan={13} className="py-10 text-center text-sm" style={{ color: 'var(--text-muted)' }}>
                                        Sin detalles.
                                    </td>
                                </tr>
                            ) : detalles.map((d, idx) => (
                                <tr
                                    key={d.id}
                                    className="border-t"
                                    style={{
                                        borderColor: 'var(--border)',
                                        background: idx % 2 === 0 ? 'var(--bg-card)' : 'var(--bg-main)',
                                    }}
                                >
                                    <td className="px-3 py-2.5">
                                        <div className="font-medium text-xs" style={{ color: 'var(--text-main)' }}>
                                            {d.colaborador?.apellidos} {d.colaborador?.nombres}
                                        </div>
                                        {d.modificado_manualmente && (
                                            <span className="text-[10px] px-1.5 py-0.5 rounded bg-yellow-50 text-yellow-700 border border-yellow-200 font-medium">
                                                Modificado manualmente
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-main)' }}>
                                        {fmt(d.sueldo_base)}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex items-center justify-end gap-1">
                                            {fmt(Number(d.horas_extras_50) + Number(d.horas_extras_100))}
                                            {(Number(d.horas_extras_50) + Number(d.horas_extras_100)) > 0 && (
                                                <button
                                                    onClick={() => setDetalleHorasExtra(d)}
                                                    className="p-0.5 rounded hover:bg-black/10 transition-colors"
                                                    title="Ver desglose 50%/100%"
                                                >
                                                    <Eye className="w-3 h-3" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                        {fmt(d.otros_ingresos)}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                        ({fmt(d.aporte_personal_iess)})
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                        {Number(d.descuento_atrasos) > 0 ? `(${fmt(d.descuento_atrasos)})` : '—'}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                        {(Number(d.descuento_prestamos) + Number(d.descuento_anticipos)) > 0
                                            ? `(${fmt(Number(d.descuento_prestamos) + Number(d.descuento_anticipos))})`
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right font-semibold" style={{ color: 'var(--text-main)' }}>
                                        {fmt(d.total_ingresos)}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                        ({fmt(d.total_egresos)})
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right font-bold text-green-600">
                                        {fmt(d.neto_pagar)}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs capitalize" style={{ color: 'var(--text-muted)' }}>
                                        {d.tipo_pago ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {d.num_cuenta ? `${d.banco ?? ''} ${d.num_cuenta}` : '—'}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center gap-1">
                                            {/* PDF individual — abre en modal */}
                                            <button
                                                onClick={() => setModalPdfUrl(route('rrhh.nomina.pdf-individual', { id: nomina.id, did: d.id }))}
                                                className="p-1.5 rounded hover:bg-blue-50 transition-colors"
                                                title="Ver rol de pago"
                                            >
                                                <FileText className="w-4 h-4 text-blue-600" />
                                            </button>
                                            {/* Editar manual — solo en borrador */}
                                            {nomina.estado === 'borrador' && (
                                                <button
                                                    onClick={() => setEditando(d)}
                                                    className="p-1.5 rounded hover:bg-amber-50 transition-colors"
                                                    title="Editar manualmente"
                                                >
                                                    <Pencil className="w-4 h-4 text-amber-600" />
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        {/* Totales */}
                        {detalles.length > 0 && (
                            <tfoot>
                                <tr style={{ background: 'var(--bg-main)', borderTop: '2px solid var(--border)' }}>
                                    <td className="px-3 py-2.5 text-xs font-bold uppercase" style={{ color: 'var(--text-main)' }}>
                                        TOTALES
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right font-bold" style={{ color: 'var(--text-main)' }}>
                                        {fmt(detalles.reduce((s, d) => s + Number(d.sueldo_base), 0))}
                                    </td>
                                    <td colSpan={2} />
                                    <td colSpan={3} />
                                    <td className="px-3 py-2.5 font-mono text-xs text-right font-bold" style={{ color: 'var(--text-main)' }}>
                                        {fmt(nomina.total_ingresos)}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right font-bold" style={{ color: 'var(--text-muted)' }}>
                                        ({fmt(nomina.total_egresos)})
                                    </td>
                                    <td className="px-3 py-2.5 font-mono text-xs text-right font-bold text-green-600">
                                        {fmt(nomina.total_neto)}
                                    </td>
                                    <td colSpan={3} />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
            {/* Modal PDF individual */}
            {modalPdfUrl && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    onClick={() => setModalPdfUrl(null)}
                >
                    <div
                        className="modal-card flex flex-col"
                        style={{ width: '85vw', maxWidth: '860px', height: '90vh' }}
                        onClick={e => e.stopPropagation()}
                    >
                        <div className="modal-header flex items-center justify-between">
                            <span className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                Rol de Pago Individual
                            </span>
                            <div className="flex items-center gap-2">
                                <a
                                    href={modalPdfUrl}
                                    download
                                    className="btn-secondary flex items-center gap-1.5 text-xs no-underline"
                                    style={{ color: 'inherit' }}
                                    onClick={e => e.stopPropagation()}
                                >
                                    <Download className="w-3.5 h-3.5" /> Descargar
                                </a>
                                <button
                                    onClick={() => setModalPdfUrl(null)}
                                    className="p-1 rounded hover:bg-red-50 text-red-500 transition-colors"
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <div className="flex-1 overflow-hidden">
                            <iframe
                                src={modalPdfUrl}
                                className="w-full h-full border-0"
                                title="Rol de pago"
                            />
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
