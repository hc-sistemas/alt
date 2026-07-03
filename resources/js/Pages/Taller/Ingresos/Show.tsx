import { Head, Link, router, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { formatFecha } from '@/lib/utils'
import { ArrowRight } from 'lucide-react'
import type { PageProps, TallerIngreso } from '@/types'

interface Props extends PageProps {
    ingreso: TallerIngreso
}

const ESTADO_INGRESO: Record<number, { label: string; variant: 'info' | 'warning' | 'default' | 'success' | 'secondary' }> = {
    0: { label: 'Ingresado',      variant: 'secondary' },
    1: { label: 'En diagnóstico', variant: 'warning' },
    2: { label: 'En proceso',     variant: 'info' },
    3: { label: 'Listo',          variant: 'success' },
    4: { label: 'Entregado',      variant: 'secondary' },
}

const ESTADO_OT: Record<string, { label: string; variant: 'info' | 'warning' | 'default' | 'success' | 'secondary' | 'danger' }> = {
    pendiente:  { label: 'Pendiente',  variant: 'warning' },
    en_proceso: { label: 'En proceso', variant: 'info' },
    listo:      { label: 'Listo',      variant: 'success' },
    entregado:  { label: 'Entregado',  variant: 'secondary' },
    facturado:  { label: 'Facturado',  variant: 'success' },
    garantia:   { label: 'Garantía',   variant: 'secondary' },
}

export default function IngresoShow() {
    const { ingreso } = usePage<Props>().props
    const cfgIngreso = ESTADO_INGRESO[ingreso.estado] ?? ESTADO_INGRESO[0]

    return (
        <AppLayout title={`Ingreso #${ingreso.id}`}>
            <Head title={`Ingreso #${ingreso.id}`} />
            <PageHeader
                title={`Ingreso #${ingreso.id}`}
                breadcrumbs={[
                    { label: 'Taller' },
                    { label: 'Ingresos', href: route('taller.ingresos.index') },
                    { label: `#${ingreso.id}` },
                ]}
            />

            <div className="p-6 max-w-3xl space-y-6">
                {/* Datos del ingreso */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            Ingreso #{ingreso.id}
                        </span>
                        <Badge variant={cfgIngreso.variant}>{cfgIngreso.label}</Badge>
                    </div>

                    <div className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha</p>
                            <p style={{ color: 'var(--text-main)' }}>{formatFecha(ingreso.fecha)}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Hora</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.hora ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Registrado por</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.usuario?.nombre ?? '—'}</p>
                        </div>
                    </div>

                    {ingreso.diagnostico_inicial && (
                        <div className="text-sm">
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Diagnóstico inicial</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.diagnostico_inicial}</p>
                        </div>
                    )}

                    {ingreso.observaciones && (
                        <div className="text-sm">
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Observaciones</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.observaciones}</p>
                        </div>
                    )}

                    {ingreso.imagen && (
                        <div className="text-sm">
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Imagen</p>
                            <img src={ingreso.imagen} alt="Imagen del equipo"
                                className="rounded-lg border max-w-xs"
                                style={{ borderColor: 'var(--border)' }} />
                        </div>
                    )}
                </div>

                {/* Cliente */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Cliente</h3>
                    </div>
                    <div className="p-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Razón social</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.cliente?.razon_social ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Identificación</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.cliente?.identificacion ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Teléfono</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.cliente?.telefono ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Email</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.cliente?.email ?? '—'}</p>
                        </div>
                    </div>
                </div>

                {/* Equipo */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Equipo</h3>
                    </div>
                    <div className="p-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Tipo</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.equipo?.tipo?.descripcion ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Marca</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.equipo?.marca ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Modelo</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.equipo?.modelo ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Número de serie</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.equipo?.numero_serie ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Color</p>
                            <p style={{ color: 'var(--text-main)' }}>{ingreso.equipo?.color ?? '—'}</p>
                        </div>
                    </div>
                </div>

                {/* Órdenes de trabajo */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Órdenes de Trabajo</h3>
                    </div>
                    {(ingreso.ordenesTrabajo ?? []).length === 0 ? (
                        <p className="p-4 text-sm" style={{ color: 'var(--text-muted)' }}>No hay órdenes de trabajo generadas.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                    <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Número</th>
                                    <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Fecha inicio</th>
                                    <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Estado</th>
                                    <th className="px-4 py-2.5" />
                                </tr>
                            </thead>
                            <tbody>
                                {(ingreso.ordenesTrabajo ?? []).map(ot => {
                                    const cfg = ESTADO_OT[ot.estado] ?? { label: ot.estado, variant: 'secondary' as const }
                                    return (
                                        <tr key={ot.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                            <td className="px-4 py-2.5 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                {ot.numero ?? `#${ot.id}`}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {formatFecha(ot.fecha_inicio)}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                            </td>
                                            <td className="px-4 py-2.5 text-right">
                                                <Link href={route('taller.ordenes.show', ot.id)}
                                                    className="inline-flex items-center gap-1 text-xs font-medium"
                                                    style={{ color: 'var(--primary)' }}>
                                                    Ver orden
                                                    <ArrowRight className="w-3.5 h-3.5" />
                                                </Link>
                                            </td>
                                        </tr>
                                    )
                                })}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Botón de regreso */}
                <div className="flex gap-3 pt-2">
                    <Button variant="outline" onClick={() => router.visit(route('taller.ingresos.index'))}>
                        Volver a Ingresos
                    </Button>
                </div>
            </div>
        </AppLayout>
    )
}
