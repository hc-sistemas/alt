import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { formatMoneda } from '@/lib/utils'
import { Search, ShieldAlert } from 'lucide-react'
import type { PageProps } from '@/types'

interface FilaGarantia {
    id: number
    codigo: string
    nombre: string
    unidades: number
    ordenes: number
    costo: number
}

interface Props extends PageProps {
    filas: FilaGarantia[]
    total: number
    filtros: { desde?: string; hasta?: string }
}

export default function GarantiasIndex() {
    const { filas, total, filtros } = usePage<Props>().props
    const [desde, setDesde] = useState(filtros.desde ?? '')
    const [hasta, setHasta] = useState(filtros.hasta ?? '')

    function buscar() {
        router.get(route('taller.garantias.index'), {
            desde: desde || undefined,
            hasta: hasta || undefined,
        }, { preserveState: true, replace: true })
    }

    return (
        <AppLayout title="Costo de garantías">
            <Head title="Costo de garantías" />
            <PageHeader
                title="Costo de garantías por producto"
                breadcrumbs={[{ label: 'Taller' }, { label: 'Garantías' }]}
            />

            <div className="p-6 space-y-4">
                <div className="flex items-center gap-3 flex-wrap">
                    <Input type="date" value={desde} onChange={e => setDesde(e.target.value)} className="w-40 shrink-0" title="Desde" />
                    <Input type="date" value={hasta} onChange={e => setHasta(e.target.value)} className="w-40 shrink-0" title="Hasta" />
                    <Button onClick={buscar}>
                        <Search className="w-4 h-4" />
                        Buscar
                    </Button>
                </div>

                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                    Repuestos usados en órdenes cerradas como garantía (no se facturan al cliente). El costo sale del costo
                    del kárdex al momento de la salida. La cuenta contable y la recuperación con el proveedor están
                    pendientes de definir (DEC-047).
                </p>

                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Código', 'Producto', 'Órdenes', 'Unidades', 'Costo'].map(h => (
                                    <th key={h} className="text-left px-4 py-3 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {filas.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <div className="flex flex-col items-center gap-2">
                                            <ShieldAlert className="w-10 h-10 opacity-20" />
                                            <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                                No hay garantías cerradas en el período
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : filas.map(f => (
                                <tr key={f.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--text-main)' }}>{f.codigo}</td>
                                    <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>{f.nombre}</td>
                                    <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>{f.ordenes}</td>
                                    <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-main)' }}>{f.unidades}</td>
                                    <td className="px-4 py-3 text-xs font-medium" style={{ color: 'var(--text-main)' }}>{formatMoneda(f.costo)}</td>
                                </tr>
                            ))}
                        </tbody>
                        {filas.length > 0 && (
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={4} className="px-4 py-3 text-right text-xs font-semibold" style={{ color: 'var(--text-muted)' }}>
                                        Costo total de garantías
                                    </td>
                                    <td className="px-4 py-3 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                        {formatMoneda(total)}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </AppLayout>
    )
}
