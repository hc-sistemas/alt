import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Search, Plus, FileSpreadsheet } from 'lucide-react'
import type { Producto, KardexMovimientoExtendido, PaginatedData, PageProps } from '@/types'

interface KardexResultado {
    producto: Producto
    movimientos: KardexMovimientoExtendido[]
    saldo_anterior: number
}

interface Props extends PageProps {
    resultados: KardexResultado[]
    productos_paginados: PaginatedData<Producto> | null
    bodegas: { id: number; nombre: string }[]
    filters: {
        buscar?: string
        bodega_id?: string
        fecha_desde?: string
        fecha_hasta?: string
        tipo?: string
    }
}

const TIPO_OPTIONS = [
    { value: '', label: 'Todos los tipos' },
    { value: 'entrada', label: 'Entrada' },
    { value: 'salida', label: 'Salida' },
    { value: 'traslado', label: 'Traslado' },
    { value: 'ajuste', label: 'Ajuste' },
    { value: 'reserva', label: 'Reserva' },
]

export default function KardexIndex() {
    const { resultados, productos_paginados, bodegas, filters } = usePage<Props>().props

    const [buscar, setBuscar]           = useState(filters.buscar ?? '')
    const [bodegaId, setBodegaId]       = useState(filters.bodega_id ?? '')
    const [fechaDesde, setFechaDesde]   = useState(filters.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta]   = useState(filters.fecha_hasta ?? '')
    const [tipo, setTipo]               = useState(filters.tipo ?? '')
    const [fechaError, setFechaError]   = useState<string | null>(null)

    const handleBuscar = () => {
        if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
            setFechaError('La fecha "desde" no puede ser posterior a "hasta"')
            return
        }
        setFechaError(null)
        router.get(route('inventario.kardex.index'), {
            buscar:      buscar || undefined,
            bodega_id:   bodegaId || undefined,
            fecha_desde: fechaDesde || undefined,
            fecha_hasta: fechaHasta || undefined,
            tipo:        tipo || undefined,
        }, { preserveState: false })
    }

    const fmtQty = (n: number | string | null | undefined) =>
        n !== null && n !== undefined ? Number(n).toFixed(0) : '—'
    const fmtMoney = (n: number | string | null | undefined) =>
        n !== null && n !== undefined ? Number(n).toFixed(2) : '—'

    const fmtFecha = (fecha: string, hora?: string | null) => {
        const [y, m, d] = fecha.split('-')
        const f = `${d}/${m}/${y.slice(2)}`
        return hora ? `${f} ${hora.substring(0, 5)}` : f
    }

    const thBase = 'px-2.5 py-2 font-medium text-xs uppercase tracking-wider whitespace-nowrap'
    const tdBase = 'px-2.5 py-2'
    const groupBorder = '2px solid var(--border)'
    const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }

    const haBuscado = resultados.length > 0
        || (productos_paginados !== null && productos_paginados.total > 0)
        || Object.values(filters).some(v => v !== undefined && v !== '')

    const redirectTo = (() => {
        const params = new URLSearchParams(
            Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v !== undefined && v !== '')
            ) as Record<string, string>
        )
        return route('inventario.kardex.index') + (params.toString() ? '?' + params.toString() : '')
    })()

    return (
        <AppLayout title="Kardex">
            <Head title="Kardex" />
            <PageHeader
                title="Kardex de Movimientos"
                description="Historial de entradas y salidas de stock por producto"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Kardex' }]}
            />

            <div className="p-6 space-y-6">
                {/* Cabecera de filtros */}
                <div className="flex gap-3 flex-wrap items-end">
                    <div className="flex-1 min-w-55">
                        <label className="block text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>BUSCAR POR</label>
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={buscar}
                                onChange={e => setBuscar(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && handleBuscar()}
                                placeholder="Código o nombre del producto..."
                                className="pl-9"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>BODEGA</label>
                        <select value={bodegaId} onChange={e => setBodegaId(e.target.value)}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm" style={selectStyle}>
                            <option value="">Todas las bodegas</option>
                            {bodegas.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>TIPO</label>
                        <select value={tipo} onChange={e => setTipo(e.target.value)}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm" style={selectStyle}>
                            {TIPO_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>DESDE</label>
                        <input type="date" value={fechaDesde} onChange={e => setFechaDesde(e.target.value)}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm" style={selectStyle} />
                    </div>
                    <div>
                        <label className="block text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>AL</label>
                        <input type="date" value={fechaHasta} onChange={e => setFechaHasta(e.target.value)}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm" style={selectStyle} />
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={handleBuscar}>
                            <Search className="w-4 h-4 mr-1" />
                            Buscar
                        </Button>
                        <Button variant="outline" disabled title="Próximamente">
                            <FileSpreadsheet className="w-4 h-4 mr-1" />
                            Excel
                        </Button>
                    </div>
                </div>

                {fechaError && (
                    <p className="text-xs mt-1" style={{ color: '#dc2626' }}>{fechaError}</p>
                )}

                {/* Estado vacío */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ingresa un término de búsqueda para consultar el kárdex.
                        </p>
                        <p className="text-xs mt-2" style={{ color: 'var(--text-muted)' }}>
                            O ve directamente a{' '}
                            <Link href={route('inventario.kardex.saldos')} className="underline" style={{ color: 'var(--primary)' }}>
                                Saldos de Inventario
                            </Link>
                        </p>
                    </div>
                )}

                {/* Sin resultados */}
                {haBuscado && resultados.length === 0 && (
                    <div className="text-center py-16">
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            No se encontraron productos con ese criterio.
                        </p>
                    </div>
                )}

                {/* Bloques de resultados */}
                {resultados.map(({ producto, movimientos, saldo_anterior }) => (
                    <ProductoKardexBlock
                        key={producto.id}
                        producto={producto}
                        movimientos={movimientos}
                        saldoAnterior={saldo_anterior}
                        thBase={thBase}
                        tdBase={tdBase}
                        groupBorder={groupBorder}
                        fmtQty={fmtQty}
                        fmtMoney={fmtMoney}
                        fmtFecha={fmtFecha}
                        redirectTo={redirectTo}
                    />
                ))}

                {/* Paginación de productos */}
                {productos_paginados && productos_paginados.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Productos {productos_paginados.from}–{productos_paginados.to} de {productos_paginados.total}
                        </p>
                        <div className="flex gap-1">
                            {productos_paginados.links.map((link, i) => (
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

function ProductoKardexBlock({
    producto,
    movimientos,
    saldoAnterior,
    thBase,
    tdBase,
    groupBorder,
    fmtQty,
    fmtMoney,
    fmtFecha,
    redirectTo,
}: {
    producto: Producto
    movimientos: KardexMovimientoExtendido[]
    saldoAnterior: number
    thBase: string
    tdBase: string
    groupBorder: string
    fmtQty: (n: number | string | null | undefined) => string
    fmtMoney: (n: number | string | null | undefined) => string
    fmtFecha: (fecha: string, hora?: string | null) => string
    redirectTo: string
}) {
    const totalIngresosCant = movimientos.reduce((s, m) => s + (m.es_ingreso === true ? Number(m.cantidad) : 0), 0)
    const totalEgresosCant  = movimientos.reduce((s, m) => s + (m.es_ingreso === false ? Number(m.cantidad) : 0), 0)
    const totalIngresosCost = movimientos.reduce((s, m) => s + (m.es_ingreso === true ? Number(m.costo_total) : 0), 0)
    const totalEgresosCost  = movimientos.reduce((s, m) => s + (m.es_ingreso === false ? Number(m.costo_total) : 0), 0)
    const saldoFinal        = movimientos.length > 0 ? movimientos[movimientos.length - 1].saldo_posterior : saldoAnterior

    return (
        <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
            {/* Cabecera del bloque */}
            <div className="flex items-center justify-between px-4 py-3" style={{ background: 'var(--bg-card)' }}>
                <div>
                    <span className="text-xs font-mono mr-2" style={{ color: 'var(--text-muted)' }}>{producto.codigo}</span>
                    <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>{producto.nombre}</span>
                </div>
                <Link href={route('inventario.kardex.ajuste', { producto_id: producto.id, redirect_to: redirectTo })}>
                    <Button size="sm">
                        <Plus className="w-3.5 h-3.5 mr-1" />
                        Registrar Ajuste
                    </Button>
                </Link>
            </div>

            {/* Tabla */}
            <div className="overflow-x-auto">
                <table className="w-full text-xs" style={{ minWidth: 1060 }}>
                    <thead>
                        <tr style={{ background: 'var(--bg-card)' }}>
                            <th colSpan={5} className={`${thBase} text-center`}
                                style={{ color: 'var(--text-main)', borderBottom: '1px solid var(--border)', borderRight: groupBorder }}>
                                Documento
                            </th>
                            <th className={`${thBase} text-center`}
                                style={{ color: 'var(--text-main)', borderBottom: '1px solid var(--border)', borderRight: groupBorder }}>
                                Transacción
                            </th>
                            <th colSpan={3} className={`${thBase} text-center`}
                                style={{ color: '#059669', borderBottom: '1px solid var(--border)', borderRight: groupBorder, background: 'color-mix(in srgb, #059669 6%, var(--bg-card))' }}>
                                Ingresos (+)
                            </th>
                            <th colSpan={3} className={`${thBase} text-center`}
                                style={{ color: '#dc2626', borderBottom: '1px solid var(--border)', borderRight: groupBorder, background: 'color-mix(in srgb, #dc2626 6%, var(--bg-card))' }}>
                                Egresos (-)
                            </th>
                            <th className={`${thBase} text-center`}
                                style={{ color: 'var(--text-main)', borderBottom: '1px solid var(--border)', background: 'color-mix(in srgb, #2563eb 6%, var(--bg-card))' }}>
                                Saldos
                            </th>
                        </tr>
                        <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                            <th className={`${thBase} text-left`} style={{ color: 'var(--text-muted)' }}>No</th>
                            <th className={`${thBase} text-left`} style={{ color: 'var(--text-muted)' }}>Fecha de transacción</th>
                            <th className={`${thBase} text-left`} style={{ color: 'var(--text-muted)' }}>Tipo</th>
                            <th className={`${thBase} text-left`} style={{ color: 'var(--text-muted)' }}>Documento No</th>
                            <th className={`${thBase} text-left`} style={{ color: 'var(--text-muted)', borderRight: groupBorder }}>Observaciones</th>
                            <th className={`${thBase} text-left`} style={{ color: 'var(--text-muted)', borderRight: groupBorder }}>Tipo</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)' }}>Cantidad</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)' }}>Costo/U</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)', borderRight: groupBorder }}>Costo/T</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)' }}>Cantidad</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)' }}>Costo/U</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)', borderRight: groupBorder }}>Costo/T</th>
                            <th className={`${thBase} text-right`} style={{ color: 'var(--text-muted)' }}>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        {/* Fila SALDO ANTERIOR */}
                        <tr className="border-t" style={{ borderColor: 'var(--border)', background: 'color-mix(in srgb, var(--primary) 4%, var(--bg-main))' }}>
                            <td className={tdBase}></td>
                            <td className={tdBase}></td>
                            <td className={tdBase}></td>
                            <td className={tdBase}></td>
                            <td className={tdBase} style={{ borderRight: groupBorder }}></td>
                            <td className={`${tdBase} font-bold text-xs uppercase`} style={{ color: 'var(--text-main)', borderRight: groupBorder }}>
                                SALDO ANTERIOR
                            </td>
                            <td className={`${tdBase} text-right`} style={{ color: 'var(--text-muted)' }}>—</td>
                            <td className={`${tdBase} text-right`} style={{ color: 'var(--text-muted)' }}>—</td>
                            <td className={`${tdBase} text-right`} style={{ color: 'var(--text-muted)', borderRight: groupBorder }}>—</td>
                            <td className={`${tdBase} text-right`} style={{ color: 'var(--text-muted)' }}>—</td>
                            <td className={`${tdBase} text-right`} style={{ color: 'var(--text-muted)' }}>—</td>
                            <td className={`${tdBase} text-right`} style={{ color: 'var(--text-muted)', borderRight: groupBorder }}>—</td>
                            <td className={`${tdBase} text-right font-mono font-bold`} style={{ color: 'var(--text-main)' }}>
                                {fmtQty(saldoAnterior)}
                            </td>
                        </tr>

                        {movimientos.length === 0 ? (
                            <tr>
                                <td colSpan={13} className="text-center py-6 text-sm" style={{ color: 'var(--text-muted)' }}>
                                    No hay movimientos para este producto con los filtros aplicados.
                                </td>
                            </tr>
                        ) : movimientos.map((m, idx) => (
                            <tr key={m.id}
                                className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                style={{ borderColor: 'var(--border)' }}>
                                <td className={`${tdBase} font-mono`} style={{ color: 'var(--text-muted)' }}>{idx + 1}</td>
                                <td className={`${tdBase} whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                    {fmtFecha(m.fecha, m.hora)}
                                </td>
                                <td className={`${tdBase} whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                    {m.documento_tipo ?? '—'}
                                </td>
                                <td className={`${tdBase} whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                    {m.documento_numero ?? (m.documento_id ? `#${m.documento_id}` : '—')}
                                </td>
                                <td className={`${tdBase} max-w-35 truncate`}
                                    style={{ color: 'var(--text-muted)', borderRight: groupBorder }}
                                    title={m.observacion ?? ''}>
                                    {m.observacion ?? '—'}
                                </td>
                                <td className={`${tdBase} text-xs uppercase whitespace-nowrap`}
                                    style={{ color: 'var(--text-main)', borderRight: groupBorder }}>
                                    {m.tipo_descriptivo}
                                </td>

                                {/* Ingresos */}
                                <td className={`${tdBase} text-right font-mono`}
                                    style={{ color: m.es_ingreso === true ? '#059669' : 'var(--text-muted)' }}>
                                    {m.es_ingreso === true ? fmtQty(m.cantidad) : '—'}
                                </td>
                                <td className={`${tdBase} text-right font-mono`}
                                    style={{ color: m.es_ingreso === true ? '#059669' : 'var(--text-muted)' }}>
                                    {m.es_ingreso === true ? fmtMoney(m.costo_unitario) : '—'}
                                </td>
                                <td className={`${tdBase} text-right font-mono`}
                                    style={{ color: m.es_ingreso === true ? '#059669' : 'var(--text-muted)', borderRight: groupBorder }}>
                                    {m.es_ingreso === true ? fmtMoney(m.costo_total) : '—'}
                                </td>

                                {/* Egresos */}
                                <td className={`${tdBase} text-right font-mono`}
                                    style={{ color: m.es_ingreso === false ? '#dc2626' : 'var(--text-muted)' }}>
                                    {m.es_ingreso === false ? fmtQty(m.cantidad) : '—'}
                                </td>
                                <td className={`${tdBase} text-right font-mono`}
                                    style={{ color: m.es_ingreso === false ? '#dc2626' : 'var(--text-muted)' }}>
                                    {m.es_ingreso === false ? fmtMoney(m.costo_unitario) : '—'}
                                </td>
                                <td className={`${tdBase} text-right font-mono`}
                                    style={{ color: m.es_ingreso === false ? '#dc2626' : 'var(--text-muted)', borderRight: groupBorder }}>
                                    {m.es_ingreso === false ? fmtMoney(m.costo_total) : '—'}
                                </td>

                                {/* Saldo */}
                                <td className={`${tdBase} text-right font-mono font-semibold`} style={{ color: 'var(--text-main)' }}>
                                    {fmtQty(m.saldo_posterior)}
                                </td>
                            </tr>
                        ))}

                        {/* Fila TOTAL */}
                        <tr className="border-t-2" style={{ borderColor: 'var(--border)', background: 'color-mix(in srgb, var(--primary) 6%, var(--bg-card))' }}>
                            <td className={tdBase}></td>
                            <td className={tdBase}></td>
                            <td className={tdBase}></td>
                            <td className={tdBase}></td>
                            <td className={tdBase} style={{ borderRight: groupBorder }}></td>
                            <td className={`${tdBase} font-bold text-xs uppercase`} style={{ color: 'var(--text-main)', borderRight: groupBorder }}>
                                TOTAL
                            </td>
                            <td className={`${tdBase} text-right font-mono font-bold`} style={{ color: '#059669' }}>
                                {totalIngresosCant > 0 ? fmtQty(totalIngresosCant) : '—'}
                            </td>
                            <td className={`${tdBase} text-right font-mono`} style={{ color: 'var(--text-muted)' }}>—</td>
                            <td className={`${tdBase} text-right font-mono font-bold`} style={{ color: '#059669', borderRight: groupBorder }}>
                                {totalIngresosCost > 0 ? fmtMoney(totalIngresosCost) : '—'}
                            </td>
                            <td className={`${tdBase} text-right font-mono font-bold`} style={{ color: '#dc2626' }}>
                                {totalEgresosCant > 0 ? fmtQty(totalEgresosCant) : '—'}
                            </td>
                            <td className={`${tdBase} text-right font-mono`} style={{ color: 'var(--text-muted)' }}>—</td>
                            <td className={`${tdBase} text-right font-mono font-bold`} style={{ color: '#dc2626', borderRight: groupBorder }}>
                                {totalEgresosCost > 0 ? fmtMoney(totalEgresosCost) : '—'}
                            </td>
                            <td className={`${tdBase} text-right font-mono font-bold`} style={{ color: 'var(--text-main)' }}>
                                {fmtQty(saldoFinal)}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    )
}
