import { Head, Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save, X } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, TallerOrdenTrabajo } from '@/types'

interface Props extends PageProps {
    orden: TallerOrdenTrabajo
}

export default function DiagnosticoForm() {
    const { orden } = usePage<Props>().props
    const { puede } = usePermiso('taller')

    const [diagnostico, setDiagnostico] = useState('')
    const [tiempoEstimado, setTiempoEstimado] = useState('')
    const [tipoTiempo, setTipoTiempo] = useState<'horas' | 'dias'>('horas')
    const [guardando, setGuardando] = useState(false)
    const [error, setError] = useState('')

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault()
        if (!diagnostico.trim()) {
            setError('El diagnóstico técnico es obligatorio.')
            return
        }
        setError('')
        setGuardando(true)
        router.post(route('taller.diagnosticos.store', orden.id), {
            diagnostico,
            tiempo_estimado: tiempoEstimado ? parseInt(tiempoEstimado, 10) : null,
            tipo_tiempo: tiempoEstimado ? tipoTiempo : null,
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    return (
        <AppLayout title="Nuevo Diagnóstico">
            <Head title="Nuevo Diagnóstico" />
            <PageHeader
                title="Nuevo Diagnóstico"
                description={`Orden ${orden.numero ?? `#${orden.id}`} — ${orden.ingreso?.cliente?.razon_social ?? ''}`}
                breadcrumbs={[
                    { label: 'Taller' },
                    { label: 'Órdenes de Trabajo', href: route('taller.ordenes.index') },
                    { label: orden.numero ?? `#${orden.id}`, href: route('taller.ordenes.show', orden.id) },
                    { label: 'Nuevo Diagnóstico' },
                ]}
            />

            <form onSubmit={handleSubmit} className="p-6 max-w-2xl space-y-6">
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Equipo</p>
                            <p style={{ color: 'var(--text-main)' }}>
                                {[orden.ingreso?.equipo?.marca, orden.ingreso?.equipo?.modelo].filter(Boolean).join(' ') || '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Cliente</p>
                            <p style={{ color: 'var(--text-main)' }}>{orden.ingreso?.cliente?.razon_social ?? '—'}</p>
                        </div>
                    </div>
                </div>

                {orden.ingreso?.diagnostico_inicial && (
                    <div className="rounded-xl border p-5 space-y-1.5" style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}>
                        <Label style={{ color: 'var(--text-muted)' }}>Diagnóstico inicial (recepción)</Label>
                        <p className="text-sm whitespace-pre-wrap" style={{ color: 'var(--text-muted)' }}>
                            {orden.ingreso.diagnostico_inicial}
                        </p>
                    </div>
                )}

                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="space-y-1.5">
                        <Label style={{ color: 'var(--text-main)' }}>Diagnóstico técnico *</Label>
                        <textarea
                            rows={5}
                            className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-(--primary) transition-shadow"
                            style={{ background: 'transparent', borderColor: error ? '#ef4444' : 'var(--border)', color: 'var(--text-main)' }}
                            placeholder="Describa el diagnóstico técnico del equipo..."
                            value={diagnostico}
                            onChange={e => { setDiagnostico(e.target.value); setError('') }}
                        />
                        {error && <p className="text-xs" style={{ color: '#ef4444' }}>{error}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label style={{ color: 'var(--text-main)' }}>Tiempo estimado</Label>
                            <Input
                                type="number"
                                min="0"
                                step="1"
                                value={tiempoEstimado}
                                onChange={e => setTiempoEstimado(e.target.value)}
                                placeholder="Ej. 2"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label style={{ color: 'var(--text-main)' }}>Unidad de tiempo</Label>
                            <select
                                value={tipoTiempo}
                                onChange={e => setTipoTiempo(e.target.value as 'horas' | 'dias')}
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            >
                                <option value="horas">Horas</option>
                                <option value="dias">Días</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="flex items-center justify-between pb-2">
                    <Link href={route('taller.ordenes.show', orden.id)}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    {puede('crear') && (
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Guardar Diagnóstico
                        </Button>
                    )}
                </div>
            </form>
        </AppLayout>
    )
}
