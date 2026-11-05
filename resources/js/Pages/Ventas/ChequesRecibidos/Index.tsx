import { Head, router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Search, Landmark } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import { fechaHoy } from '@/lib/utils'
import type { PageProps, PaginatedData } from '@/types'

interface Fila {
    id: number; numero: string; cliente: string | null; factura: string | null; banco_emisor: string | null
    monto: number; estado: string; fecha_recepcion: string | null; fecha_cheque: string | null
    postfechado: boolean; depositable: boolean; deposito: string | null
}

interface Props extends PageProps {
    cheques: PaginatedData<Fila>
    bancos: { id: number; nombre: string }[]
    bancoSugerido: string | null
    totales: { en_cartera: number; cantidad: number }
    filtros: { estado?: string; buscar?: string }
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })
const ETIQUETA: Record<string, string> = { en_cartera: 'En cartera', depositado: 'Depositado', anulado: 'Anulado' }
const selectStyle = { borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }

function DepositarModal({ cheque, bancos, sugerido, onClose }: { cheque: Fila; bancos: Props['bancos']; sugerido: string | null; onClose: () => void }) {
    const [banco, setBanco] = useState(sugerido && bancos.some(b => String(b.id) === String(sugerido)) ? String(sugerido) : '')
    const [fecha, setFecha] = useState(fechaHoy())
    const [ref, setRef] = useState('')
    const [guardando, setGuardando] = useState(false)

    function enviar(e: React.FormEvent) {
        e.preventDefault()
        setGuardando(true)
        router.patch(route('ventas.cheques-recibidos.depositar', cheque.id),
            { banco_caja_id: banco, fecha_deposito: fecha, referencia: ref },
            { preserveScroll: true, onSuccess: () => onClose(), onFinish: () => setGuardando(false) })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header"><h2>Depositar cheque N° {cheque.numero}</h2></div>
                <form onSubmit={enviar}>
                    <div className="modal-body" style={{ gap: '1rem' }}>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {cheque.cliente} · {fmt(cheque.monto)} · cheque del {cheque.fecha_cheque}
                        </p>
                        <div className="space-y-1.5">
                            <Label>Banco donde se deposita <span className="text-red-400">*</span></Label>
                            <select className="input-field" style={selectStyle} value={banco} onChange={e => setBanco(e.target.value)} required>
                                <option value="">— Seleccionar banco —</option>
                                {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                            </select>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label>Fecha de depósito <span className="text-red-400">*</span></Label>
                                <Input type="date" value={fecha} onChange={e => setFecha(e.target.value)} required />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Referencia (papeleta) <span className="text-red-400">*</span></Label>
                                <Input value={ref} onChange={e => setRef(e.target.value)} required placeholder="N° de papeleta" />
                            </div>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <Button type="submit" disabled={guardando}><Landmark className="w-4 h-4" /> Depositar</Button>
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </div>
    )
}

export default function ChequesRecibidosIndex() {
    const { cheques, bancos, bancoSugerido, totales, filtros } = usePage<Props>().props
    const { puede } = usePermiso('ventas')
    const [filtro, setFiltro] = useState({ estado: filtros.estado ?? '', buscar: filtros.buscar ?? '' })
    const [depositar, setDepositar] = useState<Fila | null>(null)

    function buscar(e: React.FormEvent) {
        e.preventDefault()
        router.get(route('ventas.cheques-recibidos.index'), filtro, { preserveState: true, replace: true })
    }

    return (
        <AppLayout title="Cheques recibidos">
            <Head title="Cheques recibidos" />
            <PageHeader title="Cheques recibidos" breadcrumbs={[{ label: 'Ventas' }, { label: 'Cheques recibidos' }]} />

            <div className="px-6 pt-6 pb-10 space-y-5 max-w-6xl">
                <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                    Los cheques de clientes quedan <strong>en cartera</strong> y no son movimiento bancario hasta que se depositan. Un cheque post fechado
                    no se puede depositar antes de su fecha.
                </p>

                <div className="rounded-xl border px-4 py-3 text-sm flex justify-between" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)', color: 'var(--text-main)' }}>
                    <span>En cartera: <strong>{totales.cantidad}</strong> cheque(s)</span>
                    <span className="font-mono font-semibold">{fmt(totales.en_cartera)}</span>
                </div>

                <form onSubmit={buscar} className="flex flex-wrap items-end gap-3">
                    <div className="space-y-1.5">
                        <Label>Estado</Label>
                        <select className="input-field" style={selectStyle} value={filtro.estado} onChange={e => setFiltro(f => ({ ...f, estado: e.target.value }))}>
                            <option value="">Todos</option>
                            {Object.entries(ETIQUETA).map(([v, n]) => <option key={v} value={v}>{n}</option>)}
                        </select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Buscar</Label>
                        <Input value={filtro.buscar} onChange={e => setFiltro(f => ({ ...f, buscar: e.target.value }))} placeholder="N° de cheque o cliente" />
                    </div>
                    <Button type="submit"><Search className="w-4 h-4" /> Buscar</Button>
                </form>

                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ color: 'var(--text-muted)' }}>
                                <th className="px-5 py-2 text-left">N° cheque</th><th className="px-3 py-2 text-left">Cliente</th>
                                <th className="px-3 py-2 text-left">Factura</th><th className="px-3 py-2 text-left">Recibido</th>
                                <th className="px-3 py-2 text-left">Fecha del cheque</th><th className="px-3 py-2 text-right">Monto</th>
                                <th className="px-3 py-2 text-left">Estado</th><th className="px-5 py-2 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cheques.data.map(c => (
                                <tr key={c.id} className="border-t" style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                    <td className="px-5 py-2 font-mono">{c.numero}</td>
                                    <td className="px-3 py-2">{c.cliente}</td>
                                    <td className="px-3 py-2">{c.factura ?? '—'}</td>
                                    <td className="px-3 py-2">{c.fecha_recepcion}</td>
                                    <td className="px-3 py-2">{c.fecha_cheque}{c.postfechado && <span className="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-800">Post fechado</span>}</td>
                                    <td className="px-3 py-2 text-right font-mono">{fmt(c.monto)}</td>
                                    <td className="px-3 py-2">{ETIQUETA[c.estado] ?? c.estado}{c.deposito && <span style={{ color: 'var(--text-muted)' }}> · {c.deposito}</span>}</td>
                                    <td className="px-5 py-2 text-right">
                                        {c.estado === 'en_cartera' && puede('editar') && (
                                            <Button type="button" disabled={!c.depositable} title={c.depositable ? 'Depositar en un banco' : 'Post fechado: aún no se puede depositar'}
                                                onClick={() => setDepositar(c)}><Landmark className="w-3.5 h-3.5" /> Depositar</Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {cheques.data.length === 0 && (
                                <tr><td colSpan={8} className="px-5 py-4" style={{ color: 'var(--text-muted)' }}>No hay cheques recibidos.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {depositar && <DepositarModal cheque={depositar} bancos={bancos} sugerido={bancoSugerido} onClose={() => setDepositar(null)} />}
        </AppLayout>
    )
}
