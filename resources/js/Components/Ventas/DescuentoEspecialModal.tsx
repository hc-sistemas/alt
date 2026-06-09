import { useState } from 'react'
import { AlertTriangle, Lock } from 'lucide-react'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'

export interface DescuentoEspecialModalProps {
    abierto: boolean
    onCerrar: () => void
    onAutorizado: (aprobacionId: number) => void
    productoNombre: string
    descuentoMaximo: number
    descuentoSolicitado: number
}

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
}

export default function DescuentoEspecialModal({
    abierto,
    onCerrar,
    onAutorizado,
    productoNombre,
    descuentoMaximo,
    descuentoSolicitado,
}: DescuentoEspecialModalProps) {
    const [codigo, setCodigo] = useState('')
    const [motivo, setMotivo] = useState('')
    const [error, setError] = useState('')
    const [cargando, setCargando] = useState(false)

    if (!abierto) return null

    const handleAutorizar = async () => {
        if (!codigo.trim() || !motivo.trim()) {
            setError('Ingrese el código y el motivo.')
            return
        }
        setCargando(true)
        setError('')
        try {
            const res = await fetch(route('ventas.validar-aprobacion'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ tipo: 'descuento_extra_factura', codigo, motivo }),
            })
            const data = await res.json() as { valido: boolean; aprobacion_id?: number; mensaje?: string }
            if (data.valido && data.aprobacion_id) {
                setCodigo('')
                setMotivo('')
                setError('')
                onAutorizado(data.aprobacion_id)
            } else {
                setError(data.mensaje ?? 'Código incorrecto.')
            }
        } catch {
            setError('Error de conexión. Intente nuevamente.')
        } finally {
            setCargando(false)
        }
    }

    const handleCerrar = () => {
        setCodigo('')
        setMotivo('')
        setError('')
        onCerrar()
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div
                className="w-full max-w-md rounded-2xl p-6 shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}
            >
                <div className="flex items-center gap-3 mb-4">
                    <AlertTriangle className="w-6 h-6 text-amber-500 shrink-0" />
                    <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                        Descuento especial requerido
                    </h3>
                </div>

                <p className="text-sm mb-5" style={{ color: 'var(--text-muted)' }}>
                    El descuento máximo para{' '}
                    <strong style={{ color: 'var(--text-main)' }}>{productoNombre || 'este producto'}</strong>{' '}
                    es <strong className="text-amber-500">{descuentoMaximo}%</strong>. Estás aplicando{' '}
                    <strong style={{ color: 'var(--text-main)' }}>{descuentoSolicitado}%</strong>.{' '}
                    Se requiere un código de autorización para continuar.
                </p>

                <div className="space-y-4">
                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Código de autorización</Label>
                        <div className="relative mt-1">
                            <Lock
                                className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none"
                                style={{ color: 'var(--text-muted)' }}
                            />
                            <Input
                                type="password"
                                className="pl-9"
                                placeholder="••••••••"
                                value={codigo}
                                onChange={e => setCodigo(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && void handleAutorizar()}
                                autoFocus
                            />
                        </div>
                    </div>

                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Motivo del descuento</Label>
                        <Input
                            className="mt-1"
                            placeholder="Ej: cliente VIP, lote por vencer..."
                            value={motivo}
                            onChange={e => setMotivo(e.target.value)}
                        />
                    </div>

                    {error && <p className="text-sm text-red-400">{error}</p>}
                </div>

                <div className="flex justify-end gap-3 mt-6">
                    <Button type="button" variant="outline" onClick={handleCerrar} disabled={cargando}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={() => void handleAutorizar()} loading={cargando}>
                        Autorizar
                    </Button>
                </div>
            </div>
        </div>
    )
}
