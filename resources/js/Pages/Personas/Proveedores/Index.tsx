import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { Plus, Search, Pencil, Trash2, FileText, FileSpreadsheet } from 'lucide-react'
import type { Proveedor, PaginatedData, PageProps } from '@/types'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'

interface Props extends PageProps {
    proveedores: PaginatedData<Proveedor>
    filters: { search?: string; tipo?: string; estado?: string }
}

const LABEL_TIPO_ID: Record<string, string> = {
    '04': 'RUC',
    '05': 'Ced',
    '06': 'Pas',
}

export default function ProveedoresIndex() {
    const { proveedores, filters } = usePage<Props>().props
    const [search, setSearch] = useState(filters.search ?? '')
    const [tipo, setTipo] = useState(filters.tipo ?? 'todos')
    const [estado, setEstado] = useState(filters.estado ?? '')
    const [pdfModal, setPdfModal] = useState(false)
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('personas.proveedores.index'), { search, tipo, estado }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, tipo, estado])

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = proveedores.data.map(p => ({
            'Identificación': p.identificacion,
            'Tipo ID':        LABEL_TIPO_ID[p.tipo_identificacion] ?? p.tipo_identificacion,
            'Razón Social':   p.razon_social,
            'Nombre Comercial': p.nombre_comercial ?? '—',
            'Teléfono':       p.telefono ?? '—',
            'Email':          p.email ?? '—',
            'Ciudad':         p.ciudad ?? '—',
            'País':           p.pais,
            'Divisa':         p.divisa,
            'Crédito':        p.tiene_credito ? `${p.dias_credito} días` : 'No',
            'Estado':         p.estado ? 'Activo' : 'Inactivo',
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Proveedores')
        XLSX.writeFile(wb, 'proveedores.xlsx')
    }

    async function eliminar(proveedor: Proveedor) {
        const confirmado = await confirmarEliminar(proveedor.razon_social)
        if (!confirmado) return
        router.delete(route('personas.proveedores.destroy', proveedor.id), {
            onSuccess: () => toastExito('Proveedor eliminado correctamente'),
            onError: () => toastError('Error al eliminar'),
        })
    }

    const tipoTabs = [
        { label: 'Todos', value: 'todos' },
        { label: 'Nacionales', value: 'nacional' },
        { label: 'Internacionales', value: 'internacional' },
    ]

    return (
        <AppLayout title="Proveedores">
            <Head title="Proveedores" />

            <PageHeader
                title="Proveedores"
                description="Gestión de proveedores nacionales e internacionales"
                breadcrumbs={[{ label: 'Personas' }, { label: 'Proveedores' }]}
            />

            <div className="p-6">
                {/* Tabs tipo */}
                <div className="flex gap-0 mb-4 border-b" style={{ borderColor: 'var(--border)' }}>
                    {tipoTabs.map(tab => (
                        <button
                            key={tab.value}
                            onClick={() => setTipo(tab.value)}
                            className="px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px"
                            style={{
                                borderBottomColor: tipo === tab.value ? 'var(--primary)' : 'transparent',
                                color: tipo === tab.value ? 'var(--primary)' : 'var(--text-muted)',
                            }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Barra de acciones */}
                <div className="flex items-center gap-4 mb-4 flex-wrap">
                    <Link href={route('personas.proveedores.create')}>
                        <Button>
                            <Plus className="w-4 h-4" />
                            Nuevo Proveedor
                        </Button>
                    </Link>

                    <div className="flex items-center gap-2">
                        <span className="text-sm whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>Buscar:</span>
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                placeholder="Identificación o razón social..."
                                className="pl-9 w-64"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-sm whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>Estado:</span>
                        <select
                            value={estado}
                            onChange={e => setEstado(e.target.value)}
                            className="input-field"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        >
                            <option value="">Todos</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>
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

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <th className="w-10 px-2 py-2 font-medium text-center" style={{ color: 'var(--text-muted)' }}>No</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Tipo</th>
                                <th className="w-36 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Identificación</th>
                                <th className="min-w-[140px] px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Razón Social</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>País</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Ciudad</th>
                                <th className="w-28 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Teléfono</th>
                                <th className="w-36 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Email</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Crédito</th>
                                <th className="w-20 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Estado</th>
                                <th className="w-16 px-2 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {proveedores.data.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="w-12 h-12 rounded-full flex items-center justify-center"
                                                style={{ background: 'var(--bg-card)' }}>
                                                <Search className="w-6 h-6" />
                                            </div>
                                            <p className="font-medium" style={{ color: 'var(--text-main)' }}>
                                                No hay proveedores registrados
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : proveedores.data.map((proveedor, index) => {
                                const numero = (proveedores.current_page - 1) * proveedores.per_page + index + 1
                                return (
                                <tr key={proveedor.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="w-10 px-2 py-2 text-center" style={{ color: 'var(--text-muted)' }}>
                                        {numero}
                                    </td>
                                    <td className="w-24 px-2 py-2">
                                        <Badge variant={proveedor.tipo === 'nacional' ? 'secondary' : 'outline'} className="capitalize">
                                            {proveedor.tipo === 'nacional' ? 'Nacional' : 'Intl.'}
                                        </Badge>
                                    </td>
                                    <td className="w-36 px-2 py-2">
                                        <div className="flex items-center gap-1.5">
                                            <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                                {LABEL_TIPO_ID[proveedor.tipo_identificacion] ?? proveedor.tipo_identificacion}
                                            </span>
                                            <span className="font-mono" style={{ color: 'var(--text-muted)' }}>
                                                {proveedor.identificacion}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-2 py-2 font-medium" style={{ color: 'var(--text-main)', maxWidth: '180px', minWidth: '140px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                        title={proveedor.razon_social}>
                                        {proveedor.razon_social}
                                    </td>
                                    <td className="w-24 px-2 py-2" style={{ color: 'var(--text-muted)', maxWidth: '96px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {proveedor.tipo === 'internacional' ? (
                                            <span>{proveedor.pais} · <span className="font-mono">{proveedor.divisa}</span></span>
                                        ) : (
                                            proveedor.pais
                                        )}
                                    </td>
                                    <td className="w-24 px-2 py-2" style={{ color: 'var(--text-muted)', maxWidth: '96px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {proveedor.ciudad ?? '—'}
                                    </td>
                                    <td className="w-28 px-2 py-2 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                        {proveedor.telefono ?? '—'}
                                    </td>
                                    <td className="w-36 px-2 py-2" style={{ color: 'var(--text-muted)', maxWidth: '144px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                        title={proveedor.email ?? ''}>
                                        {proveedor.email ?? '—'}
                                    </td>
                                    <td className="w-24 px-2 py-2">
                                        {proveedor.tiene_credito ? (
                                            <Badge variant="secondary" className="whitespace-nowrap">
                                                {proveedor.dias_credito}d
                                            </Badge>
                                        ) : (
                                            <span style={{ color: 'var(--text-muted)' }}>—</span>
                                        )}
                                    </td>
                                    <td className="w-20 px-2 py-2">
                                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded-full font-medium ${
                                            proveedor.estado
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        }`}>
                                            {proveedor.estado ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="w-16 px-2 py-2">
                                        <div className="flex items-center justify-center gap-0.5">
                                            <Link href={route('personas.proveedores.edit', proveedor.id)}>
                                                <Button variant="ghost" size="icon" title="Editar">
                                                    <Pencil className="w-3.5 h-3.5" />
                                                </Button>
                                            </Link>
                                            <Button variant="ghost" size="icon" title="Eliminar"
                                                onClick={() => eliminar(proveedor)}>
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

                {/* Paginación */}
                {proveedores.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {proveedores.from}–{proveedores.to} de {proveedores.total}
                        </p>
                        <div className="flex gap-1">
                            {proveedores.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`px-3 py-1 rounded border text-xs transition-colors ${
                                            link.active
                                                ? 'border-amber-500 bg-amber-500 text-black font-medium'
                                                : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                                        }`}
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
                url={pdfModal ? route('personas.proveedores.reporte.lista', {
                    tipo: tipo !== 'todos' ? tipo : undefined,
                    estado: estado || undefined,
                }) : ''}
                titulo="Lista de Proveedores"
                nombreDescarga="proveedores.pdf"
            />
        </AppLayout>
    )
}
