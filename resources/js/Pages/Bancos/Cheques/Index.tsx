import { useState, useEffect, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { cn } from '@/lib/utils'
import {
    Plus, X, CheckCircle, AlertTriangle, Ban,
    CreditCard, DollarSign, Clock
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

interface Stats {
    total: number
    emitidos: number
    cobrados: number
    protestados: number
    monto_total: number
}

interface Props extends PageProps {
    cheques: Cheque[]
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'num_cuenta'>[]
    filtros: { estado?: string; banco_caja_id?: string; buscar?: string }
    stats: Stats
}

// ─── Notify / SweetAlert ──────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    success: (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warning: (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error:   (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const SWAL_CSS = `.swal-pop{border-radius:20px!important;padding:28px!important;box-shadow:0 25px 60px rgba(0,0,0,.25)!important}.swal-title{font-size:1.1rem!important;font-weight:700!important}.swal-confirm,.swal-cancel{border-radius:10px!important;padding:10px 20px!important;font-weight:600!important}`
function injectCss() {
    if (document.getElementById('swal-cheques')) return
    const s = document.createElement('style'); s.id = 'swal-cheques'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectCss,
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
            <div className="modal-card max-w-lg" onClick={e => e.stopPropagation()}>

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

// ─── Página principal ─────────────────────────────────────────────────────────

export default function ChequesIndex() {
    const { cheques, bancos, filtros, stats, flash } = usePage<Props>().props

    const [showModal, setShowModal] = useState(false)
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

    async function confirmarCobro(cheque: Cheque) {
        const { value } = await Swal.fire({
            ...swalBase,
            title: `Cobrar cheque N° ${cheque.numero}`,
            html: `
                <div style="text-align:left">
                    <div style="background:#f0fdf4;border-left:4px solid #10b981;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-weight:700;color:#065f46;margin:0">${cheque.beneficiario}</p>
                        <p style="color:#047857;font-size:0.85rem;margin:4px 0 0 0">
                            Monto: ${fmt(cheque.monto)}
                        </p>
                    </div>
                    <label class="input-label">
                        Fecha de cobro <span style="color:#ef4444">*</span>
                    </label>
                    <input type="date" id="fecha-cobro"
                        value="${new Date().toISOString().split('T')[0]}"
                        class="input-field"
                        style="width:100%;margin-bottom:12px" />
                    <label class="input-label">Observación</label>
                    <textarea id="obs-cobro" class="input-field textarea-field"
                        placeholder="Opcional..." rows="2"
                        style="width:100%"></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '✓ Confirmar cobro',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            preConfirm: () => ({
                fecha_cobro: (document.getElementById('fecha-cobro') as HTMLInputElement)?.value,
                observacion: (document.getElementById('obs-cobro') as HTMLTextAreaElement)?.value,
            }),
        })
        if (value) {
            router.patch(route('bancos.cheques.estado', cheque.id), {
                estado: 'cobrado',
                fecha_cobro: value.fecha_cobro,
                observacion: value.observacion,
            }, {
                onSuccess: () => notify.success(`Cheque N° ${cheque.numero} cobrado`),
                onError: () => notify.error('Error al actualizar'),
            })
        }
    }

    async function confirmarProtesta(cheque: Cheque) {
        const { value } = await Swal.fire({
            ...swalBase,
            title: `Protestar cheque N° ${cheque.numero}`,
            html: `
                <div style="text-align:left">
                    <div style="background:#fef2f2;border-left:4px solid #ef4444;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-weight:700;color:#7f1d1d;margin:0">${cheque.beneficiario}</p>
                        <p style="color:#b91c1c;font-size:0.85rem;margin:4px 0 0 0">
                            Monto: ${fmt(cheque.monto)}
                        </p>
                    </div>
                    <label class="input-label">
                        Motivo de protesta <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="obs-protesta" class="input-field textarea-field"
                        placeholder="Describe el motivo del protesto..." rows="3"
                        style="width:100%"></textarea>
                </div>
            `,
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '✗ Confirmar protesta',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            preConfirm: () => {
                const obs = (document.getElementById('obs-protesta') as HTMLTextAreaElement)?.value
                if (!obs?.trim()) {
                    Swal.showValidationMessage('El motivo de protesta es obligatorio')
                    return false
                }
                return { observacion: obs }
            },
        })
        if (value) {
            router.patch(route('bancos.cheques.estado', cheque.id), {
                estado: 'protestado',
                observacion: value.observacion,
            }, {
                onSuccess: () => notify.warning(`Cheque N° ${cheque.numero} protestado`),
                onError: () => notify.error('Error al actualizar'),
            })
        }
    }

    const statCards = [
        { label: 'Total cheques',  value: stats.total,       color: '#1A3A5C', icon: CreditCard },
        { label: 'Emitidos',       value: stats.emitidos,    color: '#d97706', icon: Clock },
        { label: 'Cobrados',       value: stats.cobrados,    color: '#16a34a', icon: CheckCircle },
        { label: 'Protestados',    value: stats.protestados, color: '#dc2626', icon: AlertTriangle },
    ]

    return (
        <AppLayout title="Cheques" suppressFlash>
            <Head title="Cheques" />

            <div className="p-4 md:p-6 space-y-5"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* Header */}
                <div className="flex items-center justify-between flex-wrap gap-3">
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
                    <button onClick={() => setShowModal(true)}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white"
                        style={{ background: 'var(--primary)' }}>
                        <Plus className="w-4 h-4" /> Nuevo Cheque
                    </button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    {statCards.map(({ label, value, color, icon: Icon }) => (
                        <div key={label}
                             className="rounded-xl border p-4"
                             style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <div className="flex items-center justify-between mb-2">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                    {label}
                                </p>
                                <Icon className="w-4 h-4 opacity-60" style={{ color }} />
                            </div>
                            <p className="text-2xl font-bold" style={{ color }}>
                                {value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Monto total emitidos */}
                {stats.monto_total > 0 && (
                    <div className="rounded-xl border p-4 flex items-center gap-3"
                         style={{ background: 'color-mix(in srgb, #f59e0b 8%, var(--bg-card))',
                                  borderColor: '#f59e0b44' }}>
                        <DollarSign className="w-5 h-5" style={{ color: '#d97706' }} />
                        <div>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Monto total emitidos pendientes de cobro
                            </p>
                            <p className="text-xl font-bold" style={{ color: '#d97706' }}>
                                {fmt(stats.monto_total)}
                            </p>
                        </div>
                    </div>
                )}

                {/* Filtros */}
                <div className="flex flex-wrap gap-2 items-center">
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
                            className="flex items-center gap-1 px-3 py-2 rounded-lg text-xs border transition-colors"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            <X className="w-3 h-3" /> Limpiar
                        </button>
                    )}
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
                                                onClick={() => confirmarCobro(cheque)}
                                                title="Marcar como cobrado"
                                                className="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-medium transition-colors"
                                                style={{ background: '#dcfce7', color: '#16a34a' }}
                                                onMouseEnter={e => (e.currentTarget as HTMLButtonElement).style.background = '#bbf7d0'}
                                                onMouseLeave={e => (e.currentTarget as HTMLButtonElement).style.background = '#dcfce7'}>
                                                <CheckCircle className="w-3.5 h-3.5" /> Cobrar
                                            </button>
                                            <button
                                                onClick={() => confirmarProtesta(cheque)}
                                                title="Marcar como protestado"
                                                className="flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-medium transition-colors"
                                                style={{ background: '#fee2e2', color: '#dc2626' }}
                                                onMouseEnter={e => (e.currentTarget as HTMLButtonElement).style.background = '#fecaca'}
                                                onMouseLeave={e => (e.currentTarget as HTMLButtonElement).style.background = '#fee2e2'}>
                                                <Ban className="w-3.5 h-3.5" /> Protestar
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

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
