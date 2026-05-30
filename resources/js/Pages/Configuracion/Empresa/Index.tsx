import { Head, useForm, usePage } from '@inertiajs/react'
import { FormEvent } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Save, AlertTriangle, Building2, FileText, List } from 'lucide-react'
import type { Empresa, CentroCosto, PageProps } from '@/types'

interface Secuencial {
    id: number
    tipo_documento: string
    establecimiento: string
    punto_emision: string
    siguiente: number
}

interface Props extends PageProps {
    empresa: Empresa & { centrosCosto?: CentroCosto[]; secuenciales?: Secuencial[] }
    centros_costo: CentroCosto[]
    secuenciales: Secuencial[]
}

export default function EmpresaIndex() {
    const { empresa, centros_costo, secuenciales } = usePage<Props>().props

    const { data, setData, put, processing, errors } = useForm({
        razon_social: empresa.razon_social ?? '',
        nombre_comercial: empresa.nombre_comercial ?? '',
        ruc: empresa.ruc ?? '',
        direccion_matriz: empresa.direccion_matriz ?? '',
        email_notificaciones: empresa.email_notificaciones ?? '',
        telefono: empresa.telefono ?? '',
        ambiente_sri: empresa.ambiente_sri ?? '1',
        codigo_establecimiento: empresa.codigo_establecimiento ?? '001',
        codigo_punto_emision: empresa.codigo_punto_emision ?? '001',
        obligado_contabilidad: empresa.obligado_contabilidad ?? false,
        contribuyente_especial: empresa.contribuyente_especial ?? false,
        numero_resolucion_agente_retencion: empresa.numero_resolucion_agente_retencion ?? '',
    })

    function submit(e: FormEvent) {
        e.preventDefault()
        put(route('configuracion.empresa.update'))
    }

    return (
        <AppLayout title="Configuración de Empresa">
            <Head title="Empresa" />

            <PageHeader
                title="Configuración de Empresa"
                breadcrumbs={[{ label: 'Configuración' }, { label: 'Empresa' }]}
            />

            <form onSubmit={submit} className="p-6 max-w-3xl space-y-8">
                {/* Datos generales */}
                <section>
                    <div className="flex items-center gap-2 mb-4 pb-2 border-b"
                        style={{ borderColor: 'var(--border)' }}>
                        <Building2 className="w-5 h-5" style={{ color: '#F59E0B' }} />
                        <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            Datos generales
                        </h2>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2 space-y-1.5">
                            <Label>Razón social *</Label>
                            <Input value={data.razon_social} onChange={e => setData('razon_social', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Nombre comercial *</Label>
                            <Input value={data.nombre_comercial} onChange={e => setData('nombre_comercial', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>RUC *</Label>
                            <Input value={data.ruc} onChange={e => setData('ruc', e.target.value)} maxLength={13} />
                        </div>
                        <div className="sm:col-span-2 space-y-1.5">
                            <Label>Dirección matriz</Label>
                            <Input value={data.direccion_matriz} onChange={e => setData('direccion_matriz', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Email de notificaciones</Label>
                            <Input type="email" value={data.email_notificaciones} onChange={e => setData('email_notificaciones', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Teléfono</Label>
                            <Input value={data.telefono} onChange={e => setData('telefono', e.target.value)} />
                        </div>
                    </div>
                </section>

                {/* Datos SRI */}
                <section>
                    <div className="flex items-center gap-2 mb-4 pb-2 border-b"
                        style={{ borderColor: 'var(--border)' }}>
                        <FileText className="w-5 h-5" style={{ color: '#F59E0B' }} />
                        <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            Datos SRI
                        </h2>
                    </div>

                    {/* Ambiente */}
                    <div className="mb-4">
                        <Label className="mb-2 block">Ambiente de facturación</Label>
                        <div className="flex gap-2">
                            <button type="button"
                                onClick={() => setData('ambiente_sri', '1')}
                                className={`px-4 py-2 rounded-lg border text-sm font-medium transition-all ${
                                    data.ambiente_sri === '1'
                                        ? 'border-emerald-500 bg-emerald-500/10 text-emerald-400'
                                        : 'hover:border-slate-400'
                                }`}
                                style={data.ambiente_sri !== '1' ? { borderColor: 'var(--border)', color: 'var(--text-muted)' } : {}}>
                                Pruebas
                            </button>
                            <button type="button"
                                onClick={() => setData('ambiente_sri', '2')}
                                className={`px-4 py-2 rounded-lg border text-sm font-medium transition-all flex items-center gap-1.5 ${
                                    data.ambiente_sri === '2'
                                        ? 'border-amber-500 bg-amber-500/10 text-amber-400'
                                        : 'hover:border-slate-400'
                                }`}
                                style={data.ambiente_sri !== '2' ? { borderColor: 'var(--border)', color: 'var(--text-muted)' } : {}}>
                                {data.ambiente_sri === '2' && <AlertTriangle className="w-3.5 h-3.5" />}
                                Producción
                            </button>
                        </div>
                        {data.ambiente_sri === '2' && (
                            <p className="mt-2 text-xs text-amber-400 flex items-center gap-1">
                                <AlertTriangle className="w-3 h-3" />
                                Producción: los comprobantes son válidos ante el SRI
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="space-y-1.5">
                            <Label>Código establecimiento</Label>
                            <Input value={data.codigo_establecimiento} onChange={e => setData('codigo_establecimiento', e.target.value)} maxLength={3} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Punto de emisión</Label>
                            <Input value={data.codigo_punto_emision} onChange={e => setData('codigo_punto_emision', e.target.value)} maxLength={3} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>N° resolución agente ret.</Label>
                            <Input value={data.numero_resolucion_agente_retencion}
                                onChange={e => setData('numero_resolucion_agente_retencion', e.target.value)} />
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-4">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.obligado_contabilidad}
                                onChange={e => setData('obligado_contabilidad', e.target.checked)}
                                className="w-4 h-4 rounded accent-amber-500" />
                            <span className="text-sm" style={{ color: 'var(--text-main)' }}>Obligado a llevar contabilidad</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.contribuyente_especial}
                                onChange={e => setData('contribuyente_especial', e.target.checked)}
                                className="w-4 h-4 rounded accent-amber-500" />
                            <span className="text-sm" style={{ color: 'var(--text-main)' }}>Contribuyente especial</span>
                        </label>
                    </div>
                </section>

                {/* Centros de costo */}
                <section>
                    <div className="flex items-center gap-2 mb-4 pb-2 border-b"
                        style={{ borderColor: 'var(--border)' }}>
                        <List className="w-5 h-5" style={{ color: '#F59E0B' }} />
                        <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            Centros de costo
                        </h2>
                    </div>
                    <div className="space-y-2">
                        {centros_costo.map(cc => (
                            <div key={cc.id} className="flex items-center justify-between p-3 rounded-lg border"
                                style={{ borderColor: 'var(--border)' }}>
                                <div>
                                    <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>{cc.nombre}</p>
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Código: {cc.codigo} · {cc.tipo}</p>
                                </div>
                                <Badge variant={cc.estado ? 'success' : 'secondary'}>
                                    {cc.estado ? 'Activo' : 'Inactivo'}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Secuenciales */}
                {secuenciales.length > 0 && (
                    <section>
                        <h2 className="text-base font-semibold mb-4 pb-2 border-b"
                            style={{ color: 'var(--text-main)', borderColor: 'var(--border)' }}>
                            Secuenciales SRI
                        </h2>
                        <div className="rounded-xl border overflow-hidden"
                            style={{ borderColor: 'var(--border)' }}>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                        <th className="text-left px-4 py-2 text-xs font-medium" style={{ color: 'var(--text-muted)' }}>Documento</th>
                                        <th className="text-left px-4 py-2 text-xs font-medium" style={{ color: 'var(--text-muted)' }}>Est.</th>
                                        <th className="text-left px-4 py-2 text-xs font-medium" style={{ color: 'var(--text-muted)' }}>P.E.</th>
                                        <th className="text-left px-4 py-2 text-xs font-medium" style={{ color: 'var(--text-muted)' }}>Próximo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {secuenciales.map(s => (
                                        <tr key={s.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                            <td className="px-4 py-2 capitalize" style={{ color: 'var(--text-main)' }}>
                                                {s.tipo_documento.replace('_', ' ')}
                                            </td>
                                            <td className="px-4 py-2 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>{s.establecimiento}</td>
                                            <td className="px-4 py-2 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>{s.punto_emision}</td>
                                            <td className="px-4 py-2 font-mono text-xs" style={{ color: '#F59E0B' }}>
                                                {String(s.siguiente).padStart(9, '0')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {/* Acciones */}
                <div className="flex gap-3 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                    <Button type="submit" loading={processing}>
                        <Save className="w-4 h-4" />
                        Guardar cambios
                    </Button>
                </div>
            </form>
        </AppLayout>
    )
}
