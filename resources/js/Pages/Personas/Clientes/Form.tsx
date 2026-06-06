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

const TIPO_LABELS: Record<string, string> = {
    '04': '04 — RUC',
    '05': '05 — Cédula',
    '06': '06 — Pasaporte',
    '07': '07 — Consumidor Final',
}

const ID_PLACEHOLDERS: Record<string, string> = {
    '04': '0999999999001',
    '05': '0999999999',
    '06': 'Número de pasaporte',
    '07': '',
}

export default function ClienteForm() {
    const { cliente } = usePage<Props>().props
    const esEdicion = !!cliente

    const { data, setData, post, put, processing, errors } = useForm({
        tipo_identificacion: cliente?.tipo_identificacion ?? '05',
        identificacion:      cliente?.identificacion ?? '',
        razon_social:        cliente?.razon_social ?? '',
        nombre_comercial:    cliente?.nombre_comercial ?? '',
        email:               cliente?.email ?? '',
        telefono:            cliente?.telefono ?? '',
        celular:             cliente?.celular ?? '',
        direccion:           cliente?.direccion ?? '',
        ciudad:              cliente?.ciudad ?? '',
        provincia:           cliente?.provincia ?? '',
        pais:                cliente?.pais ?? 'ECUADOR',
        tiene_credito:       cliente?.tiene_credito ?? false,
        dias_credito:        cliente?.dias_credito ?? 0,
        cupo_maximo:         cliente?.cupo_maximo ?? 0,
        agente_retencion:    cliente?.agente_retencion ?? false,
        es_cliente_nuevo:    cliente?.es_cliente_nuevo ?? false,
        estado:              cliente?.estado ?? true,
    })

    function submit(e: FormEvent) {
        e.preventDefault()
        const options = {
            onSuccess: () => {
                toastExito(esEdicion ? 'Cliente actualizado correctamente' : 'Cliente creado correctamente')
                router.visit(route('personas.clientes.index'))
            },
            onError: (errs: Record<string, string>) => {
                console.log('Errores de validación:', errs)
                toastError('Error al guardar: ' + Object.values(errs).join(', '))
            },
        }
        if (esEdicion) {
            put(route('personas.clientes.update', cliente!.id), options)
        } else {
            post(route('personas.clientes.store'), options)
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

                {/* Sección 1 — Identificación */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Identificación
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Tipo de identificación *</Label>
                            <select
                                value={data.tipo_identificacion}
                                onChange={e => setData('tipo_identificacion', e.target.value)}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            >
                                {Object.entries(TIPO_LABELS).map(([val, label]) => (
                                    <option key={val} value={val}>{label}</option>
                                ))}
                            </select>
                            {errors.tipo_identificacion && <p className="text-xs text-red-400">{errors.tipo_identificacion}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Identificación *</Label>
                            <Input
                                value={data.identificacion}
                                onChange={e => setData('identificacion', e.target.value)}
                                placeholder={ID_PLACEHOLDERS[data.tipo_identificacion] ?? ''}
                                maxLength={20}
                            />
                            {errors.identificacion && <p className="text-xs text-red-400">{errors.identificacion}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Razón Social *</Label>
                            <Input
                                value={data.razon_social}
                                onChange={e => setData('razon_social', e.target.value)}
                                placeholder="Ej: Juan Pérez o Comercial XYZ S.A."
                            />
                            {errors.razon_social && <p className="text-xs text-red-400">{errors.razon_social}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Nombre Comercial</Label>
                            <Input
                                value={data.nombre_comercial}
                                onChange={e => setData('nombre_comercial', e.target.value)}
                                placeholder="Nombre comercial (opcional)"
                            />
                        </div>
                    </div>
                </section>

                {/* Sección 2 — Contacto */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Contacto
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                            <Label>Teléfono</Label>
                            <Input
                                value={data.telefono}
                                onChange={e => setData('telefono', e.target.value)}
                                placeholder="+593 2 999 9999"
                                inputMode="tel"
                            />
                            {errors.telefono && <p className="text-xs text-red-400">Ingrese un teléfono válido</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Celular</Label>
                            <Input
                                value={data.celular}
                                onChange={e => setData('celular', e.target.value)}
                                placeholder="+593 99 999 9999"
                                inputMode="tel"
                            />
                            {errors.celular && <p className="text-xs text-red-400">Ingrese un celular válido</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Dirección</Label>
                            <Input
                                value={data.direccion}
                                onChange={e => setData('direccion', e.target.value)}
                                placeholder="Dirección completa"
                            />
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
                            <Label>Provincia</Label>
                            <Input
                                value={data.provincia}
                                onChange={e => setData('provincia', e.target.value)}
                                placeholder="Pichincha"
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>País</Label>
                            <Input
                                value={data.pais}
                                onChange={e => setData('pais', e.target.value)}
                                placeholder="ECUADOR"
                            />
                        </div>
                    </div>
                </section>

                {/* Sección 3 — Crédito */}
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
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                                    <Label>Cupo máximo</Label>
                                    <Input
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={data.cupo_maximo}
                                        onChange={e => setData('cupo_maximo', e.target.value)}
                                        placeholder="5000.00"
                                    />
                                </div>
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setData('agente_retencion', !data.agente_retencion)}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                    data.agente_retencion ? 'bg-amber-500' : 'bg-slate-300 dark:bg-slate-600'
                                }`}
                            >
                                <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                    data.agente_retencion ? 'translate-x-6' : 'translate-x-1'
                                }`} />
                            </button>
                            <Label className="cursor-pointer">¿Es agente de retención?</Label>
                        </div>
                    </div>
                </section>

                {/* Sección 4 — Estado */}
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

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setData('es_cliente_nuevo', !data.es_cliente_nuevo)}
                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                    data.es_cliente_nuevo ? 'bg-blue-500' : 'bg-slate-300 dark:bg-slate-600'
                                }`}
                            >
                                <span className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                    data.es_cliente_nuevo ? 'translate-x-6' : 'translate-x-1'
                                }`} />
                            </button>
                            <Label className="cursor-pointer">¿Es cliente nuevo?</Label>
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
