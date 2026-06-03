import { Head, router, useForm, usePage } from '@inertiajs/react'
import { FormEvent } from 'react'
import { toastExito, toastError } from '@/lib/toast'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save } from 'lucide-react'
import type { Proveedor, PageProps } from '@/types'

interface Props extends PageProps {
    proveedor?: Proveedor
}

const DIVISAS = [
    { value: 'USD', label: 'USD — Dólar estadounidense' },
    { value: 'EUR', label: 'EUR — Euro' },
    { value: 'CNY', label: 'CNY — Yuan chino' },
    { value: 'GBP', label: 'GBP — Libra esterlina' },
]

export default function ProveedorForm() {
    const { proveedor } = usePage<Props>().props
    const esEdicion = !!proveedor

    const { data, setData, post, put, processing, errors } = useForm({
        tipo: proveedor?.tipo ?? 'nacional' as 'nacional' | 'internacional',
        ruc_cedula: proveedor?.ruc_cedula ?? '',
        nombre: proveedor?.nombre ?? '',
        direccion: proveedor?.direccion ?? '',
        telefono: proveedor?.telefono ?? '',
        email: proveedor?.email ?? '',
        ciudad: proveedor?.ciudad ?? '',
        pais: proveedor?.pais ?? 'Ecuador',
        divisa: proveedor?.divisa ?? 'USD',
        tiene_credito: proveedor?.tiene_credito ?? false,
        dias_credito: proveedor?.dias_credito ?? '',
        estado: proveedor?.estado ?? true,
        observaciones: proveedor?.observaciones ?? '',
    })

    function submit(e: FormEvent) {
        e.preventDefault()
        if (esEdicion) {
            put(route('personas.proveedores.update', proveedor!.id), {
                onSuccess: () => {
                    toastExito('Proveedor actualizado correctamente')
                    router.visit(route('personas.proveedores.index'))
                },
                onError: () => toastError('Error al guardar'),
            })
        } else {
            post(route('personas.proveedores.store'), {
                onSuccess: () => {
                    toastExito('Proveedor creado correctamente')
                    router.visit(route('personas.proveedores.index'))
                },
                onError: () => toastError('Error al guardar'),
            })
        }
    }

    function cambiarTipo(nuevoTipo: 'nacional' | 'internacional') {
        setData(prev => ({
            ...prev,
            tipo: nuevoTipo,
            pais: nuevoTipo === 'nacional' ? 'Ecuador' : (prev.pais && prev.pais !== 'Ecuador' ? prev.pais : ''),
            divisa: nuevoTipo === 'internacional' ? (prev.divisa || 'USD') : prev.divisa,
        }))
    }

    return (
        <AppLayout title={esEdicion ? 'Editar Proveedor' : 'Nuevo Proveedor'}>
            <Head title={esEdicion ? 'Editar Proveedor' : 'Nuevo Proveedor'} />

            <PageHeader
                title={esEdicion ? 'Editar Proveedor' : 'Nuevo Proveedor'}
                breadcrumbs={[
                    { label: 'Personas' },
                    { label: 'Proveedores', href: route('personas.proveedores.index') },
                    { label: esEdicion ? 'Editar' : 'Nuevo' },
                ]}
            />

            <form onSubmit={submit} className="p-6 max-w-2xl space-y-8">
                {/* Tabs tipo */}
                <div className="flex gap-0 border-b" style={{ borderColor: 'var(--border)' }}>
                    {[{ label: 'Nacional', value: 'nacional' as const }, { label: 'Internacional', value: 'internacional' as const }].map(tab => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => cambiarTipo(tab.value)}
                            className="px-5 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px"
                            style={{
                                borderBottomColor: data.tipo === tab.value ? 'var(--primary)' : 'transparent',
                                color: data.tipo === tab.value ? 'var(--primary)' : 'var(--text-muted)',
                            }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Datos del proveedor — condicional por tipo */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Datos del proveedor
                    </h2>

                    {data.tipo === 'nacional' ? (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label>RUC / Cédula *</Label>
                                <Input
                                    value={data.ruc_cedula}
                                    onChange={e => setData('ruc_cedula', e.target.value)}
                                    placeholder="0999999999001"
                                    maxLength={13}
                                />
                                {errors.ruc_cedula && <p className="text-xs text-red-400">{errors.ruc_cedula}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Nombre / Razón social *</Label>
                                <Input
                                    value={data.nombre}
                                    onChange={e => setData('nombre', e.target.value)}
                                    placeholder="Nombre o razón social"
                                />
                                {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>País</Label>
                                <Input value="Ecuador" disabled className="opacity-60 cursor-not-allowed" />
                            </div>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="space-y-1.5">
                                <Label>Identificación (opcional)</Label>
                                <Input
                                    value={data.ruc_cedula}
                                    onChange={e => setData('ruc_cedula', e.target.value)}
                                    placeholder="ID tributario extranjero"
                                    maxLength={20}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Nombre / Razón social *</Label>
                                <Input
                                    value={data.nombre}
                                    onChange={e => setData('nombre', e.target.value)}
                                    placeholder="Nombre o razón social"
                                />
                                {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Ciudad *</Label>
                                <Input
                                    value={data.ciudad}
                                    onChange={e => setData('ciudad', e.target.value)}
                                    placeholder="Shanghái"
                                />
                                {errors.ciudad && <p className="text-xs text-red-400">{errors.ciudad}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>Divisa *</Label>
                                <select
                                    value={data.divisa}
                                    onChange={e => setData('divisa', e.target.value)}
                                    className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                                >
                                    {DIVISAS.map(d => (
                                        <option key={d.value} value={d.value}>{d.label}</option>
                                    ))}
                                </select>
                                {errors.divisa && <p className="text-xs text-red-400">{errors.divisa}</p>}
                            </div>
                            <div className="space-y-1.5">
                                <Label>País *</Label>
                                <Input
                                    value={data.pais}
                                    onChange={e => setData('pais', e.target.value)}
                                    placeholder="China"
                                />
                                {errors.pais && <p className="text-xs text-red-400">{errors.pais}</p>}
                            </div>
                        </div>
                    )}
                </section>

                {/* Crédito */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Crédito
                    </h2>
                    <div className="space-y-4">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setData('tiene_credito', !data.tiene_credito)}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                    data.tiene_credito ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'
                                }`}
                            >
                                <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                    data.tiene_credito ? 'translate-x-6' : 'translate-x-1'
                                }`} />
                            </button>
                            <Label className="cursor-pointer">¿Tiene crédito?</Label>
                        </div>

                        {data.tiene_credito && (
                            <div className="space-y-1.5 max-w-xs">
                                <Label>Días de crédito *</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    value={data.dias_credito}
                                    onChange={e => setData('dias_credito', e.target.value)}
                                    placeholder="30"
                                />
                                {errors.dias_credito && <p className="text-xs text-red-400">{errors.dias_credito}</p>}
                            </div>
                        )}
                    </div>
                </section>

                {/* Estado */}
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={() => setData('estado', !data.estado)}
                        className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                            data.estado ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'
                        }`}
                    >
                        <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                            data.estado ? 'translate-x-6' : 'translate-x-1'
                        }`} />
                    </button>
                    <Label className="cursor-pointer">Proveedor activo</Label>
                </div>

                {/* Acciones */}
                <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        {esEdicion ? 'Guardar cambios' : 'Crear proveedor'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
