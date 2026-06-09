import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Plus, Search, Pencil, Trash2, FileText, FileSpreadsheet } from 'lucide-react'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'
import type { Marca, CategoriaProducto, Producto, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    productos: PaginatedData<Producto>
    filters: { search?: string; marca_id?: string; categoria_id?: string; tipo?: string; estado?: string }
    marcas: { id: number; nombre: string }[]
    categorias: { id: number; nombre: string; parent_id: number | null }[]
}

const TIPO_COLORES: Record<string, string> = {
    producto: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    servicio: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    combo:    'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
}

const TIPO_LABELS: Record<string, string> = { producto: 'Producto', servicio: 'Servicio', combo: 'Combo' }

export default function ProductosIndex() {
    const { productos, filters, marcas, categorias } = usePage<Props>().props

    const [search, setSearch]       = useState(filters.search ?? '')
    const [marcaId, setMarcaId]     = useState(filters.marca_id ?? '')
    const [catId, setCatId]         = useState(filters.categoria_id ?? '')
    const [tipo, setTipo]           = useState(filters.tipo ?? '')
    const [estado, setEstado]       = useState(filters.estado ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.productos.index'), {
                search: search || undefined,
                marca_id: marcaId || undefined,
                categoria_id: catId || undefined,
                tipo: tipo || undefined,
                estado: estado || undefined,
            }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, marcaId, catId, tipo, estado])

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = productos.data.map(p => ({
            'Código':     p.codigo,
            'Nombre':     p.nombre,
            'Marca':      p.marca?.nombre ?? '—',
            'Categoría':  p.categoria?.nombre ?? '—',
            'Tipo':       p.tipo,
            'PVP':        Number(p.pvp),
            'PVD':        Number(p.pvd),
            'Costo':      Number(p.costo),
            'Estado':     p.estado ? 'Activo' : 'Inactivo',
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Productos')
        XLSX.writeFile(wb, 'productos.xlsx')
    }

    async function eliminar(producto: Producto) {
        const confirmado = await confirmarEliminar(producto.nombre)
        if (!confirmado) return
        try {
            const res = await fetch(route('inventario.productos.destroy', producto.id), {
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
            router.reload({ only: ['productos'] })
            toastExito('Producto eliminado correctamente')
        } catch {
            toastError('Error al eliminar')
        }
    }

    return (
        <AppLayout title="Productos">
            <Head title="Productos" />
            <PageHeader
                title="Productos"
                description="Catálogo de productos, servicios y combos"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Productos' }]}
            />

            <div className="p-6">
                {/* Barra de filtros */}
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <Link href={route('inventario.productos.create')}>
                        <Button>
                            <Plus className="w-4 h-4" />
                            Nuevo Producto
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

                    <select value={marcaId} onChange={e => setMarcaId(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todas las marcas</option>
                        {marcas.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                    </select>

                    <select value={catId} onChange={e => setCatId(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todas las categorías</option>
                        {categorias.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>

                    <select value={tipo} onChange={e => setTipo(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los tipos</option>
                        <option value="producto">Producto</option>
                        <option value="servicio">Servicio</option>
                        <option value="combo">Combo</option>
                    </select>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todos los estados</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                    </select>

                    <div className="flex items-center gap-2 ml-auto">
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#DC2626', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#B91C1C')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#DC2626')}
                            onClick={() => {}}>
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

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Código', 'Nombre', 'Marca', 'Categoría', 'PVP', 'PVD', 'Tipo', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {productos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                                No hay productos registrados
                                            </p>
                                            <p>Crea el primero con "Nuevo Producto"</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : productos.data.map(producto => (
                                <tr key={producto.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-3 py-2.5 font-mono font-medium" style={{ color: 'var(--text-muted)' }}>
                                        {producto.codigo}
                                    </td>
                                    <td className="px-3 py-2.5 max-w-[200px]">
                                        <div className="flex items-center gap-1.5 flex-wrap">
                                            <span className="font-medium truncate" style={{ color: 'var(--text-main)' }}>
                                                {producto.nombre}
                                            </span>
                                            {producto.requiere_serie && (
                                                <span className="shrink-0 px-1.5 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                                    Serie
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2.5" style={{ color: 'var(--text-muted)' }}>
                                        {producto.marca?.nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5" style={{ color: 'var(--text-muted)' }}>
                                        {producto.categoria?.nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                        {Number(producto.pvp).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-mono" style={{ color: 'var(--text-muted)' }}>
                                        {Number(producto.pvd).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${TIPO_COLORES[producto.tipo] ?? ''}`}>
                                            {TIPO_LABELS[producto.tipo] ?? producto.tipo}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${producto.estado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'}`}>
                                            {producto.estado ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link href={route('inventario.productos.edit', producto.id)}>
                                                <Button variant="ghost" size="icon" title="Editar">
                                                    <Pencil className="w-3.5 h-3.5" />
                                                </Button>
                                            </Link>
                                            <Button variant="ghost" size="icon" title="Eliminar"
                                                onClick={() => eliminar(producto)}>
                                                <Trash2 className="w-3.5 h-3.5 text-red-400" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {productos.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {productos.from}–{productos.to} de {productos.total}
                        </p>
                        <div className="flex gap-1">
                            {productos.links.map((link, i) => (
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
