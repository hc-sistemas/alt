import React, { useState, useMemo } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { X, Save, Send, AlertTriangle } from 'lucide-react'
import type { PageProps, Empresa } from '@/types'

interface FacturaDetalleItem {
    id: number
    descripcion: string
    cantidad: number
    precio_unitario: number
    subtotal: number
    porcentaje_iva: number
    total: number
}

interface FacturaOrigen {
    id: number
    numero_completo: string
    fecha_emision: string
    total: number
    cliente: {
        razon_social: string
        identificacion: string
    } | null
    detalles: FacturaDetalleItem[]
}

interface LineaNC {
    detalle_id: number
    seleccionada: boolean
    descripcion: string
    cantidad_original: number
    cantidad_devolver: number
    precio_unitario: number
    porcentaje_iva: number
    total: number
}

interface Props extends PageProps {
    factura: FacturaOrigen
    empresa_activa: Empresa
}

function calcularTotal(linea: LineaNC): number {
    const subtotal = linea.cantidad_devolver * linea.precio_unitario
    return subtotal + subtotal * (linea.porcentaje_iva / 100)
}

export default function Form() {
    const { factura } = usePage<Props>().props

    const [lineas, setLineas] = useState<LineaNC[]>(
        factura.detalles.map(d => ({
            detalle_id: d.id,
            seleccionada: false,
            descripcion: d.descripcion,
            cantidad_original: d.cantidad,
            cantidad_devolver: d.cantidad,
            precio_unitario: d.precio_unitario,
            porcentaje_iva: d.porcentaje_iva,
            total: d.total,
        }))
    )

    const [motivo, setMotivo] = useState('')
    const [guardando, setGuardando] = useState(false)
    const [errores, setErrores] = useState<string[]>([])

    const toggleLinea = (idx: number) => {
        setLineas(prev => {
            const next = [...prev]
            next[idx] = { ...next[idx], seleccionada: !next[idx].seleccionada }
            return next
        })
    }

    const updateCantidad = (idx: number, cantidad: number) => {
        setLineas(prev => {
            const next = [...prev]
            const linea = { ...next[idx], cantidad_devolver: Math.min(Math.max(0.01, cantidad), next[idx].cantidad_original) }
            linea.total = calcularTotal(linea)
            next[idx] = linea
            return next
        })
    }

    const seleccionadas = useMemo(() => lineas.filter(l => l.seleccionada), [lineas])

    const totalNC = useMemo(
        () => seleccionadas.reduce((s, l) => s + calcularTotal(l), 0),
        [seleccionadas]
    )

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        const errs: string[] = []
        if (seleccionadas.length === 0) errs.push('Seleccione al menos un ítem para la nota de crédito.')
        if (!motivo.trim()) errs.push('El motivo de la nota de crédito es obligatorio.')
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])
        setGuardando(true)
        router.post(route('ventas.notas-credito.store'), {
            factura_id: factura.id,
            motivo,
            detalles: seleccionadas.map(l => ({
                factura_detalle_id: l.detalle_id,
                cantidad: l.cantidad_devolver,
            })),
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    return (
        <AppLayout>
            <Head title="Nueva Nota de Crédito" />
            <PageHeader
                title="Nueva Nota de Crédito"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.notas-credito.index') },
                    { label: 'Notas de Crédito', href: route('ventas.notas-credito.index') },
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

                {/* Factura origen */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Factura Origen
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Número</p>
                            <p className="font-mono font-medium" style={{ color: 'var(--text-main)' }}>{factura.numero_completo}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Fecha</p>
                            <p style={{ color: 'var(--text-main)' }}>{formatFecha(factura.fecha_emision)}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Cliente</p>
                            <p style={{ color: 'var(--text-main)' }}>{factura.cliente?.razon_social ?? '—'}</p>
                            {factura.cliente && (
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{factura.cliente.identificacion}</p>
                            )}
                        </div>
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Total factura</p>
                            <p className="font-semibold" style={{ color: 'var(--primary)' }}>{formatMoneda(factura.total)}</p>
                        </div>
                    </div>
                </div>

                {/* Ítems a devolver */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Ítems a Devolver
                    </p>
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    <th className="py-2 px-2 w-8" style={{ color: 'var(--text-muted)' }}>✓</th>
                                    <th className="py-2 px-2 text-left font-medium" style={{ color: 'var(--text-muted)' }}>Descripción</th>
                                    <th className="py-2 px-2 text-right font-medium" style={{ color: 'var(--text-muted)' }}>Cant. Original</th>
                                    <th className="py-2 px-2 text-right font-medium" style={{ color: 'var(--text-muted)' }}>Cant. a Devolver</th>
                                    <th className="py-2 px-2 text-right font-medium" style={{ color: 'var(--text-muted)' }}>Precio</th>
                                    <th className="py-2 px-2 text-right font-medium" style={{ color: 'var(--text-muted)' }}>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {lineas.map((l, idx) => (
                                    <tr
                                        key={l.detalle_id}
                                        className={cn('transition-colors', l.seleccionada && 'bg-amber-500/5')}
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                    >
                                        <td className="py-2 px-2 text-center">
                                            <input
                                                type="checkbox"
                                                checked={l.seleccionada}
                                                onChange={() => toggleLinea(idx)}
                                                className="w-4 h-4 accent-amber-500 cursor-pointer"
                                            />
                                        </td>
                                        <td className="py-2 px-2" style={{ color: 'var(--text-main)' }}>{l.descripcion}</td>
                                        <td className="py-2 px-2 text-right" style={{ color: 'var(--text-muted)' }}>{l.cantidad_original}</td>
                                        <td className="py-2 px-2 text-right" style={{ minWidth: 90 }}>
                                            {l.seleccionada ? (
                                                <Input
                                                    type="number"
                                                    min="0.01"
                                                    max={l.cantidad_original}
                                                    step="0.01"
                                                    value={l.cantidad_devolver}
                                                    className="text-xs text-right h-7"
                                                    onChange={e => updateCantidad(idx, Number(e.target.value))}
                                                />
                                            ) : (
                                                <span style={{ color: 'var(--text-muted)' }}>—</span>
                                            )}
                                        </td>
                                        <td className="py-2 px-2 text-right" style={{ color: 'var(--text-muted)' }}>{formatMoneda(l.precio_unitario)}</td>
                                        <td className="py-2 px-2 text-right font-semibold" style={{ color: l.seleccionada ? 'var(--primary)' : 'var(--text-muted)' }}>
                                            {l.seleccionada ? formatMoneda(calcularTotal(l)) : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={5} className="py-3 px-2 text-right text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                        Total Nota de Crédito:
                                    </td>
                                    <td className="py-3 px-2 text-right">
                                        <span className="text-base font-bold" style={{ color: 'var(--primary)' }}>
                                            {formatMoneda(totalNC)}
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {/* Motivo */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <Label style={{ color: 'var(--text-main)' }}>Motivo de la Nota de Crédito *</Label>
                    <textarea
                        rows={3}
                        className="mt-2 w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-shadow"
                        style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        placeholder="Indique el motivo de la devolución o nota de crédito..."
                        value={motivo}
                        onChange={e => setMotivo(e.target.value)}
                        required
                    />
                </div>

                {/* Acciones */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('ventas.notas-credito.index')}>
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
                            Emitir Nota de Crédito
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    )
}
