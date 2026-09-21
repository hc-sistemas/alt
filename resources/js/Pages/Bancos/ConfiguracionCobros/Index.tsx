import { useForm, usePage, Head } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Label } from '@/Components/ui/label'
import { Save, AlertTriangle, CheckCircle } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'

interface CuentaOpt { id: number; nombre: string; tipo: string }
interface CentroInfo { id: number; nombre: string; cajas: string[] }

interface Props extends PageProps {
    config: {
        cobro_banco_transferencia: string | null
        cobro_banco_tarjeta: string | null
        cobro_banco_cheque: string | null
        cobro_caja_efectivo: string | null
        cobro_tarjeta_modo: string | null
    }
    cuentas: CuentaOpt[]
    centros: CentroInfo[]
}

const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }

export default function ConfiguracionCobrosIndex() {
    const { config, cuentas, centros } = usePage<Props>().props
    const { puede } = usePermiso('bancos')

    const { data, setData, put, processing } = useForm({
        cobro_banco_transferencia: config.cobro_banco_transferencia ?? '',
        cobro_banco_tarjeta:       config.cobro_banco_tarjeta ?? '',
        cobro_banco_cheque:        config.cobro_banco_cheque ?? '',
        cobro_caja_efectivo:       config.cobro_caja_efectivo ?? '',
        cobro_tarjeta_modo:        config.cobro_tarjeta_modo ?? '',
    })

    const bancos = cuentas.filter(c => c.tipo === 'banco' || c.tipo === 'tarjeta')
    const cajas  = cuentas.filter(c => c.tipo === 'caja' || c.tipo === 'caja_chica')
    const tarjetaEnDatafast = data.cobro_tarjeta_modo === 'datafast'

    function submit(e: React.FormEvent) {
        e.preventDefault()
        put(route('bancos.cobros-config.update'))
    }

    const opciones = (lista: CuentaOpt[]) => lista.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)

    return (
        <AppLayout title="Configuración de cobros">
            <Head title="Configuración de cobros" />
            <PageHeader
                title="Configuración de cobros"
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Configuración de cobros' }]}
            />

            <form onSubmit={submit} className="px-6 pt-6 pb-10 space-y-6 max-w-3xl">
                <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                    Define a dónde entra el dinero de las ventas que se cobran en el acto, al emitir la factura.
                    Lo que no configures aquí <strong>no genera movimiento en Bancos</strong>; la factura se emite igual.
                    Las ventas a crédito nunca entran a Bancos (generan cuenta por cobrar).
                </p>

                <div className="rounded-xl border p-5 space-y-5" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="space-y-1.5">
                        <Label>Efectivo — caja por defecto</Label>
                        <select className="input-field" style={selectStyle} value={data.cobro_caja_efectivo}
                            onChange={e => setData('cobro_caja_efectivo', e.target.value)} disabled={!puede('editar')}>
                            <option value="">— Sin caja por defecto —</option>
                            {opciones(cajas)}
                        </select>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            El efectivo entra primero a la caja del <strong>mismo centro de costo</strong> de la factura.
                            Esta caja se usa solo cuando ese centro de costo no tiene una (ver tabla de abajo).
                        </p>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Transferencias — banco</Label>
                        <select className="input-field" style={selectStyle} value={data.cobro_banco_transferencia}
                            onChange={e => setData('cobro_banco_transferencia', e.target.value)} disabled={!puede('editar')}>
                            <option value="">— No registrar en Bancos —</option>
                            {opciones(bancos)}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Cheques — banco donde se depositan</Label>
                        <select className="input-field" style={selectStyle} value={data.cobro_banco_cheque}
                            onChange={e => setData('cobro_banco_cheque', e.target.value)} disabled={!puede('editar')}>
                            <option value="">— No registrar en Bancos —</option>
                            {opciones(bancos)}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Tarjeta — ¿cómo se cobra?</Label>
                            <select className="input-field" style={selectStyle} value={data.cobro_tarjeta_modo}
                                onChange={e => setData('cobro_tarjeta_modo', e.target.value)} disabled={!puede('editar')}>
                                <option value="">— No registrar en Bancos —</option>
                                <option value="banco">Entra directo a un banco</option>
                                <option value="datafast">Por lote Datafast (se liquida después)</option>
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Tarjeta — banco</Label>
                            <select className="input-field" style={selectStyle} value={data.cobro_banco_tarjeta}
                                onChange={e => setData('cobro_banco_tarjeta', e.target.value)}
                                disabled={!puede('editar') || tarjetaEnDatafast}>
                                <option value="">— Seleccionar —</option>
                                {opciones(bancos)}
                            </select>
                        </div>
                    </div>
                    {tarjetaEnDatafast && (
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            Con Datafast, las ventas con tarjeta no se registran al facturar: entran a Bancos cuando
                            liquidas el lote en Bancos → Datafast.
                        </p>
                    )}
                </div>

                {puede('editar') && (
                    <button type="submit" disabled={processing} className="btn-primary flex items-center gap-2">
                        <Save size={15} /> Guardar configuración
                    </button>
                )}

                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="px-5 py-3 border-b" style={{ borderColor: 'var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Caja de cada centro de costo
                        </h3>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                            Cada caja se asigna a un centro de costo al crearla en Bancos → Bancos y Cajas.
                        </p>
                    </div>
                    <table className="w-full text-[11px]">
                        <tbody>
                            {centros.map(c => (
                                <tr key={c.id} className="border-b last:border-0" style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-5 py-2.5" style={{ color: 'var(--text-main)' }}>{c.nombre}</td>
                                    <td className="px-5 py-2.5">
                                        {c.cajas.length > 0 ? (
                                            <span className="inline-flex items-center gap-1.5 text-green-600 dark:text-green-400">
                                                <CheckCircle size={13} /> {c.cajas.join(', ')}
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1.5 text-amber-500">
                                                <AlertTriangle size={13} /> Sin caja
                                                {data.cobro_caja_efectivo ? ' (usa la caja por defecto)' : ' (su efectivo no se registra en Bancos)'}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {centros.length === 0 && (
                                <tr><td className="px-5 py-4" style={{ color: 'var(--text-muted)' }}>No hay centros de costo.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </form>
        </AppLayout>
    )
}
