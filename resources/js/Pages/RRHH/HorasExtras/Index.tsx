import { useState } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { Check, X, Filter, Clock } from 'lucide-react'
import type { HorasExtrasAprobacion, Colaborador, PageProps, PaginatedData } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ColaboradorItem {
    id: number
    apellidos: string
    nombres: string
    sueldo_base: number
}

interface HoraExtra extends HorasExtrasAprobacion {
    colaborador?: { id: number; apellidos: string; nombres: string; sueldo_base: number }
    aprobado_por_usuario?: { nombre: string }
}

interface Props extends PageProps {
    extras: PaginatedData<HoraExtra>
    colaboradores: ColaboradorItem[]
    filtros: { estado?: string; colaborador_id?: string; fecha_desde?: string; fecha_hasta?: string }
    flash: { success?: string; error?: string }
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (m: string) => toast.success(m, { style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error: (m: string) => toast.error(m,   { autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── Badges ──────────────────────────────────────────────────────────────────

function EstadoBadge({ estado }: { estado: string }) {
    const map: Record<string, string> = {
        pendiente:  'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        aprobado:   'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        rechazado:  'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
    }
    const label = { pendiente: 'Pendiente', aprobado: 'Aprobado', rechazado: 'Rechazado' }
    return (
        <span className={cn('px-2 py-0.5 rounded-full text-xs font-semibold', map[estado] ?? '')}>
            {label[estado as keyof typeof label] ?? estado}
        </span>
    )
}

function TipoBadge({ tipo }: { tipo: string }) {
    return tipo === 'extraordinaria'
        ? <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">Extraordinaria ×2.0</span>
        : <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Suplementaria ×1.5</span>
}

// ─── Modal Aprobar ────────────────────────────────────────────────────────────

function ModalAprobar({ extra, onClose }: { extra: HoraExtra; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        horas_aprobadas: String(extra.horas_solicitadas),
        observacion:     '',
    })

    const valorHora    = (extra.colaborador?.sueldo_base ?? 0) / 240
    const factor       = extra.tipo === 'extraordinaria' ? 2.0 : 1.5
    const valorPreview = (parseFloat(data.horas_aprobadas || '0') * valorHora * factor).toFixed(2)

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(route('rrhh.horas-extras.aprobar', extra.id), {
            onSuccess: () => {
                onClose()
                notify.ok(`Aprobadas ${data.horas_aprobadas}h — $${valorPreview}`)
            },
            onError: (errs) => notify.error(Object.values(errs)[0] ?? 'Error al aprobar.'),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header flex items-center justify-between px-6 py-4">
                    <h2 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Aprobar Horas Extras</h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-black/10">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div className="modal-body px-6 py-5 space-y-4">
                        {/* Info del registro */}
                        <div className="rounded-lg p-3 text-xs space-y-1" style={{ background: 'var(--bg-main)' }}>
                            <p className="font-semibold" style={{ color: 'var(--text-main)' }}>
                                {extra.colaborador?.apellidos} {extra.colaborador?.nombres}
                            </p>
                            <p style={{ color: 'var(--text-muted)' }}>
                                {extra.fecha} · <TipoBadge tipo={extra.tipo} />
                            </p>
                            <p style={{ color: 'var(--text-muted)' }}>
                                Solicitado: <strong>{extra.horas_solicitadas}h</strong> ·
                                VH: ${valorHora.toFixed(4)} · Factor: ×{factor}
                            </p>
                        </div>

                        {/* Horas aprobadas */}
                        <div>
                            <Label className="input-label">Horas aprobadas (máx. 4h/día) *</Label>
                            <Input
                                type="number"
                                min="0"
                                max="4"
                                step="0.25"
                                className="input-field"
                                value={data.horas_aprobadas}
                                onChange={e => setData('horas_aprobadas', e.target.value)}
                            />
                            {errors.horas_aprobadas && (
                                <p className="mt-1 text-xs text-red-500">{errors.horas_aprobadas}</p>
                            )}
                        </div>

                        {/* Valor calculado en tiempo real */}
                        <div className="flex items-center justify-between rounded-lg px-4 py-3 text-sm font-semibold"
                            style={{ background: 'var(--bg-main)', color: 'var(--primary)' }}>
                            <span>Valor a pagar</span>
                            <span className="text-lg">${valorPreview}</span>
                        </div>

                        {/* Observación */}
                        <div>
                            <Label className="input-label">Observación (opcional)</Label>
                            <textarea
                                rows={2}
                                className="input-field resize-none"
                                placeholder="Autorización, proyecto, etc."
                                value={data.observacion}
                                onChange={e => setData('observacion', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="modal-footer flex justify-end gap-3 px-6 py-4">
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                        <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                            <Check className="w-4 h-4" />
                            {processing ? 'Aprobando…' : 'Aprobar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Rechazar ───────────────────────────────────────────────────────────

function ModalRechazar({ extra, onClose }: { extra: HoraExtra; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        observacion: '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(route('rrhh.horas-extras.rechazar', extra.id), {
            onSuccess: () => {
                onClose()
                notify.ok('Solicitud rechazada.')
            },
            onError: (errs) => notify.error(Object.values(errs)[0] ?? 'Error.'),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header flex items-center justify-between px-6 py-4">
                    <h2 className="text-sm font-semibold text-red-600">Rechazar Solicitud</h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-black/10">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div className="modal-body px-6 py-5 space-y-4">
                        <div className="rounded-lg p-3 text-xs" style={{ background: 'var(--bg-main)' }}>
                            <p className="font-semibold" style={{ color: 'var(--text-main)' }}>
                                {extra.colaborador?.apellidos} {extra.colaborador?.nombres}
                            </p>
                            <p style={{ color: 'var(--text-muted)' }}>
                                {extra.fecha} · {extra.horas_solicitadas}h · <TipoBadge tipo={extra.tipo} />
                            </p>
                        </div>

                        <div>
                            <Label className="input-label">Motivo del rechazo *</Label>
                            <textarea
                                rows={3}
                                className="input-field resize-none"
                                placeholder="Mínimo 5 caracteres…"
                                value={data.observacion}
                                onChange={e => setData('observacion', e.target.value)}
                            />
                            {errors.observacion && (
                                <p className="mt-1 text-xs text-red-500">{errors.observacion}</p>
                            )}
                        </div>
                    </div>

                    <div className="modal-footer flex justify-end gap-3 px-6 py-4">
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                            style={{ background: '#ef4444' }}>
                            <X className="w-4 h-4" />
                            {processing ? 'Rechazando…' : 'Rechazar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function HorasExtrasIndex() {
    const { extras, colaboradores, filtros, flash } = usePage<Props>().props

    const [modal, setModal] = useState<{ tipo: 'aprobar' | 'rechazar'; extra: HoraExtra } | null>(null)
    const [estado, setEstado]         = useState(filtros.estado ?? '')
    const [colabId, setColabId]       = useState(filtros.colaborador_id ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')

    if (flash?.success) notify.ok(flash.success)
    if (flash?.error)   notify.error(flash.error)

    function filtrar() {
        router.get(route('rrhh.horas-extras.index'), {
            estado:        estado        || undefined,
            colaborador_id: colabId     || undefined,
            fecha_desde:   fechaDesde   || undefined,
            fecha_hasta:   fechaHasta   || undefined,
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setEstado(''); setColabId(''); setFechaDesde(''); setFechaHasta('')
        router.get(route('rrhh.horas-extras.index'))
    }

    return (
        <AppLayout title="Horas Extras">
            <Head title="Horas Extras — RRHH" />
            <ToastContainer position="top-right" />

            <PageHeader
                title="Horas Extras"
                description="Panel de aprobación — límite legal: 4h/día · 12h/semana"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Horas Extras' }]}
            />

            <div className="p-6 space-y-4">
                {/* Filtros */}
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <Label className="input-label">Estado</Label>
                        <select className="input-field w-36"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            value={estado} onChange={e => setEstado(e.target.value)}>
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                        </select>
                    </div>

                    <div>
                        <Label className="input-label">Colaborador</Label>
                        <select className="input-field w-52"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            value={colabId} onChange={e => setColabId(e.target.value)}>
                            <option value="">Todos</option>
                            {colaboradores.map(c => (
                                <option key={c.id} value={c.id}>{c.apellidos} {c.nombres}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <Label className="input-label">Desde</Label>
                        <Input type="date" className="input-field w-40"
                            value={fechaDesde} onChange={e => setFechaDesde(e.target.value)} />
                    </div>

                    <div>
                        <Label className="input-label">Hasta</Label>
                        <Input type="date" className="input-field w-40"
                            value={fechaHasta} onChange={e => setFechaHasta(e.target.value)} />
                    </div>

                    <button onClick={filtrar} className="btn-primary flex items-center gap-2 px-4 py-2">
                        <Filter className="w-4 h-4" /> Filtrar
                    </button>

                    {(estado || colabId || fechaDesde || fechaHasta) && (
                        <button onClick={limpiar} className="flex items-center gap-1 text-xs" style={{ color: 'var(--text-muted)' }}>
                            <X className="w-3 h-3" /> Limpiar
                        </button>
                    )}

                    <span className="ml-auto text-xs" style={{ color: 'var(--text-muted)' }}>
                        {extras.total} solicitud{extras.total !== 1 ? 'es' : ''}
                    </span>
                </div>

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Colaborador', 'Fecha', 'Tipo', 'H. Solicitadas', 'H. Aprobadas', 'Valor', 'Estado', 'Aprobado por', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {extras.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-16 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay solicitudes de horas extras.
                                    </td>
                                </tr>
                            ) : extras.data.map(e => (
                                <tr key={e.id} className="border-t hover:bg-black/5 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-3 py-2.5 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {e.colaborador?.apellidos} {e.colaborador?.nombres}
                                    </td>
                                    <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                        {e.fecha}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <TipoBadge tipo={e.tipo} />
                                    </td>
                                    <td className="px-3 py-2.5 text-center font-mono" style={{ color: 'var(--text-main)' }}>
                                        {Number(e.horas_solicitadas).toFixed(2)}h
                                    </td>
                                    <td className="px-3 py-2.5 text-center font-mono" style={{ color: 'var(--text-muted)' }}>
                                        {e.horas_aprobadas != null ? `${Number(e.horas_aprobadas).toFixed(2)}h` : '—'}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono font-medium" style={{ color: 'var(--primary)' }}>
                                        ${Number(e.valor_calculado).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <EstadoBadge estado={e.estado} />
                                    </td>
                                    <td className="px-3 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                        {e.aprobado_por_usuario?.nombre ?? '—'}
                                        {e.fecha_aprobacion && (
                                            <div className="text-[10px]">
                                                {new Date(e.fecha_aprobacion).toLocaleDateString('es-EC')}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        {e.estado === 'pendiente' && (
                                            <div className="flex items-center gap-1">
                                                <button
                                                    onClick={() => setModal({ tipo: 'aprobar', extra: e })}
                                                    className="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium text-white transition-colors"
                                                    style={{ background: '#10b981' }}
                                                    title="Aprobar"
                                                >
                                                    <Check className="w-3 h-3" /> Aprobar
                                                </button>
                                                <button
                                                    onClick={() => setModal({ tipo: 'rechazar', extra: e })}
                                                    className="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium text-white transition-colors"
                                                    style={{ background: '#ef4444' }}
                                                    title="Rechazar"
                                                >
                                                    <X className="w-3 h-3" /> Rechazar
                                                </button>
                                            </div>
                                        )}
                                        {e.estado !== 'pendiente' && e.observacion && (
                                            <span className="text-[10px] italic" style={{ color: 'var(--text-muted)' }}
                                                title={e.observacion}>
                                                {e.observacion.length > 30 ? e.observacion.slice(0, 30) + '…' : e.observacion}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {extras.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            {extras.from}–{extras.to} de {extras.total}
                        </p>
                        <div className="flex gap-1">
                            {extras.links.map((link, i) => (
                                link.url ? (
                                    <a key={i} href={link.url}
                                        className={cn('px-3 py-1 rounded border text-xs transition-colors',
                                            link.active
                                                ? 'border-amber-500 bg-amber-500 text-black font-medium'
                                                : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                                        )}
                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 rounded border text-xs opacity-40"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Modales */}
            {modal?.tipo === 'aprobar' && (
                <ModalAprobar extra={modal.extra} onClose={() => setModal(null)} />
            )}
            {modal?.tipo === 'rechazar' && (
                <ModalRechazar extra={modal.extra} onClose={() => setModal(null)} />
            )}
        </AppLayout>
    )
}
