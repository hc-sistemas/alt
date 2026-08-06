import { useState, useEffect } from 'react'
import { router, usePage } from '@inertiajs/react'
import { ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Calendar, Lock, Plus, CheckCircle,
    AlertTriangle, XCircle, Unlock, ShieldCheck,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { EjercicioContable, PageProps } from '@/types'
import { notify, MESES, swalBase, injectSwalStyles } from '@/utils/contabilidad'
import { formatFecha } from '@/lib/utils'
import 'react-toastify/dist/ReactToastify.css'

interface Props extends PageProps {
    ejercicios:    EjercicioContable[]
    periodoActivo: EjercicioContable | null
}

export default function EjerciciosIndex() {
    const { ejercicios, periodoActivo, flash, auth } = usePage<Props>().props
    const { puede } = usePermiso('contabilidad')
    const esSuperAdmin = puede('editar')

    const [modalAbierto, setModalAbierto] = useState(false)
    const [modalCierreFiscal, setModalCierreFiscal] = useState(false)
    const [processing,   setProcessing]   = useState(false)
    const [form, setForm] = useState({
        anio:        new Date().getFullYear(),
        mes:         new Date().getMonth() + 1,
        descripcion: '',
    })

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warning(flash.warning)
    }, [flash?.success, flash?.error, flash?.warning])

    const yaExiste = ejercicios.some(e => e.anio === form.anio && e.mes === form.mes)

    const abrirPeriodo = () => {
        if (yaExiste) {
            notify.error(`Ya existe el período ${MESES[form.mes]} ${form.anio}`)
            return
        }
        setProcessing(true)
        router.post(route('contabilidad.ejercicios.store'), form, {
            onSuccess: () => {
                setModalAbierto(false)
                setForm({ anio: new Date().getFullYear(), mes: new Date().getMonth() + 1, descripcion: '' })
            },
            onFinish: () => setProcessing(false),
        })
    }

    const confirmarCierre = async (ejercicio: EjercicioContable) => {
        injectSwalStyles()
        const { value: result } = await Swal.fire({
            ...swalBase,
            title: `Cerrar ${ejercicio.periodo_label}`,
            html: `
                <div style="text-align:left">
                    <div style="background:#fef3c7;border:1px solid #fcd34d;
                                border-radius:10px;padding:14px;margin-bottom:16px">
                        <p style="font-weight:700;color:#92400e;margin:0 0 6px 0;
                                  font-size:0.9rem">
                            ⚠️ Acción con impacto permanente
                        </p>
                        <p style="color:#78350f;font-size:0.82rem;margin:0;
                                  line-height:1.5">
                            Una vez cerrado <strong>${ejercicio.periodo_label}</strong>,
                            nadie podrá crear ni anular asientos en ese mes.
                            Solo el Super Admin podrá reabrirlo.
                        </p>
                    </div>

                    <div style="margin-bottom:14px">
                        <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                      display:block;margin-bottom:6px">
                            Fecha de cierre <span style="color:#ef4444">*</span>
                        </label>
                        <input
                            type="date"
                            id="fecha-cierre"
                            value="${new Date().toISOString().split('T')[0]}"
                            max="${new Date().toISOString().split('T')[0]}"
                            style="width:100%;border:2px solid #e5e7eb;border-radius:8px;
                                   padding:8px 12px;font-size:0.875rem;
                                   box-sizing:border-box;font-family:inherit"
                        />
                        <p style="font-size:0.75rem;color:#9ca3af;margin:4px 0 0 0">
                            No puede ser una fecha futura
                        </p>
                    </div>

                    <div>
                        <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                      display:block;margin-bottom:6px">
                            Motivo del cierre <span style="color:#ef4444">*</span>
                        </label>
                        <textarea id="motivo-cierre"
                            style="width:100%;border:2px solid #e5e7eb;border-radius:8px;
                                   padding:10px;font-size:0.875rem;resize:vertical;
                                   min-height:80px;box-sizing:border-box;font-family:inherit"
                            placeholder="ej: Cierre mensual julio 2026,
conciliación bancaria completada..."
                        ></textarea>
                        <p style="font-size:0.75rem;color:#9ca3af;margin:4px 0 0 0">
                            Mínimo 10 caracteres
                        </p>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '🔒 Sí, cerrar período',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            preConfirm: () => {
                const fecha  = (document.getElementById('fecha-cierre') as HTMLInputElement)?.value
                const motivo = (document.getElementById('motivo-cierre') as HTMLTextAreaElement)?.value

                if (!fecha) {
                    Swal.showValidationMessage('La fecha de cierre es obligatoria')
                    return false
                }
                if (fecha > new Date().toISOString().split('T')[0]) {
                    Swal.showValidationMessage('La fecha no puede ser futura')
                    return false
                }
                if (!motivo || motivo.length < 10) {
                    Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres')
                    return false
                }
                return { fecha, motivo }
            },
        })

        if (result) {
            router.patch(
                route('contabilidad.ejercicios.cerrar', ejercicio.id),
                {
                    motivo:       result.motivo,
                    fecha_cierre: result.fecha,
                },
                {
                    onSuccess: () => notify.success(
                        `Período ${ejercicio.periodo_label} cerrado correctamente`
                    ),
                    onError: () => notify.error('Error al cerrar el período'),
                }
            )
        }
    }

    const aniosDisponibles = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() + 1 - i)

    return (
        <AppLayout title="Ejercicios Contables" suppressFlash>
            <PageHeader
                title="Ejercicios Contables"
                breadcrumbs={[{ label: 'Contabilidad' }, { label: 'Ejercicios Contables' }]}
                description={
                    periodoActivo ? (
                        <span className="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                            <CheckCircle size={12} className="shrink-0" />
                            Período activo: {periodoActivo.periodo_label}
                            {' '}· Abierto desde {periodoActivo.fecha_apertura ? formatFecha(periodoActivo.fecha_apertura) : '—'}
                            {' '}· {periodoActivo.total_asientos} asiento(s)
                        </span>
                    ) : (
                        <span className="flex items-center gap-1.5 text-red-600 dark:text-red-400">
                            <AlertTriangle size={12} className="shrink-0" />
                            Sin período contable activo — abre un período para registrar asientos.
                        </span>
                    )
                }
                actions={
                    <div className="flex items-center gap-2 flex-wrap shrink-0">
                        {puede('crear') && (
                            <button
                                onClick={() => setModalAbierto(true)}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-black whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: 'var(--primary)' }}
                            >
                                <Plus size={15} />
                                Abrir Período
                            </button>
                        )}
                        {esSuperAdmin && (
                            <button
                                onClick={() => setModalCierreFiscal(true)}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm whitespace-nowrap border transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                style={{ background: 'rgba(124,58,237,0.08)', borderColor: 'rgba(124,58,237,0.3)', color: '#7C3AED' }}
                            >
                                <ShieldCheck size={15} />
                                Cierre Fiscal Anual
                            </button>
                        )}
                    </div>
                }
            />

            <div className="p-6 space-y-5">
                {/* Tabla */}
                <div className="rounded-xl border overflow-hidden"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(245,158,11,0.05)' }}>
                                    {['Período','Descripción','Apertura','Cierre','Asientos','Estado','Acciones'].map(h => (
                                        <th key={h}
                                            className="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider"
                                            style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {ejercicios.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="text-center py-16"
                                            style={{ color: 'var(--text-muted)' }}>
                                            <Calendar size={40} className="mx-auto mb-3 opacity-20" />
                                            <p className="font-medium">No hay períodos contables</p>
                                            <p className="text-xs mt-1">Abre el primer período para comenzar.</p>
                                        </td>
                                    </tr>
                                ) : ejercicios.map(e => (
                                    <tr key={e.id}
                                        style={{
                                            borderBottom: '1px solid var(--border)',
                                            borderLeft: e.estado === 'abierto'
                                                ? '3px solid #10b981' : '3px solid transparent',
                                        }}>
                                        <td className="px-4 py-3 font-bold"
                                            style={{ color: 'var(--text-main)' }}>
                                            {e.periodo_label}
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                                            {e.descripcion ?? '—'}
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                                            {e.fecha_apertura ?? '—'}
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                                            {e.fecha_cierre ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <a href={route('contabilidad.asientos.index') + `?ejercicio_id=${e.id}`}
                                               className="font-semibold hover:underline"
                                               style={{ color: 'var(--primary)' }}>
                                                {e.total_asientos}
                                            </a>
                                        </td>
                                        <td className="px-4 py-3">
                                            {e.estado === 'abierto' ? (
                                                <span className="inline-flex items-center gap-1 px-2 py-1
                                                                 rounded-full text-xs font-semibold
                                                                 bg-green-100 text-green-800
                                                                 dark:bg-green-900/30 dark:text-green-300">
                                                    <Unlock size={11} /> Abierto
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center gap-1 px-2 py-1
                                                                 rounded-full text-xs font-semibold
                                                                 bg-red-100 text-red-800
                                                                 dark:bg-red-900/30 dark:text-red-300">
                                                    <Lock size={11} /> Cerrado
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {e.estado === 'abierto' ? (
                                                puede('anular') && (
                                                    <button
                                                        onClick={() => confirmarCierre(e)}
                                                        title="Cerrar período"
                                                        className="p-1.5 rounded-lg transition-colors
                                                                   hover:bg-red-100 dark:hover:bg-red-900/30">
                                                        <Lock size={15} className="text-red-500" />
                                                    </button>
                                                )
                                            ) : (
                                                <Lock size={15} className="text-gray-300 cursor-not-allowed"
                                                      title="Período cerrado permanentemente" />
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal abrir período */}
            {modalAbierto && (
                <div className="modal-overlay" onClick={() => setModalAbierto(false)}>
                    <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>

                        <div className="modal-header">
                            <h2>
                                <Plus size={20} style={{ color: 'var(--primary)' }} />
                                Abrir Período Contable
                            </h2>
                            <button className="modal-close" onClick={() => setModalAbierto(false)}>×</button>
                        </div>

                        <div className="modal-body" style={{ gap: '1rem' }}>
                            <div>
                                <Label>Año <span className="text-red-500">*</span></Label>
                                <select
                                    value={form.anio}
                                    onChange={e => setForm(f => ({ ...f, anio: parseInt(e.target.value) }))}
                                    className="input-field select-field mt-1"
                                >
                                    {aniosDisponibles.map(a => (
                                        <option key={a} value={a}>{a}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label>Mes <span className="text-red-500">*</span></Label>
                                <select
                                    value={form.mes}
                                    onChange={e => setForm(f => ({ ...f, mes: parseInt(e.target.value) }))}
                                    className="input-field select-field mt-1"
                                >
                                    {Object.entries(MESES).map(([num, nombre]) => (
                                        <option key={num} value={num}>{nombre}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label>
                                    Descripción{' '}
                                    <span className="font-normal" style={{ color: 'var(--text-muted)' }}>
                                        (opcional)
                                    </span>
                                </Label>
                                <Input
                                    className="mt-1"
                                    value={form.descripcion}
                                    onChange={e => setForm(f => ({ ...f, descripcion: e.target.value }))}
                                    placeholder={`ej: ${MESES[form.mes]} ${form.anio}`}
                                    maxLength={100}
                                />
                            </div>

                            {yaExiste && (
                                <div className="flex items-center gap-2 p-3 rounded-lg
                                                bg-red-50 dark:bg-red-900/20 border border-red-200">
                                    <XCircle size={16} className="text-red-500 shrink-0" />
                                    <p className="text-sm text-red-700 dark:text-red-400">
                                        El período {MESES[form.mes]} {form.anio} ya existe.
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="modal-footer">
                            <Button variant="outline" onClick={() => setModalAbierto(false)} className="flex-1">
                                Cancelar
                            </Button>
                            <Button
                                onClick={abrirPeriodo}
                                disabled={yaExiste || processing}
                                loading={processing}
                                className="flex-1"
                            >
                                Abrir Período
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {modalCierreFiscal && (
                <CierreFiscalModal onClose={() => setModalCierreFiscal(false)} />
            )}

            <ToastContainer position="top-right" autoClose={3500}
                hideProgressBar={false} newestOnTop closeOnClick
                pauseOnHover draggable theme="colored" style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px',
                              fontWeight: '500', boxShadow: '0 8px 32px rgba(0,0,0,0.18)' }}
            />
        </AppLayout>
    )
}

// ─── Modal Cierre Fiscal Anual ────────────────────────────────────────────────

function CierreFiscalModal({ onClose }: { onClose: () => void }) {
    const [anio, setAnio] = useState(new Date().getFullYear() - 1)
    const [motivo, setMotivo] = useState('')
    const [processing, setProcessing] = useState(false)
    const anios = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i)

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (motivo.trim().length < 10) return
        setProcessing(true)
        router.post(route('contabilidad.ejercicios.cierre-fiscal-anual'), { anio, motivo }, {
            onSuccess: () => onClose(),
            onError: () => {},
            onFinish: () => setProcessing(false),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2 className="flex items-center gap-2">
                        <ShieldCheck className="w-5 h-5" style={{ color: '#7C3AED' }} />
                        Cierre Fiscal Anual
                    </h2>
                    <button className="modal-close" onClick={onClose}><XCircle className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                    <div className="modal-body space-y-4">
                        {/* Aviso */}
                        <div className="rounded-lg p-3 text-xs"
                            style={{ background: 'rgba(124,58,237,0.07)', border: '1px solid rgba(124,58,237,0.2)', color: 'var(--text-muted)' }}>
                            <p className="font-semibold mb-1" style={{ color: '#7C3AED' }}>⚠️ Acción permanente e irreversible</p>
                            <p>El sistema ejecutará los 6 pasos a continuación de forma automática y atómica. Cualquier error revierte todo.</p>
                        </div>

                        {/* 6 pasos */}
                        <div className="rounded-lg overflow-hidden" style={{ border: '1px solid var(--border)' }}>
                            {[
                                { n: 1, label: 'Verificar períodos cerrados',    desc: 'Los 12 meses del año deben estar cerrados antes de continuar.' },
                                { n: 2, label: 'Calcular saldos clases 4 y 5',   desc: 'Suma debe/haber de cada cuenta de ingreso y gasto activa del año.' },
                                { n: 3, label: 'Crear asiento de cierre',         desc: 'Encera cuentas: ingresos con DEBE, gastos con HABER; saldo a Resultado del Ejercicio.' },
                                { n: 4, label: 'Determinar utilidad / pérdida',   desc: 'Resultado = Total ingresos − Total gastos. Puede ser positivo (utilidad) o negativo (pérdida).' },
                                { n: 5, label: 'Crear asiento de arrastre',       desc: 'Transfiere el resultado a Ganancias/Pérdidas Acumuladas (clase 3).' },
                                { n: 6, label: 'Registrar en auditoría',          desc: 'Guarda el evento en log_cambios_criticos con usuario, IP y montos.' },
                            ].map(({ n, label, desc }) => (
                                <div key={n} className="flex items-start gap-3 px-3 py-2.5"
                                    style={{ borderBottom: n < 6 ? '1px solid var(--border)' : 'none' }}>
                                    <span className="flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold shrink-0 mt-0.5"
                                        style={{ background: '#7C3AED', color: '#fff' }}>
                                        {n}
                                    </span>
                                    <div>
                                        <p className="text-xs font-semibold" style={{ color: 'var(--text-main)' }}>{label}</p>
                                        <p className="text-[11px] mt-0.5" style={{ color: 'var(--text-muted)' }}>{desc}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-1.5">
                            <label className="input-label">Año fiscal <span className="text-red-400">*</span></label>
                            <select value={anio} onChange={e => setAnio(Number(e.target.value))} className="input-field select-field">
                                {anios.map(a => <option key={a} value={a}>{a}</option>)}
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <label className="input-label">Motivo del cierre <span className="text-red-400">*</span></label>
                            <textarea value={motivo} onChange={e => setMotivo(e.target.value)}
                                rows={3} className="input-field textarea-field"
                                placeholder="ej: Cierre ejercicio fiscal 2025, declaración impuesto a la renta presentada..."
                                minLength={10} />
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Mínimo 10 caracteres</p>
                        </div>
                    </div>
                    <div className="modal-footer" style={{ justifyContent: 'space-between' }}>
                        <button type="submit" disabled={motivo.trim().length < 10 || processing}
                            className="btn-primary flex items-center gap-2"
                            style={{ background: '#7C3AED', color: '#fff', opacity: (motivo.trim().length < 10 || processing) ? 0.6 : 1 }}>
                            <ShieldCheck size={15} />
                            {processing ? 'Procesando...' : `Ejecutar Cierre ${anio}`}
                        </button>
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    )
}
