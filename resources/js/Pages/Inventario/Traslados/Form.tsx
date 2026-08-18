import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import BuscadorProductoModal from '@/Components/shared/BuscadorProductoModal'
import type { BuscadorProductoModalHandle, Resultado } from '@/Components/shared/BuscadorProductoModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Trash2, Save, AlertTriangle, X } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'

interface ItemForm {
    producto_id: string
    codigo_input: string
    producto_nombre: string | null
    cantidad_enviada: string
    stock_disponible: number | null
    loading: boolean
}

interface Props extends PageProps {
    bodegas: { id: number; nombre: string; tipo: string }[]
}

const emptyItem = (): ItemForm => ({
    producto_id: '', codigo_input: '', producto_nombre: null,
    cantidad_enviada: '', stock_disponible: null, loading: false,
})

export default function TrasladoForm() {
    const { bodegas } = usePage<Props>().props
    const { puede } = usePermiso('inventario')

    const { data, setData, errors } = useForm({
        bodega_origen_id:  '',
        bodega_destino_id: '',
        observacion:       '',
    })

    const [items, setItems] = useState<ItemForm[]>([emptyItem()])
    const [submitting, setSubmitting] = useState(false)

    const productoRefs = useRef<(BuscadorProductoModalHandle | null)[]>([])
    const cantidadRefs = useRef<(HTMLInputElement | null)[]>([])

    const mismaBodega = data.bodega_origen_id &&
        data.bodega_destino_id &&
        data.bodega_origen_id === data.bodega_destino_id

    async function cargarStock(index: number, productoId: string, bodegaId: string) {
        if (!productoId || !bodegaId) {
            updateItem(index, { stock_disponible: null, loading: false })
            return
        }
        updateItem(index, { loading: true })
        try {
            const url = route('inventario.kardex.getSaldo') +
                `?producto_id=${productoId}&bodega_id=${bodegaId}`
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } })
            const json = await res.json()
            updateItem(index, { stock_disponible: json.disponible ?? 0, loading: false })
        } catch {
            updateItem(index, { stock_disponible: null, loading: false })
        }
    }

    function updateItem(index: number, patch: Partial<ItemForm>) {
        setItems(prev => prev.map((item, i) => i === index ? { ...item, ...patch } : item))
    }

    function agregarItem(enfocarNueva = false) {
        setItems(prev => {
            const nuevoIndex = prev.length
            if (enfocarNueva) {
                setTimeout(() => productoRefs.current[nuevoIndex]?.focus(), 0)
            }
            return [...prev, emptyItem()]
        })
    }

    function eliminarItem(index: number) {
        setItems(prev => prev.filter((_, i) => i !== index))
        productoRefs.current.splice(index, 1)
        cantidadRefs.current.splice(index, 1)
    }

    function cantidadEnter(i: number) {
        const item = items[i]
        if (!item.producto_id || parseInt(item.cantidad_enviada, 10) < 1) return

        if (i === items.length - 1) {
            agregarItem(true)
        } else if (productoRefs.current[i + 1]) {
            productoRefs.current[i + 1]?.focus()
        } else {
            cantidadRefs.current[i + 1]?.focus()
        }
    }

    function limpiarItem(index: number) {
        updateItem(index, {
            producto_id: '', codigo_input: '', producto_nombre: null,
            stock_disponible: null,
        })
    }

    function cambiarBodegaOrigen(bodegaId: string) {
        setData('bodega_origen_id', bodegaId)
        items.forEach((item, i) => {
            if (item.producto_id) cargarStock(i, item.producto_id, bodegaId)
        })
    }

    function fijarProducto(i: number, p: Resultado) {
        updateItem(i, {
            producto_id:      p.id.toString(),
            codigo_input:     p.codigo,
            producto_nombre:  p.nombre,
            stock_disponible: p.disponible ?? null,
        })
        if (p.disponible === undefined) {
            cargarStock(i, p.id.toString(), data.bodega_origen_id)
        }
        setTimeout(() => cantidadRefs.current[i]?.focus(), 0)
    }

    async function submit() {
        if (mismaBodega || submitting) return
        setSubmitting(true)

        const payload = {
            ...data,
            detalles: items.map(item => ({
                producto_id:      item.producto_id,
                cantidad_enviada: item.cantidad_enviada,
            })),
        }

        try {
            const res = await fetch(route('inventario.traslados.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'X-Inertia': 'true',
                },
                body: JSON.stringify(payload),
            })

            if (res.status === 422) {
                const json = await res.json()
                toastError(json.message ?? 'Error de validación')
                setSubmitting(false)
                return
            }

            toastExito('Traslado creado correctamente')
            router.visit(route('inventario.traslados.index'))
        } catch {
            toastError('Error al crear el traslado')
            setSubmitting(false)
        }
    }

    return (
        <AppLayout title="Nuevo Movimiento">
            <Head title="Nuevo Movimiento" />
            <PageHeader
                title="Nuevo Movimiento"
                description="Crear traslado de stock entre bodegas"
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Movimientos', href: route('inventario.traslados.index') },
                    { label: 'Nuevo' },
                ]}
            />

            <form onSubmit={e => e.preventDefault()} className="p-6 max-w-3xl space-y-4">
                {/* Origen, destino, productos y observaciones */}
                <div className="rounded-xl border p-4 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label>Bodega origen *</Label>
                            <select value={data.bodega_origen_id}
                                onChange={e => cambiarBodegaOrigen(e.target.value)}
                                className="input-field"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                                <option value="">Seleccionar bodega...</option>
                                {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                            </select>
                            {errors.bodega_origen_id && <p className="text-xs text-red-400">{errors.bodega_origen_id}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Bodega destino *</Label>
                            <select value={data.bodega_destino_id}
                                onChange={e => setData('bodega_destino_id', e.target.value)}
                                className="input-field"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                                <option value="">Seleccionar bodega...</option>
                                {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                            </select>
                            {errors.bodega_destino_id && <p className="text-xs text-red-400">{errors.bodega_destino_id}</p>}
                        </div>
                    </div>

                    {mismaBodega && (
                        <div className="flex items-center gap-2 text-sm text-red-500">
                            <AlertTriangle className="w-4 h-4 shrink-0" />
                            La bodega origen y destino no pueden ser la misma.
                        </div>
                    )}

                    {/* Items */}
                    <div className="rounded-lg border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        <div className="px-3 py-2 flex items-center justify-between"
                            style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                            <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                Productos a trasladar
                            </h3>
                            <Button type="button" variant="outline" onClick={() => agregarItem()}>
                                <Plus className="w-4 h-4" />
                                Agregar producto
                            </Button>
                        </div>
                        <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Producto', 'Cantidad', 'Stock disponible', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-1.5 font-medium text-xs"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {items.map((item, i) => {
                                const cantidad = parseInt(item.cantidad_enviada, 10) || 0
                                const insuf    = item.stock_disponible !== null && cantidad > item.stock_disponible
                                return (
                                    <tr key={i} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-3 py-1.5 min-w-65">
                                            {item.producto_nombre ? (
                                                <div className="flex items-center gap-2 px-3 py-2 rounded-md border"
                                                    style={{ borderColor: 'var(--primary)', background: 'rgba(245,158,11,0.06)' }}>
                                                    <div className="flex-1 min-w-0">
                                                        <span className="font-mono text-xs font-semibold" style={{ color: 'var(--primary)' }}>
                                                            {item.codigo_input}
                                                        </span>
                                                        <span className="mx-1 text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                                        <span className="text-xs" style={{ color: 'var(--text-main)' }}>
                                                            {item.producto_nombre}
                                                        </span>
                                                    </div>
                                                    <button type="button" onClick={() => limpiarItem(i)}
                                                        className="shrink-0 p-0.5 rounded hover:bg-slate-200 dark:hover:bg-slate-700">
                                                        <X className="w-3 h-3" style={{ color: 'var(--text-muted)' }} />
                                                    </button>
                                                </div>
                                            ) : (
                                                <div className="space-y-1">
                                                    <BuscadorProductoModal
                                                        ref={el => { productoRefs.current[i] = el }}
                                                        onSelect={p => fijarProducto(i, p)}
                                                        disabled={!data.bodega_origen_id}
                                                        urlBusqueda={data.bodega_origen_id
                                                            ? route('inventario.traslados.productosEnBodega') + `?bodega_id=${data.bodega_origen_id}`
                                                            : undefined}
                                                    />
                                                    {!data.bodega_origen_id && (
                                                        <p className="text-xs text-amber-500">
                                                            Selecciona bodega origen primero
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-1.5 w-40">
                                            <div className="space-y-1">
                                                <Input type="number" min={1} step="1"
                                                    ref={el => { cantidadRefs.current[i] = el }}
                                                    value={item.cantidad_enviada}
                                                    onKeyDown={e => {
                                                        if (['.', ','].includes(e.key)) {
                                                            e.preventDefault()
                                                            return
                                                        }
                                                        if (e.key === 'Enter') {
                                                            e.preventDefault()
                                                            cantidadEnter(i)
                                                        }
                                                    }}
                                                    onChange={e => {
                                                        const val = e.target.value
                                                        if (val === '' || /^\d+$/.test(val)) updateItem(i, { cantidad_enviada: val })
                                                    }}
                                                    placeholder="Ej: 5" />
                                                {item.cantidad_enviada !== '' && parseInt(item.cantidad_enviada, 10) < 1 && (
                                                    <p className="text-xs text-red-400">La cantidad debe ser al menos 1</p>
                                                )}
                                                {insuf && (
                                                    <p className="text-xs text-amber-600 flex items-center gap-1">
                                                        <AlertTriangle className="w-3 h-3" />
                                                        Supera disponible
                                                    </p>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-3 py-1.5 font-mono text-sm"
                                            style={{ color: item.stock_disponible !== null && item.stock_disponible > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>
                                            {item.loading ? '...' : item.stock_disponible !== null ? item.stock_disponible.toFixed(0) : '—'}
                                        </td>
                                        <td className="px-3 py-1.5 w-10">
                                            {items.length > 1 && (
                                                <Button type="button" variant="ghost" size="icon"
                                                    onClick={() => eliminarItem(i)}>
                                                    <Trash2 className="w-4 h-4 text-red-400" />
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                        </table>
                    </div>

                    {/* Observaciones */}
                    <div className="space-y-1">
                        <Label>Observaciones</Label>
                        <textarea value={data.observacion}
                            onChange={e => setData('observacion', e.target.value)}
                            rows={2}
                            placeholder="Ej: Reposición de bodega secundaria..."
                            className="input-field"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        />
                    </div>
                </div>

                {/* Acciones */}
                <div className="flex gap-3">
                    {puede('crear') && (
                        <Button type="button" onClick={submit} loading={submitting}
                            disabled={!!mismaBodega || items.length === 0 || submitting}>
                            <Save className="w-4 h-4" />
                            Registrar Movimiento
                        </Button>
                    )}
                    <Button type="button" variant="outline"
                        onClick={() => router.visit(route('inventario.traslados.index'))}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
