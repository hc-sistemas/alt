import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import ConfirmModal from '@/Components/shared/ConfirmModal'
import { Plus, Search, Pencil, History, FileSpreadsheet, Link2, X } from 'lucide-react'
import { formatFecha } from '@/lib/utils'
import type { Usuario, Perfil, Colaborador, PaginatedData, PageProps } from '@/types'

interface Props extends PageProps {
    usuarios: PaginatedData<Usuario>
    perfiles: Perfil[]
    colaboradores: Colaborador[]
    filters: { search?: string; perfil_id?: string; estado?: string }
}

export default function UsuariosIndex() {
    const { usuarios, perfiles, colaboradores, filters } = usePage<Props>().props
    const [confirmToggle, setConfirmToggle] = useState<Usuario | null>(null)
    const [procesando, setProcesando] = useState(false)
    const [vincularUsuario, setVincularUsuario] = useState<Usuario | null>(null)
    const [colaboradorSeleccionado, setColaboradorSeleccionado] = useState<string>('')
    const [procesandoVincular, setProcesandoVincular] = useState(false)

    function abrirVincular(usuario: Usuario) {
        const actual = colaboradores.find(c => c.usuario_id === usuario.id)
        setColaboradorSeleccionado(actual ? String(actual.id) : '')
        setVincularUsuario(usuario)
    }

    function ejecutarVincular() {
        if (!vincularUsuario) return
        setProcesandoVincular(true)
        router.patch(
            route('configuracion.usuarios.vincular-colaborador', vincularUsuario.id),
            { colaborador_id: colaboradorSeleccionado || null },
            {
                onFinish: () => {
                    setProcesandoVincular(false)
                    setVincularUsuario(null)
                },
            }
        )
    }

    // Disponibles: sin usuario vinculado, o el que ya tiene este usuario
    function colaboradoresDisponibles(usuario: Usuario) {
        return colaboradores.filter(c => !c.usuario_id || c.usuario_id === usuario.id)
    }

    async function exportarExcel() {
        const XLSX = await import('xlsx')
        const filas = usuarios.data.map(u => ({
            'Nombre':  u.nombre,
            'Email':   u.email,
            'Perfil':  u.perfil?.nombre ?? '—',
            'Estado':  u.estado ? 'Activo' : 'Inactivo',
        }))
        const ws = XLSX.utils.json_to_sheet(filas)
        const wb = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(wb, ws, 'Usuarios')
        XLSX.writeFile(wb, 'usuarios.xlsx')
    }

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
                <div className="flex items-center gap-3 mb-4 flex-wrap">
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
                    <button className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium ml-auto"
                        style={{ background: '#16A34A', color: 'white', transition: 'background 0.2s' }}
                        onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                        onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                        onClick={exportarExcel}>
                        <FileSpreadsheet className="w-4 h-4" />
                        Excel
                    </button>
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
                                    style={{ color: 'var(--text-muted)' }}>Colaborador</th>
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
                                    <td colSpan={7} className="text-center py-12" style={{ color: 'var(--text-muted)' }}>
                                        <p className="text-sm">No hay usuarios registrados.</p>
                                        <Link href={route('configuracion.usuarios.create')}
                                            className="text-amber-500 hover:underline text-sm mt-1 inline-block">
                                            Crear el primero
                                        </Link>
                                    </td>
                                </tr>
                            ) : usuarios.data.map(usuario => {
                                const colabVinculado = colaboradores.find(c => c.usuario_id === usuario.id)
                                return (
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
                                        <td className="px-4 py-3 hidden lg:table-cell">
                                            {colabVinculado ? (
                                                <span className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium"
                                                    style={{ background: 'rgba(16,185,129,0.12)', color: '#059669' }}>
                                                    <Link2 className="w-3 h-3" />
                                                    {colabVinculado.apellidos} {colabVinculado.nombres.split(' ')[0]}
                                                </span>
                                            ) : (
                                                <span className="text-xs" style={{ color: 'var(--text-muted)' }}>Sin colaborador</span>
                                            )}
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
                                                <button
                                                    onClick={() => abrirVincular(usuario)}
                                                    title={colabVinculado ? 'Cambiar colaborador vinculado' : 'Vincular colaborador'}
                                                    className="p-1.5 rounded-md transition-colors"
                                                    style={{ color: colabVinculado ? '#059669' : 'var(--text-muted)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(0,0,0,0.06)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                                >
                                                    <Link2 className="w-4 h-4" />
                                                </button>
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
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {usuarios.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {usuarios.from}–{usuarios.to} de {usuarios.total}
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

            {/* Modal vincular colaborador */}
            {vincularUsuario && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    onClick={() => setVincularUsuario(null)}>
                    <div className="rounded-xl border shadow-xl p-6 w-full max-w-sm"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                        onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                Vincular Colaborador
                            </h3>
                            <button onClick={() => setVincularUsuario(null)}
                                className="p-1 rounded hover:bg-red-50 text-red-500">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        <p className="text-xs mb-4" style={{ color: 'var(--text-muted)' }}>
                            Usuario: <strong style={{ color: 'var(--text-main)' }}>{vincularUsuario.nombre}</strong>
                        </p>
                        <label className="block text-xs font-medium mb-1.5" style={{ color: 'var(--text-muted)' }}>
                            Colaborador vinculado
                        </label>
                        <select
                            value={colaboradorSeleccionado}
                            onChange={e => setColaboradorSeleccionado(e.target.value)}
                            style={{
                                display: 'block', width: '100%', height: '38px',
                                padding: '0 0.75rem', fontSize: '0.875rem',
                                borderRadius: '0.5rem', border: '1px solid var(--border)',
                                background: 'var(--bg-card)', color: 'var(--text-main)',
                                outline: 'none', cursor: 'pointer',
                            }}
                        >
                            <option value="">— Sin colaborador (desvincular) —</option>
                            {colaboradoresDisponibles(vincularUsuario).map(c => (
                                <option key={c.id} value={String(c.id)}>
                                    {c.apellidos} {c.nombres}
                                </option>
                            ))}
                        </select>
                        <div className="flex justify-end gap-2 mt-5">
                            <button
                                onClick={() => setVincularUsuario(null)}
                                className="px-3 py-1.5 text-sm rounded-md border transition-colors"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                            >
                                Cancelar
                            </button>
                            <button
                                onClick={ejecutarVincular}
                                disabled={procesandoVincular}
                                className="btn-primary px-4 py-1.5 text-sm"
                            >
                                {procesandoVincular ? 'Guardando...' : 'Guardar'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
