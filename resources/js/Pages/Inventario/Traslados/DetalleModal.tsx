import { useEffect, useState } from 'react'
import { usePage } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { ArrowRight, X, Save } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { usePermiso } from '@/Hooks/usePermiso'
import type { TrasladoBodega, PageProps } from '@/types'

interface Props {
    trasladoId: number | null
    onClose: () => void
    onChanged: () => void
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    aceptado:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    rechazado: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

const ESTADO_LABELS: Record<string, string> = {
    pendiente: 'Pendiente', aceptado: 'Aceptado', rechazado: 'Rechazado',
}

function csrfToken() {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

export default function TrasladoDetalleModal({ trasladoId, onClose, onChanged }: Props) {
    const { auth } = usePage<PageProps>().props
    const { puede } = usePermiso('inventario')

    const [traslado, setTraslado] = useState<TrasladoBodega | null>(null)
    const [cargando, setCargando] = useState(false)

    const [detalles, setDetalles] = useState<{ id: number; cantidad_recibida: string }[]>([])
    const [observacion, setObservacion] = useState('')
    const [confirmando, setConfirmando] = useState(false)

    const [modalAnular, setModalAnular] = useState(false)
    const [motivoAnular, setMotivoAnular] = useState('')
    const [anulando, setAnulando] = useState(false)

    useEffect(() => {
        if (!trasladoId) {
            setTraslado(null)
            return
        }
        setCargando(true)
        fetch(route('inventario.traslados.show', trasladoId), {
            headers: { Accept: 'application/json' },
        })
            .then(res => res.json())
            .then(json => {
                setTraslado(json.traslado)
                setDetalles((json.traslado.detalles ?? []).map((item: { id: number; cantidad_enviada: string }) => ({
                    id: item.id,
                    cantidad_recibida: Number(item.cantidad_enviada).toFixed(4),
                })))
                setObservacion('')
            })
            .catch(() => toastError('Error al cargar el movimiento'))
            .finally(() => setCargando(false))
    }, [trasladoId])

    if (!trasladoId) return null

    const isPendiente = traslado?.estado === 'pendiente'
    const puedeOperar = ['super_admin', 'admin', 'bodeguero'].includes((auth.user?.perfil ?? '').toLowerCase())

    const formatFecha = (dt: string | null) => dt
        ? new Date(dt).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '—'

    async function ejecutarConfirmar() {
        if (!traslado) return
        setConfirmando(true)
        try {
            const res = await fetch(route('inventario.traslados.confirmar', traslado.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Inertia': 'true',
                },
                body: JSON.stringify({ detalles, observacion }),
            })
            if (res.status === 422) {
                const json = await res.json()
                toastError(json.message ?? 'Error al confirmar')
                return
            }
            toastExito('Movimiento aceptado correctamente')
            onChanged()
            onClose()
        } catch {
            toastError('Error al confirmar el movimiento')
        } finally {
            setConfirmando(false)
        }
    }

    async function ejecutarAnular() {
        if (!traslado) return
        if (!motivoAnular.trim()) { toastError('El motivo es obligatorio'); return }
        setAnulando(true)
        try {
            const res = await fetch(route('inventario.traslados.anular', traslado.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Inertia': 'true',
                },
                body: JSON.stringify({ motivo: motivoAnular }),
            })
            if (res.status === 422) {
                const json = await res.json()
                toastError(json.message ?? 'Error al rechazar')
                return
            }
            toastExito('Movimiento rechazado')
            onChanged()
            onClose()
        } catch {
            toastError('Error al rechazar')
        } finally {
            setAnulando(false)
            setModalAnular(false)
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60" onClick={onClose} />
            <div className="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl p-6 space-y-4"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                        Movimiento #{trasladoId}
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                {cargando || !traslado ? (
                    <p className="text-sm py-8 text-center" style={{ color: 'var(--text-muted)' }}>Cargando...</p>
                ) : (
                    <>
                        {/* Header info */}
                        <div className="rounded-xl border p-4 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}>
                            <div className="flex items-center gap-3 flex-wrap">
                                <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                    {traslado.bodega_origen?.nombre ?? `Bodega #${traslado.bodega_origen_id}`}
                                </span>
                                <ArrowRight className="w-5 h-5" style={{ color: 'var(--text-muted)' }} />
                                <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                    {traslado.bodega_destino?.nombre ?? `Bodega #${traslado.bodega_destino_id}`}
                                </span>
                                <span className={`ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${ESTADO_COLORES[traslado.estado] ?? ''}`}>
                                    {ESTADO_LABELS[traslado.estado] ?? traslado.estado}
                                </span>
                            </div>

                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Número</p>
                                    <p style={{ color: 'var(--text-main)' }}>{traslado.numero ?? '—'}</p>
                                </div>
                                <div>
                                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha</p>
                                    <p style={{ color: 'var(--text-main)' }}>{formatFecha(traslado.fecha)}</p>
                                </div>
                                {traslado.fecha_recepcion && (
                                    <div>
                                        <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha recepción</p>
                                        <p style={{ color: 'var(--text-main)' }}>{formatFecha(traslado.fecha_recepcion)}</p>
                                    </div>
                                )}
                            </div>

                            {traslado.observacion && (
                                <div className="text-sm">
                                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>
                                        {traslado.estado === 'rechazado' ? 'Motivo de rechazo' : 'Observaciones'}
                                    </p>
                                    <p style={{ color: 'var(--text-main)' }}>{traslado.observacion}</p>
                                </div>
                            )}
                        </div>

                        {/* Tabla de ítems */}
                        <div className="rounded-lg border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ background: 'var(--bg-main)', borderBottom: '1px solid var(--border)' }}>
                                        <th className="text-left px-3 py-1.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Producto</th>
                                        <th className="text-left px-3 py-1.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Código</th>
                                        <th className="text-right px-3 py-1.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Enviado</th>
                                        {traslado.estado === 'aceptado' && (
                                            <>
                                                <th className="text-right px-3 py-1.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Recibido</th>
                                                <th className="text-right px-3 py-1.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Diferencia</th>
                                            </>
                                        )}
                                        {isPendiente && (
                                            <th className="text-right px-3 py-1.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Cant. recibida</th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {(traslado.detalles ?? []).map((item, i) => {
                                        const enviado    = Number(item.cantidad_enviada)
                                        const recibido   = item.cantidad_recibida !== null ? Number(item.cantidad_recibida) : null
                                        const diferencia = recibido !== null ? recibido - enviado : null
                                        return (
                                            <tr key={item.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                                <td className="px-3 py-1.5 font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {item.producto?.nombre ?? `#${item.producto_id}`}
                                                </td>
                                                <td className="px-3 py-1.5 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {item.producto?.codigo ?? '—'}
                                                </td>
                                                <td className="px-3 py-1.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                                    {enviado.toFixed(4)}
                                                </td>
                                                {traslado.estado === 'aceptado' && (
                                                    <>
                                                        <td className="px-3 py-1.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                                            {recibido?.toFixed(4) ?? '—'}
                                                        </td>
                                                        <td className="px-3 py-1.5 text-right font-mono"
                                                            style={{ color: diferencia === null ? 'var(--text-muted)' : diferencia < 0 ? '#EF4444' : diferencia > 0 ? '#10B981' : 'var(--text-muted)' }}>
                                                            {diferencia !== null ? (diferencia >= 0 ? '+' : '') + diferencia.toFixed(4) : '—'}
                                                        </td>
                                                    </>
                                                )}
                                                {isPendiente && (
                                                    <td className="px-3 py-1.5 text-right w-36">
                                                        <input
                                                            type="number" min={0} step="0.0001"
                                                            value={detalles[i]?.cantidad_recibida ?? ''}
                                                            onChange={e => {
                                                                const updated = [...detalles]
                                                                updated[i] = { ...updated[i], cantidad_recibida: e.target.value }
                                                                setDetalles(updated)
                                                            }}
                                                            className="w-full h-8 rounded-md border bg-transparent px-2 text-sm text-right font-mono"
                                                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                                        />
                                                    </td>
                                                )}
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>

                        {/* Confirmar recepción */}
                        {isPendiente && puedeOperar && (
                            <div className="rounded-xl border p-4 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}>
                                <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Confirmar recepción</h3>
                                <div className="space-y-1">
                                    <label className="text-sm" style={{ color: 'var(--text-muted)' }}>Observaciones</label>
                                    <textarea
                                        value={observacion}
                                        onChange={e => setObservacion(e.target.value)}
                                        rows={2}
                                        placeholder="Opcional..."
                                        className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm resize-none"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    />
                                </div>
                                <div className="flex gap-3">
                                    {puede('editar') && (
                                        <Button type="button" onClick={ejecutarConfirmar} loading={confirmando} disabled={confirmando}>
                                            <Save className="w-4 h-4" />
                                            Confirmar recepción
                                        </Button>
                                    )}
                                    {puede('anular') && (
                                        <Button type="button" variant="outline"
                                            style={{ borderColor: '#EF4444', color: '#EF4444' }}
                                            onClick={() => setModalAnular(true)}>
                                            Rechazar movimiento
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Sub-modal de rechazo */}
            {modalAnular && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-black/60" onClick={() => setModalAnular(false)} />
                    <div className="relative w-full max-w-md rounded-xl shadow-2xl p-6 space-y-4"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                Rechazar movimiento #{trasladoId}
                            </h3>
                            <button onClick={() => setModalAnular(false)} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Al rechazar se liberará el stock reservado en bodega origen. Esta acción no se puede deshacer.
                        </p>
                        <div className="space-y-1.5">
                            <label className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>Motivo *</label>
                            <textarea
                                value={motivoAnular}
                                onChange={e => setMotivoAnular(e.target.value)}
                                rows={3}
                                placeholder="Motivo del rechazo..."
                                className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm resize-none"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                autoFocus
                            />
                        </div>
                        <div className="flex gap-3 pt-1">
                            <Button onClick={ejecutarAnular} loading={anulando} disabled={anulando}
                                style={{ background: '#EF4444', color: 'white' }}>
                                Confirmar rechazo
                            </Button>
                            <Button variant="outline" onClick={() => setModalAnular(false)}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}
