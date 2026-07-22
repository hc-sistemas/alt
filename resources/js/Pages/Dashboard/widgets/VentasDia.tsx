import { TrendingUp, TrendingDown, DollarSign } from 'lucide-react'
import { formatMoneda } from '@/lib/utils'

interface Props {
    ventasHoy: number
    ventasAyer: number
}

export default function VentasDia({ ventasHoy, ventasAyer }: Props) {
    const variacion = ventasAyer > 0 ? ((ventasHoy - ventasAyer) / ventasAyer) * 100 : 0
    const positivo = variacion >= 0

    return (
        <div className="rounded-xl border p-5"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className="flex items-center justify-between mb-3">
                <p className="text-sm font-medium" style={{ color: 'var(--text-muted)' }}>Ventas del día</p>
                <div className="w-9 h-9 rounded-lg flex items-center justify-center"
                    style={{ background: 'rgba(245,158,11,0.15)' }}>
                    <DollarSign className="w-5 h-5" style={{ color: '#F59E0B' }} />
                </div>
            </div>

            <p className="text-3xl font-bold mb-1" style={{ color: '#F59E0B' }}>
                {formatMoneda(ventasHoy)}
            </p>

            <div className={`flex items-center gap-1 text-sm ${positivo ? 'text-emerald-400' : 'text-red-400'}`}>
                {positivo
                    ? <TrendingUp className="w-4 h-4" />
                    : <TrendingDown className="w-4 h-4" />
                }
                <span>{Math.abs(variacion).toFixed(1)}% vs ayer</span>
            </div>
        </div>
    )
}
