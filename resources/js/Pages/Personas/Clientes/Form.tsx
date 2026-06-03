import { Head, router, useForm, usePage } from '@inertiajs/react'
import { FormEvent } from 'react'
import { toastExito, toastError } from '@/lib/toast'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Save } from 'lucide-react'
import type { Cliente, PageProps } from '@/types'

interface Props extends PageProps {
    cliente?: Cliente
}

export default function ClienteForm() {
    const { cliente } = usePage<Props>().props
    const esEdicion = !!cliente

    const { data, setData, post, put, processing, errors } = useForm({
        ruc_cedula: cliente?.ruc_cedula ?? '',
        nombre: cliente?.nombre ?? '',
        direccion: cliente?.direccion ?? '',
        telefono: cliente?.telefono ?? '',
        email: cliente?.email ?? '',
        ciudad: cliente?.ciudad ?? '',
        pais: cliente?.pais ?? 'Ecuador',
        tiene_credito: cliente?.tiene_credito ?? false,
        dias_credito: cliente?.dias_credito ?? '',
        cupo_credito: cliente?.cupo_credito ?? '',
        es_agente_retencion: cliente?.es_agente_retencion ?? false,
        estado: cliente?.estado ?? true,
        observaciones: cliente?.observaciones ?? '',
    })

    function submit(e: FormEvent) {
        e.preventDefault()
        if (esEdicion) {
            put(route('personas.clientes.update', cliente!.id), {
                onSuccess: () => {
                    toastExito('Cliente actualizado correctamente')
                    router.visit(route('personas.clientes.index'))
                },
                onError: () => toastError('Error al guardar'),
            })
        } else {
            post(route('personas.clientes.store'), {
                onSuccess: () => {
                    toastExito('Cliente creado correctamente')
                    router.visit(route('personas.clientes.index'))
                },
                onError: () => toastError('Error al guardar'),
            })
        }
    }

    return (
        <AppLayout title={esEdicion ? 'Editar Cliente' : 'Nuevo Cliente'}>
            <Head title={esEdicion ? 'Editar Cliente' : 'Nuevo Cliente'} />

            <PageHeader
                title={esEdicion ? 'Editar Cliente' : 'Nuevo Cliente'}
                breadcrumbs={[
                    { label: 'Personas' },
                    { label: 'Clientes', href: route('personas.clientes.index') },
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
                            <Label>RUC / Cédula *</Label>
                            <Input
                                value={data.ruc_cedula}
                                onChange={e => setData('ruc_cedula', e.target.value)}
                                placeholder="0999999999001"
                                maxLength={13}
                                inputMode="numeric"
                                pattern="[0-9]*"
                            />
                            {(errors.ruc_cedula || (data.ruc_cedula.length > 0 && data.ruc_cedula.length !== 10 && data.ruc_cedula.length !== 13)) && (
                                <p className="text-xs text-red-400">
                                    {errors.ruc_cedula ?? 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos)'}
                                </p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Nombre / Razón Social *</Label>
                            <Input
                                value={data.nombre}
                                onChange={e => setData('nombre', e.target.value)}
                                placeholder="Ej: Juan Pérez o Comercial XYZ S.A."
                            />
                            {errors.nombre && <p className="text-xs text-red-400">{errors.nombre}</p>}
                        </div>
                        <div className="sm:col-span-2 space-y-1.5">
                            <Label>Dirección</Label>
                            <Input
                                value={data.direccion}
                                onChange={e => setData('direccion', e.target.value)}
                                placeholder="Dirección"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Teléfono</Label>
                            <Input
                                value={data.telefono}
                                onChange={e => setData('telefono', e.target.value)}
                                placeholder="+593 99 999 9999"
                                inputMode="tel"
                            />
                            {errors.telefono && (
                                <p className="text-xs text-red-400">Ingrese un teléfono válido (solo números, espacios y + - ())</p>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Email</Label>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                placeholder="correo@ejemplo.com"
                            />
                            {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Ciudad</Label>
                            <Input
                                value={data.ciudad}
                                onChange={e => setData('ciudad', e.target.value)}
                                placeholder="Quito"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>País</Label>
                            <Input
                                value={data.pais}
                                onChange={e => setData('pais', e.target.value)}
                                placeholder="Ecuador"
                            />
                        </div>
                    </div>
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
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pl-0">
                                <div className="space-y-1.5">
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
                                <div className="space-y-1.5">
                                    <Label>Cupo de crédito (opcional)</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={data.cupo_credito}
                                        onChange={e => setData('cupo_credito', e.target.value)}
                                        placeholder="5000.00"
                                    />
                                </div>
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setData('es_agente_retencion', !data.es_agente_retencion)}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                    data.es_agente_retencion ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-600'
                                }`}
                            >
                                <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                    data.es_agente_retencion ? 'translate-x-6' : 'translate-x-1'
                                }`} />
                            </button>
                            <Label className="cursor-pointer">¿Es agente de retención?</Label>
                        </div>
                    </div>
                </section>

                {/* Estado */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Estado
                    </h2>
                    <div className="space-y-4">
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
                            <Label className="cursor-pointer">Cliente activo</Label>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Observaciones</Label>
                            <textarea
                                value={data.observaciones}
                                onChange={e => setData('observaciones', e.target.value)}
                                rows={3}
                                placeholder="Notas internas sobre el cliente..."
                                className="flex w-full rounded-md border bg-transparent px-3 py-2 text-sm resize-none"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            />
                        </div>
                    </div>
                </section>

                {/* Acciones */}
                <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        {esEdicion ? 'Guardar cambios' : 'Crear cliente'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => window.history.back()}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
