import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Pencil, Trash2, X, Save, ChevronRight, ChevronDown } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { confirmarEliminar } from '@/lib/swal'
import type { CategoriaProducto, PageProps } from '@/types'

interface Props extends PageProps {
    categorias: CategoriaProducto[]
    todasCategorias: { id: number; nombre: string }[]
}

const emptyForm = { nombre: '', categoria_padre_id: '' as string | number, estado: true }

export default function CategoriasIndex() {
    const { categorias, todasCategorias } = usePage<Props>().props
    const [expandidos, setExpandidos] = useState<number[]>([])
    const [modalOpen, setModalOpen] = useState(false)
    const [editando, setEditando] = useState<CategoriaProducto | null>(null)
    const [form, setForm] = useState({ ...emptyForm })
    const [errors, setErrors] = useState<Record<string, string>>({})
    const [procesando, setProcesando] = useState(false)

    function toggleExpandir(id: number) {
        setExpandidos(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])
    }

    function abrirCrear(parentId?: number) {
        setEditando(null)
        setForm({ ...emptyForm, categoria_padre_id: parentId ?? '' })
        setErrors({})
        setModalOpen(true)
    }

    function abrirEditar(cat: CategoriaProducto) {
        setEditando(cat)
        setForm({
            nombre: cat.nombre,
            categoria_padre_id: cat.categoria_padre_id ?? '',
            estado: cat.estado,
        })
        setErrors({})
        setModalOpen(true)
    }

    function cerrarModal() {
        setModalOpen(false)
        setEditando(null)
        setErrors({})
    }

    function guardar() {
        setProcesando(true)
        const payload = {
            ...form,
            categoria_padre_id: form.categoria_padre_id === '' ? null : Number(form.categoria_padre_id),
        }
        const callbacks = {
            onSuccess: () => {
                toastExito(editando ? 'Categoría actualizada correctamente' : 'Categoría creada correctamente')
                cerrarModal()
                setProcesando(false)
            },
            onError: (errs: Record<string, string>) => {
                setErrors(errs)
                toastError('Error al guardar')
                setProcesando(false)
            },
        }
        if (editando) {
            router.put(route('inventario.config.categorias.update', editando.id), payload, callbacks)
        } else {
            router.post(route('inventario.config.categorias.store'), payload, callbacks)
        }
    }

    async function eliminar(cat: CategoriaProducto) {
        const confirmado = await confirmarEliminar(cat.nombre)
        if (!confirmado) return
        try {
            const res = await fetch(route('inventario.config.categorias.destroy', cat.id), {
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
            router.reload()
            toastExito('Categoría eliminada correctamente')
        } catch {
            toastError('Error al eliminar')
        }
    }

    return (
        <AppLayout title="Categorías de Producto">
            <Head title="Categorías de Producto" />
            <PageHeader
                title="Categorías de Producto"
                description="Árbol de categorías para clasificar productos"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Configuración' }, { label: 'Categorías' }]}
            />

            <div className="p-6">
                <div className="flex items-center gap-4 mb-4">
                    <Button onClick={() => abrirCrear()}>
                        <Plus className="w-4 h-4" />
                        Nueva Categoría
                    </Button>
                </div>

                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    {categorias.length === 0 ? (
                        <div className="text-center py-12 text-sm" style={{ color: 'var(--text-muted)' }}>
                            No hay categorías registradas.
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['Categoría', 'Estado', ''].map(h => (
                                        <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                            style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {categorias.map(cat => {
                                    const isExpanded = expandidos.includes(cat.id)
                                    const tieneHijos = (cat.hijos?.length ?? 0) > 0
                                    return (
                                        <>
                                            <tr key={cat.id}
                                                className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                                style={{ borderColor: 'var(--border)' }}>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            onClick={() => tieneHijos && toggleExpandir(cat.id)}
                                                            className={`w-5 h-5 flex items-center justify-center rounded transition-colors ${tieneHijos ? 'hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer' : 'cursor-default opacity-0'}`}
                                                        >
                                                            {tieneHijos && (isExpanded
                                                                ? <ChevronDown className="w-3.5 h-3.5" style={{ color: 'var(--text-muted)' }} />
                                                                : <ChevronRight className="w-3.5 h-3.5" style={{ color: 'var(--text-muted)' }} />
                                                            )}
                                                        </button>
                                                        <span className="font-semibold" style={{ color: 'var(--text-main)' }}>
                                                            {cat.nombre}
                                                            {tieneHijos && (
                                                                <span className="ml-1.5 text-xs font-normal px-1.5 py-0.5 rounded-full"
                                                                    style={{ background: 'var(--bg-card)', color: 'var(--text-muted)', border: '1px solid var(--border)' }}>
                                                                    {cat.hijos!.length}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cat.estado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'}`}>
                                                        {cat.estado ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="icon" title="Agregar subcategoría"
                                                            onClick={() => abrirCrear(cat.id)}>
                                                            <Plus className="w-3.5 h-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" title="Editar"
                                                            onClick={() => abrirEditar(cat)}>
                                                            <Pencil className="w-3.5 h-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" title="Eliminar"
                                                            onClick={() => eliminar(cat)}>
                                                            <Trash2 className="w-3.5 h-3.5 text-red-400" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>

                                            {isExpanded && cat.hijos?.map(hijo => (
                                                <tr key={hijo.id}
                                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                                    style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}>
                                                    <td className="px-4 py-2.5">
                                                        <div className="flex items-center gap-2 ml-7">
                                                            <span className="w-px h-4 rounded-full mr-1" style={{ background: 'var(--border)' }} />
                                                            <span className="text-sm" style={{ color: 'var(--text-main)' }}>
                                                                {hijo.nombre}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${hijo.estado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'}`}>
                                                            {hijo.estado ? 'Activo' : 'Inactivo'}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-2.5">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button variant="ghost" size="icon" title="Editar"
                                                                onClick={() => abrirEditar(hijo)}>
                                                                <Pencil className="w-3.5 h-3.5" />
                                                            </Button>
                                                            <Button variant="ghost" size="icon" title="Eliminar"
                                                                onClick={() => eliminar(hijo)}>
                                                                <Trash2 className="w-3.5 h-3.5 text-red-400" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </>
                                    )
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {modalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-black/60" onClick={cerrarModal} />
                    <div className="relative w-full max-w-md rounded-xl shadow-2xl p-6 space-y-4"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                {editando ? 'Editar categoría' : 'Nueva categoría'}
                            </h3>
                            <button onClick={cerrarModal} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>Nombre *</Label>
                                <Input
                                    value={form.nombre}
                                    onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                                    placeholder="Nombre de la categoría"
                                />
                                {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Categoría padre</Label>
                                <select
                                    value={form.categoria_padre_id}
                                    onChange={e => setForm(f => ({ ...f, categoria_padre_id: e.target.value }))}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    <option value="">Sin padre (categoría raíz)</option>
                                    {todasCategorias
                                        .filter(c => editando ? c.id !== editando.id : true)
                                        .map(c => (
                                            <option key={c.id} value={c.id}>{c.nombre}</option>
                                        ))}
                                </select>
                                {errors.categoria_padre_id && <p className="text-xs text-red-400">{errors.categoria_padre_id}</p>}
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Máximo 2 niveles — solo se muestran categorías raíz como padres
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setForm(f => ({ ...f, estado: !f.estado }))}
                                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${form.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'}`}
                                >
                                    <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${form.estado ? 'translate-x-6' : 'translate-x-1'}`} />
                                </button>
                                <Label>Activa</Label>
                            </div>
                        </div>

                        <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                            <Button onClick={guardar} loading={procesando}>
                                <Save className="w-4 h-4" />
                                {editando ? 'Guardar cambios' : 'Crear categoría'}
                            </Button>
                            <Button variant="outline" onClick={cerrarModal}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
