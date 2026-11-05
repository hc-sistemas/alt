import { useForm, usePage, Head } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Label } from '@/Components/ui/label'
import { Save, AlertTriangle, CheckCircle } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'

interface CuentaOpt { id: number; nombre: string; tipo: string; cuenta_contable: string | null }
interface UsoDestino { destino: string; forma: string; facturas: number; total: number; desde: string; hasta: string }
interface CentroInfo { id: number; nombre: string; cajas: string[] }

interface Props extends PageProps {
    config: {
        cobro_banco_tarjeta: string | null
        cobro_banco_cheque: string | null
        cobro_tarjeta_modo: string | null
    }
    cuentas: CuentaOpt[]
    centros: CentroInfo[]
    usos: UsoDestino[]
}

const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }

export default function ConfiguracionCobrosIndex() {
    const { config, cuentas, centros, usos } = usePage<Props>().props
    const { puede } = usePermiso('bancos')

    const { data, setData, put, processing } = useForm({
        cobro_banco_tarjeta:       config.cobro_banco_tarjeta ?? '',
        cobro_banco_cheque:        config.cobro_banco_cheque ?? '',
        cobro_tarjeta_modo:        config.cobro_tarjeta_modo ?? '',
    })

    const bancos = cuentas.filter(c => c.tipo === 'banco' || c.tipo === 'tarjeta')
    const tarjetaEnDatafast = data.cobro_tarjeta_modo === 'datafast'

    function submit(e: React.FormEvent) {
        e.preventDefault()
        put(route('bancos.cobros-config.update'))
    }

    // Cuenta contable del banco o caja elegido, visible antes de guardar
    const cuentaDe = (id: string) => cuentas.find(c => String(c.id) === String(id))?.cuenta_contable ?? null
    const CuentaContable = ({ id }: { id: string }) => id ? (
        <p className="text-xs" style={{ color: cuentaDe(id) ? 'var(--text-muted)' : '#D97706' }}>
            Cuenta contable: <strong>{cuentaDe(id) ?? 'sin cuenta contable asignada (Bancos → Bancos y Cajas)'}</strong>
        </p>
    ) : null

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
                    Las transferencias y los cheques <strong>siempre se registran en Bancos</strong>. El banco de cada transferencia se elige
                    en la factura (no hay banco por defecto); aquí solo se define a dónde van los cheques y las tarjetas.
                    Los cambios se aplican <strong>solo a las facturas nuevas</strong>.
                    Las ventas con tarjeta quedan <strong>por liquidar</strong> hasta que se liquida el lote, o entran directo a un banco.
                    Las ventas a crédito nunca entran a Bancos (generan cuenta por cobrar).
                </p>

                <div className="rounded-xl border p-5 space-y-5" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        <strong>Efectivo:</strong> entra a la caja del <strong>mismo centro de costo</strong> de la factura. No hay caja por
                        defecto: si el centro no tiene caja, su efectivo no se registra en Bancos (ver tabla de abajo).
                    </p>

                    <div className="space-y-1.5">
                        <Label>Cheques — banco donde se depositan <span className="text-red-400">*</span></Label>
                        <select className="input-field" style={selectStyle} value={data.cobro_banco_cheque} required
                            onChange={e => setData('cobro_banco_cheque', e.target.value)} disabled={!puede('editar')}>
                            <option value="">— Seleccionar banco —</option>
                            {opciones(bancos)}
                        </select>
                        <CuentaContable id={data.cobro_banco_cheque} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <Label>Tarjeta — ¿cómo se cobra? <span className="text-red-400">*</span></Label>
                            <select className="input-field" style={selectStyle} value={data.cobro_tarjeta_modo} required
                                onChange={e => setData('cobro_tarjeta_modo', e.target.value)} disabled={!puede('editar')}>
                                <option value="">— Seleccionar —</option>
                                <option value="datafast">Por liquidar (se liquida por lote)</option>
                                <option value="banco">Entra directo a un banco</option>
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Tarjeta — banco {!tarjetaEnDatafast && <span className="text-red-400">*</span>}</Label>
                            <select className="input-field" style={selectStyle} value={data.cobro_banco_tarjeta}
                                required={data.cobro_tarjeta_modo === 'banco'}
                                onChange={e => setData('cobro_banco_tarjeta', e.target.value)}
                                disabled={!puede('editar') || tarjetaEnDatafast}>
                                <option value="">— Seleccionar —</option>
                                {opciones(bancos)}
                            </select>
                            <CuentaContable id={data.cobro_banco_tarjeta} />
                        </div>
                    </div>
                    {tarjetaEnDatafast && (
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            Por liquidar: las ventas con tarjeta no mueven el banco al facturar; quedan pendientes y
                            entran a Bancos cuando liquidas el lote en Bancos → Tarjetas.
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
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Facturas registradas por destino</h3>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                            Qué facturas ya entraron a cada banco o caja. Un cambio de configuración no mueve estas facturas.
                        </p>
                    </div>
                    <table className="w-full text-[11px]">
                        <thead>
                            <tr style={{ color: 'var(--text-muted)' }}>
                                <th className="px-5 py-2 text-left">Banco / caja</th><th className="px-3 py-2 text-left">Forma</th>
                                <th className="px-3 py-2 text-right">Facturas</th><th className="px-3 py-2 text-right">Total</th>
                                <th className="px-5 py-2 text-left">Período</th>
                            </tr>
                        </thead>
                        <tbody>
                            {usos.map((u, i) => (
                                <tr key={i} className="border-t" style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                    <td className="px-5 py-2">{u.destino}</td>
                                    <td className="px-3 py-2 capitalize">{u.forma}</td>
                                    <td className="px-3 py-2 text-right">{u.facturas}</td>
                                    <td className="px-3 py-2 text-right">${u.total.toFixed(2)}</td>
                                    <td className="px-5 py-2" style={{ color: 'var(--text-muted)' }}>{u.desde} → {u.hasta}</td>
                                </tr>
                            ))}
                            {usos.length === 0 && (
                                <tr><td colSpan={5} className="px-5 py-4" style={{ color: 'var(--text-muted)' }}>Aún no hay facturas registradas en Bancos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

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
                                                {' (su efectivo no se registra en Bancos)'}
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
