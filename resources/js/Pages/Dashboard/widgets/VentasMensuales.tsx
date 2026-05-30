import { BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, Legend } from 'recharts'

const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']

const dataVacia = meses.map(mes => ({ mes, actual: 0, anterior: 0 }))

interface Props {
    data?: { mes: string; actual: number; anterior: number }[]
}

const formatY = (value: number) => `$${(value / 1000).toFixed(0)}k`

export default function VentasMensuales({ data = dataVacia }: Props) {
    return (
        <div className="rounded-xl border p-5"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <p className="text-sm font-medium mb-4" style={{ color: 'var(--text-muted)' }}>
                Ventas mensuales comparativas
            </p>

            <ResponsiveContainer width="100%" height={200}>
                <BarChart data={data} barGap={2} barSize={12}>
                    <XAxis dataKey="mes" tick={{ fontSize: 11, fill: '#94A3B8' }} axisLine={false} tickLine={false} />
                    <YAxis tickFormatter={formatY} tick={{ fontSize: 11, fill: '#94A3B8' }} axisLine={false} tickLine={false} />
                    <Tooltip
                        formatter={(value: number) => [`$${value.toLocaleString()}`, '']}
                        contentStyle={{
                            background: '#1E293B',
                            border: '1px solid #334155',
                            borderRadius: '8px',
                            fontSize: '12px',
                        }}
                    />
                    <Legend wrapperStyle={{ fontSize: '12px' }} />
                    <Bar dataKey="actual" name="Año actual" fill="#F59E0B" radius={[2, 2, 0, 0]} />
                    <Bar dataKey="anterior" name="Año anterior" fill="#94A3B8" radius={[2, 2, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    )
}
