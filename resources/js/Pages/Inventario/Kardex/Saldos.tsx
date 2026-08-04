import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Search, ArrowUpDown, Plus, Pencil, FileText, FileSpreadsheet } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { InventarioSaldo, PaginatedData, PageProps } from '@/types'

interface SaldoRow extends InventarioSaldo {
    producto_codigo?: string
    producto_nombre?: string
    producto_stock_minimo?: number
}

interface Props extends PageProps {
    saldos: PaginatedData<SaldoRow> | null
    bodegas: { id: number; nombre: string }[]
    filters: { search?: string; bodega_id?: string; solo_criticos?: string }
}

export default function KardexSaldos() {
    const { saldos, bodegas, filters } = usePage<Props>().props
    const { puede } = usePermiso('inventario')

    const [search, setSearch]         = useState(filters.search ?? '')
    const [bodegaId, setBodegaId]     = useState(filters.bodega_id ?? '')
    const [soloCriticos, setSoloCriticos] = useState(filters.solo_criticos === '1')
    const [pdfModal, setPdfModal]     = useState(false)

    const haBuscado = saldos !== null

    function buscar() {
        router.get(route('inventario.kardex.saldos'), {
            search: search || undefined,
            bodega_id: bodegaId || undefined,
            solo_criticos: soloCriticos ? '1' : undefined,
            buscado: '1',
        }, { preserveState: false })
    }

    async function exportarExcel() {
        if (!saldos) return
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
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Kárdex' }, { label: 'Inventario General' }]}
                actions={
                    puede('crear') ? (
                        <Link href={route('inventario.kardex.ajuste')}>
                            <Button>
                                <Plus className="w-4 h-4" />
                                Registrar Ajuste
                            </Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <Link href={route('inventario.kardex.index')}>
                        <Button variant="outline">
                            <ArrowUpDown className="w-4 h-4" />
                            Ver Movimientos
                        </Button>
                    </Link>

                    <select value={bodegaId} onChange={e => setBodegaId(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todas las bodegas</option>
                        {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <label className="flex items-center gap-2 cursor-pointer select-none text-sm"
                        style={{ color: 'var(--text-muted)' }}>
                        <input type="checkbox" checked={soloCriticos}
                            onChange={e => setSoloCriticos(e.target.checked)}
                            className="rounded" />
                        Solo críticos
                    </label>

                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input value={search} onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && buscar()}
                                placeholder="Código o nombre..." className="pl-9 w-52 rounded-r-none border-r-0" />
                        </div>
                        <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={buscar}
                            title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="flex items-center gap-2">
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#DC2626', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#B91C1C')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#DC2626')}
                            onClick={() => setPdfModal(true)}>
                            <FileText className="w-4 h-4" />
                            PDF
                        </button>
                        <button className="flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0"
                            style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                            onClick={exportarExcel}
                            disabled={!saldos}
                            title="Excel">
                            <FileSpreadsheet className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {/* Estado inicial: aún no se ha buscado */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar el inventario.
                        </p>
                    </div>
                )}

                {haBuscado && saldos && (
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
                )}

                {haBuscado && saldos && saldos.last_page > 1 && (
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
            <PdfPreviewModal
                abierto={pdfModal}
                onCerrar={() => setPdfModal(false)}
                url={pdfModal ? route('inventario.kardex.reporte.saldos') : ''}
                titulo="Inventario General"
                nombreDescarga="kardex_saldos.pdf"
            />
        </AppLayout>
    )
}
