import { Head, useForm, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'

interface Props extends PageProps {
    condiciones: string
    iva_servicio: number
}

export default function ConfiguracionTaller() {
    const { condiciones, iva_servicio } = usePage<Props>().props
    const { puede } = usePermiso('taller')
    const { data, setData, put, processing, errors } = useForm({
        condiciones,
        iva_servicio: String(iva_servicio),
    })

    function guardar(e: React.FormEvent) {
        e.preventDefault()
        put(route('taller.configuracion.update'), { preserveScroll: true })
    }

    return (
        <AppLayout title="Configuración del Taller">
            <Head title="Configuración del Taller" />
            <PageHeader
                title="Configuración del Taller"
                breadcrumbs={[{ label: 'Taller' }, { label: 'Configuración' }]}
            />

            <form onSubmit={guardar} className="p-6 max-w-2xl space-y-6">
                <div className="rounded-xl border p-5 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Condiciones del servicio</h3>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        Este texto se imprime en el documento de ingreso a taller. Gerencia debe confirmar el texto definitivo (DEC-054).
                    </p>
                    <textarea
                        rows={6}
                        className="w-full rounded-md border px-3 py-2 text-sm"
                        style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        value={data.condiciones}
                        onChange={e => setData('condiciones', e.target.value)}
                        disabled={!puede('editar')}
                    />
                    {errors.condiciones && <p className="text-xs text-red-500">{errors.condiciones}</p>}
                </div>

                <div className="rounded-xl border p-5 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Factura del servicio técnico</h3>
                    <div className="space-y-1.5 max-w-xs">
                        <Label style={{ color: 'var(--text-main)' }}>IVA del servicio técnico (%)</Label>
                        <Input
                            type="number" min="0" max="100" step="0.01"
                            value={data.iva_servicio}
                            onChange={e => setData('iva_servicio', e.target.value)}
                            disabled={!puede('editar')}
                        />
                        {errors.iva_servicio && <p className="text-xs text-red-500">{errors.iva_servicio}</p>}
                    </div>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        El valor del servicio (DEC-052) y el momento en que se genera la factura (DEC-053) siguen pendientes de definir:
                        hoy el valor se digita al facturar y la factura se genera con el botón cuando la orden está Lista.
                    </p>
                </div>

                {puede('editar') && (
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        Guardar
                    </Button>
                )}
            </form>
        </AppLayout>
    )
}
