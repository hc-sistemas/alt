import { AlertTriangle } from 'lucide-react'
import { Button } from '@/Components/ui/button'

interface Props {
    open: boolean
    title: string
    message: string
    confirmLabel?: string
    cancelLabel?: string
    variant?: 'danger' | 'warning'
    loading?: boolean
    onConfirm: () => void
    onCancel: () => void
}

export default function ConfirmModal({
    open, title, message,
    confirmLabel = 'Confirmar', cancelLabel = 'Cancelar',
    variant = 'danger', loading, onConfirm, onCancel
}: Props) {
    if (!open) return null

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/50" onClick={onCancel} />
            <div className="relative w-full max-w-md rounded-xl shadow-xl border p-6"
                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                <div className="flex items-start gap-4">
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${
                        variant === 'danger' ? 'bg-red-500/15' : 'bg-amber-500/15'
                    }`}>
                        <AlertTriangle className={`w-5 h-5 ${variant === 'danger' ? 'text-red-400' : 'text-amber-400'}`} />
                    </div>
                    <div>
                        <h3 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>{title}</h3>
                        <p className="text-sm mt-1" style={{ color: 'var(--text-muted)' }}>{message}</p>
                    </div>
                </div>
                <div className="flex justify-end gap-2 mt-5">
                    <Button variant="outline" onClick={onCancel} disabled={loading}>
                        {cancelLabel}
                    </Button>
                    <Button
                        variant={variant === 'danger' ? 'destructive' : 'default'}
                        onClick={onConfirm}
                        loading={loading}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    )
}
