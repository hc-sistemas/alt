import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Search, FileSpreadsheet } from 'lucide-react'
import type { PaginatedData, PageProps } from '@/types'

interface BodegaCol { id: number; empresa_id: number; nombre: string }
interface FilaConsolidado {
    id: number
    codigo: string
    nombre: string
    marca_nombre: string
    unidad: string | null
    stock: Record<number, number>
    total_empresa: Record<number, number>
    total: number
}

interface Props extends PageProps {
    productos: PaginatedData<FilaConsolidado> | null
    bodegas: BodegaCol[]
    empresas: { id: number; nombre: string }[]
    filters: { search?: string }
}

const fmt = (n: number) => Number(n).toLocaleString('es-EC', { maximumFractionDigits: 2 })

export default function ConsolidadoIndex() {
    const { productos, bodegas, empresas, filters } = usePage<Props>().props

    const [search, setSearch]   = useState(filters.search ?? '')

    const filtros = () => ({ search: search || undefined })

    function buscar() {
        router.get(route('inventario.consolidado.index'), { ...filtros(), buscado: '1' }, { preserveState: false })
    }

    const th = 'px-3 py-2 font-medium text-xs uppercase tracking-wider whitespace-nowrap text-center'
    const border = { borderColor: 'var(--border)' }

    return (
        <AppLayout title="Inventario Consolidado">
            <Head title="Inventario Consolidado" />
            <PageHeader
                title="Inventario Consolidado"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Inventario Consolidado' }]}
                actions={
                    productos ? (
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {productos.total} producto{productos.total === 1 ? '' : 's'}
                        </span>
                    ) : undefined
                }
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input value={search} onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && buscar()}
                                placeholder="Código, descripción o marca..." className="pl-9 w-72 rounded-r-none border-r-0" />
                        </div>
                        <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={buscar} title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>

                    <button className="flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0"
                        style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A' }}
                        onClick={() => { window.location.href = route('inventario.consolidado.excel', filtros()) }}
                        title="Excel (todos los productos del filtro)">
                        <FileSpreadsheet className="w-4 h-4" />
                    </button>
                </div>

                {!productos && (
                    <p className="text-sm text-center py-16" style={{ color: 'var(--text-muted)' }}>
                        Presiona buscar para ver el inventario, o descarga el Excel con todos los productos.
                    </p>
                )}

                {productos && (
                    <div className="rounded-xl border overflow-x-auto" style={border}>
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', color: 'var(--text-muted)' }}>
                                    <th className={th} rowSpan={2}>#</th>
                                    <th className={th} rowSpan={2}>Código</th>
                                    <th className={th} rowSpan={2}>Producto</th>
                                    <th className={th} rowSpan={2}>Marca</th>
                                    {empresas.map(e => (
                                        <th key={e.id} className={th} style={{ ...border, borderLeftWidth: 1 }}
                                            colSpan={bodegas.filter(b => b.empresa_id === e.id).length + 1}>
                                            {e.nombre}
                                        </th>
                                    ))}
                                    <th className={th} rowSpan={2}>Total general</th>
                                </tr>
                                <tr style={{ background: 'var(--bg-card)', color: 'var(--text-muted)' }}>
                                    {empresas.map(e => (
                                        <FragmentoCab key={e.id} bodegas={bodegas.filter(b => b.empresa_id === e.id)} th={th} />
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {productos.data.length === 0 ? (
                                    <tr><td colSpan={5 + bodegas.length + empresas.length} className="text-center py-16 text-sm"
                                        style={{ color: 'var(--text-muted)' }}>Sin resultados.</td></tr>
                                ) : productos.data.map((p, idx) => (
                                    <tr key={p.id} className="border-t" style={border}>
                                        <td className="px-3 py-2 text-center" style={{ color: 'var(--text-muted)' }}>{productos.from + idx}</td>
                                        <td className="px-3 py-2 font-mono whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>{p.codigo}</td>
                                        <td className="px-3 py-2 font-medium" style={{ color: 'var(--text-main)' }}>{p.nombre}</td>
                                        <td className="px-3 py-2 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>{p.marca_nombre}</td>
                                        {empresas.map(e => (
                                            <FragmentoFila key={e.id} fila={p} empresaId={e.id}
                                                bodegas={bodegas.filter(b => b.empresa_id === e.id)} />
                                        ))}
                                        <td className="px-3 py-2 text-right font-mono font-semibold" style={{ color: 'var(--text-main)' }}>{fmt(p.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {productos && productos.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {productos.from}–{productos.to} de {productos.total}
                        </p>
                        <div className="flex gap-1 flex-wrap">
                            {productos.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`px-3 py-1 rounded border text-xs ${link.active ? 'border-amber-500 bg-amber-500 text-black font-medium' : ''}`}
                                        style={!link.active ? { ...border, color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                ) : (
                                    <span key={i} className="px-3 py-1 rounded border text-xs opacity-40"
                                        style={{ ...border, color: 'var(--text-muted)' }}
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

function FragmentoCab({ bodegas, th }: { bodegas: BodegaCol[]; th: string }) {
    return (
        <>
            {bodegas.map(b => <th key={b.id} className={th}>{b.nombre}</th>)}
            <th className={th}>Total</th>
        </>
    )
}

function FragmentoFila({ fila, empresaId, bodegas }: { fila: FilaConsolidado; empresaId: number; bodegas: BodegaCol[] }) {
    return (
        <>
            {bodegas.map(b => {
                const v = fila.stock[b.id] ?? 0
                return (
                    <td key={b.id} className="px-3 py-2 text-right font-mono"
                        style={{ color: v === 0 ? 'var(--text-muted)' : 'var(--text-main)' }}>{fmt(v)}</td>
                )
            })}
            <td className="px-3 py-2 text-right font-mono font-medium" style={{ color: 'var(--text-main)' }}>
                {fmt(fila.total_empresa[empresaId] ?? 0)}
            </td>
        </>
    )
}
