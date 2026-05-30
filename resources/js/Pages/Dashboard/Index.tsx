import { Head, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import SkeletonCard from '@/Components/shared/SkeletonCard'
import VentasDia from './widgets/VentasDia'
import MetaMes from './widgets/MetaMes'
import VentasMensuales from './widgets/VentasMensuales'
import { Wrench, CreditCard, Wallet, Calendar } from 'lucide-react'
import type { PageProps } from '@/types'
import { formatMoneda } from '@/lib/utils'

interface Stats {
    ventas_hoy: number
    ventas_ayer: number
    meta_mes: number
    ventas_mes: number
}

interface Props extends PageProps {
    stats: Stats
}

function saludo(): string {
    const h = new Date().getHours()
    if (h < 12) return 'Buenos días'
    if (h < 19) return 'Buenas tardes'
    return 'Buenas noches'
}

export default function Dashboard() {
    const { auth, stats, empresa_activa } = usePage<Props>().props
    const hoy = new Date().toLocaleDateString('es-EC', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                            {saludo()}, {auth.user?.nombre?.split(' ')[0]} 👋
                        </h2>
                        <p className="text-sm capitalize mt-0.5" style={{ color: 'var(--text-muted)' }}>{hoy}</p>
                    </div>

                    <div className="flex items-center gap-2">
                        {empresa_activa && (
                            <span className="px-3 py-1.5 rounded-full text-xs font-semibold border"
                                style={{
                                    background: 'rgba(245,158,11,0.1)',
                                    borderColor: 'rgba(245,158,11,0.3)',
                                    color: '#F59E0B'
                                }}>
                                {empresa_activa.nombre_comercial}
                            </span>
                        )}
                        <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            <Calendar className="w-3.5 h-3.5" />
                            Este mes
                        </div>
                    </div>
                </div>

                {/* Widgets grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <VentasDia
                        ventasHoy={stats?.ventas_hoy ?? 0}
                        ventasAyer={stats?.ventas_ayer ?? 0}
                    />
                    <MetaMes
                        ventasMes={stats?.ventas_mes ?? 0}
                        metaMes={stats?.meta_mes ?? 0}
                    />

                    {/* Widget CxP próximas */}
                    <div className="rounded-xl border p-5"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="flex items-center justify-between mb-3">
                            <p className="text-sm font-medium" style={{ color: 'var(--text-muted)' }}>CxP próximas</p>
                            <div className="w-9 h-9 rounded-lg flex items-center justify-center bg-red-500/10">
                                <CreditCard className="w-5 h-5 text-red-400" />
                            </div>
                        </div>
                        <p className="text-3xl font-bold mb-1" style={{ color: 'var(--text-main)' }}>
                            {formatMoneda(0)}
                        </p>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Sin vencimientos próximos</p>
                    </div>

                    {/* Widget Flujo de caja */}
                    <div className="rounded-xl border p-5"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="flex items-center justify-between mb-3">
                            <p className="text-sm font-medium" style={{ color: 'var(--text-muted)' }}>Flujo de caja</p>
                            <div className="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-500/10">
                                <Wallet className="w-5 h-5 text-blue-400" />
                            </div>
                        </div>
                        <p className="text-3xl font-bold mb-1" style={{ color: 'var(--text-main)' }}>
                            {formatMoneda(0)}
                        </p>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Sin datos bancarios</p>
                    </div>
                </div>

                {/* Gráfico ventas mensuales */}
                <div className="grid grid-cols-1 xl:grid-cols-3 gap-4">
                    <div className="xl:col-span-2">
                        <VentasMensuales />
                    </div>

                    {/* OT Activas */}
                    <div className="rounded-xl border p-5"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="flex items-center justify-between mb-4">
                            <p className="text-sm font-medium" style={{ color: 'var(--text-muted)' }}>
                                Órdenes de Trabajo
                            </p>
                            <div className="w-9 h-9 rounded-lg flex items-center justify-center"
                                style={{ background: 'rgba(59,130,246,0.15)' }}>
                                <Wrench className="w-5 h-5 text-blue-400" />
                            </div>
                        </div>

                        <div className="flex flex-col items-center justify-center py-8 text-center">
                            <Wrench className="w-10 h-10 mb-3 opacity-30" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay órdenes activas
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    )
}
