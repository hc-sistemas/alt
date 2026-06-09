import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import BuscadorProductoModal from '@/Components/shared/BuscadorProductoModal'
import type { Resultado } from '@/Components/shared/BuscadorProductoModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save, Info, AlertTriangle, X } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { PageProps } from '@/types'

interface Props extends PageProps {
    bodegas: { id: number; nombre: string }[]
    productoId: number | null
    bodegaId: number | null
}

export default function KardexAjuste() {
    const { bodegas, productoId, bodegaId } = usePage<Props>().props

    const [productoFijado, setProductoFijado] = useState<Resultado | null>(null)

    const [saldoDisponible, setSaldoDisponible] = useState<number | null>(null)
    const [loadingSaldo, setLoadingSaldo] = useState(false)

    const { data, setData, post, processing, errors } = useForm({
        producto_id:    productoId?.toString() ?? '',
        bodega_id:      bodegaId?.toString() ?? '',
        tipo_ajuste:    'positivo' as 'positivo' | 'negativo',
        cantidad:       '',
        costo_unitario: '',
        motivo:         '',
    })

    function limpiarProducto() {
        setProductoFijado(null)
        setData('producto_id', '')
        setSaldoDisponible(null)
    }

    // Cargar saldo disponible cuando cambia producto o bodega
    useEffect(() => {
        if (!data.producto_id || !data.bodega_id) {
            setSaldoDisponible(null)
            return
        }
        setLoadingSaldo(true)
        const url = route('inventario.kardex.getSaldo') +
            `?producto_id=${data.producto_id}&bodega_id=${data.bodega_id}`
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(json => { setSaldoDisponible(json.disponible ?? null) })
            .catch(() => setSaldoDisponible(null))
            .finally(() => setLoadingSaldo(false))
    }, [data.producto_id, data.bodega_id])

    const cantidadNum = parseInt(data.cantidad, 10) || 0
    const stockInsuficiente = data.tipo_ajuste === 'negativo' &&
        saldoDisponible !== null && cantidadNum > saldoDisponible

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('inventario.kardex.storeAjuste'), {
            onSuccess: () => {
                toastExito('Ajuste registrado correctamente')
                router.visit(route('inventario.kardex.saldos'))
            },
            onError: (errs) => {
                const msg = Object.values(errs)[0] ?? 'Error al registrar el ajuste'
                toastError(msg)
            },
        })
    }

    return (
        <AppLayout title="Ajuste de Inventario">
            <Head title="Ajuste de Inventario" />
            <PageHeader
                title="Ajuste de Inventario"
                description="Registrar entrada o salida manual de stock"
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Kárdex', href: route('inventario.kardex.saldos') },
                    { label: 'Ajuste' },
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-2xl space-y-5">
                {/* Producto — búsqueda por código, nombre o marca */}
                <div className="space-y-1.5">
                    <Label>Producto *</Label>
                    {productoFijado ? (
                        <div className="flex items-center gap-2 px-3 py-2 rounded-md border"
                            style={{ borderColor: 'var(--primary)', background: 'rgba(245,158,11,0.06)' }}>
                            <div className="flex-1 min-w-0">
                                <span className="font-mono text-xs font-semibold" style={{ color: 'var(--primary)' }}>
                                    {productoFijado.codigo}
                                </span>
                                <span className="mx-2 text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                    {productoFijado.nombre}
                                </span>
                            </div>
                            <button type="button" onClick={limpiarProducto}
                                className="shrink-0 p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
                                title="Cambiar producto">
                                <X className="w-3.5 h-3.5" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>
                    ) : (
                        <BuscadorProductoModal
                            onSelect={p => {
                                setProductoFijado(p)
                                setData('producto_id', p.id.toString())
                            }}
                        />
                    )}
                    {errors.producto_id && <p className="text-xs text-red-400">{errors.producto_id}</p>}
                </div>

                {/* Bodega */}
                <div className="space-y-1.5">
                    <Label>Bodega *</Label>
                    <select
                        value={data.bodega_id}
                        onChange={e => setData('bodega_id', e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                    >
                        <option value="">Seleccionar bodega...</option>
                        {bodegas.map(b => (
                            <option key={b.id} value={b.id}>{b.nombre}</option>
                        ))}
                    </select>
                    {errors.bodega_id && <p className="text-xs text-red-400">{errors.bodega_id}</p>}
                </div>

                {/* Stock disponible en tiempo real */}
                {(data.producto_id && data.bodega_id) && (
                    <div className="px-4 py-3 rounded-lg text-sm"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                        <span style={{ color: 'var(--text-muted)' }}>Stock disponible actual: </span>
                        {loadingSaldo ? (
                            <span style={{ color: 'var(--text-muted)' }}>Cargando...</span>
                        ) : (
                            <span className="font-bold font-mono" style={{ color: 'var(--primary)' }}>
                                {saldoDisponible !== null ? saldoDisponible.toFixed(4) : '—'}
                            </span>
                        )}
                    </div>
                )}

                {/* Tipo de ajuste */}
                <div className="space-y-1.5">
                    <Label>Tipo de ajuste *</Label>
                    <div className="flex gap-3">
                        {[
                            { value: 'positivo', label: 'Positivo (Entrada)' },
                            { value: 'negativo', label: 'Negativo (Salida)' },
                        ].map(opt => (
                            <label key={opt.value}
                                className="flex items-center gap-2 cursor-pointer px-4 py-2.5 rounded-lg border transition-colors"
                                style={{
                                    borderColor: data.tipo_ajuste === opt.value ? 'var(--primary)' : 'var(--border)',
                                    background: data.tipo_ajuste === opt.value ? 'rgba(245,158,11,0.08)' : 'var(--bg-card)',
                                    color: data.tipo_ajuste === opt.value ? 'var(--primary)' : 'var(--text-muted)',
                                }}>
                                <input type="radio" name="tipo_ajuste" value={opt.value}
                                    checked={data.tipo_ajuste === opt.value}
                                    onChange={e => setData('tipo_ajuste', e.target.value as 'positivo' | 'negativo')}
                                    className="accent-amber-500" />
                                <span className="text-sm font-medium">{opt.label}</span>
                            </label>
                        ))}
                    </div>
                    {errors.tipo_ajuste && <p className="text-xs text-red-400">{errors.tipo_ajuste}</p>}
                </div>

                {/* Cantidad */}
                <div className="space-y-1.5">
                    <Label>Cantidad *</Label>
                    <Input
                        type="number"
                        min={1}
                        step="1"
                        value={data.cantidad}
                        onKeyDown={e => ['.', ','].includes(e.key) && e.preventDefault()}
                        onChange={e => {
                            const val = e.target.value
                            if (val === '' || /^\d+$/.test(val)) setData('cantidad', val)
                        }}
                        placeholder="Ej: 5"
                    />
                    {errors.cantidad && <p className="text-xs text-red-400">{errors.cantidad}</p>}
                    {data.cantidad !== '' && parseInt(data.cantidad, 10) < 1 && (
                        <p className="text-xs text-red-400">La cantidad debe ser al menos 1</p>
                    )}
                    {stockInsuficiente && (
                        <div className="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400">
                            <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                            La cantidad supera el stock disponible ({saldoDisponible?.toFixed(4)}). El servidor rechazará la operación.
                        </div>
                    )}
                </div>

                {/* Costo unitario — solo para ajuste positivo */}
                {data.tipo_ajuste === 'positivo' && (
                    <div className="space-y-1.5">
                        <Label>Costo unitario *</Label>
                        <Input
                            type="number"
                            min={0}
                            step="0.0001"
                            value={data.costo_unitario}
                            onChange={e => setData('costo_unitario', e.target.value)}
                            placeholder="Costo unitario del producto ingresado"
                        />
                        {errors.costo_unitario && <p className="text-xs text-red-400">{errors.costo_unitario}</p>}
                        <div className="flex items-start gap-1.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                            <Info className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                            <span>Se utilizará para recalcular el costo promedio ponderado del producto.</span>
                        </div>
                    </div>
                )}

                {/* Motivo */}
                <div className="space-y-1.5">
                    <Label>Motivo *</Label>
                    <textarea
                        value={data.motivo}
                        onChange={e => setData('motivo', e.target.value)}
                        rows={3}
                        placeholder="Ej: Conteo físico, mercadería dañada, ajuste de sistema..."
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                    />
                    {errors.motivo && <p className="text-xs text-red-400">{errors.motivo}</p>}
                </div>

                {/* Acciones */}
                <div className="flex gap-3 pt-4 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        Registrar ajuste
                    </Button>
                    <Button type="button" variant="outline"
                        onClick={() => router.visit(route('inventario.kardex.saldos'))}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
