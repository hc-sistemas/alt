import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Pencil, Trash2, X, Save } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { confirmarEliminar } from '@/lib/swal'
import type { Bodega, CentroCosto, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    bodegas: PaginatedData<Bodega>
    centrosCosto: Pick<CentroCosto, 'id' | 'nombre' | 'codigo'>[]
    filters: { tipo?: string }
    tipos: string[]
}

const TIPO_COLORES: Record<string, string> = {
    general:     'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    importacion: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    taller:      'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    reserva:     'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    cuarentena:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

const TIPO_LABELS: Record<string, string> = {
    general: 'General', importacion: 'Importación',
    taller: 'Taller', reserva: 'Reserva', cuarentena: 'Cuarentena',
}

const emptyForm = {
    nombre: '', tipo: 'general', centro_costo_id: '' as string | number,
    es_virtual: false, estado: true,
}

export default function BodegasIndex() {
    const { bodegas, centrosCosto, filters, tipos } = usePage<Props>().props
    const [tipoFiltro, setTipoFiltro] = useState(filters.tipo ?? '')

    const [modalOpen, setModalOpen] = useState(false)
    const [editando, setEditando] = useState<Bodega | null>(null)
    const [form, setForm] = useState({ ...emptyForm })
    const [errors, setErrors] = useState<Record<string, string>>({})
    const [procesando, setProcesando] = useState(false)

    function aplicarFiltro(tipo: string) {
        setTipoFiltro(tipo)
        router.get(route('inventario.config.bodegas.index'), { tipo: tipo || undefined }, { preserveState: true, replace: true })
    }

    function abrirCrear() {
        setEditando(null)
        setForm({ ...emptyForm })
        setErrors({})
        setModalOpen(true)
    }

    function abrirEditar(bodega: Bodega) {
        setEditando(bodega)
        setForm({
            nombre: bodega.nombre,
            tipo: bodega.tipo,
            centro_costo_id: bodega.centro_costo_id ?? '',
            es_virtual: bodega.es_virtual,
            estado: bodega.estado,
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
            centro_costo_id: form.centro_costo_id === '' ? null : Number(form.centro_costo_id),
        }
        const callbacks = {
            onSuccess: () => {
                toastExito(editando ? 'Bodega actualizada correctamente' : 'Bodega creada correctamente')
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
            router.put(route('inventario.config.bodegas.update', editando.id), payload, callbacks)
        } else {
            router.post(route('inventario.config.bodegas.store'), payload, callbacks)
        }
    }

    async function eliminar(bodega: Bodega) {
        const confirmado = await confirmarEliminar(bodega.nombre)
        if (!confirmado) return
        try {
            const res = await fetch(route('inventario.config.bodegas.destroy', bodega.id), {
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
            router.reload({ only: ['bodegas'] })
            toastExito('Bodega eliminada correctamente')
        } catch {
            toastError('Error al eliminar')
        }
    }

    return (
        <AppLayout title="Bodegas">
            <Head title="Bodegas" />
            <PageHeader
                title="Bodegas"
                description="Gestión de bodegas y almacenes de la empresa"
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Configuración' }, { label: 'Bodegas' }]}
            />

            <div className="p-6">
                <div className="flex items-center gap-4 mb-4 flex-wrap">
                    <Button onClick={abrirCrear}>
                        <Plus className="w-4 h-4" />
                        Nueva Bodega
                    </Button>
                    <div className="flex items-center gap-2">
                        <span className="text-sm whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>Tipo:</span>
                        <select
                            value={tipoFiltro}
                            onChange={e => aplicarFiltro(e.target.value)}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        >
                            <option value="">Todos</option>
                            {tipos.map(t => <option key={t} value={t}>{TIPO_LABELS[t] ?? t}</option>)}
                        </select>
                    </div>
                </div>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Nombre', 'Tipo', 'Centro de Costo', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {bodegas.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center py-12 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay bodegas registradas.
                                    </td>
                                </tr>
                            ) : bodegas.data.map(bodega => (
                                <tr key={bodega.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {bodega.nombre}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${TIPO_COLORES[bodega.tipo] ?? ''}`}>
                                            {TIPO_LABELS[bodega.tipo] ?? bodega.tipo}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        {bodega.centro_costo?.nombre ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${bodega.estado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'}`}>
                                            {bodega.estado ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" title="Editar" onClick={() => abrirEditar(bodega)}>
                                                <Pencil className="w-4 h-4" />
                                            </Button>
                                            <Button variant="ghost" size="icon" title="Eliminar" onClick={() => eliminar(bodega)}>
                                                <Trash2 className="w-4 h-4 text-red-400" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {bodegas.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {bodegas.from}–{bodegas.to} de {bodegas.total}
                        </p>
                        <div className="flex gap-1">
                            {bodegas.links.map((link, i) => (
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
                                {editando ? 'Editar bodega' : 'Nueva bodega'}
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
                                    placeholder="Nombre de la bodega"
                                />
                                {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Tipo *</Label>
                                <select
                                    value={form.tipo}
                                    onChange={e => setForm(f => ({ ...f, tipo: e.target.value }))}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    {tipos.map(t => <option key={t} value={t}>{TIPO_LABELS[t] ?? t}</option>)}
                                </select>
                                {errors.tipo && <p className="text-xs text-red-400">{errors.tipo}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Centro de Costo</Label>
                                <select
                                    value={form.centro_costo_id}
                                    onChange={e => setForm(f => ({ ...f, centro_costo_id: e.target.value }))}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    <option value="">Sin centro de costo</option>
                                    {centrosCosto.map(cc => (
                                        <option key={cc.id} value={cc.id}>{cc.nombre} ({cc.codigo})</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setForm(f => ({ ...f, es_virtual: !f.es_virtual }))}
                                    className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${form.es_virtual ? 'bg-blue-500' : 'bg-slate-300 dark:bg-slate-600'}`}
                                >
                                    <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${form.es_virtual ? 'translate-x-6' : 'translate-x-1'}`} />
                                </button>
                                <Label>Virtual</Label>
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
                                {editando ? 'Guardar cambios' : 'Crear bodega'}
                            </Button>
                            <Button variant="outline" onClick={cerrarModal}>Cancelar</Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
