import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import ConfirmModal from '@/Components/shared/ConfirmModal'
import { Plus, Search, Eye, Pencil, History } from 'lucide-react'
import { formatFecha } from '@/lib/utils'
import type { Usuario, Perfil, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    usuarios: PaginatedData<Usuario>
    perfiles: Perfil[]
    filters: { search?: string; perfil_id?: string; estado?: string }
}

export default function UsuariosIndex() {
    const { usuarios, perfiles, filters } = usePage<Props>().props
    const [confirmToggle, setConfirmToggle] = useState<Usuario | null>(null)
    const [procesando, setProcesando] = useState(false)

    function buscar(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault()
        const form = new FormData(e.currentTarget)
        router.get(route('configuracion.usuarios.index'), {
            search: form.get('search') as string,
        }, { preserveState: true })
    }

    function toggleEstado() {
        if (!confirmToggle) return
        setProcesando(true)
        router.patch(route('configuracion.usuarios.toggle-estado', confirmToggle.id), {}, {
            onFinish: () => { setProcesando(false); setConfirmToggle(null) },
        })
    }

    return (
        <AppLayout title="Usuarios">
            <Head title="Usuarios" />

            <PageHeader
                title="Usuarios"
                description="Gestión de cuentas de acceso al sistema"
                breadcrumbs={[{ label: 'Configuración' }, { label: 'Usuarios' }]}
                actions={
                    <Link href={route('configuracion.usuarios.create')}>
                        <Button><Plus className="w-4 h-4" /> Nuevo Usuario</Button>
                    </Link>
                }
            />

            <div className="p-6">
                {/* Filtros */}
                <div className="flex gap-3 mb-4">
                    <form onSubmit={buscar} className="flex gap-2 flex-1">
                        <div className="relative flex-1 max-w-xs">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Buscar nombre, email, username..."
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" variant="outline" size="sm">Buscar</Button>
                    </form>
                </div>

                {/* Tabla */}
                <div className="rounded-xl border overflow-hidden"
                    style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <th className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                    style={{ color: 'var(--text-muted)' }}>Usuario</th>
                                <th className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider hidden sm:table-cell"
                                    style={{ color: 'var(--text-muted)' }}>Perfil</th>
                                <th className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider hidden md:table-cell"
                                    style={{ color: 'var(--text-muted)' }}>Empresa</th>
                                <th className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider hidden lg:table-cell"
                                    style={{ color: 'var(--text-muted)' }}>Último acceso</th>
                                <th className="text-center px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                    style={{ color: 'var(--text-muted)' }}>Estado</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {usuarios.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-12" style={{ color: 'var(--text-muted)' }}>
                                        <p className="text-sm">No hay usuarios registrados.</p>
                                        <Link href={route('configuracion.usuarios.create')}
                                            className="text-amber-500 hover:underline text-sm mt-1 inline-block">
                                            Crear el primero
                                        </Link>
                                    </td>
                                </tr>
                            ) : usuarios.data.map((usuario, i) => (
                                <tr key={usuario.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-black shrink-0"
                                                style={{ background: '#F59E0B' }}>
                                                {usuario.nombre.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="font-medium" style={{ color: 'var(--text-main)' }}>{usuario.nombre}</p>
                                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{usuario.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 hidden sm:table-cell">
                                        <Badge variant="secondary" className="capitalize">
                                            {usuario.perfil?.nombre ?? '—'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-xs hidden md:table-cell"
                                        style={{ color: 'var(--text-muted)' }}>
                                        {usuario.empresa?.nombre_comercial ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-xs hidden lg:table-cell"
                                        style={{ color: 'var(--text-muted)' }}>
                                        {usuario.ultimo_acceso ? formatFecha(usuario.ultimo_acceso) : 'Nunca'}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <button
                                            onClick={() => setConfirmToggle(usuario)}
                                            className={`relative inline-flex h-5 w-9 items-center rounded-full transition-colors ${
                                                usuario.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'
                                            }`}
                                        >
                                            <span className={`inline-block h-3.5 w-3.5 rounded-full bg-white shadow-sm transition-transform ${
                                                usuario.estado ? 'translate-x-4' : 'translate-x-0.5'
                                            }`} />
                                        </button>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Link href={route('configuracion.usuarios.edit', usuario.id)}>
                                                <Button variant="ghost" size="icon" title="Editar">
                                                    <Pencil className="w-4 h-4" />
                                                </Button>
                                            </Link>
                                            <Link href={route('configuracion.usuarios.show', usuario.id)}>
                                                <Button variant="ghost" size="icon" title="Historial">
                                                    <History className="w-4 h-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {usuarios.meta.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {usuarios.meta.from}–{usuarios.meta.to} de {usuarios.meta.total}
                        </p>
                        <div className="flex gap-1">
                            {usuarios.links.map((link, i) => (
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
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

            <ConfirmModal
                open={!!confirmToggle}
                title={confirmToggle?.estado ? 'Desactivar usuario' : 'Activar usuario'}
                message={`¿Confirmas ${confirmToggle?.estado ? 'desactivar' : 'activar'} al usuario ${confirmToggle?.nombre}?`}
                confirmLabel={confirmToggle?.estado ? 'Desactivar' : 'Activar'}
                variant={confirmToggle?.estado ? 'danger' : 'warning'}
                loading={procesando}
                onConfirm={toggleEstado}
                onCancel={() => setConfirmToggle(null)}
            />
        </AppLayout>
    )
}
