import { useState } from 'react'
import { router, usePage, Head, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { cn } from '@/lib/utils'
import {
    Search, FileText, Download, ChevronLeft,
    TrendingUp, TrendingDown, ArrowUpDown,
} from 'lucide-react'
import {
    useReactTable,
    getCoreRowModel,
    getSortedRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    flexRender,
    type ColumnDef,
    type SortingState,
} from '@tanstack/react-table'
import type { PageProps } from '@/types'

// ─── Types ────────────────────────────────────────────────────────────────────

interface BancoSimple { id: number; nombre: string; tipo: string }
interface CentroCostoSimple { id: number; nombre: string; codigo: string }

interface Movimiento {
    id: number
    banco: string | null
    tipo: 'ingreso' | 'egreso'
    sub_tipo: string
    fecha: string
    monto: number
    beneficiario: string | null
    descripcion: string | null
    num_documento: string | null
    centro_costo: string | null
    conciliado: boolean
}

interface Totales {
    ingresos: number
    egresos: number
    count: number
}

interface Props extends PageProps {
    bancos: BancoSimple[]
    centrosCosto: CentroCostoSimple[]
    movimientos: Movimiento[]
    totales: Totales
    filtros: Record<string, string>
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const fmt = (n: number | string | null | undefined) =>
    '$' + Number(n ?? 0).toLocaleString('es-EC', { minimumFractionDigits: 2 })

const SUB_TIPO_LABELS: Record<string, string> = {
    transferencia: 'Transferencia',
    cheque:        'Cheque',
    efectivo:      'Efectivo',
    deposito:      'Depósito',
}

// ─── Campo helper ─────────────────────────────────────────────────────────────

function Campo({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="block text-xs font-medium mb-1" style={{ color: 'var(--text-muted)' }}>
                {label}
            </label>
            {children}
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function ConsultaCobrosPagos() {
    const { bancos, centrosCosto, movimientos, totales, filtros } = usePage<Props>().props

    // Filtros locales (inicializados desde server)
    const [bancoCajaId,   setBancoCajaId]   = useState(filtros.banco_caja_id  ?? '')
    const [tipo,          setTipo]          = useState(filtros.tipo            ?? '')
    const [subTipo,       setSubTipo]       = useState(filtros.sub_tipo        ?? '')
    const [fechaDesde,    setFechaDesde]    = useState(filtros.fecha_desde     ?? '')
    const [fechaHasta,    setFechaHasta]    = useState(filtros.fecha_hasta     ?? '')
    const [beneficiario,  setBeneficiario]  = useState(filtros.beneficiario    ?? '')
    const [numDocumento,  setNumDocumento]  = useState(filtros.num_documento   ?? '')
    const [centroCostoId, setCentroCostoId] = useState(filtros.centro_costo_id ?? '')

    const [sorting, setSorting]             = useState<SortingState>([])
    const [globalFilter, setGlobalFilter]   = useState('')

    // ── Buscar ─────────────────────────────────────────────────────────────────
    function buscar() {
        const params: Record<string, string> = {}
        if (bancoCajaId)   params.banco_caja_id   = bancoCajaId
        if (tipo)          params.tipo             = tipo
        if (subTipo)       params.sub_tipo         = subTipo
        if (fechaDesde)    params.fecha_desde      = fechaDesde
        if (fechaHasta)    params.fecha_hasta      = fechaHasta
        if (beneficiario)  params.beneficiario     = beneficiario
        if (numDocumento)  params.num_documento    = numDocumento
        if (centroCostoId) params.centro_costo_id  = centroCostoId
        router.get(route('bancos.reportes.consulta'), params, { preserveState: true })
    }

    function limpiar() {
        setBancoCajaId(''); setTipo(''); setSubTipo('')
        setFechaDesde(''); setFechaHasta(''); setBeneficiario('')
        setNumDocumento(''); setCentroCostoId('')
        router.get(route('bancos.reportes.consulta'))
    }

    // ── Exportar ───────────────────────────────────────────────────────────────
    function exportar(formato: 'excel' | 'pdf') {
        const p = new URLSearchParams()
        if (bancoCajaId)   p.set('banco_caja_id',   bancoCajaId)
        if (tipo)          p.set('tipo',             tipo)
        if (subTipo)       p.set('sub_tipo',         subTipo)
        if (fechaDesde)    p.set('fecha_desde',      fechaDesde)
        if (fechaHasta)    p.set('fecha_hasta',      fechaHasta)
        if (beneficiario)  p.set('beneficiario',     beneficiario)
        if (numDocumento)  p.set('num_documento',    numDocumento)
        if (centroCostoId) p.set('centro_costo_id',  centroCostoId)
        const url = formato === 'excel'
            ? route('bancos.reportes.consulta-excel') + '?' + p
            : route('bancos.reportes.consulta-pdf')   + '?' + p
        window.open(url, '_blank')
    }

    // ── Columnas TanStack ─────────────────────────────────────────────────────
    const columns: ColumnDef<Movimiento>[] = [
        {
            accessorKey: 'fecha',
            header: 'Fecha',
            size: 90,
            cell: ({ getValue }) => (
                <span className="font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                    {getValue() as string}
                </span>
            ),
        },
        {
            accessorKey: 'banco',
            header: 'Banco / Caja',
            size: 140,
            cell: ({ getValue }) => (
                <span className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                    {getValue() as string ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'tipo',
            header: 'Tipo',
            size: 90,
            cell: ({ getValue }) => {
                const v = getValue() as string
                return (
                    <span className={cn(
                        'px-2 py-0.5 rounded-full text-[10px] font-semibold',
                        v === 'ingreso'
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                    )}>
                        {v === 'ingreso' ? 'Ingreso' : 'Egreso'}
                    </span>
                )
            },
        },
        {
            accessorKey: 'sub_tipo',
            header: 'Sub-tipo',
            size: 110,
            cell: ({ getValue }) => (
                <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    {SUB_TIPO_LABELS[getValue() as string] ?? getValue() as string}
                </span>
            ),
        },
        {
            accessorKey: 'beneficiario',
            header: 'Beneficiario',
            size: 170,
            cell: ({ getValue }) => (
                <span className="text-xs truncate block max-w-40" style={{ color: 'var(--text-main)' }}>
                    {getValue() as string ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'descripcion',
            header: 'Descripción',
            size: 200,
            cell: ({ getValue }) => (
                <span className="text-xs truncate block max-w-47.5" style={{ color: 'var(--text-muted)' }}>
                    {getValue() as string ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'num_documento',
            header: 'Nº Doc.',
            size: 90,
            cell: ({ getValue }) => (
                <span className="font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                    {getValue() as string ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'centro_costo',
            header: 'Centro Costo',
            size: 130,
            cell: ({ getValue }) => (
                <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    {getValue() as string ?? '—'}
                </span>
            ),
        },
        {
            accessorKey: 'monto',
            header: 'Monto',
            size: 110,
            cell: ({ row }) => {
                const m = row.original
                return (
                    <span className={cn(
                        'text-xs font-semibold font-mono',
                        m.tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                    )}>
                        {m.tipo === 'egreso' ? '-' : '+'}{fmt(m.monto)}
                    </span>
                )
            },
        },
        {
            accessorKey: 'conciliado',
            header: 'Conciliado',
            size: 90,
            cell: ({ getValue }) => (
                <span className={cn(
                    'text-[10px] px-1.5 py-0.5 rounded',
                    getValue()
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                )}>
                    {getValue() ? 'Sí' : 'No'}
                </span>
            ),
        },
    ]

    const table = useReactTable({
        data: movimientos,
        columns,
        state: { sorting, globalFilter },
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: { pagination: { pageSize: 25 } },
    })

    return (
        <AppLayout title="Consulta Cobros y Pagos">
            <Head title="Consulta Cobros y Pagos" />

            <div className="p-4 md:p-6 space-y-5" style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* ── Header ─────────────────────────────────────────── */}
                <div className="flex items-center gap-3">
                    <Link href={route('bancos.reportes.index')}
                        className="p-2 rounded-xl hover:opacity-70 transition-opacity"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                        <ChevronLeft className="w-4 h-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Consulta de Cobros y Pagos
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Búsqueda avanzada de movimientos bancarios
                        </p>
                    </div>
                </div>

                {/* ── Panel de filtros ──────────────────────────────── */}
                <div className="rounded-2xl border p-5"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8 gap-3 mb-4">
                        <Campo label="Banco / Caja">
                            <select value={bancoCajaId} onChange={e => setBancoCajaId(e.target.value)}
                                className="input-field select-field">
                                <option value="">— Todos —</option>
                                {bancos.map(b => (
                                    <option key={b.id} value={b.id}>{b.nombre}</option>
                                ))}
                            </select>
                        </Campo>
                        <Campo label="Tipo">
                            <select value={tipo} onChange={e => setTipo(e.target.value)}
                                className="input-field select-field">
                                <option value="">— Todos —</option>
                                <option value="ingreso">Ingresos</option>
                                <option value="egreso">Egresos</option>
                            </select>
                        </Campo>
                        <Campo label="Sub-tipo">
                            <select value={subTipo} onChange={e => setSubTipo(e.target.value)}
                                className="input-field select-field">
                                <option value="">— Todos —</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="cheque">Cheque</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="deposito">Depósito</option>
                            </select>
                        </Campo>
                        <Campo label="Desde">
                            <input type="date" value={fechaDesde} onChange={e => setFechaDesde(e.target.value)}
                                className="input-field" />
                        </Campo>
                        <Campo label="Hasta">
                            <input type="date" value={fechaHasta} onChange={e => setFechaHasta(e.target.value)}
                                className="input-field" />
                        </Campo>
                        <Campo label="Beneficiario">
                            <input type="text" value={beneficiario} onChange={e => setBeneficiario(e.target.value)}
                                placeholder="Buscar…" className="input-field"
                                onKeyDown={e => e.key === 'Enter' && buscar()} />
                        </Campo>
                        <Campo label="Nº Documento">
                            <input type="text" value={numDocumento} onChange={e => setNumDocumento(e.target.value)}
                                placeholder="Buscar…" className="input-field"
                                onKeyDown={e => e.key === 'Enter' && buscar()} />
                        </Campo>
                        <Campo label="Centro de Costo">
                            <select value={centroCostoId} onChange={e => setCentroCostoId(e.target.value)}
                                className="input-field select-field">
                                <option value="">— Todos —</option>
                                {centrosCosto.map(c => (
                                    <option key={c.id} value={c.id}>{c.nombre}</option>
                                ))}
                            </select>
                        </Campo>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        <button onClick={buscar}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white"
                            style={{ background: 'var(--primary)' }}>
                            <Search className="w-4 h-4" /> Buscar
                        </button>
                        <button onClick={limpiar}
                            className="px-4 py-2 rounded-xl text-sm font-medium"
                            style={{ background: 'var(--bg-main)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                            Limpiar
                        </button>
                        {movimientos.length > 0 && (
                            <>
                                <button onClick={() => exportar('excel')}
                                    className="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-white ml-auto"
                                    style={{ background: '#2D6A4F' }}>
                                    <Download className="w-4 h-4" /> Excel
                                </button>
                                <button onClick={() => exportar('pdf')}
                                    className="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-medium text-white"
                                    style={{ background: '#1A3A5C' }}>
                                    <FileText className="w-4 h-4" /> PDF
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* ── Totales ───────────────────────────────────────── */}
                {movimientos.length > 0 && (
                    <div className="grid grid-cols-3 gap-3">
                        <div className="rounded-xl border p-3 flex items-center gap-3"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <TrendingUp className="w-8 h-8 text-green-500 shrink-0" />
                            <div>
                                <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                    Total ingresos
                                </p>
                                <p className="font-bold text-green-600 dark:text-green-400">{fmt(totales.ingresos)}</p>
                            </div>
                        </div>
                        <div className="rounded-xl border p-3 flex items-center gap-3"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <TrendingDown className="w-8 h-8 text-red-500 shrink-0" />
                            <div>
                                <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                    Total egresos
                                </p>
                                <p className="font-bold text-red-600 dark:text-red-400">{fmt(totales.egresos)}</p>
                            </div>
                        </div>
                        <div className="rounded-xl border p-3 flex items-center gap-3"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <ArrowUpDown className="w-8 h-8 shrink-0" style={{ color: 'var(--primary)' }} />
                            <div>
                                <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                                    Neto · {totales.count} mov.
                                </p>
                                <p className="font-bold" style={{ color: 'var(--text-main)' }}>
                                    {fmt(totales.ingresos - totales.egresos)}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* ── Tabla ─────────────────────────────────────────── */}
                {movimientos.length === 0 ? (
                    <div className="rounded-2xl border p-12 text-center"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <Search className="w-10 h-10 mx-auto mb-3 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                            Aplica filtros y presiona Buscar
                        </p>
                        <p className="text-xs mt-1" style={{ color: 'var(--text-muted)' }}>
                            Puedes filtrar por banco, tipo, fechas o beneficiario
                        </p>
                    </div>
                ) : (
                    <div className="rounded-2xl border overflow-hidden"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                        {/* Búsqueda local */}
                        <div className="px-4 py-3 border-b flex items-center gap-3"
                            style={{ borderColor: 'var(--border)' }}>
                            <Search className="w-4 h-4 shrink-0" style={{ color: 'var(--text-muted)' }} />
                            <input type="text" value={globalFilter} onChange={e => setGlobalFilter(e.target.value)}
                                placeholder="Filtrar resultados…"
                                className="flex-1 text-sm bg-transparent outline-none"
                                style={{ color: 'var(--text-main)' }} />
                            <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                {table.getFilteredRowModel().rows.length} resultados
                            </span>
                        </div>

                        {/* Tabla */}
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    {table.getHeaderGroups().map(hg => (
                                        <tr key={hg.id} style={{ background: 'rgba(245,158,11,0.05)' }}>
                                            {hg.headers.map(h => (
                                                <th key={h.id}
                                                    className="px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wider cursor-pointer select-none"
                                                    style={{ color: 'var(--text-muted)', borderBottom: '1px solid var(--border)' }}
                                                    onClick={h.column.getToggleSortingHandler()}>
                                                    <div className="flex items-center gap-1">
                                                        {flexRender(h.column.columnDef.header, h.getContext())}
                                                        {h.column.getIsSorted() === 'asc'  && ' ↑'}
                                                        {h.column.getIsSorted() === 'desc' && ' ↓'}
                                                    </div>
                                                </th>
                                            ))}
                                        </tr>
                                    ))}
                                </thead>
                                <tbody>
                                    {table.getPaginationRowModel().rows.map((row, i) => (
                                        <tr key={row.id}
                                            style={{
                                                borderBottom: '1px solid var(--border)',
                                                background: i % 2 === 0 ? 'transparent' : 'rgba(0,0,0,0.02)',
                                            }}>
                                            {row.getVisibleCells().map(cell => (
                                                <td key={cell.id} className="px-3 py-2">
                                                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Paginación */}
                        <div className="px-4 py-3 border-t flex items-center justify-between gap-3"
                            style={{ borderColor: 'var(--border)' }}>
                            <div className="flex items-center gap-2">
                                <button onClick={() => table.previousPage()} disabled={!table.getCanPreviousPage()}
                                    className="px-3 py-1.5 rounded-lg text-xs font-medium disabled:opacity-40"
                                    style={{ background: 'var(--bg-main)', border: '1px solid var(--border)', color: 'var(--text-main)' }}>
                                    ← Anterior
                                </button>
                                <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Página {table.getState().pagination.pageIndex + 1} de {table.getPageCount()}
                                </span>
                                <button onClick={() => table.nextPage()} disabled={!table.getCanNextPage()}
                                    className="px-3 py-1.5 rounded-lg text-xs font-medium disabled:opacity-40"
                                    style={{ background: 'var(--bg-main)', border: '1px solid var(--border)', color: 'var(--text-main)' }}>
                                    Siguiente →
                                </button>
                            </div>
                            <select value={table.getState().pagination.pageSize}
                                onChange={e => table.setPageSize(Number(e.target.value))}
                                className="text-xs rounded-lg px-2 py-1.5"
                                style={{ background: 'var(--bg-main)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                                {[25, 50, 100].map(s => <option key={s} value={s}>{s} / página</option>)}
                            </select>
                        </div>
                    </div>
                )}

            </div>
        </AppLayout>
    )
}
