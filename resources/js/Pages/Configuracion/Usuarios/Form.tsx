import { Head, useForm, usePage } from '@inertiajs/react'
import { FormEvent } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { Usuario, Perfil, Empresa, CentroCosto, PageProps } from '@/types'

interface ColaboradorDisponible {
    id: number
    empresa_id: number
    apellidos: string
    nombres: string
    cedula_ruc: string
}

interface Props extends PageProps {
    colaboradores_disponibles?: ColaboradorDisponible[]
    usuario?: Usuario & { empresas?: Empresa[] }
    perfiles: Perfil[]
    empresas: Empresa[]
    centros_costo: (CentroCosto & { empresa?: Empresa })[]
}

export default function UsuarioForm() {
    const { usuario, perfiles, empresas, centros_costo, colaboradores_disponibles = [] } = usePage<Props>().props
    const { puede } = usePermiso('configuracion')
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
        // Vínculo con RRHH (solo al crear): todo empleado es también colaborador
        es_empleado: true,
        colaborador_modo: 'crear' as 'crear' | 'vincular',
        colaborador_id: '',
        colab_apellidos: '',
        colab_nombres: '',
        colab_cedula_ruc: '',
        colab_fecha_ingreso: new Date().toISOString().slice(0, 10),
        colab_sueldo_base: '',
        colab_cargo: '',
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

    const disponibles = colaboradores_disponibles.filter(c =>
        (data.empresas as number[]).includes(c.empresa_id))

    const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }
    const selectClass = 'flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm'

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
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
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

                {/* Empleado / Colaborador (RRHH) — solo al crear */}
                {!esEdicion && (
                    <section>
                        <h2 className="text-base font-semibold mb-1 pb-2 border-b"
                            style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                            Empleado (RRHH)
                        </h2>
                        <p className="text-xs mb-3" style={{ color: 'var(--text-muted)' }}>
                            Un usuario que trabaja en la empresa también es colaborador: así puede timbrar
                            asistencia y aparece en nómina. Desmárcalo para usuarios externos (ej. contador externo).
                        </p>
                        <label className="flex items-center gap-2 text-sm mb-4 cursor-pointer"
                            style={{ color: 'var(--text-main)' }}>
                            <input type="checkbox" checked={data.es_empleado}
                                onChange={e => setData('es_empleado', e.target.checked)} />
                            Es empleado de la empresa
                        </label>

                        {data.es_empleado && (
                            <div className="space-y-4">
                                <div className="flex gap-2">
                                    {(['crear', 'vincular'] as const).map(m => (
                                        <button key={m} type="button" onClick={() => setData('colaborador_modo', m)}
                                            className="px-3 py-1.5 rounded-lg border text-sm transition-all"
                                            style={{
                                                borderColor: data.colaborador_modo === m ? '#F59E0B' : 'var(--border)',
                                                color: data.colaborador_modo === m ? '#F59E0B' : 'var(--text-muted)',
                                            }}>
                                            {m === 'crear' ? 'Crear ficha de colaborador' : 'Vincular a colaborador existente'}
                                        </button>
                                    ))}
                                </div>

                                {data.colaborador_modo === 'vincular' ? (
                                    <div className="space-y-1.5">
                                        <Label>Colaborador *</Label>
                                        <select value={data.colaborador_id}
                                            onChange={e => setData('colaborador_id', e.target.value)}
                                            className={selectClass} style={selectStyle}>
                                            <option value="">Seleccionar colaborador sin usuario...</option>
                                            {disponibles.map(c => (
                                                <option key={c.id} value={c.id}>
                                                    {c.apellidos} {c.nombres} — {c.cedula_ruc}
                                                </option>
                                            ))}
                                        </select>
                                        {disponibles.length === 0 && (
                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                                No hay colaboradores sin usuario en las empresas seleccionadas.
                                            </p>
                                        )}
                                        {errors.colaborador_id && <p className="text-xs text-red-400">{errors.colaborador_id}</p>}
                                    </div>
                                ) : (
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div className="space-y-1.5">
                                            <Label>Apellidos *</Label>
                                            <Input value={data.colab_apellidos}
                                                onChange={e => setData('colab_apellidos', e.target.value)}
                                                error={errors.colab_apellidos} />
                                            {errors.colab_apellidos && <p className="text-xs text-red-400">{errors.colab_apellidos}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Nombres *</Label>
                                            <Input value={data.colab_nombres}
                                                onChange={e => setData('colab_nombres', e.target.value)}
                                                error={errors.colab_nombres} />
                                            {errors.colab_nombres && <p className="text-xs text-red-400">{errors.colab_nombres}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Cédula / RUC *</Label>
                                            <Input value={data.colab_cedula_ruc} maxLength={13}
                                                onChange={e => setData('colab_cedula_ruc', e.target.value)}
                                                error={errors.colab_cedula_ruc} />
                                            {errors.colab_cedula_ruc && <p className="text-xs text-red-400">{errors.colab_cedula_ruc}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Fecha de ingreso *</Label>
                                            <Input type="date" value={data.colab_fecha_ingreso}
                                                onChange={e => setData('colab_fecha_ingreso', e.target.value)}
                                                error={errors.colab_fecha_ingreso} />
                                            {errors.colab_fecha_ingreso && <p className="text-xs text-red-400">{errors.colab_fecha_ingreso}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Sueldo base *</Label>
                                            <Input type="number" step="0.01" min="0" value={data.colab_sueldo_base}
                                                onChange={e => setData('colab_sueldo_base', e.target.value)}
                                                error={errors.colab_sueldo_base} />
                                            {errors.colab_sueldo_base && <p className="text-xs text-red-400">{errors.colab_sueldo_base}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Cargo</Label>
                                            <Input value={data.colab_cargo}
                                                onChange={e => setData('colab_cargo', e.target.value)} />
                                        </div>
                                        <p className="sm:col-span-2 text-xs" style={{ color: 'var(--text-muted)' }}>
                                            El resto de la ficha (horario, banco, décimos, etc.) se completa después en RRHH → Colaboradores.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </section>
                )}

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
                                {errors.codigo_aprobacion && (
                                    <p className="text-xs text-red-400">{errors.codigo_aprobacion}</p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Confirmar PIN</Label>
                                <Input type="password" value={data.codigo_aprobacion_confirmation}
                                    onChange={e => setData('codigo_aprobacion_confirmation', e.target.value)}
                                    placeholder="••••" maxLength={6} />
                                {errors.codigo_aprobacion_confirmation && (
                                    <p className="text-xs text-red-400">{errors.codigo_aprobacion_confirmation}</p>
                                )}
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
                    {(esEdicion ? puede('editar') : puede('crear')) && (
                        <Button type="submit" loading={processing}>
                            <Save className="w-4 h-4" />
                            {esEdicion ? 'Guardar cambios' : 'Crear usuario'}
                        </Button>
                    )}
                    <Button type="button" variant="outline"
                        onClick={() => window.history.back()}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
