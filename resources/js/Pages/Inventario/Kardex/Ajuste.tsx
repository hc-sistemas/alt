import { Head, router, usePage } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import BuscadorProductoModal from '@/Components/shared/BuscadorProductoModal'
import type { Resultado } from '@/Components/shared/BuscadorProductoModal'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import { Plus, Save, AlertTriangle, X } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'

interface ProductoOption {
    id: number
    codigo: string
    nombre: string
}

interface Props extends PageProps {
    productos: ProductoOption[]
    bodegas: { id: number; nombre: string }[]
    productoId: number | null
    bodegaId: number | null
    redirect_to: string
}

interface LineaAjuste {
    producto_id: number | null
    codigo: string
    nombre: string
    tipo_ajuste: 'positivo' | 'negativo'
    cantidad: string
    costo_unitario: string
    _disponible: number | null
    _disponibleCargando: boolean
}

function lineaVacia(): LineaAjuste {
    return {
        producto_id: null, codigo: '', nombre: '',
        tipo_ajuste: 'positivo', cantidad: '', costo_unitario: '',
        _disponible: null, _disponibleCargando: false,
    }
}

export default function KardexAjuste() {
    const { productos, bodegas, productoId, bodegaId, redirect_to } = usePage<Props>().props
    const { puede } = usePermiso('inventario')

    const productoInicial = productoId
        ? productos.find(p => p.id === productoId) ?? null
        : null

    const [bodegaIdSel, setBodegaIdSel] = useState(bodegaId?.toString() ?? '')
    const [motivo, setMotivo] = useState('')
    const [detalles, setDetalles] = useState<LineaAjuste[]>(() => [
        productoInicial
            ? { ...lineaVacia(), producto_id: productoInicial.id, codigo: productoInicial.codigo, nombre: productoInicial.nombre }
            : lineaVacia(),
    ])
    const [guardando, setGuardando] = useState(false)

    // Precarga el stock disponible de la línea inicial cuando se llega con
    // producto_id + bodega_id ya resueltos por query string (ej. desde el
    // lápiz de editar en Saldos.tsx) — sin esto el hint de stock queda vacío
    // hasta que el usuario interactúe manualmente con la línea.
    useEffect(() => {
        if (productoInicial && bodegaIdSel) {
            cargarStock(0, productoInicial.id, bodegaIdSel)
        }
    }, [])

    function updateLinea(idx: number, patch: Partial<LineaAjuste>) {
        setDetalles(prev => prev.map((d, i) => i === idx ? { ...d, ...patch } : d))
    }

    async function cargarStock(idx: number, productoId: number, bodega: string) {
        if (!bodega) {
            updateLinea(idx, { _disponible: null, _disponibleCargando: false })
            return
        }
        updateLinea(idx, { _disponibleCargando: true })
        try {
            const url = route('inventario.kardex.getSaldo') +
                `?producto_id=${productoId}&bodega_id=${bodega}`
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } })
            const json = await res.json()
            updateLinea(idx, { _disponible: json.disponible ?? 0, _disponibleCargando: false })
        } catch {
            updateLinea(idx, { _disponible: null, _disponibleCargando: false })
        }
    }

    function cambiarBodega(nuevaBodegaId: string) {
        setBodegaIdSel(nuevaBodegaId)
        detalles.forEach((d, i) => {
            if (d.producto_id !== null) cargarStock(i, d.producto_id, nuevaBodegaId)
        })
    }

    function seleccionarProducto(idx: number, p: Resultado) {
        updateLinea(idx, { producto_id: p.id, codigo: p.codigo, nombre: p.nombre })
        cargarStock(idx, p.id, bodegaIdSel)
    }

    function limpiarProducto(idx: number) {
        updateLinea(idx, { producto_id: null, codigo: '', nombre: '', _disponible: null })
    }

    function addLinea() {
        setDetalles(prev => [...prev, lineaVacia()])
    }

    function removeLinea(idx: number) {
        setDetalles(prev => prev.filter((_, i) => i !== idx))
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()

        const erroresGlobales: string[] = []
        if (!bodegaIdSel) erroresGlobales.push('Debe seleccionar una bodega.')
        if (!motivo.trim()) erroresGlobales.push('Ingrese el motivo del ajuste.')
        detalles.forEach((d, i) => {
            if (d.producto_id === null) erroresGlobales.push(`Línea ${i + 1}: seleccione un producto.`)
            if (!d.cantidad || parseInt(d.cantidad, 10) < 1) erroresGlobales.push(`Línea ${i + 1}: la cantidad debe ser al menos 1.`)
            if (d.tipo_ajuste === 'positivo' && d.costo_unitario === '') erroresGlobales.push(`Línea ${i + 1}: ingrese el costo unitario.`)
        })

        if (erroresGlobales.length > 0) {
            erroresGlobales.forEach(msg => toastError(msg))
            return
        }

        setGuardando(true)
        router.post(route('inventario.kardex.storeAjuste'), {
            bodega_id: bodegaIdSel,
            motivo,
            redirect_to,
            detalles: detalles.map(d => ({
                producto_id: d.producto_id,
                tipo_ajuste: d.tipo_ajuste,
                cantidad: d.cantidad,
                costo_unitario: d.tipo_ajuste === 'positivo' ? d.costo_unitario : null,
            })),
        }, {
            onSuccess: () => {
                toastExito('Ajuste registrado correctamente')
            },
            onError: (errs) => {
                const msg = Object.values(errs)[0] ?? 'Error al registrar el ajuste'
                toastError(msg as string)
            },
            onFinish: () => setGuardando(false),
        })
    }

    const tdInput = "w-full text-xs py-1.5 px-2 rounded border focus:outline-none"
    const tdInputStyle = { background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }
    const hintSlotCls = "h-4 mt-0.5 text-[11px] font-medium leading-4 whitespace-nowrap overflow-hidden"

    return (
        <AppLayout title="Ajuste de Inventario">
            <Head title="Ajuste de Inventario" />
            <PageHeader
                title="Ajuste de Inventario"
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Kárdex', href: route('inventario.kardex.saldos') },
                    { label: 'Ajuste' },
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-5xl space-y-6">
                {/* Bodega + motivo */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="space-y-1.5 max-w-sm">
                        <Label>Bodega *</Label>
                        <select
                            value={bodegaIdSel}
                            onChange={e => cambiarBodega(e.target.value)}
                            className="input-field"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        >
                            <option value="">Seleccionar bodega...</option>
                            {bodegas.map(b => (
                                <option key={b.id} value={b.id}>{b.nombre}</option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Motivo *</Label>
                        <textarea
                            value={motivo}
                            onChange={e => setMotivo(e.target.value)}
                            rows={2}
                            placeholder="Ej: Conteo físico, mercadería dañada, ajuste de sistema..."
                            className="input-field"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        />
                    </div>
                </div>

                {/* Líneas de ajuste */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3 flex items-center justify-between"
                        style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Productos a ajustar
                        </h3>
                        <Button type="button" variant="outline" onClick={addLinea}>
                            <Plus className="w-4 h-4" />
                            Agregar producto
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['Producto', 'Tipo', 'Cantidad', 'Costo unitario', 'Stock disponible', ''].map(h => (
                                        <th key={h} className="text-left px-4 py-2.5 font-medium text-xs whitespace-nowrap"
                                            style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((det, idx) => {
                                    const cantidadNum = parseInt(det.cantidad, 10) || 0
                                    const stockInsuficiente = det.tipo_ajuste === 'negativo' &&
                                        det._disponible !== null && cantidadNum > det._disponible

                                    return (
                                        <tr key={idx} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                            {/* Producto */}
                                            <td className="px-4 py-2.5 min-w-65 align-top">
                                                {det.producto_id !== null ? (
                                                    <div className="flex items-center gap-2 px-3 py-2 rounded-md border"
                                                        style={{ borderColor: 'var(--primary)', background: 'rgba(245,158,11,0.06)' }}>
                                                        <div className="flex-1 min-w-0">
                                                            <span className="font-mono text-xs font-semibold" style={{ color: 'var(--primary)' }}>
                                                                {det.codigo}
                                                            </span>
                                                            <span className="mx-1 text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                                            <span className="text-xs" style={{ color: 'var(--text-main)' }}>
                                                                {det.nombre}
                                                            </span>
                                                        </div>
                                                        <button type="button" onClick={() => limpiarProducto(idx)}
                                                            className="shrink-0 p-0.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700">
                                                            <X className="w-3 h-3" style={{ color: 'var(--text-muted)' }} />
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div className="space-y-1">
                                                        <BuscadorProductoModal
                                                            onSelect={p => seleccionarProducto(idx, p)}
                                                            disabled={!bodegaIdSel}
                                                        />
                                                        {!bodegaIdSel && (
                                                            <p className="text-xs text-amber-500">
                                                                Selecciona bodega primero
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </td>

                                            {/* Tipo de ajuste */}
                                            <td className="px-4 py-2.5 w-48 align-top">
                                                <select
                                                    className={tdInput}
                                                    style={tdInputStyle}
                                                    value={det.tipo_ajuste}
                                                    onChange={e => updateLinea(idx, { tipo_ajuste: e.target.value as 'positivo' | 'negativo' })}
                                                >
                                                    <option value="positivo">Positivo (Entrada)</option>
                                                    <option value="negativo">Negativo (Salida)</option>
                                                </select>
                                            </td>

                                            {/* Cantidad */}
                                            <td className="px-4 py-2.5 w-28 align-top">
                                                <input
                                                    type="number"
                                                    min={1}
                                                    step="1"
                                                    className={cn(tdInput, 'text-right')}
                                                    style={tdInputStyle}
                                                    value={det.cantidad}
                                                    onKeyDown={e => ['.', ','].includes(e.key) && e.preventDefault()}
                                                    onChange={e => {
                                                        const val = e.target.value
                                                        if (val === '' || /^\d+$/.test(val)) updateLinea(idx, { cantidad: val })
                                                    }}
                                                    placeholder="Ej: 5"
                                                />
                                                {stockInsuficiente && (
                                                    <div className={hintSlotCls} style={{ color: 'var(--color-danger)' }}>
                                                        <span className="inline-flex items-center gap-1">
                                                            <AlertTriangle className="w-3 h-3 shrink-0" />
                                                            Supera disponible
                                                        </span>
                                                    </div>
                                                )}
                                            </td>

                                            {/* Costo unitario — solo para ajuste positivo */}
                                            <td className="px-4 py-2.5 w-32 align-top">
                                                {det.tipo_ajuste === 'positivo' ? (
                                                    <input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        className={cn(tdInput, 'text-right')}
                                                        style={tdInputStyle}
                                                        value={det.costo_unitario}
                                                        onChange={e => updateLinea(idx, { costo_unitario: e.target.value })}
                                                        placeholder="0.00"
                                                    />
                                                ) : (
                                                    <span className="text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                                )}
                                            </td>

                                            {/* Stock disponible */}
                                            <td className="px-4 py-2.5 w-28 font-mono text-sm align-top"
                                                style={{ color: det._disponible !== null && det._disponible > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>
                                                {det._disponibleCargando ? '...' : det._disponible !== null ? det._disponible.toFixed(0) : '—'}
                                            </td>

                                            {/* Eliminar */}
                                            <td className="px-4 py-2.5 w-10 align-top">
                                                {detalles.length > 1 && (
                                                    <Button type="button" variant="ghost" size="icon"
                                                        onClick={() => removeLinea(idx)}>
                                                        <X className="w-4 h-4 text-red-400" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex gap-3">
                    {puede('crear') && (
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Registrar ajuste
                        </Button>
                    )}
                    <Button type="button" variant="outline"
                        onClick={() => router.visit(redirect_to)}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
