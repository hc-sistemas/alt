import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save, Info } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { Producto, PageProps } from '@/types'

interface Props extends PageProps {
    producto: Producto | null
    marcas: { id: number; nombre: string }[]
    categorias: { id: number; nombre: string; categoria_padre_id: number | null }[]
    bodegas: { id: number; nombre: string; tipo: string }[]
    cuentas: { id: number; codigo: string; descripcion: string }[]
}

type Tab = 'general' | 'precios' | 'inventario' | 'contabilidad'

const TABS: { key: Tab; label: string }[] = [
    { key: 'general', label: 'General' },
    { key: 'precios', label: 'Precios' },
    { key: 'inventario', label: 'Inventario' },
    { key: 'contabilidad', label: 'Contabilidad' },
]

const TAB_FIELDS: Record<Tab, string[]> = {
    general: ['codigo', 'nombre', 'tipo', 'unidad', 'descripcion'],
    precios: ['pvp', 'pvd', 'costo', 'descuento_maximo', 'porcentaje_iva', 'porcentaje_ice'],
    inventario: ['stock_minimo', 'stock_maximo'],
    contabilidad: ['cuenta_inventario', 'cuenta_costo_ventas', 'cuenta_ventas'],
}

export default function ProductoForm() {
    const { producto, marcas, categorias, cuentas } = usePage<Props>().props
    const esEdicion = !!producto

    const [activeTab, setActiveTab] = useState<Tab>('general')

    const { data, setData, post, put, processing, errors } = useForm({
        codigo: producto?.codigo ?? '',
        nombre: producto?.nombre ?? '',
        descripcion: producto?.descripcion ?? '',
        tipo: producto?.tipo ?? 'producto',
        unidad: producto?.unidad ?? 'unidad',
        marca_id: producto?.marca_id?.toString() ?? '',
        categoria_id: producto?.categoria_id?.toString() ?? '',
        requiere_serie: producto?.requiere_serie ?? false,
        pvp: producto?.pvp?.toString() ?? '0',
        pvd: producto?.pvd?.toString() ?? '0',
        costo: producto?.costo?.toString() ?? '0',
        descuento_maximo: producto?.descuento_maximo?.toString() ?? '0',
        porcentaje_iva: producto?.porcentaje_iva?.toString() ?? '15',
        porcentaje_ice: producto?.porcentaje_ice?.toString() ?? '0',
        stock_minimo: producto ? Math.round(Number(producto.stock_minimo)).toString() : '0',
        stock_maximo: producto?.stock_maximo ? Math.round(Number(producto.stock_maximo)).toString() : '',
        cuenta_inventario: producto?.cuenta_inventario ?? '',
        cuenta_costo_ventas: producto?.cuenta_costo_ventas ?? '',
        cuenta_ventas: producto?.cuenta_ventas ?? '',
        estado: producto?.estado ?? true,
    })

    function tabHasErrors(tab: Tab): boolean {
        return TAB_FIELDS[tab].some(f => !!errors[f as keyof typeof errors])
    }

    const pvpNum = parseFloat(data.pvp) || 0
    const costoNum = parseFloat(data.costo) || 0
    const margen = pvpNum > 0 && costoNum > 0
        ? ((pvpNum - costoNum) / pvpNum * 100).toFixed(2)
        : null

    function submit(e: React.FormEvent) {
        e.preventDefault()

        const payload = {
            ...data,
            marca_id: data.marca_id || null,
            categoria_id: data.categoria_id || null,
            stock_minimo: Math.round(Number(data.stock_minimo)),
            stock_maximo: data.stock_maximo ? Math.round(Number(data.stock_maximo)) : null,
            cuenta_inventario: data.cuenta_inventario || null,
            cuenta_costo_ventas: data.cuenta_costo_ventas || null,
            cuenta_ventas: data.cuenta_ventas || null,
        }

        if (esEdicion) {
            put(route('inventario.productos.update', producto!.id), {
                ...payload,
                onSuccess: () => {
                    toastExito('Producto actualizado correctamente')
                    router.visit(route('inventario.productos.index'))
                },
                onError: () => toastError('Error al guardar — revisa los campos marcados'),
            } as Parameters<typeof put>[1])
        } else {
            post(route('inventario.productos.store'), {
                ...payload,
                onSuccess: () => {
                    toastExito('Producto creado correctamente')
                    router.visit(route('inventario.productos.index'))
                },
                onError: () => toastError('Error al guardar — revisa los campos marcados'),
            } as Parameters<typeof post>[1])
        }
    }

    return (
        <AppLayout title={esEdicion ? 'Editar Producto' : 'Nuevo Producto'}>
            <Head title={esEdicion ? 'Editar Producto' : 'Nuevo Producto'} />

            <PageHeader
                title={esEdicion ? 'Editar Producto' : 'Nuevo Producto'}
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Productos', href: route('inventario.productos.index') },
                    { label: esEdicion ? 'Editar' : 'Nuevo' },
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-3xl space-y-0">
                {/* Tabs */}
                <div className="flex border-b" style={{ borderColor: 'var(--border)' }}>
                    {TABS.map(tab => {
                        const hasErr = tabHasErrors(tab.key)
                        return (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveTab(tab.key)}
                                className="relative px-5 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px"
                                style={{
                                    borderBottomColor: activeTab === tab.key ? 'var(--primary)' : 'transparent',
                                    color: activeTab === tab.key ? 'var(--primary)' : 'var(--text-muted)',
                                }}
                            >
                                {tab.label}
                                {hasErr && (
                                    <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-red-500" />
                                )}
                            </button>
                        )
                    })}
                </div>

                <div className="pt-6 space-y-5">
                    {/* ── Pestaña: General ── */}
                    {activeTab === 'general' && (
                        <>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label>Código *</Label>
                                    <Input
                                        value={data.codigo}
                                        onChange={e => setData('codigo', e.target.value)}
                                        placeholder="Ej: PROD-001"
                                    />
                                    {errors.codigo && <p className="text-xs text-red-400">{errors.codigo}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Nombre *</Label>
                                    <Input
                                        value={data.nombre}
                                        onChange={e => setData('nombre', e.target.value)}
                                        placeholder="Ej: Controlador DJ Pioneer DDJ-400"
                                    />
                                    {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Tipo *</Label>
                                    <select
                                        value={data.tipo}
                                        onChange={e => setData('tipo', e.target.value as 'producto' | 'servicio' | 'repuesto' | 'insumo')}
                                        className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                    >
                                        <option value="producto">Producto</option>
                                        <option value="servicio">Servicio</option>
                                        <option value="repuesto">Repuesto</option>
                                        <option value="insumo">Insumo</option>
                                    </select>
                                    {errors.tipo && <p className="text-xs text-red-400">{errors.tipo}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Unidad *</Label>
                                    <select
                                        value={data.unidad}
                                        onChange={e => setData('unidad', e.target.value)}
                                        className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                    >
                                        {['unidad', 'par', 'caja', 'metro', 'hora', 'kit'].map(u => (
                                            <option key={u} value={u}>{u.charAt(0).toUpperCase() + u.slice(1)}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Marca</Label>
                                    <select
                                        value={data.marca_id}
                                        onChange={e => setData('marca_id', e.target.value)}
                                        className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                    >
                                        <option value="">Sin marca</option>
                                        {marcas.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Categoría</Label>
                                    <select
                                        value={data.categoria_id}
                                        onChange={e => setData('categoria_id', e.target.value)}
                                        className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                    >
                                        <option value="">Sin categoría</option>
                                        {categorias.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                                    </select>
                                </div>
                            </div>

                            <div className="sm:col-span-2 space-y-1.5">
                                <Label>Descripción</Label>
                                <textarea
                                    value={data.descripcion}
                                    onChange={e => setData('descripcion', e.target.value)}
                                    rows={2}
                                    placeholder="Ej: Descripción del producto..."
                                    className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm resize-none"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                />
                            </div>

                            <div className="space-y-3">
                                <div className="flex items-center gap-3">
                                    <button type="button"
                                        onClick={() => setData('requiere_serie', !data.requiere_serie)}
                                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.requiere_serie ? 'bg-violet-500' : 'bg-slate-300 dark:bg-slate-600'}`}>
                                        <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${data.requiere_serie ? 'translate-x-6' : 'translate-x-1'}`} />
                                    </button>
                                    <Label>Requiere número de serie</Label>
                                </div>
                                {data.requiere_serie && (
                                    <div className="flex items-start gap-2 px-3 py-2 rounded-lg text-xs"
                                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                                        <Info className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                                        <span>Los números de serie se registran al ingresar stock desde el módulo de Kárdex.</span>
                                    </div>
                                )}
                                <div className="flex items-center gap-3">
                                    <button type="button"
                                        onClick={() => setData('estado', !data.estado)}
                                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${data.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'}`}>
                                        <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${data.estado ? 'translate-x-6' : 'translate-x-1'}`} />
                                    </button>
                                    <Label>Producto activo</Label>
                                </div>
                            </div>
                        </>
                    )}

                    {/* ── Pestaña: Precios ── */}
                    {activeTab === 'precios' && (
                        <>
                            {margen !== null && (
                                <div className="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium"
                                    style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                                    <span style={{ color: 'var(--text-muted)' }}>Margen estimado:</span>
                                    <span style={{ color: parseFloat(margen) > 0 ? '#10B981' : '#EF4444' }}>
                                        {margen}%
                                    </span>
                                </div>
                            )}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label>PVP — Precio Venta Público</Label>
                                    <Input type="number" min={0} step="0.0001"
                                        value={data.pvp}
                                        onChange={e => setData('pvp', e.target.value)}
                                        placeholder="Ej: 0.0000" />
                                    {errors.pvp && <p className="text-xs text-red-400">{errors.pvp}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>PVD — Precio Venta Distribuidor</Label>
                                    <Input type="number" min={0} step="0.0001"
                                        value={data.pvd}
                                        onChange={e => setData('pvd', e.target.value)}
                                        placeholder="Ej: 0.0000" />
                                    {errors.pvd && <p className="text-xs text-red-400">{errors.pvd}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Costo</Label>
                                    <Input type="number" min={0} step="0.0001"
                                        value={data.costo}
                                        onChange={e => setData('costo', e.target.value)}
                                        placeholder="Ej: 0.0000" />
                                    {errors.costo && <p className="text-xs text-red-400">{errors.costo}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Descuento máximo (%)</Label>
                                    <Input type="number" min={0} max={100} step="0.01"
                                        value={data.descuento_maximo}
                                        onChange={e => setData('descuento_maximo', e.target.value)}
                                        placeholder="Ej: 0.00" />
                                    {errors.descuento_maximo && <p className="text-xs text-red-400">{errors.descuento_maximo}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>IVA (%)</Label>
                                    <select value={data.porcentaje_iva}
                                        onChange={e => setData('porcentaje_iva', e.target.value)}
                                        className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                                        <option value="0">0%</option>
                                        <option value="5">5%</option>
                                        <option value="15">15%</option>
                                    </select>
                                    {errors.porcentaje_iva && <p className="text-xs text-red-400">{errors.porcentaje_iva}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>ICE (%)</Label>
                                    <Input type="number" min={0} step="0.01"
                                        value={data.porcentaje_ice}
                                        onChange={e => setData('porcentaje_ice', e.target.value)}
                                        placeholder="Ej: 0.00" />
                                    {errors.porcentaje_ice && <p className="text-xs text-red-400">{errors.porcentaje_ice}</p>}
                                </div>
                            </div>
                        </>
                    )}

                    {/* ── Pestaña: Inventario ── */}
                    {activeTab === 'inventario' && (
                        <>
                            <div className="flex items-start gap-2 px-3 py-2.5 rounded-lg text-xs"
                                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                                <Info className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                                <span>El stock se gestiona desde el módulo de Kárdex. Aquí solo se configuran los umbrales de alerta.</span>
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label>Stock mínimo</Label>
                                    <Input
                                        type="number" step="1" min="0"
                                        value={data.stock_minimo}
                                        onKeyDown={e => ['.', ','].includes(e.key) && e.preventDefault()}
                                        onChange={e => {
                                            const val = e.target.value
                                            if (val === '' || /^\d+$/.test(val)) setData('stock_minimo', val)
                                        }}
                                        placeholder="Ej: 0" />
                                    {errors.stock_minimo && <p className="text-xs text-red-400">{errors.stock_minimo}</p>}
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Stock máximo (opcional)</Label>
                                    <Input
                                        type="number" step="1" min="0"
                                        value={data.stock_maximo}
                                        onKeyDown={e => ['.', ','].includes(e.key) && e.preventDefault()}
                                        onChange={e => {
                                            const val = e.target.value
                                            if (val === '' || /^\d+$/.test(val)) setData('stock_maximo', val)
                                        }}
                                        placeholder="Sin límite" />
                                    {errors.stock_maximo && <p className="text-xs text-red-400">{errors.stock_maximo}</p>}
                                </div>
                            </div>
                        </>
                    )}

                    {/* ── Pestaña: Contabilidad ── */}
                    {activeTab === 'contabilidad' && (
                        <div className="grid grid-cols-1 gap-4">
                            <div className="space-y-1.5">
                                <Label>Cuenta de Inventario</Label>
                                <select
                                    value={data.cuenta_inventario ?? ''}
                                    onChange={e => setData('cuenta_inventario', e.target.value)}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    <option value="">— Sin cuenta asignada —</option>
                                    {cuentas.map(c => (
                                        <option key={c.id} value={c.codigo}>
                                            {c.codigo} — {c.descripcion}
                                        </option>
                                    ))}
                                </select>
                                {errors.cuenta_inventario && <p className="text-xs text-red-400">{errors.cuenta_inventario}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Cuenta Costo de Ventas</Label>
                                <select
                                    value={data.cuenta_costo_ventas ?? ''}
                                    onChange={e => setData('cuenta_costo_ventas', e.target.value)}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    <option value="">— Sin cuenta asignada —</option>
                                    {cuentas.map(c => (
                                        <option key={c.id} value={c.codigo}>
                                            {c.codigo} — {c.descripcion}
                                        </option>
                                    ))}
                                </select>
                                {errors.cuenta_costo_ventas && <p className="text-xs text-red-400">{errors.cuenta_costo_ventas}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Cuenta de Ventas</Label>
                                <select
                                    value={data.cuenta_ventas ?? ''}
                                    onChange={e => setData('cuenta_ventas', e.target.value)}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    <option value="">— Sin cuenta asignada —</option>
                                    {cuentas.map(c => (
                                        <option key={c.id} value={c.codigo}>
                                            {c.codigo} — {c.descripcion}
                                        </option>
                                    ))}
                                </select>
                                {errors.cuenta_ventas && <p className="text-xs text-red-400">{errors.cuenta_ventas}</p>}
                            </div>
                        </div>
                    )}
                </div>

                {/* Acciones */}
                <div className="flex gap-3 pt-6 mt-6 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        {esEdicion ? 'Guardar cambios' : 'Crear producto'}
                    </Button>
                    <Button type="button" variant="outline"
                        onClick={() => router.visit(route('inventario.productos.index'))}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
