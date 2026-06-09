import React, { useState, useMemo } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, Trash2, Save, X, Send, AlertTriangle } from 'lucide-react'
import type { PageProps, Empresa } from '@/types'

interface FacturaCliente {
    razon_social: string
    identificacion: string
}

interface FacturaOrigen {
    id: number
    numero_completo: string
    fecha_emision: string
    total: number
    cliente: FacturaCliente | null
}

interface ImpuestoCatalogo {
    codigo: string
    descripcion: string
    tipo: 'IR' | 'IVA'
    porcentaje: number
}

interface LineaRetencion {
    tipo: 'IR' | 'IVA'
    codigo: string
    descripcion: string
    porcentaje: number
    base_imponible: number
    valor_retenido: number
}

interface Props extends PageProps {
    factura: FacturaOrigen
    impuestos: ImpuestoCatalogo[]
    empresa_activa: Empresa
}

function calcularRetencion(linea: LineaRetencion): LineaRetencion {
    return {
        ...linea,
        valor_retenido: Math.round(linea.base_imponible * (linea.porcentaje / 100) * 100) / 100,
    }
}

function lineaVacia(factura: FacturaOrigen): LineaRetencion {
    return {
        tipo: 'IR',
        codigo: '',
        descripcion: '',
        porcentaje: 0,
        base_imponible: factura.total,
        valor_retenido: 0,
    }
}

export default function Form() {
    const { factura, impuestos } = usePage<Props>().props

    const [lineas, setLineas] = useState<LineaRetencion[]>([lineaVacia(factura)])
    const [guardando, setGuardando] = useState(false)
    const [errores, setErrores] = useState<string[]>([])

    const totalRetenido = useMemo(
        () => lineas.reduce((s, l) => s + l.valor_retenido, 0),
        [lineas]
    )

    const updateLinea = (idx: number, patch: Partial<LineaRetencion>) => {
        setLineas(prev => {
            const next = [...prev]
            next[idx] = calcularRetencion({ ...next[idx], ...patch })
            return next
        })
    }

    const handleImpuestoChange = (idx: number, codigo: string) => {
        const imp = impuestos.find(i => i.codigo === codigo)
        if (!imp) return
        updateLinea(idx, { codigo: imp.codigo, descripcion: imp.descripcion, tipo: imp.tipo, porcentaje: imp.porcentaje })
    }

    const addLinea = () => setLineas(prev => [...prev, lineaVacia(factura)])
    const removeLinea = (idx: number) => setLineas(prev => prev.filter((_, i) => i !== idx))

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        const errs: string[] = []
        if (lineas.length === 0) errs.push('Agregue al menos una retención.')
        if (lineas.some(l => !l.codigo)) errs.push('Todas las retenciones deben tener un código.')
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])
        setGuardando(true)
        router.post(route('ventas.retenciones.store'), {
            factura_id: factura.id,
            detalles: lineas.map(l => ({
                tipo: l.tipo,
                codigo: l.codigo,
                descripcion: l.descripcion,
                porcentaje: l.porcentaje,
                base_imponible: l.base_imponible,
                valor_retenido: l.valor_retenido,
            })),
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    return (
        <AppLayout>
            <Head title="Nueva Retención" />
            <PageHeader
                title="Nueva Retención"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.retenciones.index') },
                    { label: 'Retenciones', href: route('ventas.retenciones.index') },
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

                {/* Tabla de retenciones */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Retenciones Aplicadas
                        </p>
                        <Button type="button" size="sm" onClick={addLinea}>
                            <Plus className="w-4 h-4" />
                            Agregar retención
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    {['Tipo', 'Código / Descripción', '% Retención', 'Base Imponible', 'Valor Retenido', ''].map((h, i) => (
                                        <th
                                            key={i}
                                            className={cn('py-2 px-2 font-medium text-left', i >= 2 && 'text-right')}
                                            style={{ color: 'var(--text-muted)' }}
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {lineas.map((l, idx) => (
                                    <tr key={idx} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="py-1.5 px-2" style={{ minWidth: 80 }}>
                                            <select
                                                className="w-full h-8 rounded-md border px-2 text-xs"
                                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                                value={l.tipo}
                                                onChange={e => updateLinea(idx, { tipo: e.target.value as 'IR' | 'IVA', codigo: '', descripcion: '', porcentaje: 0 })}
                                            >
                                                <option value="IR">IR</option>
                                                <option value="IVA">IVA</option>
                                            </select>
                                        </td>
                                        <td className="py-1.5 px-2" style={{ minWidth: 200 }}>
                                            <select
                                                className="w-full h-8 rounded-md border px-2 text-xs"
                                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                                value={l.codigo}
                                                onChange={e => handleImpuestoChange(idx, e.target.value)}
                                            >
                                                <option value="">Seleccionar...</option>
                                                {impuestos
                                                    .filter(i => i.tipo === l.tipo)
                                                    .map(i => (
                                                        <option key={i.codigo} value={i.codigo}>
                                                            {i.codigo} — {i.descripcion}
                                                        </option>
                                                    ))}
                                            </select>
                                        </td>
                                        <td className="py-1.5 px-2" style={{ minWidth: 90 }}>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                                value={l.porcentaje}
                                                className="text-xs text-right"
                                                onChange={e => updateLinea(idx, { porcentaje: Number(e.target.value) })}
                                            />
                                        </td>
                                        <td className="py-1.5 px-2" style={{ minWidth: 110 }}>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={l.base_imponible}
                                                className="text-xs text-right"
                                                onChange={e => updateLinea(idx, { base_imponible: Number(e.target.value) })}
                                            />
                                        </td>
                                        <td className="py-1.5 px-2 text-right font-semibold text-red-400" style={{ minWidth: 110 }}>
                                            {formatMoneda(l.valor_retenido)}
                                        </td>
                                        <td className="py-1.5 px-2">
                                            {lineas.length > 1 && (
                                                <button type="button" className="p-1 rounded hover:bg-red-500/10 transition-colors" onClick={() => removeLinea(idx)}>
                                                    <Trash2 className="w-4 h-4 text-red-400" />
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={4} className="py-3 px-2 text-right text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                        Total Retenido:
                                    </td>
                                    <td className="py-3 px-2 text-right">
                                        <span className="text-base font-bold text-red-400">{formatMoneda(totalRetenido)}</span>
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('ventas.retenciones.index')}>
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
                            Guardar Retención
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    )
}
