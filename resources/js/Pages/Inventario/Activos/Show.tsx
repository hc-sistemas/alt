import { Head, Link, useForm, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Input } from '@/Components/ui/input'
import { Pencil, TrendingDown } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { ActivoFijo, PageProps } from '@/types'

interface Props extends PageProps {
    activo: ActivoFijo
}

const CATEGORIA_LABELS: Record<string, string> = {
    terreno: 'Terreno', edificio: 'Edificio', vehiculo: 'Vehículo',
    equipo_computo: 'Equipo de Cómputo', maquinaria: 'Maquinaria',
    muebles: 'Muebles y Enseres', instalaciones: 'Instalaciones', otro: 'Otro',
}

const ESTADO_COLORES: Record<string, string> = {
    activo:       'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    dado_de_baja: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    vendido:      'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
}

const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

function fmt(v: number | string) {
    return Number(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function Card({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
    return (
        <div className="rounded-xl p-4 border" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>{label}</p>
            <p className="text-lg font-semibold font-mono" style={{ color: accent ? 'var(--primary)' : 'var(--text-main)' }}>
                $ {value}
            </p>
        </div>
    )
}

export default function ActivoFijoShow() {
    const { activo } = usePage<Props>().props

    const now = new Date()
    const { data, setData, post, processing, errors, reset } = useForm({
        periodo_año: now.getFullYear().toString(),
        periodo_mes: (now.getMonth() + 1).toString(),
    })

    const valorAdq   = Number(activo.valor_adquisicion)
    const valorRes   = Number(activo.valor_residual)
    const depAcum    = Number(activo.depreciacion_acumulada)
    const valorLibro = Number(activo.valor_libro)

    const totalDepreciable = valorAdq - valorRes
    const pctDepreciado = totalDepreciable > 0
        ? Math.min(100, (depAcum / totalDepreciable) * 100)
        : 0

    const depMensual = activo.vida_util_años > 0 && totalDepreciable > 0
        ? ((valorAdq - valorRes) / (activo.vida_util_años * 12)).toFixed(2)
        : '0.00'

    const puedeDepreciar = activo.estado === 'activo' && (valorLibro - valorRes) > 0

    function registrarDepreciacion(e: React.FormEvent) {
        e.preventDefault()
        post(route('inventario.activos.depreciar', activo.id), {
            onSuccess: () => toastExito('Depreciación registrada correctamente'),
            onError: (err) => toastError(err.message ?? 'Error al registrar depreciación'),
        })
    }

    return (
        <AppLayout title={`Activo: ${activo.codigo}`}>
            <Head title={`Activo: ${activo.codigo}`} />

            <PageHeader
                title={activo.nombre}
                description={`Código: ${activo.codigo}`}
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Activos Fijos', href: route('inventario.activos.index') },
                    { label: activo.codigo },
                ]}
                actions={
                    activo.estado === 'activo' ? (
                        <Link href={route('inventario.activos.edit', activo.id)}>
                            <Button variant="outline">
                                <Pencil className="w-4 h-4" />
                                Editar
                            </Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="p-6 space-y-6 max-w-4xl">
                {/* Cards resumen */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <Card label="Valor de adquisición" value={fmt(valorAdq)} />
                    <Card label="Depreciación acumulada" value={fmt(depAcum)} />
                    <Card label="Valor en libros" value={fmt(valorLibro)} accent />
                </div>

                {/* Barra de progreso */}
                {totalDepreciable > 0 && (
                    <div className="rounded-xl p-4 border space-y-2"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <div className="flex items-center justify-between text-xs" style={{ color: 'var(--text-muted)' }}>
                            <span>Progreso de depreciación</span>
                            <span className="font-mono">{pctDepreciado.toFixed(1)}%</span>
                        </div>
                        <div className="h-2.5 rounded-full overflow-hidden" style={{ background: 'var(--border)' }}>
                            <div className="h-full rounded-full transition-all"
                                style={{
                                    width: `${pctDepreciado}%`,
                                    background: pctDepreciado >= 100 ? '#EF4444' : 'var(--primary)',
                                }} />
                        </div>
                        <div className="flex justify-between text-xs" style={{ color: 'var(--text-muted)' }}>
                            <span>$ 0</span>
                            <span>Depreciable: $ {fmt(totalDepreciable)}</span>
                        </div>
                    </div>
                )}

                {/* Ficha del activo */}
                <div className="rounded-xl border p-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    {[
                        ['Estado', <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${ESTADO_COLORES[activo.estado] ?? ''}`}>
                            {activo.estado.replace('_', ' ')}
                        </span>],
                        ['Categoría', CATEGORIA_LABELS[activo.categoria] ?? activo.categoria],
                        ['Ubicación', activo.ubicacion ?? '—'],
                        ['Fecha adquisición', activo.fecha_adquisicion],
                        ['Vida útil', `${activo.vida_util_años} años`],
                        ['Valor residual', `$ ${fmt(valorRes)}`],
                        ['Dep. mensual estimada', `$ ${depMensual}`],
                        ['Método', activo.metodo_depreciacion],
                    ].map(([label, value]) => (
                        <div key={String(label)}>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>{label}</p>
                            <p className="font-medium" style={{ color: 'var(--text-main)' }}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* Formulario depreciación */}
                {puedeDepreciar && (
                    <div className="rounded-xl border p-5 space-y-4"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <div className="flex items-center gap-2">
                            <TrendingDown className="w-4 h-4" style={{ color: 'var(--primary)' }} />
                            <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                Registrar depreciación
                            </h3>
                        </div>

                        <div className="flex items-start gap-2 px-3 py-2.5 rounded-lg text-xs"
                            style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.2)', color: 'var(--text-muted)' }}>
                            <span>Monto a depreciar este período:</span>
                            <span className="font-semibold font-mono ml-1" style={{ color: 'var(--primary)' }}>
                                $ {depMensual}
                            </span>
                        </div>

                        <form onSubmit={registrarDepreciacion} className="flex flex-wrap gap-4 items-end">
                            <div className="space-y-1.5">
                                <Label>Año</Label>
                                <Input type="number" min={2000} max={2100}
                                    value={data.periodo_año}
                                    onChange={e => setData('periodo_año', e.target.value)}
                                    className="w-28" />
                                {errors.periodo_año && <p className="text-xs text-red-400">{errors.periodo_año}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Mes</Label>
                                <select value={data.periodo_mes}
                                    onChange={e => setData('periodo_mes', e.target.value)}
                                    className="input-field w-40">
                                    {MESES.slice(1).map((m, i) => (
                                        <option key={i + 1} value={i + 1}>{m}</option>
                                    ))}
                                </select>
                                {errors.periodo_mes && <p className="text-xs text-red-400">{errors.periodo_mes}</p>}
                            </div>
                            <Button type="submit" loading={processing}>
                                <TrendingDown className="w-4 h-4" />
                                Registrar depreciación
                            </Button>
                        </form>
                    </div>
                )}

                {/* Historial */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                            Historial de depreciaciones
                        </h3>
                    </div>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Año', 'Mes', 'Monto', 'Dep. Acumulada', 'Valor Libro'].map(h => (
                                    <th key={h} className="text-left px-4 py-2.5 font-medium text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {(!activo.depreciaciones || activo.depreciaciones.length === 0) ? (
                                <tr>
                                    <td colSpan={5} className="text-center py-8" style={{ color: 'var(--text-muted)' }}>
                                        No hay depreciaciones registradas aún.
                                    </td>
                                </tr>
                            ) : activo.depreciaciones.map(d => (
                                <tr key={d.id}
                                    className="border-t hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-4 py-2.5 font-mono" style={{ color: 'var(--text-main)' }}>{d.periodo_año}</td>
                                    <td className="px-4 py-2.5" style={{ color: 'var(--text-muted)' }}>
                                        {MESES[d.periodo_mes] ?? d.periodo_mes}
                                    </td>
                                    <td className="px-4 py-2.5 font-mono text-right" style={{ color: 'var(--text-main)' }}>
                                        $ {fmt(d.monto)}
                                    </td>
                                    <td className="px-4 py-2.5 font-mono text-right" style={{ color: 'var(--text-muted)' }}>
                                        $ {fmt(d.depreciacion_acumulada_al_periodo)}
                                    </td>
                                    <td className="px-4 py-2.5 font-mono text-right font-semibold" style={{ color: 'var(--text-main)' }}>
                                        $ {fmt(d.valor_libro_al_periodo)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    )
}
