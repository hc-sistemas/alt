import React, { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { formatMoneda } from '@/lib/utils'
import { Plus, Trash2, Save, X, Send, AlertTriangle } from 'lucide-react'
import type { PageProps, Empresa, Transportista } from '@/types'

interface FacturaDetalleItem {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
}

interface FacturaOrigen {
    id: number
    numero_completo: string
    total: number
    detalles: FacturaDetalleItem[]
}

interface ItemTransporte {
    descripcion: string
    cantidad: number
}

interface Props extends PageProps {
    factura: FacturaOrigen | null
    transportistas: Transportista[]
    empresa_activa: Empresa
}

function itemDesdeDetalle(d: FacturaDetalleItem): ItemTransporte {
    return { descripcion: d.descripcion, cantidad: d.cantidad }
}

function itemVacio(): ItemTransporte {
    return { descripcion: '', cantidad: 1 }
}

const hoy = new Date().toISOString().slice(0, 10)

export default function Form() {
    const { factura, transportistas } = usePage<Props>().props

    const [transportistaId, setTransportistaId] = useState<number | ''>(transportistas[0]?.id ?? '')
    const [fechaInicio, setFechaInicio] = useState(hoy)
    const [fechaFin, setFechaFin] = useState(hoy)
    const [origen, setOrigen] = useState('')
    const [destino, setDestino] = useState('')
    const [ruta, setRuta] = useState('')
    const [motivo, setMotivo] = useState('')
    const [facturaNumero, setFacturaNumero] = useState(factura?.numero_completo ?? '')

    const [items, setItems] = useState<ItemTransporte[]>(
        factura ? factura.detalles.map(itemDesdeDetalle) : [itemVacio()]
    )

    const [guardando, setGuardando] = useState(false)
    const [errores, setErrores] = useState<string[]>([])

    const updateItem = (idx: number, patch: Partial<ItemTransporte>) => {
        setItems(prev => {
            const next = [...prev]
            next[idx] = { ...next[idx], ...patch }
            return next
        })
    }

    const addItem = () => setItems(prev => [...prev, itemVacio()])
    const removeItem = (idx: number) => setItems(prev => prev.filter((_, i) => i !== idx))

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        const errs: string[] = []
        if (!transportistaId) errs.push('Seleccione un transportista.')
        if (!fechaInicio) errs.push('La fecha de inicio de transporte es obligatoria.')
        if (!destino.trim()) errs.push('El destino es obligatorio.')
        if (!motivo.trim()) errs.push('El motivo es obligatorio.')
        if (items.length === 0) errs.push('Agregue al menos un ítem a transportar.')
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])
        setGuardando(true)
        router.post(route('ventas.guias-remision.store'), {
            factura_id: factura?.id ?? null,
            transportista_id: transportistaId,
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin,
            origen,
            destino,
            ruta,
            motivo,
            items: items.map(i => ({ descripcion: i.descripcion, cantidad: i.cantidad })),
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    return (
        <AppLayout>
            <Head title="Nueva Guía de Remisión" />
            <PageHeader
                title="Nueva Guía de Remisión"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.guias-remision.index') },
                    { label: 'Guías de Remisión', href: route('ventas.guias-remision.index') },
                    { label: 'Nueva' },
                ]}
            />

            <form onSubmit={handleSubmit} className="p-6 space-y-6 max-w-5xl">

                {errores.length > 0 && (
                    <div
                        className="rounded-lg p-4 border"
                        style={{ background: 'rgba(239,68,68,.1)', borderColor: 'rgba(239,68,68,.3)' }}
                    >
                        <ul className="space-y-1">
                            {errores.map((e, i) => (
                                <li key={i} className="text-sm text-red-400 flex items-center gap-2">
                                    <AlertTriangle className="w-3.5 h-3.5 shrink-0" /> {e}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* Encabezado */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Datos del Transporte
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Transportista *</Label>
                            <select
                                className="mt-1 w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={transportistaId}
                                onChange={e => setTransportistaId(Number(e.target.value))}
                                required
                            >
                                <option value="">Seleccionar transportista...</option>
                                {transportistas.map(t => (
                                    <option key={t.id} value={t.id}>
                                        {t.razon_social}{t.placa ? ` — ${t.placa}` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Factura origen (opcional)</Label>
                            <Input
                                className="mt-1 font-mono"
                                placeholder="N° de factura..."
                                value={facturaNumero}
                                onChange={e => setFacturaNumero(e.target.value)}
                                readOnly={!!factura}
                                style={factura ? { color: 'var(--text-muted)', cursor: 'not-allowed' } : {}}
                            />
                            {factura && (
                                <p className="text-xs mt-1" style={{ color: 'var(--text-muted)' }}>
                                    Total: {formatMoneda(factura.total)}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Fecha inicio transporte *</Label>
                            <Input
                                type="date"
                                className="mt-1"
                                value={fechaInicio}
                                onChange={e => setFechaInicio(e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Fecha fin transporte</Label>
                            <Input
                                type="date"
                                className="mt-1"
                                value={fechaFin}
                                min={fechaInicio}
                                onChange={e => setFechaFin(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Origen</Label>
                            <Input
                                className="mt-1"
                                placeholder="Punto de origen..."
                                value={origen}
                                onChange={e => setOrigen(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Destino *</Label>
                            <Input
                                className="mt-1"
                                placeholder="Punto de destino..."
                                value={destino}
                                onChange={e => setDestino(e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Ruta</Label>
                            <Input
                                className="mt-1"
                                placeholder="Ruta del transporte..."
                                value={ruta}
                                onChange={e => setRuta(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Motivo *</Label>
                            <Input
                                className="mt-1"
                                placeholder="Motivo del traslado..."
                                value={motivo}
                                onChange={e => setMotivo(e.target.value)}
                                required
                            />
                        </div>
                    </div>
                </div>

                {/* Ítems a transportar */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Ítems a Transportar
                        </p>
                        {!factura && (
                            <Button type="button" size="sm" onClick={addItem}>
                                <Plus className="w-4 h-4" />
                                Agregar ítem
                            </Button>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    <th className="py-2 px-2 text-left font-medium" style={{ color: 'var(--text-muted)' }}>Descripción</th>
                                    <th className="py-2 px-2 text-right font-medium" style={{ color: 'var(--text-muted)' }}>Cantidad</th>
                                    {!factura && <th className="py-2 px-2 w-8" />}
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item, idx) => (
                                    <tr key={idx} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="py-1.5 px-2">
                                            {factura ? (
                                                <span style={{ color: 'var(--text-main)' }}>{item.descripcion}</span>
                                            ) : (
                                                <Input
                                                    value={item.descripcion}
                                                    placeholder="Descripción del ítem..."
                                                    className="text-xs"
                                                    onChange={e => updateItem(idx, { descripcion: e.target.value })}
                                                />
                                            )}
                                        </td>
                                        <td className="py-1.5 px-2" style={{ minWidth: 90 }}>
                                            {factura ? (
                                                <span className="block text-right" style={{ color: 'var(--text-main)' }}>{item.cantidad}</span>
                                            ) : (
                                                <Input
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    value={item.cantidad}
                                                    className="text-xs text-right"
                                                    onChange={e => updateItem(idx, { cantidad: Number(e.target.value) })}
                                                />
                                            )}
                                        </td>
                                        {!factura && (
                                            <td className="py-1.5 px-2">
                                                {items.length > 1 && (
                                                    <button type="button" className="p-1 rounded hover:bg-red-500/10 transition-colors" onClick={() => removeItem(idx)}>
                                                        <Trash2 className="w-4 h-4 text-red-400" />
                                                    </button>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('ventas.guias-remision.index')}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    <div className="flex gap-3">
                        <span title="Funcionalidad en desarrollo">
                            <Button type="button" variant="secondary" disabled>
                                <Send className="w-4 h-4" />
                                Enviar al SRI
                            </Button>
                        </span>
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Guardar Guía
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    )
}
