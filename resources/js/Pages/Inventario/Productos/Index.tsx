import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Plus, Search, Pencil, Trash2, FileSpreadsheet } from 'lucide-react'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'
import { usePermiso } from '@/Hooks/usePermiso'
import type { Producto, PaginatedData, PageProps } from '@/types'

/** El servidor agrega la categoría y subcategoría ya separadas y si el producto tiene cuentas contables (PC-CAT). */
type ProductoLista = Producto & { categoria_nombre?: string | null; subcategoria_nombre?: string | null; clasificado?: boolean }

interface Props extends PageProps {
    productos: PaginatedData<Producto> | null
    filters: { search?: string; marca_id?: string; categoria_id?: string; tipo?: string; estado?: string }
    categorias: { id: number; nombre: string; categoria_padre_id: number | null }[]
    pendientes_total: number
}

const TIPO_COLORES: Record<string, string> = {
    producto: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    servicio: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    repuesto: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    insumo:   'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
}

const TIPO_LABELS: Record<string, string> = { producto: 'Producto', servicio: 'Servicio', repuesto: 'Repuesto', insumo: 'Insumo' }

export default function ProductosIndex() {
    const { productos, filters, categorias, pendientes_total } = usePage<Props>().props
    const { puede } = usePermiso('inventario')

    const [search, setSearch]       = useState(filters.search ?? '')
    const [catId, setCatId]         = useState(filters.categoria_id ?? '')
    const [tipo, setTipo]           = useState(filters.tipo ?? '')
    const [estado, setEstado]       = useState(filters.estado ?? '')
    // Ver el nombre completo del producto (sin recortar), 02-02 §3.1 / CAM-053
    const [nombresCompletos, setNombresCompletos] = useState(false)

    const haBuscado = productos !== null

    function buscar() {
        router.get(route('inventario.productos.index'), {
            search: search || undefined,
            categoria_id: catId || undefined,
            tipo: tipo || undefined,
            estado: estado || undefined,
            buscado: '1',
        }, { preserveState: false })
    }

    // El Excel lo arma el servidor con TODO lo que coincide con los filtros, no solo la página que se ve
    function exportarExcel() {
        const params = new URLSearchParams()
        if (search) params.set('search', search)
        if (catId) params.set('categoria_id', catId)
        if (tipo) params.set('tipo', tipo)
        if (estado) params.set('estado', estado)
        window.location.href = route('inventario.productos.excel') + (params.toString() ? '?' + params.toString() : '')
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
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Productos' }]}
                actions={
                    <div className="flex items-center gap-3">
                        {productos && (
                            <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                {productos.total} producto{productos.total === 1 ? '' : 's'}
                            </span>
                        )}
                        {puede('crear') && (
                            <Link href={route('inventario.productos.create')}>
                                <Button>
                                    <Plus className="w-4 h-4" />
                                    Nuevo
                                </Button>
                            </Link>
                        )}
                    </div>
                }
            />

            <div className="p-6">
                {/* Barra de filtros */}
                <div className="flex items-center gap-3 mb-4 flex-nowrap overflow-x-auto">
                    <select value={tipo} onChange={e => setTipo(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los tipos</option>
                        <option value="producto">Producto</option>
                        <option value="servicio">Servicio</option>
                        <option value="repuesto">Repuesto</option>
                        <option value="insumo">Insumo</option>
                    </select>

                    <select value={catId} onChange={e => setCatId(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todas las categorías</option>
                        <option value="pendientes">⚠ Sin subcategoría ({pendientes_total})</option>
                        {categorias.filter(c => !c.categoria_padre_id).map(raiz => {
                            const hijas = categorias.filter(c => c.categoria_padre_id === raiz.id)
                            return hijas.length === 0 ? (
                                <option key={raiz.id} value={raiz.id}>{raiz.nombre}</option>
                            ) : (
                                <optgroup key={raiz.id} label={raiz.nombre}>
                                    <option value={raiz.id}>Todas ({raiz.nombre})</option>
                                    {hijas.map(h => <option key={h.id} value={h.id}>{h.nombre}</option>)}
                                </optgroup>
                            )
                        })}
                    </select>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los estados</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                    </select>

                    <div className="flex shrink-0" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && buscar()}
                                placeholder="Código o nombre..."
                                className="pl-9 w-52 rounded-r-none border-r-0"
                            />
                        </div>
                        <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={buscar}
                            title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>

                    <button className="flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0"
                        style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A', transition: 'background 0.2s' }}
                        onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                        onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                        onClick={exportarExcel}
                        disabled={!productos}
                        title="Excel">
                        <FileSpreadsheet className="w-4 h-4" />
                    </button>

                    <label className="flex items-center gap-1.5 text-xs shrink-0 cursor-pointer select-none" style={{ color: 'var(--text-muted)' }}
                        title="Muestra el nombre completo de cada producto, sin recortarlo">
                        <input type="checkbox" checked={nombresCompletos} onChange={e => setNombresCompletos(e.target.checked)} />
                        Nombres completos
                    </label>
                </div>

                {/* Estado inicial: aún no se ha buscado */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar los productos.
                        </p>
                    </div>
                )}

                {/* Tabla */}
                {haBuscado && productos && (
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['#', 'Código', 'Nombre', 'Marca comercial', 'Marca', 'Categoría', 'Subcategoría', 'PVP', 'PVD', 'Tipo', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {productos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                                No hay productos registrados
                                            </p>
                                            <p>Crea el primero con "Nuevo"</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : productos.data.map((producto, idx) => (
                                <tr key={producto.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-3 py-2.5 font-mono" style={{ color: 'var(--text-muted)' }}>
                                        {productos.from + idx}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono font-medium" style={{ color: 'var(--text-muted)' }}>
                                        {producto.codigo}
                                    </td>
                                    <td className={nombresCompletos ? 'px-3 py-2.5 min-w-64' : 'px-3 py-2.5 max-w-50'}>
                                        <div className="flex items-center gap-1.5 flex-wrap">
                                            <span className={nombresCompletos ? 'font-medium break-words' : 'font-medium truncate'} style={{ color: 'var(--text-main)' }}>
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
                                        {producto.marca_fabricante ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5" style={{ color: 'var(--text-muted)' }}>
                                        {(producto as ProductoLista).categoria_nombre ?? '—'}
                                    </td>
                                    <td className="px-3 py-2.5" style={{ color: 'var(--text-muted)' }}>
                                        {(producto as ProductoLista).subcategoria_nombre ? (
                                            <span style={{ color: 'var(--text-main)' }}>{(producto as ProductoLista).subcategoria_nombre}</span>
                                        ) : (producto as ProductoLista).clasificado ? (
                                            '—'
                                        ) : (
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                                style={{ background: 'rgba(245,158,11,0.15)', color: '#D97706' }}
                                                title="Sin subcategoría con cuentas contables: no se puede facturar ni comprar">
                                                Falta subcategoría
                                            </span>
                                        )}
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
                                            {puede('editar') && (
                                                <Link href={route('inventario.productos.edit', producto.id)}>
                                                    <Button variant="ghost" size="icon" title="Editar">
                                                        <Pencil className="w-3.5 h-3.5" />
                                                    </Button>
                                                </Link>
                                            )}
                                            {puede('eliminar') && (
                                                <Button variant="ghost" size="icon" title="Eliminar"
                                                    onClick={() => eliminar(producto)}>
                                                    <Trash2 className="w-3.5 h-3.5 text-red-400" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                )}

                {/* Paginación */}
                {haBuscado && productos && productos.last_page > 1 && (
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
