import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import type { Perfil, Modulo, Permiso, LimiteDescuento, PageProps } from '@/types'

interface PerfilConPermisos extends Perfil {
    permisos: Permiso[]
    limite_descuento?: LimiteDescuento[]
}

interface Props extends PageProps {
    perfiles: PerfilConPermisos[]
    modulos: Modulo[]
}

const acciones: { key: keyof Permiso; label: string }[] = [
    { key: 'ver', label: 'Ver' },
    { key: 'crear', label: 'Crear' },
    { key: 'editar', label: 'Editar' },
    { key: 'eliminar', label: 'Eliminar' },
    { key: 'anular', label: 'Anular' },
]

export default function PermisosIndex() {
    const { perfiles, modulos } = usePage<Props>().props
    const [perfilActivo, setPerfilActivo] = useState(perfiles[0]?.id ?? 0)
    const [guardando, setGuardando] = useState<string | null>(null)

    const perfil = perfiles.find(p => p.id === perfilActivo)
    const permisoMap = new Map(perfil?.permisos.map(p => [p.modulo_id, p]) ?? [])

    function actualizarPermiso(moduloId: number, accion: string, valor: boolean) {
        const key = `${moduloId}-${accion}`
        setGuardando(key)
        router.post(route('configuracion.permisos.actualizar'), {
            perfil_id: perfilActivo,
            modulo_id: moduloId,
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
                {/* Tabs de perfiles */}
                <div className="flex flex-wrap gap-1 mb-6 border-b pb-3"
                    style={{ borderColor: 'var(--border)' }}>
                    {perfiles.map(p => (
                        <button
                            key={p.id}
                            onClick={() => setPerfilActivo(p.id)}
                            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors capitalize ${
                                perfilActivo === p.id
                                    ? 'text-black'
                                    : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                            }`}
                            style={perfilActivo === p.id ? { background: '#F59E0B' } : { color: 'var(--text-muted)' }}
                        >
                            {p.nombre.replace('_', ' ')}
                        </button>
                    ))}
                </div>

                {/* Tabla de permisos */}
                <div className="rounded-xl border overflow-x-auto"
                    style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm min-w-[600px]">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <th className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                    style={{ color: 'var(--text-muted)' }}>Módulo</th>
                                {acciones.map(a => (
                                    <th key={a.key} className="text-center px-4 py-3 font-medium text-xs uppercase tracking-wider"
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
                                        className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors"
                                        style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                            {modulo.nombre}
                                        </td>
                                        {acciones.map(a => {
                                            const key = `${modulo.id}-${a.key}`
                                            const valor = permiso?.[a.key as keyof Permiso] as boolean ?? false
                                            return (
                                                <td key={a.key} className="px-4 py-3 text-center">
                                                    <input
                                                        type="checkbox"
                                                        checked={valor}
                                                        disabled={guardando === key}
                                                        onChange={e => actualizarPermiso(modulo.id, a.key, e.target.checked)}
                                                        className="w-4 h-4 rounded accent-amber-500 cursor-pointer"
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
                    <div className="mt-6 rounded-xl border p-5"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <h3 className="font-semibold mb-4" style={{ color: 'var(--text-main)' }}>
                            Límites de descuento — {perfil.nombre.replace('_', ' ')}
                        </h3>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                    % máximo sin aprobación
                                </label>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    defaultValue={perfil.limite_descuento?.[0]?.porcentaje_maximo ?? 0}
                                    className="w-24"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                    ¿Puede aprobar descuentos?
                                </label>
                                <input
                                    type="checkbox"
                                    defaultChecked={perfil.limite_descuento?.[0]?.puede_aprobar ?? false}
                                    className="w-4 h-4 rounded accent-amber-500"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <label className="text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                    % máximo que puede aprobar
                                </label>
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    defaultValue={perfil.limite_descuento?.[0]?.porcentaje_aprobacion_max ?? 0}
                                    className="w-24"
                                />
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
