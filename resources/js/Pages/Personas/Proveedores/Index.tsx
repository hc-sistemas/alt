import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { Plus, Search, Pencil, Trash2, FileText, FileSpreadsheet } from 'lucide-react'
import type { Proveedor, PaginatedData, PageProps } from '@/types'
import { confirmarEliminar } from '@/lib/swal'
import { toastExito, toastError } from '@/lib/toast'
import { cn } from "../../../lib/utils";

interface Props extends PageProps {
    proveedores: PaginatedData<Proveedor>
    filters: { search?: string; tipo?: string; estado?: string }
}

export default function ProveedoresIndex() {
    const { proveedores, filters } = usePage<Props>().props
    const [search, setSearch] = useState(filters.search ?? '')
    const [tipo, setTipo] = useState(filters.tipo ?? 'todos')
    const [estado, setEstado] = useState(filters.estado ?? '')
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
            'RUC/Cédula':       p.identificacion ?? '—',
            'Razón Social':     p.razon_social,
            'Nombre Comercial': p.nombre_comercial ?? '—',
            'Teléfono':         p.telefono ?? '—',
            'Email':            p.email ?? '—',
            'Ciudad':           p.ciudad ?? '—',
            'País':             p.pais,
            'Crédito':          p.tiene_credito ? `${p.dias_credito} días` : 'No',
            'Estado':           p.estado ? 'Activo' : 'Inactivo',
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
                <div className={cn('flex', 'gap-0', 'mb-4', 'border-b')} style={{ borderColor: 'var(--border)' }}>
                    {tipoTabs.map(tab => (
                        <button
                            key={tab.value}
                            onClick={() => setTipo(tab.value)}
                            className={cn('-mb-px', 'px-4', 'py-2', 'border-b-2', 'font-medium', 'text-sm', 'transition-colors')}
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
                <div className={cn('flex', 'flex-wrap', 'items-center', 'gap-4', 'mb-4')}>
                    <Link href={route('personas.proveedores.create')}>
                        <Button>
                            <Plus className={cn('w-4', 'h-4')} />
                            Nuevo Proveedor
                        </Button>
                    </Link>

                    <div className={cn('flex', 'items-center', 'gap-2')}>
                        <span className={cn('text-sm', 'whitespace-nowrap')} style={{ color: 'var(--text-muted)' }}>Buscar:</span>
                        <div className="relative">
                            <Search className={cn('top-2.5', 'left-3', 'absolute', 'w-4', 'h-4')} style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={search}
                                onChange={e => setSearch(e.target.value)}
<<<<<<< Updated upstream
                                placeholder="RUC o nombre..."
                                className="pl-9 w-56"
=======
                                placeholder="Identificación o razón social..."
                                className={cn('pl-9', 'w-64')}
>>>>>>> Stashed changes
                            />
                        </div>
                    </div>

                    <div className={cn('flex', 'items-center', 'gap-2')}>
                        <span className={cn('text-sm', 'whitespace-nowrap')} style={{ color: 'var(--text-muted)' }}>Estado:</span>
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

<<<<<<< Updated upstream
                    <div className="flex items-center gap-2 ml-auto">
                        <a href={route('personas.proveedores.reporte.lista')} target="_blank" rel="noreferrer"
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
=======
                    <div className={cn('flex', 'items-center', 'gap-2', 'ml-auto')}>
                        <button
                            onClick={() => setPdfModal(true)}
                            className={cn('flex', 'items-center', 'gap-1.5', 'px-3', 'py-1.5', 'rounded-md', 'font-medium', 'text-sm')}
>>>>>>> Stashed changes
                            style={{ background: '#DC2626', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#B91C1C')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#DC2626')}>
                            <FileText className={cn('w-4', 'h-4')} />
                            PDF
<<<<<<< Updated upstream
                        </a>
                        <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
=======
                        </button>
                        <button className={cn('flex', 'items-center', 'gap-1.5', 'px-3', 'py-1.5', 'rounded-md', 'font-medium', 'text-sm')}
>>>>>>> Stashed changes
                            style={{ background: '#16A34A', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                            onClick={exportarExcel}>
                            <FileSpreadsheet className={cn('w-4', 'h-4')} />
                            Excel
                        </button>
                    </div>
                </div>

                {/* Tabla */}
                <div className={cn('border', 'rounded-xl', 'overflow-x-auto')} style={{ borderColor: 'var(--border)' }}>
                    <table className={cn('w-full', 'text-xs')}>
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
<<<<<<< Updated upstream
                                <th className="w-10 px-2 py-2 font-medium text-center" style={{ color: 'var(--text-muted)' }}>No</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Tipo</th>
                                <th className="w-32 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>RUC/ID</th>
                                <th className="min-w-[140px] px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Nombre</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>País</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Ciudad</th>
                                <th className="w-28 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Teléfono</th>
                                <th className="w-36 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Email</th>
                                <th className="w-24 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Crédito</th>
                                <th className="w-20 px-2 py-2 font-medium text-left" style={{ color: 'var(--text-muted)' }}>Estado</th>
                                <th className="w-16 px-2 py-2" />
=======
                                <th className={cn('px-2', 'py-2', 'w-10', 'font-medium', 'text-center')} style={{ color: 'var(--text-muted)' }}>No</th>
                                <th className={cn('px-2', 'py-2', 'w-24', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Tipo</th>
                                <th className={cn('px-2', 'py-2', 'w-36', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Identificación</th>
                                <th className={cn('px-2', 'py-2', 'min-w-35', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Razón Social</th>
                                <th className={cn('px-2', 'py-2', 'w-24', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>País</th>
                                <th className={cn('px-2', 'py-2', 'w-24', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Ciudad</th>
                                <th className={cn('px-2', 'py-2', 'w-28', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Teléfono</th>
                                <th className={cn('px-2', 'py-2', 'w-36', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Email</th>
                                <th className={cn('px-2', 'py-2', 'w-24', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Crédito</th>
                                <th className={cn('px-2', 'py-2', 'w-20', 'font-medium', 'text-left')} style={{ color: 'var(--text-muted)' }}>Estado</th>
                                <th className={cn('px-2', 'py-2', 'w-16')} />
>>>>>>> Stashed changes
                            </tr>
                        </thead>
                        <tbody>
                            {proveedores.data.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className={cn('py-16', 'text-center')} style={{ color: 'var(--text-muted)' }}>
                                        <div className={cn('flex', 'flex-col', 'items-center', 'gap-2')}>
                                            <div className={cn('flex', 'justify-center', 'items-center', 'rounded-full', 'w-12', 'h-12')}
                                                style={{ background: 'var(--bg-card)' }}>
                                                <Search className={cn('w-6', 'h-6')} />
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
<<<<<<< Updated upstream
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
                                    <td className="w-32 px-2 py-2 font-mono" style={{ color: 'var(--text-muted)' }}>
                                        {proveedor.identificacion ?? '—'}
                                    </td>
                                    <td className="px-2 py-2 font-medium" style={{ color: 'var(--text-main)', maxWidth: '180px', minWidth: '140px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                        title={proveedor.nombre_comercial ?? proveedor.razon_social}>
                                        {proveedor.nombre_comercial ?? proveedor.razon_social}
                                    </td>
                                    <td className="w-24 px-2 py-2" style={{ color: 'var(--text-muted)', maxWidth: '96px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                        {proveedor.tipo === 'internacional' && proveedor.divisa ? (
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
=======
                                    <tr key={proveedor.id}
                                        className={cn('hover:bg-slate-50', 'dark:hover:bg-slate-800/50', 'border-t', 'transition-colors')}
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className={cn('px-2', 'py-2', 'w-10', 'text-center')} style={{ color: 'var(--text-muted)' }}>
                                            {numero}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-24')}>
                                            <Badge variant={proveedor.tipo === 'nacional' ? 'secondary' : 'outline'} className="capitalize">
                                                {proveedor.tipo === 'nacional' ? 'Nacional' : 'Intl.'}
>>>>>>> Stashed changes
                                            </Badge>
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-36')}>
                                            <div className={cn('flex', 'items-center', 'gap-1.5')}>
                                                <span className={cn('inline-flex', 'items-center', 'bg-slate-100', 'dark:bg-slate-800', 'px-1.5', 'py-0.5', 'rounded', 'font-medium', 'text-[10px]', 'text-slate-600', 'dark:text-slate-400')}>
                                                    {LABEL_TIPO_ID[proveedor.tipo_identificacion] ?? proveedor.tipo_identificacion}
                                                </span>
                                                <span className="font-mono" style={{ color: 'var(--text-muted)' }}>
                                                    {proveedor.identificacion}
                                                </span>
                                            </div>
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'font-medium')} style={{ color: 'var(--text-main)', maxWidth: '180px', minWidth: '140px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                            title={proveedor.razon_social}>
                                            {proveedor.razon_social}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-24')} style={{ color: 'var(--text-muted)', maxWidth: '96px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {proveedor.tipo === 'internacional' ? (
                                                <span>{proveedor.pais} · <span className="font-mono">{proveedor.divisa}</span></span>
                                            ) : (
                                                proveedor.pais
                                            )}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-24')} style={{ color: 'var(--text-muted)', maxWidth: '96px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {proveedor.ciudad ?? '—'}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-28', 'whitespace-nowrap')} style={{ color: 'var(--text-muted)' }}>
                                            {proveedor.telefono ?? '—'}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-36')} style={{ color: 'var(--text-muted)', maxWidth: '144px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}
                                            title={proveedor.email ?? ''}>
                                            {proveedor.email ?? '—'}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-24')}>
                                            {proveedor.tiene_credito ? (
                                                <Badge variant="secondary" className="whitespace-nowrap">
                                                    {proveedor.dias_credito}d
                                                </Badge>
                                            ) : (
                                                <span style={{ color: 'var(--text-muted)' }}>—</span>
                                            )}
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-20')}>
                                            <span className={`inline-flex items-center px-1.5 py-0.5 rounded-full font-medium ${proveedor.estado
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                                }`}>
                                                {proveedor.estado ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className={cn('px-2', 'py-2', 'w-16')}>
                                            <div className={cn('flex', 'justify-center', 'items-center', 'gap-0.5')}>
                                                <Link href={route('personas.proveedores.edit', proveedor.id)}>
                                                    <Button variant="ghost" size="icon" title="Editar">
                                                        <Pencil className={cn('w-3.5', 'h-3.5')} />
                                                    </Button>
                                                </Link>
                                                <Button variant="ghost" size="icon" title="Eliminar"
                                                    onClick={() => eliminar(proveedor)}>
                                                    <Trash2 className={cn('w-3.5', 'h-3.5', 'text-red-400')} />
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
                    <div className={cn('flex', 'justify-between', 'items-center', 'mt-4', 'text-sm')}>
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {proveedores.from}–{proveedores.to} de {proveedores.total}
                        </p>
                        <div className={cn('flex', 'gap-1')}>
                            {proveedores.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`px-3 py-1 rounded border text-xs transition-colors ${link.active
                                                ? 'border-amber-500 bg-amber-500 text-black font-medium'
                                                : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                                            }`}
                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className={cn('opacity-40', 'px-3', 'py-1', 'border', 'rounded', 'text-xs')}
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
