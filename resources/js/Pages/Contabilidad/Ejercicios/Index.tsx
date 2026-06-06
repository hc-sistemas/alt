import { useState, useEffect } from 'react'
import { router, usePage } from '@inertiajs/react'
import { ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Calendar, Lock, Unlock, Plus, CheckCircle,
    AlertTriangle, XCircle,
} from 'lucide-react'
import type { EjercicioContable, PageProps } from '@/types'
import { notify, MESES, swalBase, injectSwalStyles } from '@/utils/contabilidad'
import 'react-toastify/dist/ReactToastify.css'

interface Props extends PageProps {
    ejercicios:    EjercicioContable[]
    periodoActivo: EjercicioContable | null
}

export default function EjerciciosIndex() {
    const { ejercicios, periodoActivo, flash, auth } = usePage<Props>().props
    const esSuperAdmin = auth.user?.perfil === 'super_admin'

    const [modalAbierto, setModalAbierto] = useState(false)
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
        const { value: motivo } = await Swal.fire({
            ...swalBase,
            title: `Cerrar ${ejercicio.periodo_label}`,
            html: `
                <div style="text-align:left">
                    <div style="background:#fef3c7;border:1px solid #fcd34d;
                                border-radius:10px;padding:14px;margin-bottom:16px">
                        <p style="font-weight:700;color:#92400e;margin:0 0 6px 0;font-size:0.9rem">
                            ⚠️ Acción con impacto permanente
                        </p>
                        <p style="color:#78350f;font-size:0.82rem;margin:0;line-height:1.5">
                            Una vez cerrado <strong>${ejercicio.periodo_label}</strong>,
                            nadie podrá crear ni anular asientos en ese período.
                            Solo el Super Admin podrá reabrirlo.
                        </p>
                    </div>
                    <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                  display:block;margin-bottom:6px">
                        Motivo del cierre <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="motivo-cierre"
                        style="width:100%;border:2px solid #e5e7eb;border-radius:8px;
                               padding:10px;font-size:0.875rem;resize:vertical;
                               min-height:80px;box-sizing:border-box;font-family:inherit"
                        placeholder="ej: Cierre mensual, conciliación bancaria completada...">
                    </textarea>
                    <p style="font-size:0.75rem;color:#9ca3af;margin:4px 0 0 0">
                        Mínimo 10 caracteres
                    </p>
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
                const m = (document.getElementById('motivo-cierre') as HTMLTextAreaElement)?.value
                if (!m || m.length < 10) {
                    Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres')
                    return false
                }
                return m
            },
        })

        if (motivo) {
            router.patch(
                route('contabilidad.ejercicios.cerrar', ejercicio.id),
                { motivo },
            )
        }
    }

    const confirmarReapertura = async (ejercicio: EjercicioContable) => {
        const result = await Swal.fire({
            ...swalBase,
            title: 'Reabrir período',
            html: `
                <div style="text-align:center;padding:8px 0">
                    <div style="font-size:3rem;margin-bottom:12px">🔓</div>
                    <p style="color:#374151;font-size:0.95rem;margin:0 0 12px 0">
                        ¿Reabrir <strong>${ejercicio.periodo_label}</strong>?
                    </p>
                    <div style="background:#fef3c7;border-radius:8px;padding:10px">
                        <p style="color:#92400e;font-size:0.82rem;margin:0">
                            Recuerda cerrarlo nuevamente cuando termines los ajustes.
                        </p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '🔓 Sí, reabrir',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
        })

        if (result.isConfirmed) {
            router.patch(route('contabilidad.ejercicios.reabrir', ejercicio.id))
        }
    }

    const aniosDisponibles = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() + 1 - i)

    return (
        <AppLayout title="Ejercicios Contables" suppressFlash>
            <div className="p-6 space-y-5">
                <div className="mb-6">
                    {/* Fila 1 — Título */}
                    <div className="flex items-center gap-3 mb-4">
                        <div className="p-2 rounded-xl"
                             style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                            <Calendar size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold"
                                style={{ color: 'var(--text-main)' }}>
                                Ejercicios Contables
                            </h1>
                            <p className="text-sm"
                               style={{ color: 'var(--text-muted)' }}>
                                Control de períodos contables mensuales
                            </p>
                        </div>
                    </div>

                    {/* Fila 2 — Botones debajo */}
                    <div className="flex items-center gap-2 flex-wrap">
                        <button
                            onClick={() => setModalAbierto(true)}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                            style={{ background: 'var(--primary)' }}
                        >
                            <Plus size={15} />
                            Abrir Nuevo Período
                        </button>
                    </div>
                </div>
                {/* Banner período activo */}
                {periodoActivo ? (
                    <div className="flex items-center gap-3 p-4 rounded-xl border"
                         style={{
                             background: 'color-mix(in srgb, #10b981 8%, var(--bg-card))',
                             borderColor: '#10b981',
                         }}>
                        <CheckCircle size={18} className="text-green-500 shrink-0" />
                        <div>
                            <p className="font-semibold text-sm text-green-700 dark:text-green-400">
                                Período activo: {periodoActivo.periodo_label}
                            </p>
                            <p className="text-xs text-green-600 dark:text-green-500 mt-0.5">
                                Abierto desde {periodoActivo.fecha_apertura} ·
                                {' '}{periodoActivo.total_asientos} asiento(s) registrado(s)
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-3 p-4 rounded-xl border"
                         style={{
                             background: 'color-mix(in srgb, #ef4444 8%, var(--bg-card))',
                             borderColor: '#ef4444',
                         }}>
                        <AlertTriangle size={18} className="text-red-500 shrink-0" />
                        <div>
                            <p className="font-semibold text-sm text-red-700 dark:text-red-400">
                                Sin período contable activo
                            </p>
                            <p className="text-xs text-red-600 dark:text-red-500 mt-0.5">
                                Abre un período para poder registrar asientos.
                            </p>
                        </div>
                    </div>
                )}

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
                                                <button
                                                    onClick={() => confirmarCierre(e)}
                                                    title="Cerrar período"
                                                    className="p-1.5 rounded-lg transition-colors
                                                               hover:bg-red-100 dark:hover:bg-red-900/30">
                                                    <Lock size={15} className="text-red-500" />
                                                </button>
                                            ) : esSuperAdmin ? (
                                                <button
                                                    onClick={() => confirmarReapertura(e)}
                                                    title="Reabrir período"
                                                    className="p-1.5 rounded-lg transition-colors
                                                               hover:bg-amber-100 dark:hover:bg-amber-900/30">
                                                    <Unlock size={15} className="text-amber-500" />
                                                </button>
                                            ) : (
                                                <Lock size={15} className="text-gray-300 cursor-not-allowed" />
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
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.55)' }}
                     onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-md rounded-2xl p-6 shadow-2xl"
                         style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                         onClick={e => e.stopPropagation()}>

                        <div className="flex items-center justify-between mb-5">
                            <h2 className="text-lg font-bold flex items-center gap-2"
                                style={{ color: 'var(--text-main)' }}>
                                <Plus size={20} style={{ color: 'var(--primary)' }} />
                                Abrir Período Contable
                            </h2>
                            <button onClick={() => setModalAbierto(false)}
                                    className="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <Label>Año <span className="text-red-500">*</span></Label>
                                <select
                                    value={form.anio}
                                    onChange={e => setForm(f => ({ ...f, anio: parseInt(e.target.value) }))}
                                    className="mt-1 w-full rounded-lg px-3 py-2 text-sm border
                                               focus:outline-none focus:ring-1 focus:ring-amber-500
                                               dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)',
                                             color: 'var(--text-main)' }}
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
                                    className="mt-1 w-full rounded-lg px-3 py-2 text-sm border
                                               focus:outline-none focus:ring-1 focus:ring-amber-500
                                               dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)',
                                             color: 'var(--text-main)' }}
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

                        <div className="flex gap-3 mt-6">
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

            <ToastContainer position="top-right" autoClose={3500}
                hideProgressBar={false} newestOnTop closeOnClick
                pauseOnHover draggable theme="colored" style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px',
                              fontWeight: '500', boxShadow: '0 8px 32px rgba(0,0,0,0.18)' }}
            />
        </AppLayout>
    )
}
