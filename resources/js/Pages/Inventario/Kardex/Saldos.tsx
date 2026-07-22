import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Search, ArrowUpDown, Plus, Pencil, FileSpreadsheet } from 'lucide-react'
import type { InventarioSaldo, PaginatedData, PageProps } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'

interface SaldoRow extends InventarioSaldo {
    producto_codigo?: string
    producto_nombre?: string
    producto_stock_minimo?: number
}

interface Props extends PageProps {
    saldos: PaginatedData<SaldoRow>
    bodegas: { id: number; nombre: string }[]
    filters: { search?: string; bodega_id?: string; solo_criticos?: string }
}

export default function KardexSaldos() {
    const { saldos, bodegas, filters } = usePage<Props>().props
    const { puede } = usePermiso('inventario')

    const [search, setSearch]         = useState(filters.search ?? '')
    const [bodegaId, setBodegaId]     = useState(filters.bodega_id ?? '')
    const [soloCriticos, setSoloCriticos] = useState(filters.solo_criticos === '1')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
    const isFirstRender = useRef(true)

    useEffect(() => {
        if (isFirstRender.current) { isFirstRender.current = false; return }
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.kardex.saldos'), {
                search: search || undefined,
                bodega_id: bodegaId || undefined,
                solo_criticos: soloCriticos ? '1' : undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, bodegaId, soloCriticos])

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = saldos.data.map(s => {
            const nombre     = s.producto_nombre ?? s.producto?.nombre ?? '—'
            const codigo     = s.producto_codigo ?? s.producto?.codigo ?? '—'
            const bodega     = s.bodega?.nombre ?? `Bodega #${s.bodega_id}`
            const valorTotal = Number(s.stock_actual) * Number(s.costo_promedio)
            return {
                'Código Producto': codigo,
                'Producto':        nombre,
                'Bodega':          bodega,
                'Cantidad':        Number(s.stock_actual),
                'Costo Promedio':  Number(s.costo_promedio),
                'Valor Total':     Number(valorTotal.toFixed(2)),
            }
        })
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Kárdex Saldos')
        XLSX.writeFile(wb, 'kardex_saldos.xlsx')
    }

    return (
        <AppLayout title="Inventario General">
            <Head title="Inventario General" />
            <PageHeader
                title="Inventario General"
                description="Stock actual por producto y bodega"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Kárdex' }, { label: 'Inventario General' }]}
                actions={
                    <div className="flex items-center gap-2">
                        <Link href={route('inventario.kardex.index')}>
                            <Button variant="outline">
                                <ArrowUpDown className="w-4 h-4" />
                                Ver Movimientos
                            </Button>
                        </Link>
                        {puede('crear') && (
                            <Link href={route('inventario.kardex.ajuste')}>
                                <Button>
                                    <Plus className="w-4 h-4" />
                                    Registrar
                                </Button>
                            </Link>
                        )}
                    </div>
                }
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <select value={bodegaId} onChange={e => setBodegaId(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todas las bodegas</option>
                        {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <label className="flex items-center gap-2 cursor-pointer select-none text-sm shrink-0"
                        style={{ color: 'var(--text-muted)' }}>
                        <input type="checkbox" checked={soloCriticos}
                            onChange={e => setSoloCriticos(e.target.checked)}
                            className="rounded" />
                        Solo críticos
                    </label>

                    <div className="flex items-center gap-3 shrink-0 ml-auto">
                        <div className="flex shrink-0" role="group">
                            <div className="relative">
                                <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                                <Input value={search} onChange={e => setSearch(e.target.value)}
                                    placeholder="Código o nombre..." className="pl-9 w-72 rounded-r-none border-r-0" />
                            </div>
                            <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                                style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                                title="Buscar">
                                <Search className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="flex shrink-0" role="group">
                            <button className="flex items-center justify-center w-9 h-9 rounded-md border text-sm font-medium"
                                style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A', transition: 'background 0.2s' }}
                                onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                                onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                                onClick={exportarExcel}
                                title="Excel">
                                <FileSpreadsheet className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Código', 'Producto', 'Bodega', 'Cantidad', 'Costo Prom.', 'Valor Total', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {saldos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-16 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        {soloCriticos
                                            ? 'No hay productos con stock crítico.'
                                            : 'No hay saldos registrados aún.'}
                                    </td>
                                </tr>
                            ) : saldos.data.map(saldo => {
                                const valorTotal = Number(saldo.stock_actual) * Number(saldo.costo_promedio)
                                const esCritico = saldo.producto_stock_minimo !== undefined &&
                                    Number(saldo.stock_actual) <= Number(saldo.producto_stock_minimo)
                                const nombre = saldo.producto_nombre ?? saldo.producto?.nombre ?? '—'
                                const codigo = saldo.producto_codigo ?? saldo.producto?.codigo ?? '—'
                                const bodegaNombre = saldo.bodega?.nombre ?? `Bodega #${saldo.bodega_id}`

                                return (
                                    <tr key={saldo.id}
                                        className="border-t transition-colors"
                                        style={{
                                            borderColor: 'var(--border)',
                                            background: esCritico ? 'rgba(245, 158, 11, 0.05)' : undefined,
                                        }}>
                                        <td className="px-3 py-2.5 font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {codigo}
                                        </td>
                                        <td className="px-3 py-2.5 max-w-45">
                                            <span className="font-medium truncate block" style={{ color: 'var(--text-main)' }}>
                                                {nombre}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2.5 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                            {bodegaNombre}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono">
                                            <div className="flex items-center justify-end gap-1.5">
                                                {esCritico && (
                                                    <span className="px-1.5 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                                        CRÍTICO
                                                    </span>
                                                )}
                                                <span style={{ color: esCritico ? '#EF4444' : 'var(--text-main)' }}>
                                                    {Number(saldo.stock_actual).toFixed(0)}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {Number(saldo.costo_promedio).toFixed(2)}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono font-medium" style={{ color: 'var(--text-main)' }}>
                                            {valorTotal.toFixed(2)}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {puede('crear') && (
                                                <Link href={route('inventario.kardex.ajuste', {
                                                    producto_id: saldo.producto_id,
                                                    bodega_id: saldo.bodega_id,
                                                })}>
                                                    <Button variant="ghost" size="icon" title="Registrar ajuste">
                                                        <Pencil className="w-3.5 h-3.5" />
                                                    </Button>
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {saldos.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {saldos.from}–{saldos.to} de {saldos.total}
                        </p>
                        <div className="flex gap-1">
                            {saldos.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`px-3 py-1 rounded border text-xs transition-colors ${link.active ? 'border-amber-500 bg-amber-500 text-black font-medium' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}
                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 rounded border text-xs opacity-40"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
