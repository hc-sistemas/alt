import { useState } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Pencil, Trash2, ToggleLeft, ToggleRight, X, Building2 } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

interface Departamento {
    id: number
    nombre: string
    descripcion: string | null
    estado: boolean
    colaboradores_count: number
    colaboradores_activos_count: number
}

interface Props extends PageProps {
    departamentos: Departamento[]
    colaboradores_sin_dep: number
}

// ─── Modal Crear/Editar ───────────────────────────────────────────────────────

function DepartamentoModal({ departamento, onClose }: { departamento?: Departamento; onClose: () => void }) {
    const isEditar = !!departamento
    const { data, setData, post, put, processing, errors } = useForm({
        nombre: departamento?.nombre ?? '',
        descripcion: departamento?.descripcion ?? '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        const opts = {
            onSuccess: () => {
                onClose()
                toast.success(isEditar ? 'Departamento actualizado.' : 'Departamento creado.')
            },
        }
        if (isEditar) {
            put(route('rrhh.departamentos.update', departamento!.id), opts)
        } else {
            post(route('rrhh.departamentos.store'), opts)
        }
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header flex items-center justify-between px-6 py-4">
                    <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                        {isEditar ? 'Editar Departamento' : 'Nuevo Departamento'}
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg transition-colors hover:bg-black/10">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div className="modal-body px-6 py-5 space-y-4">
                        <div>
                            <Label className="input-label">Nombre *</Label>
                            <Input className="input-field" value={data.nombre} autoFocus maxLength={100}
                                onChange={e => setData('nombre', e.target.value)} />
                            {errors.nombre && <p className="mt-1 text-xs text-red-500">{errors.nombre}</p>}
                        </div>
                        <div>
                            <Label className="input-label">Descripción</Label>
                            <Input className="input-field" value={data.descripcion} maxLength={300}
                                onChange={e => setData('descripcion', e.target.value)} />
                            {errors.descripcion && <p className="mt-1 text-xs text-red-500">{errors.descripcion}</p>}
                        </div>
                    </div>
                    <div className="modal-footer flex items-center justify-end gap-3 px-6 py-4">
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {isEditar ? 'Guardar' : 'Crear'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function DepartamentosIndex() {
    const { departamentos, colaboradores_sin_dep } = usePage<Props>().props
    const { puede } = usePermiso('rrhh')
    const [modal, setModal] = useState<{ departamento?: Departamento } | null>(null)

    function toggle(d: Departamento) {
        router.patch(route('rrhh.departamentos.toggle', d.id), {}, { preserveScroll: true })
    }

    function eliminar(d: Departamento) {
        Swal.fire({
            title: '¿Eliminar departamento?',
            text: d.nombre,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
        }).then(res => {
            if (res.isConfirmed) {
                router.delete(route('rrhh.departamentos.destroy', d.id), { preserveScroll: true })
            }
        })
    }

    return (
        <AppLayout title="Departamentos">
            <Head title="Departamentos — RRHH" />
            <ToastContainer position="top-right" />

            <PageHeader
                title="Departamentos"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Departamentos' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setModal({})} className="btn-primary flex items-center gap-2">
                            <Plus className="w-4 h-4" /> Nuevo
                        </button>
                    ) : undefined
                }
            />

            <div className="p-6 space-y-4">
                {colaboradores_sin_dep > 0 && (
                    <div className="rounded-xl border px-4 py-3 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)', background: 'var(--bg-card)' }}>
                        {colaboradores_sin_dep} colaborador(es) activo(s) sin departamento. Asígnalos desde
                        RRHH → Colaboradores → pestaña Cargo.
                    </div>
                )}

                {departamentos.length === 0 ? (
                    <div className="text-center py-16">
                        <Building2 className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Aún no hay departamentos. Crea el primero para clasificar a tus colaboradores.
                        </p>
                    </div>
                ) : (
                    <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['Departamento', 'Descripción', 'Colaboradores', 'Estado', ''].map(h => (
                                        <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                            style={{ color: 'var(--text-muted)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {departamentos.map(d => (
                                    <tr key={d.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-3 py-2.5 font-medium" style={{ color: 'var(--text-main)' }}>{d.nombre}</td>
                                        <td className="px-3 py-2.5" style={{ color: 'var(--text-muted)' }}>{d.descripcion ?? '—'}</td>
                                        <td className="px-3 py-2.5" style={{ color: 'var(--text-main)' }}>
                                            {d.colaboradores_activos_count}
                                            {d.colaboradores_count > d.colaboradores_activos_count && (
                                                <span style={{ color: 'var(--text-muted)' }}>
                                                    {' '}(+{d.colaboradores_count - d.colaboradores_activos_count} inactivos)
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            {d.estado
                                                ? <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Activo</span>
                                                : <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Inactivo</span>}
                                        </td>
                                        <td className="px-3 py-2.5">
                                            <div className="flex items-center gap-1 justify-end">
                                                {puede('editar') && (
                                                    <>
                                                        <button onClick={() => setModal({ departamento: d })} title="Editar"
                                                            className="p-1.5 rounded-lg hover:bg-black/10">
                                                            <Pencil className="w-3.5 h-3.5" style={{ color: 'var(--text-muted)' }} />
                                                        </button>
                                                        <button onClick={() => toggle(d)} title={d.estado ? 'Desactivar' : 'Activar'}
                                                            className="p-1.5 rounded-lg hover:bg-black/10">
                                                            {d.estado
                                                                ? <ToggleRight className="w-4 h-4 text-emerald-500" />
                                                                : <ToggleLeft className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />}
                                                        </button>
                                                    </>
                                                )}
                                                {puede('eliminar') && d.colaboradores_count === 0 && (
                                                    <button onClick={() => eliminar(d)} title="Eliminar"
                                                        className="p-1.5 rounded-lg hover:bg-black/10">
                                                        <Trash2 className="w-3.5 h-3.5 text-red-500" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {modal && <DepartamentoModal departamento={modal.departamento} onClose={() => setModal(null)} />}
        </AppLayout>
    )
}
