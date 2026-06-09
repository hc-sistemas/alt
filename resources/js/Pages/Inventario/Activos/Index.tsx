import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Plus, Search, Pencil, Trash2, Eye, FileText, FileSpreadsheet } from 'lucide-react'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'
import type { ActivoFijo, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    activos: PaginatedData<ActivoFijo>
    estados: string[]
    filters: { search?: string; estado?: string }
}

const ESTADO_COLORES: Record<string, string> = {
    activo:       'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    dado_de_baja: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    vendido:      'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
}

const ESTADO_LABELS: Record<string, string> = {
    activo: 'Activo', dado_de_baja: 'Baja', vendido: 'Vendido',
}

function fmt(v: number | string) {
    return Number(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function ActivosIndex() {
    const { activos, estados, filters } = usePage<Props>().props

    const [search, setSearch] = useState(filters.search ?? '')
    const [estado, setEstado] = useState(filters.estado ?? '')
    const [pdfModal, setPdfModal] = useState(false)
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.activos.index'), {
                search: search || undefined,
                estado: estado || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, estado])

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = activos.data.map(a => ({
            'Código':                 a.codigo,
            'Nombre':                 a.nombre,
            'Fecha Adquisición':      a.fecha_adquisicion,
            'Costo Adquisición':      Number(a.costo_adquisicion),
            'Depreciación Acumulada': Number(a.depreciacion_acumulada),
            'Valor en Libros':        Number(a.valor_en_libros),
            'Estado':                 ESTADO_LABELS[a.estado] ?? a.estado,
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Activos Fijos')
        XLSX.writeFile(wb, 'activos_fijos.xlsx')
    }

    async function eliminar(activo: ActivoFijo) {
        const confirmado = await confirmarEliminar(activo.nombre)
        if (!confirmado) return
        try {
            const res = await fetch(route('inventario.activos.destroy', activo.id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                    'X-Inertia': 'true',
                },
            })
            if (res.status === 422) {
                const json = await res.json()
                toastError(json.message)
                return
            }
            router.reload({ only: ['activos'] })
            toastExito('Activo eliminado correctamente')
        } catch {
            toastError('Error al eliminar')
        }
    }

    return (
        <AppLayout title="Activos Fijos">
            <Head title="Activos Fijos" />
            <PageHeader
                title="Activos Fijos"
                description="Registro y depreciación de activos fijos"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Activos Fijos' }]}
            />

            <div className="p-6">
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <Link href={route('inventario.activos.create')}>
                        <Button>
                            <Plus className="w-4 h-4" />
                            Nuevo Activo
                        </Button>
                    </Link>

                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Código o nombre..."
                            className="pl-9 w-52"
                        />
                    </div>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los estados</option>
                        {estados.map(e => (
                            <option key={e} value={e}>{ESTADO_LABELS[e] ?? e}</option>
                        ))}
                    </select>

                    <div className="flex items-center gap-2 ml-auto">
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#DC2626', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#B91C1C')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#DC2626')}
                            onClick={() => setPdfModal(true)}>
                            <FileText className="w-4 h-4" />
                            PDF
                        </button>
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#16A34A', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                            onClick={exportarExcel}>
                            <FileSpreadsheet className="w-4 h-4" />
                            Excel
                        </button>
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Código', 'Nombre', 'Fecha Adq.', 'Costo Adq.', 'Dep. Acumulada', 'Valor Libros', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {activos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                            No hay activos registrados
                                        </p>
                                        <p>Crea el primero con "Nuevo Activo"</p>
                                    </td>
                                </tr>
                            ) : activos.data.map(activo => {
                                const totalDepreciable = Number(activo.costo_adquisicion) - Number(activo.valor_residual)
                                const totDepreciado = totalDepreciable > 0 && Number(activo.valor_en_libros) <= Number(activo.valor_residual)
                                return (
                                    <tr key={activo.id}
                                        className={`border-t transition-colors ${totDepreciado ? 'bg-amber-50/40 dark:bg-amber-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'}`}
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-3 py-2.5 font-mono font-medium" style={{ color: 'var(--text-muted)' }}>
                                            {activo.codigo}
                                        </td>
                                        <td className="px-3 py-2.5 max-w-[200px] truncate font-medium" style={{ color: 'var(--text-main)' }}
                                            title={activo.nombre}>
                                            {activo.nombre}
                                        </td>
                                        <td className="px-3 py-2.5 whitespace-nowrap font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {activo.fecha_adquisicion}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                            {fmt(activo.costo_adquisicion)}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                            {fmt(activo.depreciacion_acumulada)}
                                        </td>
                                        <td className="px-3 py-2.5 text-right font-mono font-semibold"
                                            style={{ color: totDepreciado ? '#F59E0B' : 'var(--text-main)' }}>
                                            {fmt(activo.valor_en_libros)}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${ESTADO_COLORES[activo.estado] ?? ''}`}>
                                                {ESTADO_LABELS[activo.estado] ?? activo.estado}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={route('inventario.activos.show', activo.id)}>
                                                    <Button variant="ghost" size="icon" title="Ver detalle">
                                                        <Eye className="w-3.5 h-3.5" />
                                                    </Button>
                                                </Link>
                                                <Link href={route('inventario.activos.edit', activo.id)}>
                                                    <Button variant="ghost" size="icon" title="Editar">
                                                        <Pencil className="w-3.5 h-3.5" />
                                                    </Button>
                                                </Link>
                                                <Button variant="ghost" size="icon" title="Eliminar"
                                                    onClick={() => eliminar(activo)}>
                                                    <Trash2 className="w-3.5 h-3.5 text-red-400" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {activos.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {activos.from}–{activos.to} de {activos.total}
                        </p>
                        <div className="flex gap-1">
                            {activos.links.map((link, i) => (
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
                url={pdfModal ? route('inventario.activos.reporte.lista') : ''}
                titulo="Activos Fijos"
                nombreDescarga="activos_fijos.pdf"
            />
        </AppLayout>
    )
}
