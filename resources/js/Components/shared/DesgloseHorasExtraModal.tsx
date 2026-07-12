import { X } from 'lucide-react'

// Modal Detalle (Regla NOM-06: "desglosado en un pop-up si el empleado quiere
// ver cuántas horas al 50% y 100% le están pagando"). Reutilizable desde el
// panel de Horas Extras (una sola solicitud) y desde el Rol de Pagos / Nómina
// (subtotales ya agregados del período).

function round2(n: number): number {
    return Math.round(n * 100) / 100
}

export default function DesgloseHorasExtraModal({ sueldoBase, horas50, horas100, nota, onClose }: {
    sueldoBase: number
    horas50: number
    horas100: number
    nota?: string
    onClose: () => void
}) {
    const valorHora   = sueldoBase / 240
    const subtotal50  = round2(horas50 * valorHora * 1.5)
    const subtotal100 = round2(horas100 * valorHora * 2.0)
    const total = round2(subtotal50 + subtotal100)

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header flex items-center justify-between px-6 py-4">
                    <h2 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                        Desglose de Horas Extras
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-black/10">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                <div className="modal-body px-6 py-5 space-y-4">
                    <div className="rounded-lg p-3 text-xs" style={{ background: 'var(--bg-main)' }}>
                        <div className="flex items-center justify-between">
                            <span style={{ color: 'var(--text-muted)' }}>Sueldo Base</span>
                            <span className="font-mono font-medium" style={{ color: 'var(--text-main)' }}>${sueldoBase.toFixed(2)}</span>
                        </div>
                        <div className="flex items-center justify-between mt-1">
                            <span style={{ color: 'var(--text-muted)' }}>Valor Hora (VH = Sueldo Base / 240)</span>
                            <span className="font-mono font-medium" style={{ color: 'var(--text-main)' }}>${valorHora.toFixed(4)}</span>
                        </div>
                        {nota && (
                            <p className="mt-2 text-[10px] italic" style={{ color: 'var(--text-muted)' }}>
                                {nota}
                            </p>
                        )}
                    </div>

                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                <th className="text-left py-1.5 font-medium" style={{ color: 'var(--text-muted)' }}>Concepto</th>
                                <th className="text-center py-1.5 font-medium" style={{ color: 'var(--text-muted)' }}>Horas</th>
                                <th className="text-right py-1.5 font-medium" style={{ color: 'var(--text-muted)' }}>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                <td className="py-2" style={{ color: 'var(--text-main)' }}>
                                    Suplementarias (50%) <span className="text-[10px]" style={{ color: 'var(--text-muted)' }}>VH×1.5</span>
                                </td>
                                <td className="py-2 text-center font-mono">{horas50.toFixed(2)}h</td>
                                <td className="py-2 text-right font-mono font-medium">${subtotal50.toFixed(2)}</td>
                            </tr>
                            <tr>
                                <td className="py-2" style={{ color: 'var(--text-main)' }}>
                                    Extraordinarias (100%) <span className="text-[10px]" style={{ color: 'var(--text-muted)' }}>VH×2.0</span>
                                </td>
                                <td className="py-2 text-center font-mono">{horas100.toFixed(2)}h</td>
                                <td className="py-2 text-right font-mono font-medium">${subtotal100.toFixed(2)}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div className="flex items-center justify-between rounded-lg px-4 py-3 text-sm font-semibold"
                        style={{ background: 'var(--bg-main)', color: 'var(--primary)' }}>
                        <span>Total</span>
                        <span className="text-lg">${total.toFixed(2)}</span>
                    </div>
                </div>

                <div className="modal-footer flex justify-end px-6 py-4">
                    <button type="button" onClick={onClose} className="btn-secondary">Cerrar</button>
                </div>
            </div>
        </div>
    )
}
