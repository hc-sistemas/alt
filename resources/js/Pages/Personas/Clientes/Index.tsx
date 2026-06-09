import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { Plus, Search, Pencil, Trash2, FileText, FileSpreadsheet } from 'lucide-react'
import { formatMoneda } from '@/lib/utils'
import type { Cliente, PaginatedData, PageProps } from '@/types'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'

interface Props extends PageProps {
    clientes: PaginatedData<Cliente>
    filters: { search?: string; estado?: string }
}

const TIPO_BADGE: Record<string, string> = {
    '04': 'RUC',
    '05': 'CED',
    '06': 'PAS',
    '07': 'CF',
}

export default function ClientesIndex() {
    const { clientes, filters } = usePage<Props>().props
    const [search, setSearch] = useState(filters.search ?? '')
    const [estado, setEstado] = useState(filters.estado ?? '')
    const [pdfModal, setPdfModal] = useState(false)
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('personas.clientes.index'), { search, estado }, { preserveState: true, replace: true })
        }, 400)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, estado])

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = clientes.data.map(c => ({
            'Tipo':           TIPO_BADGE[c.tipo_identificacion] ?? c.tipo_identificacion,
            'Identificación': c.identificacion,
            'Razón Social':   c.razon_social,
            'Teléfono':       c.telefono ?? '—',
            'Email':          c.email ?? '—',
            'Ciudad':         c.ciudad ?? '—',
            'Crédito':        c.tiene_credito ? `${c.dias_credito} días` : 'No',
            'Estado':         c.estado ? 'Activo' : 'Inactivo',
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Clientes')
        XLSX.writeFile(wb, 'clientes.xlsx')
    }

    async function eliminar(cliente: Cliente) {
        const confirmado = await confirmarEliminar(cliente.razon_social)
        if (!confirmado) return
        router.delete(route('personas.clientes.destroy', cliente.id), {
            onSuccess: () => toastExito('Cliente eliminado correctamente'),
            onError: () => toastError('Error al eliminar'),
        })
    }

    return (
        <AppLayout title="Clientes">
            <Head title="Clientes" />

            <PageHeader
                title="Clientes"
                description="Gestión de clientes del sistema"
                breadcrumbs={[{ label: 'Personas' }, { label: 'Clientes' }]}
            />

            <div className="p-6">
                {/* Barra de acciones */}
                <div className="flex items-center gap-4 mb-4 flex-wrap">
                    <Link href={route('personas.clientes.create')}>
                        <Button>
                            <Plus className="w-4 h-4" />
                            Nuevo Cliente
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
                                className="pl-9 w-56"
                            />
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-sm whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>Estado:</span>
                        <select
                            value={estado}
                            onChange={e => setEstado(e.target.value)}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
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
                                <th className="w-36 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Identificación</th>
                                <th className="min-w-[160px] px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Nombre / Razón Social</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Cantón</th>
                                <th className="px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Dirección</th>
                                <th className="w-28 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Teléfono</th>
                                <th className="w-36 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Email</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Crédito</th>
                                <th className="w-20 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Estado</th>
                                <th className="w-16 px-2 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            {clientes.data.length === 0 ? (
                                <tr>
                                    <td colSpan={10} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <div className="w-12 h-12 rounded-full flex items-center justify-center"
                                                style={{ background: 'var(--bg-card)' }}>
                                                <Search className="w-6 h-6" />
                                            </div>
                                            <p className="font-medium" style={{ color: 'var(--text-main)' }}>
                                                No hay clientes registrados
                                            </p>
                                            <p>Crea el primero usando el botón "Nuevo Cliente"</p>
                                        </div>
                                    </td>
                                </tr>
                            ) : clientes.data.map((cliente, index) => {
                                const numero = (clientes.current_page - 1) * clientes.per_page + index + 1
                                return (
                                <tr key={cliente.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="w-10 px-2 py-2 text-center" style={{ color: 'var(--text-muted)' }}>
                                        {numero}
                                    </td>
                                    <td className="w-36 px-2 py-2">
                                        <span className="inline-flex items-center gap-1">
                                            <span className="text-xs font-medium px-1 py-0.5 rounded"
                                                style={{ background: 'var(--bg-main)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                                                {TIPO_BADGE[cliente.tipo_identificacion] ?? cliente.tipo_identificacion}
                                            </span>
                                            <span className="font-mono" style={{ color: 'var(--text-muted)' }}>
                                                {cliente.identificacion}
                                            </span>
                                        </span>
                                    </td>
                                    <td className="px-2 py-2 font-medium" style={{ color: 'var(--text-main)', maxWidth: '200px', minWidth: '160px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                        title={cliente.razon_social}>
                                        {cliente.razon_social}
                                        {cliente.agente_retencion && (
                                            <Badge variant="outline" className="ml-1 text-xs">AR</Badge>
                                        )}
                                    </td>
                                    <td className="w-24 px-2 py-2 truncate" style={{ color: 'var(--text-muted)', maxWidth: '96px' }}>
                                        {cliente.ciudad ?? '—'}
                                    </td>
                                    <td className="px-2 py-2 truncate" style={{ color: 'var(--text-muted)', maxWidth: '160px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {cliente.direccion ?? '—'}
                                    </td>
                                    <td className="w-28 px-2 py-2 whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>
                                        {cliente.telefono ?? '—'}
                                    </td>
                                    <td className="w-36 px-2 py-2 truncate" style={{ color: 'var(--text-muted)', maxWidth: '144px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                        title={cliente.email ?? ''}>
                                        {cliente.email ?? '—'}
                                    </td>
                                    <td className="w-24 px-2 py-2">
                                        {cliente.tiene_credito ? (
                                            <Badge variant="secondary" className="whitespace-nowrap">
                                                {cliente.dias_credito}d
                                                {cliente.cupo_maximo ? ` · ${formatMoneda(cliente.cupo_maximo)}` : ''}
                                            </Badge>
                                        ) : (
                                            <span style={{ color: 'var(--text-muted)' }}>—</span>
                                        )}
                                    </td>
                                    <td className="w-20 px-2 py-2">
                                        <span className={`inline-flex items-center px-1.5 py-0.5 rounded-full font-medium ${
                                            cliente.estado
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        }`}>
                                            {cliente.estado ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="w-16 px-2 py-2">
                                        <div className="flex items-center justify-center gap-0.5">
                                            <Link href={route('personas.clientes.edit', cliente.id)}>
                                                <Button variant="ghost" size="icon" title="Editar">
                                                    <Pencil className="w-3.5 h-3.5" />
                                                </Button>
                                            </Link>
                                            <Button variant="ghost" size="icon" title="Eliminar"
                                                onClick={() => eliminar(cliente)}>
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
                {clientes.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {clientes.from}–{clientes.to} de {clientes.total}
                        </p>
                        <div className="flex gap-1">
                            {clientes.links.map((link, i) => (
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
                url={pdfModal ? route('personas.clientes.reporte.lista', { estado: estado || undefined }) : ''}
                titulo="Lista de Clientes"
                nombreDescarga="clientes.pdf"
            />
        </AppLayout>
    )
}
