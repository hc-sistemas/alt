import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { usePermiso } from '@/Hooks/usePermiso'
import type { Perfil, Modulo, Permiso, LimiteDescuento, PageProps } from '@/types'
import { cn } from "../../../lib/utils";

interface PerfilConPermisos extends Perfil {
    permisos: Permiso[]
    limite_descuento?: LimiteDescuento[]
}

interface EmpresaOpcion {
    id: number
    nombre_comercial: string
    nombre_selector?: string
}

interface Props extends PageProps {
    perfiles: PerfilConPermisos[]
    modulos: Modulo[]
    empresas: EmpresaOpcion[]
    empresaId: number
}

const acciones: { key: keyof Permiso; label: string }[] = [
    { key: 'ver', label: 'Ver' },
    { key: 'crear', label: 'Crear' },
    { key: 'editar', label: 'Editar' },
    { key: 'eliminar', label: 'Eliminar' },
    { key: 'anular', label: 'Anular' },
]

interface LimitesPanelProps {
    perfil: PerfilConPermisos
    puedeEditar: boolean
}

function LimitesDescuentoPanel({ perfil, puedeEditar }: LimitesPanelProps) {
    const actual = perfil.limite_descuento?.[0]
    const [sinAprobacion, setSinAprobacion] = useState(String(actual?.porcentaje_maximo ?? 0))
    const [puedeAprobar, setPuedeAprobar] = useState(actual?.puede_aprobar ?? false)
    const [soloCeroOMaximo, setSoloCeroOMaximo] = useState(actual?.solo_cero_o_maximo ?? false)
    const [conAprobacion, setConAprobacion] = useState(String(actual?.porcentaje_aprobacion_max ?? 0))
    const [guardando, setGuardando] = useState(false)

    function guardar() {
        setGuardando(true)
        router.post(route('configuracion.permisos.limite'), {
            perfil_id: perfil.id,
            porcentaje_maximo: Number(sinAprobacion) || 0,
            puede_aprobar: puedeAprobar,
            solo_cero_o_maximo: soloCeroOMaximo,
            porcentaje_aprobacion_max: Number(conAprobacion) || 0,
        }, {
            preserveScroll: true,
            onFinish: () => setGuardando(false),
        })
    }

    return (
        <div className={cn('mt-6', 'p-5', 'border', 'rounded-xl')}
            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
            <h3 className={cn('mb-4', 'font-semibold')} style={{ color: 'var(--text-main)' }}>
                Límites de descuento — {perfil.nombre.replace('_', ' ')}
            </h3>
            <div className={cn('gap-4', 'grid', 'grid-cols-1', 'sm:grid-cols-4')}>
                <div className="space-y-1.5">
                    <label className={cn('font-medium', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                        % máximo sin aprobación
                    </label>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        value={sinAprobacion}
                        onChange={e => setSinAprobacion(e.target.value)}
                        disabled={!puedeEditar}
                        className="w-24"
                    />
                </div>
                <div className="space-y-1.5">
                    <label className={cn('font-medium', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                        ¿Puede aprobar descuentos?
                    </label>
                    <input
                        type="checkbox"
                        checked={puedeAprobar}
                        onChange={e => setPuedeAprobar(e.target.checked)}
                        disabled={!puedeEditar}
                        className={cn('rounded', 'w-4', 'h-4', 'accent-amber-500')}
                    />
                </div>
                <div className="space-y-1.5">
                    <label className={cn('font-medium', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                        Solo 0 o el máximo (sin valores intermedios)
                    </label>
                    <input
                        type="checkbox"
                        checked={soloCeroOMaximo}
                        onChange={e => setSoloCeroOMaximo(e.target.checked)}
                        disabled={!puedeEditar}
                        className={cn('rounded', 'w-4', 'h-4', 'accent-amber-500')}
                    />
                </div>
                <div className="space-y-1.5">
                    <label className={cn('font-medium', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                        {puedeAprobar ? '% máximo que puede aprobar' : '% máximo con aprobación de un supervisor'}
                    </label>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        value={conAprobacion}
                        onChange={e => setConAprobacion(e.target.value)}
                        disabled={!puedeEditar}
                        className="w-24"
                    />
                </div>
            </div>
            {puedeEditar && (
                <button
                    type="button"
                    onClick={guardar}
                    disabled={guardando}
                    className={cn('mt-4', 'px-4', 'py-2', 'rounded-lg', 'font-medium', 'text-black', 'text-sm', 'disabled:opacity-50')}
                    style={{ background: 'var(--primary)' }}
                >
                    {guardando ? 'Guardando…' : 'Guardar límites'}
                </button>
            )}
        </div>
    )
}

export default function PermisosIndex() {
    const { perfiles, modulos, empresas, empresaId } = usePage<Props>().props
    const { puede } = usePermiso('configuracion')
    const [perfilActivo, setPerfilActivo] = useState(perfiles[0]?.id ?? 0)
    const [guardando, setGuardando] = useState<string | null>(null)

    const perfil = perfiles.find(p => p.id === perfilActivo)
    // Super Admin y Admin son los dueños del sistema: pueden todo y no dependen de esta matriz
    const esAdministrador = perfil?.nombre === 'super_admin' || perfil?.nombre === 'admin'
    const permisoMap = new Map(perfil?.permisos.map(p => [p.modulo_id, p]) ?? [])

    function cambiarEmpresa(id: number) {
        router.get(route('configuracion.permisos.index'), { empresa_id: id }, { preserveState: true })
    }

    function actualizarPermiso(moduloId: number, accion: string, valor: boolean) {
        const key = `${moduloId}-${accion}`
        setGuardando(key)
        router.post(route('configuracion.permisos.actualizar'), {
            perfil_id: perfilActivo,
            modulo_id: moduloId,
            empresa_id: empresaId,
            accion,
            valor,
        }, {
            preserveState: true,
            onFinish: () => setGuardando(null),
        })
    }

    return (
        <AppLayout title="Permisos">
            <Head title="Permisos" />

            <PageHeader
                title="Permisos"
                description="Matriz de acceso por perfil y módulo"
                breadcrumbs={[{ label: 'Configuración' }, { label: 'Permisos' }]}
            />

            <div className="p-6">
                {/* Selector de empresa */}
                <div className={cn('flex', 'flex-wrap', 'gap-1', 'mb-4')}>
                    {empresas.map(e => (
                        <button
                            key={e.id}
                            onClick={() => cambiarEmpresa(e.id)}
                            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${empresaId === e.id
                                    ? 'text-black'
                                    : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                                }`}
                            style={empresaId === e.id ? { background: '#F59E0B' } : { color: 'var(--text-muted)', border: '1px solid var(--border)' }}
                        >
                            {e.nombre_selector ?? e.nombre_comercial}
                        </button>
                    ))}
                </div>

                {/* Tabs de perfiles */}
                <div className={cn('flex', 'flex-wrap', 'gap-1', 'mb-6', 'pb-3', 'border-b')}
                    style={{ borderColor: 'var(--border)' }}>
                    {perfiles.map(p => (
                        <button
                            key={p.id}
                            onClick={() => setPerfilActivo(p.id)}
                            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors capitalize ${perfilActivo === p.id
                                    ? 'text-black'
                                    : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                                }`}
                            style={perfilActivo === p.id ? { background: '#F59E0B' } : { color: 'var(--text-muted)' }}
                        >
                            {p.nombre.replace('_', ' ')}
                        </button>
                    ))}
                </div>

                {esAdministrador && (
                    <p className={cn('mb-3', 'text-sm')} style={{ color: 'var(--text-muted)' }}>
                        Este perfil puede hacer todo en el sistema: no se restringe desde esta matriz.
                    </p>
                )}

                {/* Tabla de permisos */}
                <div className={cn('border', 'rounded-xl', 'overflow-x-auto')}
                    style={{ borderColor: 'var(--border)' }}>
                    <table className={cn('w-full', 'min-w-150', 'text-sm')}>
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <th className={cn('px-4', 'py-3', 'font-medium', 'text-xs', 'text-left', 'uppercase', 'tracking-wider')}
                                    style={{ color: 'var(--text-muted)' }}>Módulo</th>
                                {acciones.map(a => (
                                    <th key={a.key} className={cn('px-4', 'py-3', 'font-medium', 'text-xs', 'text-center', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>
                                        {a.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {modulos.map(modulo => {
                                const permiso = permisoMap.get(modulo.id)
                                return (
                                    <tr key={modulo.id}
                                        className={cn('hover:bg-slate-50', 'dark:hover:bg-slate-800/30', 'border-t', 'transition-colors')}
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className={cn('px-4', 'py-3', 'font-medium')}
                                            style={{ color: modulo.es_hijo ? 'var(--text-muted)' : 'var(--text-main)', paddingLeft: modulo.es_hijo ? 36 : undefined }}>
                                            {modulo.es_hijo ? '↳ ' : ''}{modulo.nombre}
                                        </td>
                                        {acciones.map(a => {
                                            const key = `${modulo.id}-${a.key}`
                                            const valor = esAdministrador ? true : (permiso?.[a.key as keyof Permiso] as boolean ?? false)
                                            return (
                                                <td key={a.key} className={cn('px-4', 'py-3', 'text-center')}>
                                                    <input
                                                        type="checkbox"
                                                        checked={valor}
                                                        disabled={esAdministrador || guardando === key || !puede('editar')}
                                                        onChange={e => actualizarPermiso(modulo.id, a.key, e.target.checked)}
                                                        className={cn('rounded', 'w-4', 'h-4', 'accent-amber-500', 'cursor-pointer')}
                                                    />
                                                </td>
                                            )
                                        })}
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Límites de descuento */}
                {perfil && (
                    <LimitesDescuentoPanel
                        key={perfil.id}
                        perfil={perfil}
                        puedeEditar={puede('editar')}
                    />
                )}
            </div>
        </AppLayout>
    )
}
