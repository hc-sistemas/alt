import { Head, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Pencil, Trash2, X, Save, Search } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { confirmarEliminar } from '@/lib/swal'
import type { Marca, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    marcas: PaginatedData<Marca>
    filters: { search?: string }
}

const emptyForm = { nombre: '', estado: true }

export default function MarcasIndex() {
    const { marcas, filters } = usePage<Props>().props
    const [search, setSearch] = useState(filters.search ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    const [modalOpen, setModalOpen] = useState(false)
    const [editando, setEditando] = useState<Marca | null>(null)
    const [form, setForm] = useState({ ...emptyForm })
    const [errors, setErrors] = useState<Record<string, string>>({})
    const [procesando, setProcesando] = useState(false)
    const [errorEliminar, setErrorEliminar] = useState<string | null>(null)

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.config.marcas.index'), { search }, { preserveState: true, replace: true })
        }, 300)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search])

    function abrirCrear() {
        setEditando(null)
        setForm({ ...emptyForm })
        setErrors({})
        setErrorEliminar(null)
        setModalOpen(true)
    }

    function abrirEditar(marca: Marca) {
        setEditando(marca)
        setForm({ nombre: marca.nombre, estado: marca.estado })
        setErrors({})
        setErrorEliminar(null)
        setModalOpen(true)
    }

    function cerrarModal() {
        setModalOpen(false)
        setEditando(null)
        setErrors({})
    }

    function guardar() {
        setProcesando(true)
        const callbacks = {
            onSuccess: () => {
                toastExito(editando ? 'Marca actualizada correctamente' : 'Marca creada correctamente')
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
            router.put(route('inventario.config.marcas.update', editando.id), form, callbacks)
        } else {
            router.post(route('inventario.config.marcas.store'), form, callbacks)
        }
    }

    async function eliminar(marca: Marca) {
        const confirmado = await confirmarEliminar(marca.nombre)
        if (!confirmado) return
        setErrorEliminar(null)
        try {
            const res = await fetch(route('inventario.config.marcas.destroy', marca.id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                    'X-Inertia': 'true',
                },
            })
            if (res.status === 422) {
                const json = await res.json()
                setErrorEliminar(json.message)
                toastError(json.message)
                return
            }
            router.reload({ only: ['marcas'] })
            toastExito('Marca eliminada correctamente')
        } catch {
            toastError('Error al eliminar')
        }
    }

    return (
        <AppLayout title="Marcas">
            <Head title="Marcas" />
            <PageHeader
                title="Marcas"
                description="Gestión de marcas de productos"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Configuración' }, { label: 'Marcas' }]}
            />

            <div className="p-6">
                {errorEliminar && (
                    <div className="mb-4 px-4 py-3 rounded-lg text-sm"
                        style={{ background: '#FEF2F2', color: '#991B1B', border: '1px solid #FECACA' }}>
                        {errorEliminar}
                    </div>
                )}

                <div className="flex items-center gap-4 mb-4 flex-wrap">
                    <Button onClick={abrirCrear}>
                        <Plus className="w-4 h-4" />
                        Nueva Marca
                    </Button>
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Buscar marca..."
                            className="pl-9 w-56"
                        />
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Nombre', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {marcas.data.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="text-center py-12 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay marcas registradas.
                                    </td>
                                </tr>
                            ) : marcas.data.map(marca => (
                                <tr key={marca.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {marca.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                            marca.estado
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        }`}>
                                            {marca.estado ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" title="Editar" onClick={() => abrirEditar(marca)}>
                                                <Pencil className="w-4 h-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" title="Eliminar" onClick={() => eliminar(marca)}>
                                                <Trash2 className="w-4 h-4 text-red-400" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {marcas.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {marcas.from}–{marcas.to} de {marcas.total}
                        </p>
                        <div className="flex gap-1">
                            {marcas.links.map((link, i) => (
                                link.url ? (
                                    <button key={i}
                                        onClick={() => router.get(link.url!)}
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

            {modalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-black/60" onClick={cerrarModal} />
                    <div className="relative w-full max-w-md rounded-xl shadow-2xl p-6 space-y-4"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                {editando ? 'Editar marca' : 'Nueva marca'}
                            </h3>
                            <button onClick={cerrarModal} className="modal-close">
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>Nombre *</Label>
                                <Input
                                    value={form.nombre}
                                    onChange={e => setForm(f => ({ ...f, nombre: e.target.value }))}
                                    placeholder="Nombre de la marca"
                                />
                                {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setForm(f => ({ ...f, estado: !f.estado }))}
                                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${form.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'}`}
                                >
                                    <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${form.estado ? 'translate-x-6' : 'translate-x-1'}`} />
                                </button>
                                <Label>Activo</Label>
                            </div>
                        </div>

                        <div className="modal-footer">
                            <Button onClick={guardar} loading={procesando}>
                                <Save className="w-4 h-4" />
                                {editando ? 'Guardar cambios' : 'Crear marca'}
                            </Button>
                            <Button variant="outline" onClick={cerrarModal}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
