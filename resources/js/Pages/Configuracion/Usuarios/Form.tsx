import { Head, useForm, usePage } from '@inertiajs/react'
import { FormEvent } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save } from 'lucide-react'
import type { Usuario, Perfil, Empresa, CentroCosto, PageProps } from '@/types'

interface Props extends PageProps {
    usuario?: Usuario & { empresas?: Empresa[] }
    perfiles: Perfil[]
    empresas: Empresa[]
    centros_costo: (CentroCosto & { empresa?: Empresa })[]
}

export default function UsuarioForm() {
    const { usuario, perfiles, empresas, centros_costo } = usePage<Props>().props
    const esEdicion = !!usuario

    const { data, setData, post, put, processing, errors } = useForm({
        nombre: usuario?.nombre ?? '',
        email: usuario?.email ?? '',
        username: usuario?.username ?? '',
        telefono: usuario?.telefono ?? '',
        perfil_id: usuario?.perfil_id ?? '',
        empresa_id: usuario?.empresa_id ?? '',
        centro_costo_id: usuario?.centro_costo_id ?? '',
        password: '',
        password_confirmation: '',
        codigo_aprobacion: '',
        codigo_aprobacion_confirmation: '',
        empresas: usuario?.empresas?.map(e => e.id) ?? [],
        estado: usuario?.estado ?? true,
    })

    function submit(e: FormEvent) {
        e.preventDefault()
        if (esEdicion) {
            put(route('configuracion.usuarios.update', usuario!.id))
        } else {
            post(route('configuracion.usuarios.store'))
        }
    }

    function toggleEmpresa(id: number) {
        const empresasActuales = data.empresas as number[]
        if (empresasActuales.includes(id)) {
            setData('empresas', empresasActuales.filter(e => e !== id))
        } else {
            setData('empresas', [...empresasActuales, id])
        }
    }

    const esAdminPlus = ['super_admin', 'admin'].includes(
        perfiles.find(p => p.id === Number(data.perfil_id))?.nombre ?? ''
    )

    return (
        <AppLayout title={esEdicion ? 'Editar Usuario' : 'Nuevo Usuario'}>
            <Head title={esEdicion ? 'Editar Usuario' : 'Nuevo Usuario'} />

            <PageHeader
                title={esEdicion ? 'Editar Usuario' : 'Nuevo Usuario'}
                breadcrumbs={[
                    { label: 'Configuración' },
                    { label: 'Usuarios', href: route('configuracion.usuarios.index') },
                    { label: esEdicion ? 'Editar' : 'Nuevo' }
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-2xl space-y-8">
                {/* Datos personales */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Datos personales
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2 space-y-1.5">
                            <Label>Nombre completo *</Label>
                            <Input value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                                placeholder="Nombre completo" error={errors.nombre} />
                            {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Email *</Label>
                            <Input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                                placeholder="correo@empresa.com" error={errors.email} />
                            {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Teléfono</Label>
                            <Input value={data.telefono} onChange={e => setData('telefono', e.target.value)}
                                placeholder="+593 99 999 9999" />
                        </div>
                    </div>
                </section>

                {/* Acceso */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Acceso al sistema
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Username *</Label>
                            <Input value={data.username} onChange={e => setData('username', e.target.value)}
                                placeholder="nombre.apellido" error={errors.username} />
                            {errors.username && <p className="text-xs text-red-400">{errors.username}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Perfil *</Label>
                            <select
                                value={data.perfil_id}
                                onChange={e => setData('perfil_id', e.target.value)}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            >
                                <option value="">Seleccionar perfil...</option>
                                {perfiles.map(p => (
                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                ))}
                            </select>
                            {errors.perfil_id && <p className="text-xs text-red-400">{errors.perfil_id}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Empresa principal *</Label>
                            <select
                                value={data.empresa_id}
                                onChange={e => setData('empresa_id', e.target.value)}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            >
                                <option value="">Seleccionar empresa...</option>
                                {empresas.map(e => (
                                    <option key={e.id} value={e.id}>{e.nombre_comercial}</option>
                                ))}
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Centro de costo</Label>
                            <select
                                value={data.centro_costo_id}
                                onChange={e => setData('centro_costo_id', e.target.value)}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            >
                                <option value="">Sin centro de costo</option>
                                {centros_costo.map(cc => (
                                    <option key={cc.id} value={cc.id}>{cc.nombre}</option>
                                ))}
                            </select>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Contraseña {esEdicion ? '' : '*'}</Label>
                            <Input type="password" value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                placeholder={esEdicion ? 'Dejar en blanco para no cambiar' : '••••••••'}
                                error={errors.password} />
                            {errors.password && <p className="text-xs text-red-400">{errors.password}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Confirmar contraseña</Label>
                            <Input type="password" value={data.password_confirmation}
                                onChange={e => setData('password_confirmation', e.target.value)}
                                placeholder="••••••••" />
                        </div>
                    </div>

                    {/* Empresas con acceso */}
                    <div className="mt-4 space-y-2">
                        <Label>Empresas con acceso *</Label>
                        <div className="flex flex-wrap gap-2">
                            {empresas.map(e => {
                                const seleccionada = (data.empresas as number[]).includes(e.id)
                                return (
                                    <button
                                        key={e.id}
                                        type="button"
                                        onClick={() => toggleEmpresa(e.id)}
                                        className={`px-3 py-1.5 rounded-lg border text-sm transition-all ${
                                            seleccionada
                                                ? 'border-amber-500 bg-amber-500/10 font-medium'
                                                : 'hover:border-amber-500/50'
                                        }`}
                                        style={{
                                            borderColor: seleccionada ? '#F59E0B' : 'var(--border)',
                                            color: seleccionada ? '#F59E0B' : 'var(--text-muted)',
                                        }}
                                    >
                                        {e.nombre_comercial}
                                    </button>
                                )
                            })}
                        </div>
                        {errors.empresas && <p className="text-xs text-red-400">{errors.empresas}</p>}
                    </div>
                </section>

                {/* Código de aprobación */}
                {esAdminPlus && (
                    <section>
                        <h2 className="text-base font-semibold mb-1 pb-2 border-b"
                            style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                            Código de aprobación
                        </h2>
                        <p className="text-xs mb-3" style={{ color: 'var(--text-muted)' }}>
                            PIN de 4-6 dígitos usado para aprobar operaciones especiales (descuentos, anulaciones, etc.)
                        </p>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label>PIN</Label>
                                <Input type="password" value={data.codigo_aprobacion}
                                    onChange={e => setData('codigo_aprobacion', e.target.value)}
                                    placeholder="••••" maxLength={6} />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Confirmar PIN</Label>
                                <Input type="password" value={data.codigo_aprobacion_confirmation}
                                    onChange={e => setData('codigo_aprobacion_confirmation', e.target.value)}
                                    placeholder="••••" maxLength={6} />
                            </div>
                        </div>
                    </section>
                )}

                {/* Estado */}
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => setData('estado', !data.estado)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                            data.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'
                        }`}
                    >
                        <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                            data.estado ? 'translate-x-6' : 'translate-x-1'
                        }`} />
                    </button>
                    <Label className="cursor-pointer">Usuario activo</Label>
                </div>

                {/* Acciones */}
                <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        {esEdicion ? 'Guardar cambios' : 'Crear usuario'}
                    </Button>
                    <Button type="button" variant="outline"
                        onClick={() => window.history.back()}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
