import { Head, usePage } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import SkeletonCard from '@/Components/shared/SkeletonCard'
import VentasDia from './widgets/VentasDia'
import MetaMes from './widgets/MetaMes'
import VentasMensuales from './widgets/VentasMensuales'
import { Wrench, CreditCard, Wallet, Calendar, FileDown, FileSpreadsheet, TrendingUp } from 'lucide-react'
import type { PageProps } from '@/types'
import { cn, formatMoneda } from '@/lib/utils'
import axios from '@/lib/axios'

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

type Periodo = 'mensual' | 'trimestral' | 'anual'

interface ResumenVentas {
    periodo: Periodo
    fecha_desde: string
    fecha_hasta: string
    total_ventas: number
    cantidad: number
}

const PERIODOS: { value: Periodo; label: string }[] = [
    { value: 'mensual', label: 'Mensual' },
    { value: 'trimestral', label: 'Trimestral' },
    { value: 'anual', label: 'Anual' },
]

// F1 (CHECKLIST_ERRORES_COMPLICACIONES.md): ventas mensual/trimestral/anual +
// descarga PDF/Excel del resumen del período elegido.
function ResumenVentasPeriodo() {
    const [periodo, setPeriodo] = useState<Periodo>('mensual')
    const [resumen, setResumen] = useState<ResumenVentas | null>(null)
    const [cargando, setCargando] = useState(false)

    useEffect(() => {
        setCargando(true)
        axios.get<ResumenVentas>(route('dashboard.ventas-resumen'), { params: { periodo } })
            .then(({ data }) => setResumen(data))
            .finally(() => setCargando(false))
    }, [periodo])

    const btnExport = "inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border transition-colors"

    return (
        <div className="rounded-xl border p-5" style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div className="flex items-center gap-2">
                    <div className="w-9 h-9 rounded-lg flex items-center justify-center bg-emerald-500/10">
                        <TrendingUp className="w-5 h-5 text-emerald-500" />
                    </div>
                    <div>
                        <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>Resumen de ventas</p>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            {resumen ? `${resumen.fecha_desde} — ${resumen.fecha_hasta}` : '—'}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                    {/* Selector de período */}
                    <div className="flex shrink-0" role="group">
                        {PERIODOS.map((p, i) => (
                            <button
                                key={p.value}
                                onClick={() => setPeriodo(p.value)}
                                className={cn(
                                    'px-3 py-1.5 text-xs font-medium border transition-colors',
                                    i === 0 && 'rounded-l-md',
                                    i === PERIODOS.length - 1 && 'rounded-r-md',
                                    i > 0 && 'border-l-0',
                                )}
                                style={periodo === p.value
                                    ? { background: 'var(--primary)', borderColor: 'var(--primary)', color: '#fff' }
                                    : { borderColor: 'var(--border)', color: 'var(--text-muted)', background: 'transparent' }
                                }
                            >
                                {p.label}
                            </button>
                        ))}
                    </div>

                    {/* Exportar — btn-group al final, después del selector (convención del proyecto) */}
                    <div className="flex shrink-0" role="group">
                        <a
                            href={route('dashboard.ventas-resumen.pdf', { periodo })}
                            target="_blank" rel="noreferrer"
                            className={cn(btnExport, 'rounded-l-md')}
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <FileDown className="w-3.5 h-3.5" /> PDF
                        </a>
                        <a
                            href={route('dashboard.ventas-resumen.excel', { periodo })}
                            className={cn(btnExport, 'rounded-r-md border-l-0')}
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <FileSpreadsheet className="w-3.5 h-3.5" /> Excel
                        </a>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Total vendido</p>
                    <p className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                        {cargando ? '—' : formatMoneda(resumen?.total_ventas ?? 0)}
                    </p>
                </div>
                <div>
                    <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Facturas</p>
                    <p className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                        {cargando ? '—' : (resumen?.cantidad ?? 0)}
                    </p>
                </div>
            </div>
        </div>
    )
}

export default function Dashboard() {
    const { auth, stats } = usePage<Props>().props
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
                        <div className="flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            <Calendar className="w-3.5 h-3.5" />
                            Este mes
                        </div>
                    </div>
                </div>

                <ResumenVentasPeriodo />

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
