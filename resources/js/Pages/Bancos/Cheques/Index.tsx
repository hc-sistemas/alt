import { useState, useEffect, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { cn } from '@/lib/utils'
import {
    Plus, X, CheckCircle, XCircle,
    CreditCard
} from 'lucide-react'
import type { BancoCaja, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Cheque {
    id: number
    numero: string
    banco_nombre: string | null
    banco_caja_id: number
    banco: string | null
    cuenta: string | null
    monto: number
    fecha_emision: string | null
    fecha_cobro: string | null
    beneficiario: string
    estado: 'emitido' | 'cobrado' | 'protestado' | 'anulado'
    observacion: string | null
    movimiento_id: number | null
}

interface Props extends PageProps {
    cheques: Cheque[]
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'num_cuenta'>[]
    filtros: { estado?: string; banco_caja_id?: string; buscar?: string }
}

// ─── Notify / SweetAlert ──────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    success: (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warning: (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error:   (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) => '$' + Number(n ?? 0).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── Badge estado ─────────────────────────────────────────────────────────────

function EstadoBadge({ estado }: { estado: Cheque['estado'] }) {
    const map = {
        emitido:    { label: 'Emitido',    cls: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' },
        cobrado:    { label: 'Cobrado',    cls: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' },
        protestado: { label: 'Protestado', cls: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
        anulado:    { label: 'Anulado',    cls: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
    }
    const { label, cls } = map[estado] ?? map.anulado
    return (
        <span className={cn('px-2 py-0.5 rounded-full text-[11px] font-semibold', cls)}>
            {label}
        </span>
    )
}

// ─── Modal nuevo cheque ───────────────────────────────────────────────────────

function NuevoModal({ bancos, onClose }: {
    bancos: Props['bancos']
    onClose: () => void
}) {
    const { data, setData, post, processing, errors } = useForm({
        banco_caja_id: '', numero: '', beneficiario: '', monto: '',
        fecha_emision: new Date().toISOString().split('T')[0],
        banco: '', cuenta: '', fecha_cobro: '', observacion: '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('bancos.cheques.store'), {
            onSuccess: () => { onClose(); notify.success('Cheque registrado') },
            onError: () => notify.error('Revisa los campos'),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-xl" onClick={e => e.stopPropagation()}>

                {/* Header */}
                <div className="modal-header">
                    <h2>
                        <CreditCard className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        Nuevo Cheque
                    </h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>

                {/* Body */}
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <div className="grid grid-cols-2 gap-4">
                        {/* Banco */}
                        <div className="col-span-2">
                            <label className="input-label">
                                Banco <span className="text-red-500">*</span>
                            </label>
                            <select value={data.banco_caja_id}
                                onChange={e => setData('banco_caja_id', e.target.value)}
                                className="input-field select-field mt-1">
                                <option value="">— Seleccionar banco —</option>
                                {bancos.map(b => (
                                    <option key={b.id} value={b.id}>
                                        {b.nombre}{b.num_cuenta ? ` (${b.num_cuenta})` : ''}
                                    </option>
                                ))}
                            </select>
                            {errors.banco_caja_id && <p className="text-xs text-red-500 mt-1">{errors.banco_caja_id}</p>}
                        </div>

                        {/* N° Cheque */}
                        <div>
                            <label className="input-label">
                                N° Cheque <span className="text-red-500">*</span>
                            </label>
                            <input type="text" value={data.numero}
                                onChange={e => setData('numero', e.target.value)}
                                placeholder="Ej: 001234"
                                className="input-field mt-1" />
                            {errors.numero && <p className="text-xs text-red-500 mt-1">{errors.numero}</p>}
                        </div>

                        {/* Monto */}
                        <div>
                            <label className="input-label">
                                Monto <span className="text-red-500">*</span>
                            </label>
                            <input type="number" step="0.01" min="0.01" value={data.monto}
                                onChange={e => setData('monto', e.target.value)}
                                placeholder="0.00"
                                className="input-field mt-1 text-right tabular-nums" />
                            {errors.monto && <p className="text-xs text-red-500 mt-1">{errors.monto}</p>}
                        </div>

                        {/* Beneficiario */}
                        <div className="col-span-2">
                            <label className="input-label">
                                Beneficiario <span className="text-red-500">*</span>
                            </label>
                            <input type="text" value={data.beneficiario}
                                onChange={e => setData('beneficiario', e.target.value)}
                                placeholder="Nombre del beneficiario"
                                className="input-field mt-1" />
                            {errors.beneficiario && <p className="text-xs text-red-500 mt-1">{errors.beneficiario}</p>}
                        </div>

                        {/* Fecha emisión */}
                        <div>
                            <label className="input-label">
                                Fecha emisión <span className="text-red-500">*</span>
                            </label>
                            <input type="date" value={data.fecha_emision}
                                onChange={e => setData('fecha_emision', e.target.value)}
                                className="input-field mt-1" />
                        </div>

                        {/* Fecha cobro */}
                        <div>
                            <label className="input-label">Fecha cobro</label>
                            <input type="date" value={data.fecha_cobro}
                                onChange={e => setData('fecha_cobro', e.target.value)}
                                className="input-field mt-1" />
                        </div>

                        {/* Banco emisor */}
                        <div>
                            <label className="input-label">Banco emisor</label>
                            <input type="text" value={data.banco}
                                onChange={e => setData('banco', e.target.value)}
                                placeholder="Ej: Banco Pichincha"
                                className="input-field mt-1" />
                        </div>

                        {/* N° Cuenta */}
                        <div>
                            <label className="input-label">N° Cuenta</label>
                            <input type="text" value={data.cuenta}
                                onChange={e => setData('cuenta', e.target.value)}
                                placeholder="Ej: 2201234567"
                                className="input-field mt-1" />
                        </div>

                        {/* Observación */}
                        <div className="col-span-2">
                            <label className="input-label">Observación</label>
                            <textarea value={data.observacion}
                                onChange={e => setData('observacion', e.target.value)}
                                placeholder="Opcional..."
                                rows={2}
                                className="input-field textarea-field mt-1" />
                        </div>
                    </div>

                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing} className="btn-primary">
                        <Plus className="w-4 h-4" /> Registrar Cheque
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Cambio de Estado ───────────────────────────────────────────────────

function CambioEstadoModal({ cheque, estadoNuevo, onClose }: {
    cheque: Cheque
    estadoNuevo: 'cobrado' | 'protestado'
    onClose: () => void
}) {
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0])
    const [obs, setObs] = useState('')
    const [processing, setProcessing] = useState(false)

    function confirmarCambioEstado() {
        if (estadoNuevo === 'protestado' && !obs.trim()) {
            notify.error('El motivo de protesta es obligatorio')
            return
        }
        setProcessing(true)
        router.patch(route('bancos.cheques.estado', cheque.id), {
            estado: estadoNuevo,
            ...(estadoNuevo === 'cobrado' ? { fecha_cobro: fecha } : {}),
            observacion: obs,
        }, {
            onSuccess: () => {
                estadoNuevo === 'cobrado'
                    ? notify.success(`Cheque N° ${cheque.numero} cobrado`)
                    : notify.warning(`Cheque N° ${cheque.numero} protestado`)
                onClose()
            },
            onError: () => { notify.error('Error al actualizar'); setProcessing(false) },
            onFinish: () => setProcessing(false),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>

                <div className="modal-header">
                    <h2 className="flex items-center gap-2">
                        {estadoNuevo === 'cobrado'
                            ? <><CheckCircle className="w-5 h-5 text-green-500" /> Confirmar cobro</>
                            : <><XCircle className="w-5 h-5 text-red-500" /> Marcar protestado</>
                        }
                    </h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>

                {/* Resumen cheque */}
                <div className="mx-6 mt-4 rounded-lg p-3 space-y-1.5"
                    style={{
                        background: estadoNuevo === 'cobrado' ? 'rgba(16,185,129,0.08)' : 'rgba(239,68,68,0.08)',
                        border: `1px solid ${estadoNuevo === 'cobrado' ? 'rgba(16,185,129,0.25)' : 'rgba(239,68,68,0.25)'}`,
                    }}>
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--text-muted)' }}>Beneficiario</span>
                        <span className="font-semibold" style={{ color: 'var(--text-main)' }}>{cheque.beneficiario}</span>
                    </div>
                    <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                        style={{
                            borderColor: estadoNuevo === 'cobrado' ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)',
                            color: estadoNuevo === 'cobrado' ? '#059669' : '#dc2626',
                        }}>
                        <span>Monto</span>
                        <span>{fmt(cheque.monto)}</span>
                    </div>
                </div>

                <div className="modal-body">
                    {estadoNuevo === 'cobrado' && (
                        <div className="space-y-1.5">
                            <label className="input-label">Fecha de cobro <span className="text-red-400">*</span></label>
                            <input type="date" value={fecha} onChange={e => setFecha(e.target.value)}
                                className="input-field" />
                        </div>
                    )}
                    <div className="space-y-1.5">
                        <label className="input-label">
                            {estadoNuevo === 'cobrado' ? 'Observación' : 'Motivo de protesta'}
                            {estadoNuevo === 'protestado' && <span className="text-red-400"> *</span>}
                        </label>
                        <textarea value={obs} onChange={e => setObs(e.target.value)}
                            rows={estadoNuevo === 'protestado' ? 3 : 2}
                            className="input-field textarea-field"
                            placeholder={estadoNuevo === 'cobrado' ? 'Opcional...' : 'Describe el motivo del protesto...'} />
                    </div>
                </div>

                <div className="modal-footer" style={{ justifyContent: 'space-between' }}>
                    <button onClick={confirmarCambioEstado} disabled={processing}
                        className="btn-primary flex items-center gap-2"
                        style={{
                            background: estadoNuevo === 'cobrado' ? '#059669' : '#dc2626',
                            boxShadow: 'none',
                        }}>
                        {estadoNuevo === 'cobrado'
                            ? <><CheckCircle size={15} /> Confirmar cobro</>
                            : <><XCircle size={15} /> Marcar protestado</>
                        }
                    </button>
                    <button onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function ChequesIndex() {
    const { cheques, bancos, filtros, flash } = usePage<Props>().props

    const [showModal, setShowModal] = useState(false)
    const [estadoModal, setEstadoModal] = useState<{ cheque: Cheque; estado: 'cobrado' | 'protestado' } | null>(null)
    const [buscar, setBuscar] = useState(filtros.buscar ?? '')
    const [estado, setEstado] = useState(filtros.estado ?? '')
    const [bancoId, setBancoId] = useState(filtros.banco_caja_id ?? '')

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.warning) notify.warning(flash.warning as string)
        if (flash?.error)   notify.error(flash.error as string)
    }, [flash])

    const filtrados = useMemo(() => {
        let list = [...cheques]
        if (buscar.trim()) {
            const q = buscar.toLowerCase()
            list = list.filter(c =>
                c.numero.toLowerCase().includes(q) ||
                c.beneficiario.toLowerCase().includes(q) ||
                (c.banco_nombre ?? '').toLowerCase().includes(q)
            )
        }
        if (estado) list = list.filter(c => c.estado === estado)
        if (bancoId) list = list.filter(c => String(c.banco_caja_id) === bancoId)
        return list
    }, [cheques, buscar, estado, bancoId])

    function abrirCambioEstado(cheque: Cheque, nuevoEstado: 'cobrado' | 'protestado') {
        setEstadoModal({ cheque, estado: nuevoEstado })
    }

    return (
        <AppLayout title="Cheques" suppressFlash>
            <Head title="Cheques" />

            <div className="p-4 md:p-6 space-y-5"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* Header */}
                <div className="flex items-center gap-3">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <CreditCard size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Cheques
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {filtrados.length} de {cheques.length} cheques
                        </p>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="flex items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2 items-center">
                        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2 whitespace-nowrap">
                            <Plus className="w-4 h-4" /> Nuevo Cheque
                        </button>
                        <div className="relative">
                            <input type="text" placeholder="Buscar N°, beneficiario..."
                                value={buscar} onChange={e => setBuscar(e.target.value)}
                                className="input-field"
                                style={{ width: '220px', paddingLeft: '0.875rem' }} />
                        </div>
                        <select value={estado} onChange={e => setEstado(e.target.value)}
                            className="input-field select-field"
                            style={{ width: 'auto' }}>
                            <option value="">Todos los estados</option>
                            <option value="emitido">Emitido</option>
                            <option value="cobrado">Cobrado</option>
                            <option value="protestado">Protestado</option>
                            <option value="anulado">Anulado</option>
                        </select>
                        <select value={bancoId} onChange={e => setBancoId(e.target.value)}
                            className="input-field select-field"
                            style={{ width: 'auto' }}>
                            <option value="">Todos los bancos</option>
                            {bancos.map(b => (
                                <option key={b.id} value={b.id}>{b.nombre}</option>
                            ))}
                        </select>
                        {(buscar || estado || bancoId) && (
                            <button onClick={() => { setBuscar(''); setEstado(''); setBancoId('') }}
                                className="btn-secondary flex items-center gap-1 whitespace-nowrap">
                                <X className="w-3 h-3" /> Limpiar
                            </button>
                        )}
                    </div>
                </div>

                {/* Tabla */}
                <div className="rounded-2xl border overflow-hidden"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    {/* Encabezado tabla */}
                    <div className="grid gap-2 px-4 py-3 border-b text-[11px] font-semibold uppercase tracking-wider"
                         style={{
                             gridTemplateColumns: '1fr 2fr 3fr 1fr 1fr 1fr 1fr auto',
                             borderColor: 'var(--border)',
                             background: 'rgba(245,158,11,0.05)',
                             color: 'var(--text-muted)',
                         }}>
                        <span>N° Cheque</span>
                        <span>Banco</span>
                        <span>Beneficiario</span>
                        <span className="text-right">Monto</span>
                        <span>Emisión</span>
                        <span>Cobro</span>
                        <span>Estado</span>
                        <span>Acciones</span>
                    </div>

                    {filtrados.length === 0 ? (
                        <div className="py-16 text-center">
                            <CreditCard className="w-10 h-10 mx-auto mb-3 opacity-20"
                                        style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay cheques que coincidan con los filtros
                            </p>
                        </div>
                    ) : (
                        filtrados.map((cheque, i) => (
                            <div key={cheque.id}
                                 className="grid gap-2 px-4 py-3 border-b items-center text-sm transition-colors hover:opacity-90"
                                 style={{
                                     gridTemplateColumns: '1fr 2fr 3fr 1fr 1fr 1fr 1fr auto',
                                     borderColor: 'var(--border)',
                                     background: i % 2 === 0 ? 'transparent'
                                         : 'color-mix(in srgb, var(--bg-main) 30%, transparent)',
                                 }}>
                                <div>
                                    <p className="font-mono font-semibold text-xs"
                                       style={{ color: 'var(--text-main)' }}>
                                        {cheque.numero}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                        {cheque.banco_nombre ?? '—'}
                                    </p>
                                    {cheque.banco && (
                                        <p className="text-[11px]" style={{ color: 'var(--text-muted)' }}>
                                            {cheque.banco}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: 'var(--text-main)' }}>
                                        {cheque.beneficiario}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-xs font-semibold tabular-nums"
                                       style={{ color: 'var(--text-main)' }}>
                                        {fmt(cheque.monto)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {cheque.fecha_emision ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {cheque.fecha_cobro ?? '—'}
                                    </p>
                                </div>
                                <div>
                                    <EstadoBadge estado={cheque.estado} />
                                </div>
                                <div className="flex items-center gap-1">
                                    {cheque.estado === 'emitido' && (
                                        <>
                                            <button
                                                onClick={() => abrirCambioEstado(cheque, 'cobrado')}
                                                title="Marcar como cobrado"
                                                className="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold transition-colors"
                                                style={{ background: '#D1FAE5', color: '#065F46' }}
                                                onMouseEnter={e => (e.currentTarget as HTMLButtonElement).style.background = '#A7F3D0'}
                                                onMouseLeave={e => (e.currentTarget as HTMLButtonElement).style.background = '#D1FAE5'}>
                                                <CheckCircle className="w-3 h-3" /> Cobrar
                                            </button>
                                            <button
                                                onClick={() => abrirCambioEstado(cheque, 'protestado')}
                                                title="Marcar como protestado"
                                                className="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold transition-colors"
                                                style={{ background: '#FEE2E2', color: '#7B2D2D' }}
                                                onMouseEnter={e => (e.currentTarget as HTMLButtonElement).style.background = '#FECACA'}
                                                onMouseLeave={e => (e.currentTarget as HTMLButtonElement).style.background = '#FEE2E2'}>
                                                <XCircle className="w-3 h-3" /> Protestar
                                            </button>
                                        </>
                                    )}
                                    {cheque.estado !== 'emitido' && (
                                        <span className="text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                    )}
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>

            {showModal && (
                <NuevoModal bancos={bancos} onClose={() => setShowModal(false)} />
            )}

            {estadoModal && (
                <CambioEstadoModal
                    cheque={estadoModal.cheque}
                    estadoNuevo={estadoModal.estado}
                    onClose={() => setEstadoModal(null)}
                />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
