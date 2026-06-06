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

const TIPOS_ID_NACIONAL = [
    { value: '04', label: '04 — RUC' },
    { value: '05', label: '05 — Cédula' },
]

const TIPOS_ID_INTERNACIONAL = [
    { value: '04', label: '04 — Tax ID' },
    { value: '06', label: '06 — Pasaporte' },
]

export default function ProveedorForm() {
    const { proveedor } = usePage<Props>().props
    const esEdicion = !!proveedor

    const { data, setData, post, put, processing, errors } = useForm({
        tipo:                (proveedor?.tipo ?? 'nacional') as 'nacional' | 'internacional',
        tipo_identificacion: (proveedor?.tipo_identificacion ?? '04') as '04' | '05' | '06',
        identificacion:      proveedor?.identificacion ?? '',
        razon_social:        proveedor?.razon_social ?? '',
        nombre_comercial:    proveedor?.nombre_comercial ?? '',
        email:               proveedor?.email ?? '',
        telefono:            proveedor?.telefono ?? '',
        direccion:           proveedor?.direccion ?? '',
        ciudad:              proveedor?.ciudad ?? '',
        pais:                proveedor?.pais ?? 'ECUADOR',
        divisa:              proveedor?.divisa ?? 'USD',
        tiene_credito:       proveedor?.tiene_credito ?? false,
        dias_credito:        proveedor?.dias_credito ?? 0,
        estado:              proveedor?.estado ?? true,
    })

    function cambiarTipo(nuevoTipo: 'nacional' | 'internacional') {
        setData(prev => ({
            ...prev,
            tipo:                nuevoTipo,
            tipo_identificacion: '04' as const,
            pais:                nuevoTipo === 'nacional' ? 'ECUADOR' : (prev.pais && prev.pais !== 'ECUADOR' ? prev.pais : ''),
        }))
    }

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

    const tiposId = data.tipo === 'nacional' ? TIPOS_ID_NACIONAL : TIPOS_ID_INTERNACIONAL

    const placeholderIdentificacion =
        data.tipo === 'nacional'
            ? (data.tipo_identificacion === '04' ? '0999999999001' : '0999999999')
            : (data.tipo_identificacion === '06' ? 'AB123456' : 'ID tributario')

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
                    {([{ label: 'Nacional', value: 'nacional' }, { label: 'Internacional', value: 'internacional' }] as const).map(tab => (
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

                {/* Datos principales */}
                <section>
                    <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                        style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                        Datos del proveedor
                    </h2>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        {/* Tipo identificación */}
                        <div className="space-y-1.5">
                            <Label>Tipo identificación *</Label>
                            <select
                                value={data.tipo_identificacion}
                                onChange={e => setData('tipo_identificacion', e.target.value as '04' | '05' | '06')}
                                className="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                            >
                                {tiposId.map(t => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                            {errors.tipo_identificacion && <p className="text-xs text-red-400">{errors.tipo_identificacion}</p>}
                        </div>

                        {/* Identificación */}
                        <div className="space-y-1.5">
                            <Label>Identificación *</Label>
                            <Input
                                value={data.identificacion}
                                onChange={e => setData('identificacion', e.target.value)}
                                placeholder={placeholderIdentificacion}
                                maxLength={20}
                            />
                            {errors.identificacion && <p className="text-xs text-red-400">{errors.identificacion}</p>}
                        </div>

                        {/* Razón social */}
                        <div className="space-y-1.5">
                            <Label>Razón social *</Label>
                            <Input
                                value={data.razon_social}
                                onChange={e => setData('razon_social', e.target.value)}
                                placeholder="Razón social"
                            />
                            {errors.razon_social && <p className="text-xs text-red-400">{errors.razon_social}</p>}
                        </div>

                        {/* Nombre comercial */}
                        <div className="space-y-1.5">
                            <Label>Nombre comercial</Label>
                            <Input
                                value={data.nombre_comercial}
                                onChange={e => setData('nombre_comercial', e.target.value)}
                                placeholder="Nombre comercial (opcional)"
                            />
                            {errors.nombre_comercial && <p className="text-xs text-red-400">{errors.nombre_comercial}</p>}
                        </div>

                        {/* Email */}
                        <div className="space-y-1.5">
                            <Label>Email</Label>
                            <Input
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                placeholder="correo@proveedor.com"
                            />
                            {errors.email && <p className="text-xs text-red-400">{errors.email}</p>}
                        </div>

                        {/* Teléfono */}
                        <div className="space-y-1.5">
                            <Label>Teléfono</Label>
                            <Input
                                value={data.telefono}
                                onChange={e => setData('telefono', e.target.value)}
                                placeholder="+593 99 999 9999"
                            />
                            {errors.telefono && <p className="text-xs text-red-400">{errors.telefono}</p>}
                        </div>

                        {/* Dirección */}
                        <div className="space-y-1.5 sm:col-span-2">
                            <Label>Dirección</Label>
                            <Input
                                value={data.direccion}
                                onChange={e => setData('direccion', e.target.value)}
                                placeholder="Dirección"
                            />
                            {errors.direccion && <p className="text-xs text-red-400">{errors.direccion}</p>}
                        </div>

                        {/* Campos según tipo */}
                        {data.tipo === 'nacional' ? (
                            <div className="space-y-1.5">
                                <Label>País</Label>
                                <Input value="ECUADOR" disabled className="opacity-60 cursor-not-allowed" />
                            </div>
                        ) : (
                            <>
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
                                    <Label>País *</Label>
                                    <Input
                                        value={data.pais}
                                        onChange={e => setData('pais', e.target.value)}
                                        placeholder="China"
                                    />
                                    {errors.pais && <p className="text-xs text-red-400">{errors.pais}</p>}
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
                            </>
                        )}
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
                            <div className="space-y-1.5 max-w-xs">
                                <Label>Días de crédito *</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.dias_credito}
                                    onChange={e => setData('dias_credito', Number(e.target.value))}
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
