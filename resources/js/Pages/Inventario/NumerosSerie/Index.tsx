import { Head, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Search, AlertTriangle } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, PaginatedData } from '@/types'

interface SerieFila {
    id: number; numero_serie: string; estado: string; producto: string | null
    bodega: string | null; factura_venta_id: number | null; fecha: string | null
}
interface ResumenFila { id: number; producto: string; stock: number; registradas: number; faltan: number }

interface Props extends PageProps {
    series: PaginatedData<SerieFila> | null
    resumen: ResumenFila[]
    bodegas: { id: number; nombre: string }[]
    productos: { id: number; codigo: string; nombre: string }[]
    estados: string[]
    filtros: { estado?: string; bodega_id?: string; buscar?: string }
}

const ETIQUETA: Record<string, string> = { disponible: 'En bodega', vendido: 'Vendido', reservado: 'Reservado', garantia: 'En garantía' }
const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }

export default function NumerosSerieIndex() {
    const { series, resumen, bodegas, productos, estados, filtros } = usePage<Props>().props
    const { puede } = usePermiso('inventario')
    const [filtro, setFiltro] = useState({ estado: filtros.estado ?? '', bodega_id: filtros.bodega_id ?? '', buscar: filtros.buscar ?? '' })

    const { data, setData, post, processing, reset, errors } = useForm({ producto_id: '', bodega_id: '', series: '' })

    function buscar(e?: React.FormEvent) {
        e?.preventDefault()
        router.get(route('inventario.series.index'), { ...filtro, buscado: '1' }, { preserveState: true, replace: true })
    }

    function registrar(e: React.FormEvent) {
        e.preventDefault()
        post(route('inventario.series.store'), { preserveScroll: true, onSuccess: () => reset('series') })
    }

    const faltantes = resumen.reduce((a, r) => a + r.faltan, 0)

    return (
        <AppLayout title="Números de serie">
            <Head title="Números de serie" />
            <PageHeader
                title="Números de serie"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Números de serie' }]}
            />

            <div className="px-6 pt-6 pb-10 space-y-6 max-w-6xl">
                <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                    Los DJ / controladores, computadoras, pantallas LED y TV exigen el número de serie de cada unidad. Regístrelos aquí al ingresar la
                    factura de compra; en una inspección de bodega puede ver cuáles siguen en bodega y cuáles se vendieron.
                </p>

                {faltantes > 0 && (
                    <div className="rounded-xl border px-4 py-3 text-sm flex items-center gap-2" style={{ borderColor: '#F59E0B', background: 'rgba(245,158,11,0.08)', color: 'var(--text-main)' }}>
                        <AlertTriangle size={16} style={{ color: '#D97706' }} />
                        Faltan por registrar <strong>{faltantes}</strong> número(s) de serie frente al stock de los productos que los exigen.
                    </div>
                )}

                {/* Resumen por producto */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="px-5 py-3 border-b text-sm font-semibold" style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                        Productos con serie: stock frente a series registradas
                    </div>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ color: 'var(--text-muted)' }}>
                                <th className="px-5 py-2 text-left">Producto</th><th className="px-3 py-2 text-right">Stock</th>
                                <th className="px-3 py-2 text-right">Series registradas</th><th className="px-5 py-2 text-right">Faltan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {resumen.map(r => (
                                <tr key={r.id} className="border-t" style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                    <td className="px-5 py-2">{r.producto}</td>
                                    <td className="px-3 py-2 text-right font-mono">{r.stock}</td>
                                    <td className="px-3 py-2 text-right font-mono">{r.registradas}</td>
                                    <td className="px-5 py-2 text-right font-mono font-semibold" style={{ color: r.faltan > 0 ? '#D97706' : '#059669' }}>{r.faltan}</td>
                                </tr>
                            ))}
                            {resumen.length === 0 && (
                                <tr><td colSpan={4} className="px-5 py-4" style={{ color: 'var(--text-muted)' }}>No hay stock de productos que exijan número de serie.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Registro */}
                {puede('crear') && (
                    <form onSubmit={registrar} className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Registrar números de serie</p>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label>Producto <span className="text-red-400">*</span></Label>
                                <select className="input-field" style={selectStyle} value={data.producto_id} onChange={e => setData('producto_id', e.target.value)} required>
                                    <option value="">— Seleccionar —</option>
                                    {productos.map(p => <option key={p.id} value={p.id}>{p.codigo} — {p.nombre}</option>)}
                                </select>
                                {errors.producto_id && <p className="text-red-400 text-xs">{errors.producto_id}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Bodega <span className="text-red-400">*</span></Label>
                                <select className="input-field" style={selectStyle} value={data.bodega_id} onChange={e => setData('bodega_id', e.target.value)} required>
                                    <option value="">— Seleccionar —</option>
                                    {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                                </select>
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Números de serie (uno por línea) <span className="text-red-400">*</span></Label>
                            <textarea className="input-field textarea-field" rows={5} value={data.series}
                                onChange={e => setData('series', e.target.value)} placeholder={'SN-0001\nSN-0002'} />
                            {errors.series && <p className="text-red-400 text-xs">{errors.series}</p>}
                        </div>
                        <Button type="submit" disabled={processing}><Plus className="w-4 h-4" /> Registrar</Button>
                    </form>
                )}

                {/* Inspección de bodega */}
                <form onSubmit={buscar} className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1.5">
                        <Label>Estado</Label>
                        <select className="input-field" style={selectStyle} value={filtro.estado} onChange={e => setFiltro(f => ({ ...f, estado: e.target.value }))}>
                            <option value="">Todos</option>
                            {estados.map(s => <option key={s} value={s}>{ETIQUETA[s] ?? s}</option>)}
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Bodega</Label>
                        <select className="input-field" style={selectStyle} value={filtro.bodega_id} onChange={e => setFiltro(f => ({ ...f, bodega_id: e.target.value }))}>
                            <option value="">Todas</option>
                            {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Buscar</Label>
                        <Input value={filtro.buscar} onChange={e => setFiltro(f => ({ ...f, buscar: e.target.value }))} placeholder="Serie, código o producto" />
                    </div>
                    <Button type="submit"><Search className="w-4 h-4" /> Buscar</Button>
                </form>

                {series && (
                    <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ color: 'var(--text-muted)' }}>
                                    <th className="px-5 py-2 text-left">Serie</th><th className="px-3 py-2 text-left">Producto</th>
                                    <th className="px-3 py-2 text-left">Bodega</th><th className="px-3 py-2 text-left">Estado</th>
                                    <th className="px-3 py-2 text-left">Registrada</th><th className="px-5 py-2 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {series.data.map(s => (
                                    <tr key={s.id} className="border-t" style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                        <td className="px-5 py-2 font-mono">{s.numero_serie}</td>
                                        <td className="px-3 py-2">{s.producto}</td>
                                        <td className="px-3 py-2">{s.bodega}</td>
                                        <td className="px-3 py-2">{ETIQUETA[s.estado] ?? s.estado}</td>
                                        <td className="px-3 py-2">{s.fecha}</td>
                                        <td className="px-5 py-2 text-right">
                                            {puede('editar') && (
                                                <select className="input-field" style={{ ...selectStyle, padding: '2px 6px' }} value={s.estado}
                                                    onChange={e => router.patch(route('inventario.series.estado', s.id), { estado: e.target.value }, { preserveScroll: true })}>
                                                    {estados.map(x => <option key={x} value={x}>{ETIQUETA[x] ?? x}</option>)}
                                                </select>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {series.data.length === 0 && (
                                    <tr><td colSpan={6} className="px-5 py-4" style={{ color: 'var(--text-muted)' }}>Sin resultados.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
