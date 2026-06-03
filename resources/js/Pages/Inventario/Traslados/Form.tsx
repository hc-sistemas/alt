import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import BuscadorProductoModal from '@/Components/shared/BuscadorProductoModal'
import type { Resultado } from '@/Components/shared/BuscadorProductoModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Trash2, Save, AlertTriangle, X } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
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

    const { data, setData, post, processing, errors } = useForm({
        bodega_origen_id:  '',
        bodega_destino_id: '',
        notas_origen:      '',
    })

    const [items, setItems] = useState<ItemForm[]>([emptyItem()])

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

    function agregarItem() {
        setItems(prev => [...prev, emptyItem()])
    }

    function eliminarItem(index: number) {
        setItems(prev => prev.filter((_, i) => i !== index))
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
            producto_id:    p.id.toString(),
            codigo_input:   p.codigo,
            producto_nombre: p.nombre,
        })
        cargarStock(i, p.id.toString(), data.bodega_origen_id)
    }

    async function submit(e: React.FormEvent) {
        e.preventDefault()

        if (mismaBodega) return

        const payload = {
            ...data,
            items: items.map(item => ({
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
                return
            }

            toastExito('Traslado creado correctamente')
            router.visit(route('inventario.traslados.index'))
        } catch {
            toastError('Error al crear el traslado')
        }
    }

    return (
        <AppLayout title="Nuevo Traslado">
            <Head title="Nuevo Traslado" />
            <PageHeader
                title="Nuevo Traslado"
                description="Crear traslado de stock entre bodegas"
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Traslados', href: route('inventario.traslados.index') },
                    { label: 'Nuevo' },
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-3xl space-y-6">
                {/* Header: bodegas + notas */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Origen y Destino</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Bodega origen *</Label>
                            <select value={data.bodega_origen_id}
                                onChange={e => cambiarBodegaOrigen(e.target.value)}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                                <option value="">Seleccionar bodega...</option>
                                {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                            </select>
                            {errors.bodega_origen_id && <p className="text-xs text-red-400">{errors.bodega_origen_id}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Bodega destino *</Label>
                            <select value={data.bodega_destino_id}
                                onChange={e => setData('bodega_destino_id', e.target.value)}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
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

                    <div className="space-y-1.5">
                        <Label>Notas de origen</Label>
                        <textarea value={data.notas_origen}
                            onChange={e => setData('notas_origen', e.target.value)}
                            rows={2}
                            placeholder="Ej: Reposición de bodega secundaria..."
                            className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm resize-none"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        />
                    </div>
                </div>

                {/* Items */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3 flex items-center justify-between"
                        style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Productos a trasladar
                        </h3>
                        <Button type="button" variant="outline" onClick={agregarItem}>
                            <Plus className="w-4 h-4" />
                            Agregar producto
                        </Button>
                    </div>

                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Producto', 'Cantidad', 'Stock disponible', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-2.5 font-medium text-xs"
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
                                        <td className="px-4 py-2.5 min-w-[260px]">
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
                                                        onSelect={p => fijarProducto(i, p)}
                                                    />
                                                    {!data.bodega_origen_id && (
                                                        <p className="text-xs text-amber-500">
                                                            Selecciona bodega origen primero
                                                        </p>
                                                    )}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 w-40">
                                            <div className="space-y-1">
                                                <Input type="number" min={1} step="1"
                                                    value={item.cantidad_enviada}
                                                    onKeyDown={e => ['.', ','].includes(e.key) && e.preventDefault()}
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
                                        <td className="px-4 py-2.5 font-mono text-sm"
                                            style={{ color: item.stock_disponible !== null && item.stock_disponible > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>
                                            {item.loading ? '...' : item.stock_disponible !== null ? item.stock_disponible.toFixed(4) : '—'}
                                        </td>
                                        <td className="px-4 py-2.5 w-10">
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

                {/* Acciones */}
                <div className="flex gap-3">
                    <Button type="submit" loading={processing} disabled={!!mismaBodega || items.length === 0}>
                        <Save className="w-4 h-4" />
                        Crear traslado
                    </Button>
                    <Button type="button" variant="outline"
                        onClick={() => router.visit(route('inventario.traslados.index'))}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
