import { RadialBarChart, RadialBar, ResponsiveContainer } from 'recharts'
import { formatMoneda } from '@/lib/utils'
import { Target } from 'lucide-react'

interface Props {
    ventasMes: number
    metaMes: number
}

export default function MetaMes({ ventasMes, metaMes }: Props) {
    const porcentaje = metaMes > 0 ? Math.min((ventasMes / metaMes) * 100, 100) : 0
    const color = porcentaje < 50 ? '#EF4444' : porcentaje < 80 ? '#F59E0B' : '#10B981'

    const data = [{ value: porcentaje, fill: color }]

    return (
        <div className="rounded-xl border p-5"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className="flex items-center justify-between mb-3">
                <p className="text-sm font-medium" style={{ color: 'var(--text-muted)' }}>Meta del mes</p>
                <div className="w-9 h-9 rounded-lg flex items-center justify-center"
                    style={{ background: 'rgba(16,185,129,0.15)' }}>
                    <Target className="w-5 h-5 text-emerald-400" />
                </div>
            </div>

            <div className="flex items-center gap-4">
                <div className="relative w-20 h-20">
                    <ResponsiveContainer width="100%" height="100%">
                        <RadialBarChart cx="50%" cy="50%" innerRadius="65%" outerRadius="90%"
                            data={data} startAngle={90} endAngle={-270}>
                            <RadialBar dataKey="value" cornerRadius={4} background={{ fill: 'rgba(148,163,184,0.1)' }} />
                        </RadialBarChart>
                    </ResponsiveContainer>
                    <div className="absolute inset-0 flex items-center justify-center">
                        <span className="text-lg font-bold" style={{ color }}>
                            {porcentaje.toFixed(0)}%
                        </span>
                    </div>
                </div>

                <div>
                    <p className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                        {formatMoneda(ventasMes)}
                    </p>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        de {formatMoneda(metaMes)}
                    </p>
                </div>
            </div>
        </div>
    )
}
