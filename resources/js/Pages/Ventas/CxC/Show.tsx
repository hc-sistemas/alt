import { Head, usePage, Link } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Badge } from '@/Components/ui/badge'
import { formatMoneda, formatFecha } from '@/lib/utils'
import { ArrowLeft } from 'lucide-react'
import type { PageProps } from '@/types'

interface CxCCobro {
    id: number
    fecha: string
    valor: number
    forma_pago: string
    observacion: string | null
    usuario_nombre: string | null
}

interface CxCFull {
    id: number
    cliente_razon: string
    cliente_identificacion: string
    documento_tipo: string
    documento_numero: string
    fecha_emision: string
    fecha_vencimiento: string
    monto: number
    saldo: number
    dias_vencido: number
    estado: 'pendiente' | 'parcial' | 'cobrada' | 'vencida' | 'castigada'
    cobros: CxCCobro[]
}

interface Props extends PageProps {
    cuenta: CxCFull
}

const ESTADO_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    parcial:    { label: 'Parcial',    variant: 'info'      as const },
    cobrada:    { label: 'Cobrada',    variant: 'success'   as const },
    vencida:    { label: 'Vencida',    variant: 'danger'    as const },
    castigada:  { label: 'Castigada',  variant: 'outline'   as const },
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>{label}</p>
            <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>{value}</p>
        </div>
    )
}

export default function Show() {
    const { cuenta } = usePage<Props>().props
    const cfg = ESTADO_CONFIG[cuenta.estado] ?? ESTADO_CONFIG.pendiente
    const totalCobrado = cuenta.cobros.reduce((s, c) => s + c.valor, 0)

    return (
        <AppLayout>
            <Head title={`CxC — ${cuenta.documento_numero}`} />
            <PageHeader
                title={`Cuenta por Cobrar — ${cuenta.documento_numero}`}
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Cuentas por Cobrar', href: route('ventas.cxc.index') },
                    { label: cuenta.documento_numero },
                ]}
            />

            <div className="p-6 space-y-6 max-w-5xl">

                <div className="flex items-center gap-3">
                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                    <Link href={route('ventas.cxc.index')}>
                        <button type="button" className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500" style={{ color: 'var(--text-muted)' }}>
                            <ArrowLeft className="w-3.5 h-3.5" />
                            Volver a Cuentas por Cobrar
                        </button>
                    </Link>
                </div>

                {/* Resumen financiero */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Monto original', valor: cuenta.monto, color: 'var(--text-main)' },
                        { label: 'Total cobrado', valor: totalCobrado, color: 'rgb(52,211,153)' },
                        { label: 'Saldo pendiente', valor: cuenta.saldo, color: cuenta.saldo > 0 ? 'var(--primary)' : 'var(--text-muted)' },
                    ].map(item => (
                        <div
                            key={item.label}
                            className="rounded-xl p-4 border"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                        >
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>{item.label}</p>
                            <p className="text-xl font-bold" style={{ color: item.color }}>{formatMoneda(item.valor)}</p>
                        </div>
                    ))}
                </div>

                {/* Datos del documento */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>Datos del Documento</p>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <InfoRow label="Cliente" value={cuenta.cliente_razon} />
                        <InfoRow label="Identificación" value={cuenta.cliente_identificacion} />
                        <InfoRow label="Tipo documento" value={cuenta.documento_tipo} />
                        <InfoRow label="N° Documento" value={<span className="font-mono">{cuenta.documento_numero}</span>} />
                        <InfoRow label="Fecha emisión" value={formatFecha(cuenta.fecha_emision)} />
                        <InfoRow label="Fecha vencimiento" value={formatFecha(cuenta.fecha_vencimiento)} />
                        {cuenta.dias_vencido > 0 && (
                            <InfoRow
                                label="Días vencido"
                                value={
                                    <span className={
                                        cuenta.dias_vencido <= 30 ? 'text-yellow-500' :
                                        cuenta.dias_vencido <= 60 ? 'text-orange-500' : 'text-red-400'
                                    }>
                                        {cuenta.dias_vencido} días
                                    </span>
                                }
                            />
                        )}
                    </div>
                </div>

                {/* Historial de cobros */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="px-5 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Historial de Cobros
                        </p>
                    </div>
                    {cuenta.cobros.length === 0 ? (
                        <p className="px-5 py-6 text-sm text-center" style={{ color: 'var(--text-muted)' }}>Sin cobros registrados</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Fecha', 'Forma de pago', 'Observación', 'Usuario', 'Valor'].map(h => (
                                            <th key={h} className="px-4 py-3 text-left font-semibold uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {cuenta.cobros.map(c => (
                                        <tr key={c.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{formatFecha(c.fecha)}</td>
                                            <td className="px-4 py-3 capitalize" style={{ color: 'var(--text-main)' }}>{c.forma_pago}</td>
                                            <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{c.observacion ?? '—'}</td>
                                            <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>{c.usuario_nombre ?? '—'}</td>
                                            <td className="px-4 py-3 font-semibold text-emerald-400">{formatMoneda(c.valor)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr style={{ borderTop: '2px solid var(--border)' }}>
                                        <td colSpan={4} className="px-4 py-3 text-right text-xs font-medium" style={{ color: 'var(--text-muted)' }}>Total cobrado:</td>
                                        <td className="px-4 py-3">
                                            <span className="font-bold text-emerald-400">{formatMoneda(totalCobrado)}</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    )
}
