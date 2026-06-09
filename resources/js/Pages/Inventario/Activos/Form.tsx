import { Head, router, useForm, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save, Info } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { ActivoFijo, PageProps } from '@/types'

interface Props extends PageProps {
    activoFijo: ActivoFijo | null
}

export default function ActivoFijoForm() {
    const { activoFijo } = usePage<Props>().props
    const esEdicion = !!activoFijo

    const { data, setData, post, put, processing, errors } = useForm({
        codigo:            activoFijo?.codigo ?? '',
        nombre:            activoFijo?.nombre ?? '',
        descripcion:       activoFijo?.descripcion ?? '',
        fecha_adquisicion: activoFijo?.fecha_adquisicion ?? '',
        costo_adquisicion: activoFijo?.costo_adquisicion?.toString() ?? '',
        valor_residual:    activoFijo?.valor_residual?.toString() ?? '0',
        vida_util_anios:   activoFijo?.vida_util_anios?.toString() ?? '',
        cuenta_id:         activoFijo?.cuenta_id?.toString() ?? '',
    })

    const valorAdq  = parseFloat(data.costo_adquisicion) || 0
    const valorRes  = parseFloat(data.valor_residual) || 0
    const vidaAnios = parseInt(data.vida_util_anios) || 0
    const depMensual = vidaAnios > 0 && valorAdq > valorRes
        ? ((valorAdq - valorRes) / (vidaAnios * 12)).toFixed(2)
        : null

    function submit(e: React.FormEvent) {
        e.preventDefault()
        const payload = {
            ...data,
            valor_residual: data.valor_residual || '0',
            cuenta_id:      data.cuenta_id || null,
        }

        if (esEdicion) {
            put(route('inventario.activos.update', activoFijo!.id), {
                ...payload,
                onSuccess: () => {
                    toastExito('Activo actualizado correctamente')
                    router.visit(route('inventario.activos.index'))
                },
                onError: () => toastError('Error al guardar — revisa los campos marcados'),
            } as Parameters<typeof put>[1])
        } else {
            post(route('inventario.activos.store'), {
                ...payload,
                onSuccess: () => {
                    toastExito('Activo creado correctamente')
                    router.visit(route('inventario.activos.index'))
                },
                onError: () => toastError('Error al guardar — revisa los campos marcados'),
            } as Parameters<typeof post>[1])
        }
    }

    const selectCls = "flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
    const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }

    return (
        <AppLayout title={esEdicion ? 'Editar Activo' : 'Nuevo Activo Fijo'}>
            <Head title={esEdicion ? 'Editar Activo' : 'Nuevo Activo Fijo'} />

            <PageHeader
                title={esEdicion ? 'Editar Activo' : 'Nuevo Activo Fijo'}
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Activos Fijos', href: route('inventario.activos.index') },
                    { label: esEdicion ? 'Editar' : 'Nuevo' },
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-2xl space-y-8">
                {/* Datos generales */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Datos generales
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Código *</Label>
                            <Input value={data.codigo} onChange={e => setData('codigo', e.target.value)}
                                placeholder="Ej: AF-001" />
                            {errors.codigo && <p className="text-xs text-red-400">{errors.codigo}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Nombre *</Label>
                            <Input value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                                placeholder="Ej: Camioneta Chevrolet D-MAX 2022" />
                            {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                        </div>
                        <div className="sm:col-span-2 space-y-1.5">
                            <Label>Descripción</Label>
                            <textarea value={data.descripcion}
                                onChange={e => setData('descripcion', e.target.value)}
                                rows={2} placeholder="Ej: Descripción del activo..."
                                className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm resize-none"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }} />
                        </div>
                    </div>
                </section>

                {/* Valores y depreciación */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Valores y depreciación
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Fecha de adquisición *</Label>
                            <Input type="date" value={data.fecha_adquisicion}
                                onChange={e => setData('fecha_adquisicion', e.target.value)} />
                            {errors.fecha_adquisicion && <p className="text-xs text-red-400">{errors.fecha_adquisicion}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Costo de adquisición *</Label>
                            <Input type="number" min={0} step="0.01" value={data.costo_adquisicion}
                                onChange={e => setData('costo_adquisicion', e.target.value)}
                                placeholder="Ej: 0.00" />
                            {errors.costo_adquisicion && <p className="text-xs text-red-400">{errors.costo_adquisicion}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Valor residual</Label>
                            <Input type="number" min={0} step="0.01" value={data.valor_residual}
                                onChange={e => setData('valor_residual', e.target.value)}
                                placeholder="Ej: 0.00" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Vida útil (años) *</Label>
                            <Input type="number" min={1} max={100} value={data.vida_util_anios}
                                onChange={e => setData('vida_util_anios', e.target.value)}
                                placeholder="Ej: 5" />
                            {errors.vida_util_anios && <p className="text-xs text-red-400">{errors.vida_util_anios}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Método de depreciación</Label>
                            <select disabled className={`${selectCls} opacity-60 cursor-not-allowed`} style={selectStyle}>
                                <option value="lineal">Lineal</option>
                            </select>
                        </div>

                        {depMensual !== null && (
                            <div className="sm:col-span-2 flex items-center gap-2 px-3 py-2.5 rounded-lg text-sm"
                                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                                <Info className="w-4 h-4 shrink-0" style={{ color: 'var(--primary)' }} />
                                <span style={{ color: 'var(--text-muted)' }}>Depreciación mensual estimada:</span>
                                <span className="font-semibold font-mono" style={{ color: 'var(--primary)' }}>
                                    $ {depMensual}
                                </span>
                            </div>
                        )}
                    </div>
                </section>

                {/* Contabilidad */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Contabilidad
                    </h2>
                    <div className="flex items-start gap-2 px-3 py-2.5 rounded-lg text-xs mb-4"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-muted)' }}>
                        <Info className="w-3.5 h-3.5 shrink-0 mt-0.5" />
                        <span>Vincula este activo a una cuenta del Plan de Cuentas (ID numérico).</span>
                    </div>
                    <div className="space-y-1.5 max-w-sm">
                        <Label>Cuenta contable</Label>
                        <Input type="number" value={data.cuenta_id}
                            onChange={e => setData('cuenta_id', e.target.value)}
                            placeholder="ID de cuenta — disponible con Plan de Cuentas" />
                    </div>
                </section>

                {/* Acciones */}
                <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        {esEdicion ? 'Guardar cambios' : 'Crear activo'}
                    </Button>
                    <Button type="button" variant="outline"
                        onClick={() => router.visit(route('inventario.activos.index'))}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
