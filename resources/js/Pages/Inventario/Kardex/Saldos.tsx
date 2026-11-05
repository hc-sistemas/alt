import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Search, Plus, Pencil, FileText, FileSpreadsheet, Eye, X } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { InventarioSaldo, PaginatedData, PageProps } from '@/types'

interface SaldoRow extends InventarioSaldo {
    producto_codigo?: string
    producto_nombre?: string
    producto_stock_minimo?: number
}

interface FichaProducto {
    codigo: string
    codigo_externo: string | null
    nombre: string
    descripcion: string | null
    tipo: string
    unidad: string
    marca: string | null
    marca_fabricante: string | null
    categoria: string | null
    requiere_serie: boolean
    peso: number | null
    costo: number | null
    pvp: number | null
    pvd: number | null
    descuento_maximo: number | null
    porcentaje_iva: number | null
    tiene_ice: boolean
    porcentaje_ice: number | null
    stock_minimo: number | null
    stock_maximo: number | null
    ref_importacion: string | null
    estado: boolean
    cuenta_inventario: string | null
    cuenta_costo_ventas: string | null
    cuenta_ventas: string | null
    stock: { bodega: string; stock_actual: number; costo_promedio: number }[]
}

const dash = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : String(v))
const money = (v: number | null) => (v === null || v === undefined ? '—' : `$ ${Number(v).toFixed(2)}`)
const pct = (v: number | null) => (v === null || v === undefined ? '—' : `${Number(v)} %`)

function fichaSecciones(f: FichaProducto): { titulo: string; campos: [string, string][] }[] {
    return [
        { titulo: 'General', campos: [
            ['Código externo', dash(f.codigo_externo)],
            ['Tipo', dash(f.tipo)],
            ['Unidad', dash(f.unidad)],
            ['Marca', dash(f.marca)],
            ['Fabricante', dash(f.marca_fabricante)],
            ['Categoría', dash(f.categoria)],
            ['Peso', dash(f.peso)],
            ['Requiere serie', f.requiere_serie ? 'Sí' : 'No'],
            ['Ref. importación', dash(f.ref_importacion)],
            ['Estado', f.estado ? 'Activo' : 'Inactivo'],
        ] },
        { titulo: 'Precios e impuestos', campos: [
            ['Costo', money(f.costo)],
            ['PVP', money(f.pvp)],
            ['PVD', money(f.pvd)],
            ['Descuento máximo', pct(f.descuento_maximo)],
            ['IVA', pct(f.porcentaje_iva)],
            ['ICE', f.tiene_ice ? pct(f.porcentaje_ice) : 'No aplica'],
        ] },
        { titulo: 'Stock', campos: [
            ['Stock mínimo', dash(f.stock_minimo)],
            ['Stock máximo', dash(f.stock_maximo)],
        ] },
        { titulo: 'Contabilidad', campos: [
            ['Cuenta inventario', dash(f.cuenta_inventario)],
            ['Cuenta costo de ventas', dash(f.cuenta_costo_ventas)],
            ['Cuenta ventas', dash(f.cuenta_ventas)],
        ] },
    ]
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

    const [fichaAbierta, setFichaAbierta]     = useState(false)
    const [ficha, setFicha]                   = useState<FichaProducto | null>(null)
    const [cargandoFicha, setCargandoFicha]   = useState(false)
    const [errorFicha, setErrorFicha]         = useState('')

    async function verFicha(productoId: number) {
        setFichaAbierta(true)
        setFicha(null)
        setErrorFicha('')
        setCargandoFicha(true)
        try {
            const res = await fetch(route('inventario.productos.ficha', productoId), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
            if (!res.ok) throw new Error()
            setFicha(await res.json())
        } catch {
            setErrorFicha('No se pudo cargar la ficha del producto.')
        } finally {
            setCargandoFicha(false)
        }
    }

    const haBuscado = saldos !== null

    function buscar() {
        router.get(route('inventario.kardex.saldos'), {
            search: search || undefined,
            bodega_id: bodegaId || undefined,
            solo_criticos: soloCriticos ? '1' : undefined,
            buscado: '1',
        }, { preserveState: false })
    }

    function filtrosActivos() {
        return {
            search: search || undefined,
            bodega_id: bodegaId || undefined,
            solo_criticos: soloCriticos ? '1' : undefined,
        }
    }

    // El servidor arma el Excel con todos los registros del filtro (no solo la página visible).
    function exportarExcel() {
        window.location.href = route('inventario.kardex.excel.saldos', filtrosActivos())
    }

    return (
        <AppLayout title="Inventario General">
            <Head title="Inventario General" />
            <PageHeader
                title="Inventario General"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Kárdex' }, { label: 'Inventario General' }]}
                actions={
                    <div className="flex items-center gap-3">
                        {saldos && (
                            <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                {saldos.total} producto{saldos.total === 1 ? '' : 's'}
                            </span>
                        )}
                        {puede('crear') && (
                            <Link href={route('inventario.kardex.ajuste')}>
                                <Button>
                                    <Plus className="w-4 h-4" />
                                    Registrar Ajuste
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
                                {['#', 'Código', 'Producto', 'Bodega', 'Cantidad', 'Costo Prom.', 'Valor Total', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {saldos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-16 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        {soloCriticos
                                            ? 'No hay productos con stock crítico.'
                                            : 'No hay saldos registrados aún.'}
                                    </td>
                                </tr>
                            ) : saldos.data.map((saldo, idx) => {
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
                                        <td className="px-3 py-2.5 text-center" style={{ color: 'var(--text-muted)' }}>
                                            {(saldos.from ?? 1) + idx}
                                        </td>
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
                                        <td className="px-3 py-2.5 whitespace-nowrap">
                                            <Button variant="ghost" size="icon" title="Ver ficha del producto"
                                                onClick={() => verFicha(saldo.producto_id)}>
                                                <Eye className="w-3.5 h-3.5" />
                                            </Button>
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
            {fichaAbierta && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                    style={{ background: 'rgba(0,0,0,0.6)' }} onClick={() => setFichaAbierta(false)}>
                    <div className="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl border"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                        onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-5 py-4 border-b sticky top-0"
                            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                            <h2 className="font-semibold" style={{ color: 'var(--text-main)' }}>Ficha del producto</h2>
                            <button onClick={() => setFichaAbierta(false)} title="Cerrar" style={{ color: 'var(--text-muted)' }}>
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-5 space-y-5">
                            {cargandoFicha && <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Cargando...</p>}
                            {errorFicha && <p className="text-sm text-red-500">{errorFicha}</p>}
                            {ficha && (
                                <>
                                    <div>
                                        <p className="text-xs font-mono" style={{ color: 'var(--text-muted)' }}>{ficha.codigo}</p>
                                        <p className="text-lg font-semibold" style={{ color: 'var(--text-main)' }}>{ficha.nombre}</p>
                                        {ficha.descripcion && (
                                            <p className="text-sm mt-1" style={{ color: 'var(--text-muted)' }}>{ficha.descripcion}</p>
                                        )}
                                    </div>
                                    {fichaSecciones(ficha).map(sec => (
                                        <div key={sec.titulo}>
                                            <h3 className="text-xs uppercase tracking-wider font-medium mb-2" style={{ color: 'var(--primary)' }}>
                                                {sec.titulo}
                                            </h3>
                                            <dl className="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3">
                                                {sec.campos.map(([k, v]) => (
                                                    <div key={k}>
                                                        <dt className="text-xs" style={{ color: 'var(--text-muted)' }}>{k}</dt>
                                                        <dd className="text-sm" style={{ color: 'var(--text-main)' }}>{v}</dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        </div>
                                    ))}
                                    <div>
                                        <h3 className="text-xs uppercase tracking-wider font-medium mb-2" style={{ color: 'var(--primary)' }}>
                                            Stock por bodega
                                        </h3>
                                        <table className="w-full text-xs">
                                            <thead>
                                                <tr style={{ color: 'var(--text-muted)' }}>
                                                    <th className="text-left py-1">Bodega</th>
                                                    <th className="text-right py-1">Cantidad</th>
                                                    <th className="text-right py-1">Costo prom.</th>
                                                </tr>
                                            </thead>
                                            <tbody style={{ color: 'var(--text-main)' }}>
                                                {ficha.stock.length === 0 ? (
                                                    <tr><td colSpan={3} className="py-2" style={{ color: 'var(--text-muted)' }}>Sin saldos.</td></tr>
                                                ) : ficha.stock.map(s => (
                                                    <tr key={s.bodega} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                                        <td className="py-1.5">{s.bodega}</td>
                                                        <td className="py-1.5 text-right font-mono">{Number(s.stock_actual).toFixed(0)}</td>
                                                        <td className="py-1.5 text-right font-mono">{Number(s.costo_promedio).toFixed(2)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
            <PdfPreviewModal
                abierto={pdfModal}
                onCerrar={() => setPdfModal(false)}
                url={pdfModal ? route('inventario.kardex.reporte.saldos', filtrosActivos()) : ''}
                titulo="Inventario General"
                nombreDescarga="kardex_saldos.pdf"
            />
        </AppLayout>
    )
}
