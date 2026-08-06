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
import { usePermiso } from '@/Hooks/usePermiso'
import type { PaginatedData, PageProps } from '@/types'

interface TipoEquipo {
    id: number
    descripcion: string
    estado: boolean
}

interface Props extends PageProps {
    tiposEquipo: PaginatedData<TipoEquipo>
    filters: { search?: string }
}

const emptyForm = { descripcion: '', estado: true }

export default function TiposEquipoIndex() {
    const { tiposEquipo, filters } = usePage<Props>().props
    const { puede } = usePermiso('taller')
    const [search, setSearch] = useState(filters.search ?? '')
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
    const isFirstRender = useRef(true)

    const [modalOpen, setModalOpen] = useState(false)
    const [editando, setEditando] = useState<TipoEquipo | null>(null)
    const [form, setForm] = useState({ ...emptyForm })
    const [errors, setErrors] = useState<Record<string, string>>({})
    const [procesando, setProcesando] = useState(false)
    const [errorEliminar, setErrorEliminar] = useState<string | null>(null)

    useEffect(() => {
        if (isFirstRender.current) { isFirstRender.current = false; return }
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('taller.tipos-equipo.index'), { search }, { preserveState: true, replace: true })
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

    function abrirEditar(tipoEquipo: TipoEquipo) {
        setEditando(tipoEquipo)
        setForm({ descripcion: tipoEquipo.descripcion, estado: tipoEquipo.estado })
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
                toastExito(editando ? 'Tipo de equipo actualizado correctamente' : 'Tipo de equipo creado correctamente')
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
            router.patch(route('taller.tipos-equipo.update', editando.id), form, callbacks)
        } else {
            router.post(route('taller.tipos-equipo.store'), form, callbacks)
        }
    }

    async function eliminar(tipoEquipo: TipoEquipo) {
        const confirmado = await confirmarEliminar(tipoEquipo.descripcion)
        if (!confirmado) return
        setErrorEliminar(null)
        try {
            const res = await fetch(route('taller.tipos-equipo.destroy', tipoEquipo.id), {
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
            router.reload({ only: ['tiposEquipo'] })
            toastExito('Tipo de equipo eliminado correctamente')
        } catch {
            toastError('Error al eliminar')
        }
    }

    return (
        <AppLayout title="Tipos de Equipo">
            <Head title="Tipos de Equipo" />
            <PageHeader
                title="Tipos de Equipo"
                description="Gestión de tipos de equipo del taller"
                breadcrumbs={[{ label: 'Taller' }, { label: 'Tipos de Equipo' }]}
                actions={
                    puede('crear') ? (
                        <Button onClick={abrirCrear}>
                            <Plus className="w-4 h-4" />
                            Nuevo
                        </Button>
                    ) : undefined
                }
            />

            <div className="p-6">
                {errorEliminar && (
                    <div className="mb-4 px-4 py-3 rounded-lg text-sm"
                        style={{ background: '#FEF2F2', color: '#991B1B', border: '1px solid #FECACA' }}>
                        {errorEliminar}
                    </div>
                )}

                <div className="flex items-center gap-4 mb-4 flex-wrap">
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Buscar tipo de equipo..."
                            className="pl-9 w-56"
                        />
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Descripción', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {tiposEquipo.data.length === 0 ? (
                                <tr>
                                    <td colSpan={3} className="text-center py-12 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay tipos de equipo registrados.
                                    </td>
                                </tr>
                            ) : tiposEquipo.data.map(tipoEquipo => (
                                <tr key={tipoEquipo.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {tipoEquipo.descripcion}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                            tipoEquipo.estado
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        }`}>
                                            {tipoEquipo.estado ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {puede('editar') && (
                                                <Button variant="ghost" size="icon" title="Editar" onClick={() => abrirEditar(tipoEquipo)}>
                                                    <Pencil className="w-4 h-4" />
                                                </Button>
                                            )}
                                            {puede('eliminar') && (
                                                <Button variant="ghost" size="icon" title="Eliminar" onClick={() => eliminar(tipoEquipo)}>
                                                    <Trash2 className="w-4 h-4 text-red-400" />
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {tiposEquipo.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {tiposEquipo.from}–{tiposEquipo.to} de {tiposEquipo.total}
                        </p>
                        <div className="flex gap-1">
                            {tiposEquipo.links.map((link, i) => (
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
                                {editando ? 'Editar tipo de equipo' : 'Nuevo tipo de equipo'}
                            </h3>
                            <button onClick={cerrarModal} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>Descripción *</Label>
                                <Input
                                    value={form.descripcion}
                                    onChange={e => setForm(f => ({ ...f, descripcion: e.target.value }))}
                                    placeholder="Descripción del tipo de equipo"
                                />
                                {errors.descripcion && <p className="text-xs text-red-400">{errors.descripcion}</p>}
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

                        <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                            <Button onClick={guardar} loading={procesando}>
                                <Save className="w-4 h-4" />
                                {editando ? 'Guardar cambios' : 'Crear tipo de equipo'}
                            </Button>
                            <Button variant="outline" onClick={cerrarModal}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
