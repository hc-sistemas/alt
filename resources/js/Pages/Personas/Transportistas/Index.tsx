import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Plus, Pencil, Trash2, X, Save, Search, FileText, FileSpreadsheet } from 'lucide-react'
import type { Transportista, PageProps } from '@/types'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'

interface Props extends PageProps {
    transportistas: Transportista[]
}

const emptyForm = {
    identificacion: '',
    razon_social: '',
    placa: '',
    email: '',
    telefono: '',
    direccion: '',
    estado: true,
}

export default function TransportistasIndex() {
    const { transportistas } = usePage<Props>().props
    const [search, setSearch] = useState('')
    const [pdfModal, setPdfModal] = useState(false)

    const [modalOpen, setModalOpen] = useState(false)
    const [editando, setEditando] = useState<Transportista | null>(null)
    const [form, setForm] = useState({ ...emptyForm })
    const [procesando, setProcesando] = useState(false)
    const [errors, setErrors] = useState<Record<string, string>>({})

    function abrirCrear() {
        setEditando(null)
        setForm({ ...emptyForm })
        setErrors({})
        setModalOpen(true)
    }

    function abrirEditar(t: Transportista) {
        setEditando(t)
        setForm({
            identificacion: t.identificacion ?? '',
            razon_social: t.razon_social,
            placa: t.placa ?? '',
            email: t.email ?? '',
            telefono: t.telefono ?? '',
            direccion: t.direccion ?? '',
            estado: t.estado,
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
        const esEdicion = !!editando
        const callbacks = {
            onSuccess: () => {
                toastExito(esEdicion ? 'Transportista actualizado correctamente' : 'Transportista creado correctamente')
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
            router.put(route('personas.transportistas.update', editando.id), form, callbacks)
        } else {
            router.post(route('personas.transportistas.store'), form, callbacks)
        }
    }

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = transportistas.map(t => ({
            'Identificación': t.identificacion ?? '—',
            'Razón Social':   t.razon_social,
            'Placa':          t.placa ?? '—',
            'Email':          t.email ?? '—',
            'Teléfono':       t.telefono ?? '—',
            'Estado':         t.estado ? 'Activo' : 'Inactivo',
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Transportistas')
        XLSX.writeFile(wb, 'transportistas.xlsx')
    }

    async function eliminar(t: Transportista) {
        const confirmado = await confirmarEliminar(t.razon_social)
        if (!confirmado) return
        router.delete(route('personas.transportistas.destroy', t.id), {
            onSuccess: () => toastExito('Transportista eliminado correctamente'),
            onError: () => toastError('Error al eliminar'),
        })
    }

    return (
        <AppLayout title="Transportistas">
            <Head title="Transportistas" />

            <PageHeader
                title="Transportistas"
                description="Empresas y personas que transportan mercadería"
                breadcrumbs={[{ label: 'Personas' }, { label: 'Transportistas' }]}
            />

            <div className="p-6">
                {/* Barra de acciones */}
                <div className="flex items-center gap-4 mb-4 flex-wrap">
                    <Button onClick={abrirCrear}>
                        <Plus className="w-4 h-4" />
                        Nuevo Transportista
                    </Button>

                    <div className="flex items-center gap-2">
                        <span className="text-sm whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>Buscar:</span>
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder="Razón social o identificación..."
                                className="pl-9 w-64"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2 ml-auto">
                        <button
                            onClick={() => setPdfModal(true)}
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#DC2626', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#B91C1C')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#DC2626')}>
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
                    {(() => {
                        const term = search.toLowerCase()
                        const filtrados = search
                            ? transportistas.filter(t =>
                                t.razon_social.toLowerCase().includes(term) ||
                                (t.identificacion ?? '').toLowerCase().includes(term)
                              )
                            : transportistas

                        return (
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    {['No', 'Razón Social', 'Identificación', 'Placa', 'Email', 'Teléfono', 'Estado', ''].map(h => (
                                        <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                            style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {filtrados.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="text-center py-12" style={{ color: 'var(--text-muted)' }}>
                                            {search ? (
                                                <p className="text-sm">No hay resultados para "{search}".</p>
                                            ) : (
                                                <>
                                                    <p className="text-sm">No hay transportistas registrados.</p>
                                                    <button onClick={abrirCrear}
                                                        className="text-amber-500 hover:underline text-sm mt-1 inline-block">
                                                        Agregar el primero
                                                    </button>
                                                </>
                                            )}
                                        </td>
                                    </tr>
                                ) : filtrados.map((t, index) => (
                                    <tr key={t.id}
                                        className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-4 py-3 text-xs text-center" style={{ color: 'var(--text-muted)' }}>
                                            {index + 1}
                                        </td>
                                        <td className="px-4 py-3 font-medium whitespace-nowrap" style={{ color: 'var(--text-main)' }}>
                                            {t.razon_social}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className="font-mono text-xs whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                                {t.identificacion ?? '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {t.placa ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {t.email ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-xs whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                            {t.telefono ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                                                t.estado
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                            }`}>
                                                {t.estado ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                <Button variant="ghost" size="icon" title="Editar" onClick={() => abrirEditar(t)}>
                                                    <Pencil className="w-4 h-4" />
                                                </Button>
                                                <Button variant="ghost" size="icon" title="Eliminar"
                                                    onClick={() => eliminar(t)}>
                                                    <Trash2 className="w-4 h-4 text-red-400" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        )
                    })()}
                </div>
            </div>

            <PdfPreviewModal
                abierto={pdfModal}
                onCerrar={() => setPdfModal(false)}
                url={pdfModal ? route('personas.transportistas.reporte.lista') : ''}
                titulo="Lista de Transportistas"
                nombreDescarga="transportistas.pdf"
            />

            {/* Modal crear/editar */}
            {modalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-black/60" onClick={cerrarModal} />
                    <div className="relative w-full max-w-lg rounded-xl shadow-2xl p-6 space-y-5"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                {editando ? 'Editar transportista' : 'Nuevo transportista'}
                            </h3>
                            <button onClick={cerrarModal} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700">
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="sm:col-span-2 space-y-1.5">
                                <Label>Razón social *</Label>
                                <Input
                                    value={form.razon_social}
                                    onChange={e => setForm(f => ({ ...f, razon_social: e.target.value }))}
                                    placeholder="Transportes XYZ S.A."
                                />
                                {errors.razon_social && <p className="text-xs text-red-400">{errors.razon_social}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Identificación</Label>
                                <Input
                                    value={form.identificacion}
                                    onChange={e => setForm(f => ({ ...f, identificacion: e.target.value }))}
                                    placeholder="Cédula o RUC"
                                    maxLength={20}
                                />
                                {errors.identificacion && <p className="text-xs text-red-400">{errors.identificacion}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Placa</Label>
                                <Input
                                    value={form.placa}
                                    onChange={e => setForm(f => ({ ...f, placa: e.target.value.toUpperCase() }))}
                                    placeholder="ABC-1234"
                                    maxLength={20}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Email</Label>
                                <Input
                                    type="email"
                                    value={form.email}
                                    onChange={e => setForm(f => ({ ...f, email: e.target.value }))}
                                    placeholder="contacto@empresa.com"
                                />
                                {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Teléfono</Label>
                                <Input
                                    value={form.telefono}
                                    onChange={e => setForm(f => ({ ...f, telefono: e.target.value }))}
                                    placeholder="+593 99 999 9999"
                                    inputMode="tel"
                                />
                                {errors.telefono && <p className="text-xs text-red-400">{errors.telefono}</p>}
                            </div>
                            <div className="sm:col-span-2 space-y-1.5">
                                <Label>Dirección</Label>
                                <Input
                                    value={form.direccion}
                                    onChange={e => setForm(f => ({ ...f, direccion: e.target.value }))}
                                    placeholder="Dirección del transportista"
                                    maxLength={300}
                                />
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setForm(f => ({ ...f, estado: !f.estado }))}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                    form.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'
                                }`}
                            >
                                <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                    form.estado ? 'translate-x-6' : 'translate-x-1'
                                }`} />
                            </button>
                            <Label>Activo</Label>
                        </div>

                        <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                            <Button onClick={guardar} loading={procesando}>
                                <Save className="w-4 h-4" />
                                {editando ? 'Guardar cambios' : 'Crear transportista'}
                            </Button>
                            <Button variant="outline" onClick={cerrarModal}>
                                Cancelar
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
