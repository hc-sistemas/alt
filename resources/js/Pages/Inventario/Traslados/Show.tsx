import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { ArrowRight, X, Save } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { Traslado, PageProps } from '@/types'

interface Props extends PageProps {
    traslado: Traslado
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    confirmado: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    anulado:    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

export default function TrasladoShow() {
    const { traslado } = usePage<Props>().props
    const isPendiente = traslado.estado === 'pendiente'

    // Estado local para el modal de anulación
    const [modalAnular, setModalAnular] = useState(false)
    const [motivoAnular, setMotivoAnular] = useState('')
    const [anulando, setAnulando] = useState(false)

    // Formulario de confirmación de recepción
    const { data, setData, processing } = useForm({
        items: (traslado.items ?? []).map(item => ({
            id: item.id,
            cantidad_recibida: Number(item.cantidad_enviada).toFixed(4),
            notas: '',
        })),
        notas_destino: '',
    })

    const formatFecha = (dt: string | null) => dt
        ? new Date(dt).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : '—'

    async function confirmar(e: React.FormEvent) {
        e.preventDefault()
        try {
            const res = await fetch(route('inventario.traslados.confirmar', traslado.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Inertia': 'true',
                },
                body: JSON.stringify(data),
            })
            if (res.status === 422) {
                const json = await res.json()
                toastError(json.message ?? 'Error al confirmar')
                return
            }
            toastExito('Traslado confirmado correctamente')
            router.reload()
        } catch {
            toastError('Error al confirmar el traslado')
        }
    }

    async function anular() {
        if (!motivoAnular.trim()) { toastError('El motivo es obligatorio'); return }
        setAnulando(true)
        try {
            const res = await fetch(route('inventario.traslados.anular', traslado.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Inertia': 'true',
                },
                body: JSON.stringify({ motivo: motivoAnular }),
            })
            if (res.status === 422) {
                const json = await res.json()
                toastError(json.message ?? 'Error al anular')
                return
            }
            toastExito('Traslado anulado')
            router.visit(route('inventario.traslados.index'))
        } catch {
            toastError('Error al anular')
        } finally {
            setAnulando(false)
            setModalAnular(false)
        }
    }

    return (
        <AppLayout title={`Traslado #${traslado.id}`}>
            <Head title={`Traslado #${traslado.id}`} />
            <PageHeader
                title={`Traslado #${traslado.id}`}
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Traslados', href: route('inventario.traslados.index') },
                    { label: `#${traslado.id}` },
                ]}
            />

            <div className="p-6 max-w-3xl space-y-6">
                {/* Header info */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    {/* Origen → Destino */}
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            {traslado.bodega_origen?.nombre ?? `Bodega #${traslado.bodega_origen_id}`}
                        </span>
                        <ArrowRight className="w-5 h-5" style={{ color: 'var(--text-muted)' }} />
                        <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            {traslado.bodega_destino?.nombre ?? `Bodega #${traslado.bodega_destino_id}`}
                        </span>
                        <span className={`ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${ESTADO_COLORES[traslado.estado] ?? ''}`}>
                            {traslado.estado}
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha traslado</p>
                            <p style={{ color: 'var(--text-main)' }}>{formatFecha(traslado.fecha_traslado)}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Usuario origen</p>
                            <p style={{ color: 'var(--text-main)' }}>{traslado.usuario_origen?.nombre ?? `#${traslado.usuario_origen_id}`}</p>
                        </div>
                        {traslado.fecha_confirmacion && (
                            <>
                                <div>
                                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha confirmación</p>
                                    <p style={{ color: 'var(--text-main)' }}>{formatFecha(traslado.fecha_confirmacion)}</p>
                                </div>
                                <div>
                                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Usuario destino</p>
                                    <p style={{ color: 'var(--text-main)' }}>{traslado.usuario_destino?.nombre ?? '—'}</p>
                                </div>
                            </>
                        )}
                    </div>

                    {traslado.notas_origen && (
                        <div className="text-sm">
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Notas de origen</p>
                            <p style={{ color: 'var(--text-main)' }}>{traslado.notas_origen}</p>
                        </div>
                    )}
                    {traslado.notas_destino && (
                        <div className="text-sm">
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>
                                {traslado.estado === 'anulado' ? 'Motivo de anulación' : 'Notas de destino'}
                            </p>
                            <p style={{ color: 'var(--text-main)' }}>{traslado.notas_destino}</p>
                        </div>
                    )}
                </div>

                {/* Tabla de items */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Productos</h3>
                    </div>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Producto</th>
                                <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Código</th>
                                <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Enviado</th>
                                {traslado.estado === 'confirmado' && (
                                    <>
                                        <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Recibido</th>
                                        <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Diferencia</th>
                                    </>
                                )}
                                {isPendiente && (
                                    <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Cant. recibida</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {(traslado.items ?? []).map((item, i) => {
                                const enviado   = Number(item.cantidad_enviada)
                                const recibido  = item.cantidad_recibida !== null ? Number(item.cantidad_recibida) : null
                                const diferencia = recibido !== null ? recibido - enviado : null
                                return (
                                    <tr key={item.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-4 py-2.5 font-medium" style={{ color: 'var(--text-main)' }}>
                                            {item.producto?.nombre ?? `#${item.producto_id}`}
                                        </td>
                                        <td className="px-4 py-2.5 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {item.producto?.codigo ?? '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                            {enviado.toFixed(4)}
                                        </td>
                                        {traslado.estado === 'confirmado' && (
                                            <>
                                                <td className="px-4 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                                    {recibido?.toFixed(4) ?? '—'}
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-mono"
                                                    style={{ color: diferencia === null ? 'var(--text-muted)' : diferencia < 0 ? '#EF4444' : diferencia > 0 ? '#10B981' : 'var(--text-muted)' }}>
                                                    {diferencia !== null ? (diferencia >= 0 ? '+' : '') + diferencia.toFixed(4) : '—'}
                                                </td>
                                            </>
                                        )}
                                        {isPendiente && (
                                            <td className="px-4 py-2.5 text-right w-36">
                                                <input
                                                    type="number" min={0} step="0.0001"
                                                    value={data.items[i]?.cantidad_recibida ?? ''}
                                                    onChange={e => {
                                                        const updated = [...data.items]
                                                        updated[i] = { ...updated[i], cantidad_recibida: e.target.value }
                                                        setData('items', updated)
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

                {/* Formulario de confirmación */}
                {isPendiente && (
                    <form onSubmit={confirmar} className="rounded-xl border p-5 space-y-4"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Confirmar recepción</h3>
                        <div className="space-y-1.5">
                            <label className="text-sm" style={{ color: 'var(--text-muted)' }}>Notas de recepción</label>
                            <textarea
                                value={data.notas_destino}
                                onChange={e => setData('notas_destino', e.target.value)}
                                rows={2}
                                placeholder="Observaciones de la recepción..."
                                className="input-field"
                            />
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" loading={processing}>
                                <Save className="w-4 h-4" />
                                Confirmar recepción
                            </Button>
                            <Button type="button" variant="outline"
                                style={{ borderColor: '#EF4444', color: '#EF4444' }}
                                onClick={() => setModalAnular(true)}>
                                Anular traslado
                            </Button>
                        </div>
                    </form>
                )}
            </div>

            {/* Modal de anulación */}
            {modalAnular && (
                <div className="modal-overlay" onClick={() => setModalAnular(false)}>
                    <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                        <div className="modal-header">
                            <div>
                                <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Anular traslado #{traslado.id}
                                </h3>
                            </div>
                            <button onClick={() => setModalAnular(false)} className="modal-close">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <div className="modal-body space-y-4">
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Al anular se liberará el stock reservado en bodega origen. Esta acción no se puede deshacer.
                            </p>
                            <div className="space-y-1.5">
                                <label className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>Motivo *</label>
                                <textarea
                                    value={motivoAnular}
                                    onChange={e => setMotivoAnular(e.target.value)}
                                    rows={3}
                                    placeholder="Motivo de la anulación..."
                                    className="input-field"
                                    autoFocus
                                />
                            </div>
                        </div>
                        <div className="modal-footer">
                            <Button onClick={anular} loading={anulando}
                                style={{ background: '#EF4444', color: 'white' }}>
                                Confirmar anulación
                            </Button>
                            <Button variant="outline" onClick={() => setModalAnular(false)}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
