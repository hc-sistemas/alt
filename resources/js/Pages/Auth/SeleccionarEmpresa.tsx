import { useForm, Head } from '@inertiajs/react'
import { FormEvent } from 'react'
import AuthLayout from '@/Layouts/AuthLayout'
import { Button } from '@/Components/ui/button'
import { Building2, ChevronRight } from 'lucide-react'
import type { Empresa } from '@/types'

interface Props {
    empresas: Partial<Empresa>[]
}

export default function SeleccionarEmpresa({ empresas }: Props) {
    const { data, setData, post, processing } = useForm({
        empresa_id: 0,
    })

    function seleccionar(id: number) {
        setData('empresa_id', id)
    }

    function submit(e: FormEvent) {
        e.preventDefault()
        if (!data.empresa_id) return
        post(route('empresa.cambiar'))
    }

    return (
        <AuthLayout>
            <Head title="Seleccionar Empresa" />

            <div className="space-y-6">
                <div>
                    <h2 className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                        Seleccionar Empresa
                    </h2>
                    <p className="mt-1 text-sm" style={{ color: 'var(--text-muted)' }}>
                        Tienes acceso a múltiples empresas. ¿Con cuál deseas trabajar?
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    {empresas.map(empresa => (
                        <button
                            key={empresa.id}
                            type="button"
                            onClick={() => seleccionar(empresa.id!)}
                            className={`w-full flex items-center gap-3 p-4 rounded-lg border text-left transition-all ${
                                data.empresa_id === empresa.id
                                    ? 'border-amber-500 bg-amber-500/10'
                                    : 'border-[var(--border)] hover:border-amber-500/50 hover:bg-amber-500/5'
                            }`}
                        >
                            <div className="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                                style={{ background: 'rgba(245,158,11,0.1)' }}>
                                <Building2 className="w-5 h-5" style={{ color: '#F59E0B' }} />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-medium text-sm truncate" style={{ color: 'var(--text-main)' }}>
                                    {empresa.nombre_comercial}
                                </p>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>RUC: {empresa.ruc}</p>
                            </div>
                            <ChevronRight className="w-4 h-4 shrink-0" style={{ color: 'var(--text-muted)' }} />
                        </button>
                    ))}

                    <Button
                        type="submit"
                        className="w-full h-10 font-semibold mt-2"
                        disabled={!data.empresa_id}
                        loading={processing}
                    >
                        Continuar
                    </Button>
                </form>
            </div>
        </AuthLayout>
    )
}
