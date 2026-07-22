import { Head, Link, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Badge } from '@/Components/ui/badge'
import { LogIn, LogOut, AlertCircle, ShieldAlert } from 'lucide-react'
import { formatFecha } from '@/lib/utils'
import type { Usuario, LogSesion, PageProps } from '@/types'

interface Props extends PageProps {
    usuario: Usuario
    accesos: LogSesion[]
}

const tipoConfig = {
    login_ok: { label: 'Acceso exitoso', icon: LogIn, variant: 'success' as const },
    login_fail: { label: 'Intento fallido', icon: AlertCircle, variant: 'danger' as const },
    logout: { label: 'Cierre de sesión', icon: LogOut, variant: 'secondary' as const },
    forzado: { label: 'Sesión forzada', icon: ShieldAlert, variant: 'warning' as const },
}

export default function UsuarioShow() {
    const { usuario, accesos } = usePage<Props>().props

    return (
        <AppLayout title="Historial de accesos">
            <Head title="Historial de accesos" />

            <PageHeader
                title="Historial de accesos"
                description={`${usuario.nombre} — ${usuario.email}`}
                breadcrumbs={[
                    { label: 'Configuración' },
                    { label: 'Usuarios', href: route('configuracion.usuarios.index') },
                    { label: 'Accesos' }
                ]}
            />

            <div className="p-6 max-w-2xl">
                <div className="rounded-xl border overflow-hidden"
                    style={{ borderColor: 'var(--border)' }}>
                    {accesos.length === 0 ? (
                        <div className="py-12 text-center" style={{ color: 'var(--text-muted)' }}>
                            <LogIn className="w-10 h-10 mx-auto mb-3 opacity-30" />
                            <p>Sin accesos registrados</p>
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    <th className="text-left px-4 py-3 text-xs font-medium uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>Tipo</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium uppercase tracking-wider hidden sm:table-cell"
                                        style={{ color: 'var(--text-muted)' }}>IP</th>
                                    <th className="text-left px-4 py-3 text-xs font-medium uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                {accesos.map((log, i) => {
                                    const config = tipoConfig[log.tipo]
                                    const Icon = config.icon
                                    return (
                                        <tr key={log.id}
                                            className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                            style={{ borderColor: 'var(--border)' }}>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Icon className="w-4 h-4" />
                                                    <Badge variant={config.variant}>{config.label}</Badge>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs hidden sm:table-cell"
                                                style={{ color: 'var(--text-muted)' }}>
                                                {log.ip_address ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {log.created_at ? formatFecha(log.created_at) : '—'}
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AppLayout>
    )
}
