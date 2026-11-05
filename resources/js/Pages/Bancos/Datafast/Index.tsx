import { useState, useEffect, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn, fechaHoy } from '@/lib/utils'
import { Plus, X, CreditCard, CheckCircle, Search, Settings2, AlertTriangle } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { BancoCaja, DatafastLote, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface LoteRow {
    id: number; numero_lote: string; fecha: string; banco: string | null
    banco_caja_id: number; total_vouchers: number; estado: 'pendiente' | 'liquidado'
    procesador: string | null; procesador_id: number | null; tipo_tarjeta: string | null
    dias: number; atrasado: boolean
    liquidacion: null | {
        fecha_deposito: string; valor_bruto: number
        comision_datafast: number; iva_comision: number; valor_neto: number
        diferencia: number; requiere_revision: boolean
    }
}

interface VentaPendiente {
    id: number; valor: number; procesador_id: number | null; tipo_tarjeta: string | null
    lote_venta: string | null; autorizacion: string | null; factura: string | null; fecha: string; cliente: string | null
}

interface Procesador {
    id: number; nombre: string; banco_destino_id: number | null
    comision_credito: string; comision_debito: string; comision_diferido: string
    retencion_iva_pct: string; retencion_ir_pct: string; iva_comision_pct: string; plazo_dias: number; estado: boolean
}

const TIPOS_TARJETA = [
    { v: 'credito', n: 'Crédito' }, { v: 'debito', n: 'Débito' }, { v: 'diferido', n: 'Diferido' },
]

interface Filtros {
    banco_caja_id?: string
    procesador_id?: string
    estado?:        string
    fecha_desde?:   string
    fecha_hasta?:   string
    buscar?:        string
}

interface Props extends PageProps {
    pendientes: VentaPendiente[]
    lotes:   LoteRow[] | null
    bancos:  Pick<BancoCaja, 'id' | 'nombre' | 'tipo'>[]
    procesadores: Procesador[]
    puedeLiquidar: boolean
    filtros: Filtros
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── Modal Nuevo Lote ─────────────────────────────────────────────────────────

function LoteModal({ bancos, procesadores, pendientes, onClose }: {
    bancos: Props['bancos']; procesadores: Procesador[]; pendientes: VentaPendiente[]; onClose: () => void
}) {
    const terminales = bancos.filter(b => b.tipo === 'tarjeta')
    const { data, setData, post, processing, errors } = useForm({
        banco_caja_id: '',
        procesador_id: '',
        tipo_tarjeta: '',
        numero_lote: '',
        fecha: new Date().toISOString().split('T')[0],
        bruto_reporte: '',
        pagos: [] as number[],
    })

    // Solo se ofrecen las ventas del procesador y tipo de tarjeta elegidos (si se eligieron)
    const visibles = pendientes.filter(v =>
        (!data.procesador_id || v.procesador_id === null || String(v.procesador_id) === data.procesador_id) &&
        (!data.tipo_tarjeta || v.tipo_tarjeta === null || v.tipo_tarjeta === data.tipo_tarjeta))
    const suma = Math.round(pendientes.filter(v => data.pagos.includes(v.id)).reduce((a, v) => a + v.valor, 0) * 100) / 100
    const bruto = Number(data.bruto_reporte || 0)
    const cuadra = data.pagos.length > 0 && Math.abs(suma - bruto) <= 0.01

    function alternar(id: number) {
        setData('pagos', data.pagos.includes(id) ? data.pagos.filter(x => x !== id) : [...data.pagos, id])
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('bancos.datafast.lote'), {
            onSuccess: () => onClose(),
            onError: (errs) => notify.error(Object.values(errs).join(' | ')),
        })
    }
    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-2xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Nuevo lote de tarjetas</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <div className="space-y-1.5">
                        <Label>Terminal <span className="text-red-400">*</span></Label>
                        <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                            className="input-field select-field"
                            disabled={terminales.length === 0}>
                            <option value="">— Seleccionar —</option>
                            {terminales.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                        </select>
                        {terminales.length === 0 && (
                            <p className="text-xs mt-1" style={{ color: '#ef4444' }}>
                                No hay terminales de tarjeta. Crea uno en <strong>Bancos → Cuentas</strong> con tipo "Tarjeta".
                            </p>
                        )}
                        {errors.banco_caja_id && <p className="text-red-400 text-xs">{errors.banco_caja_id}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Procesador</Label>
                            <select value={data.procesador_id} onChange={e => setData('procesador_id', e.target.value)}
                                className="input-field select-field">
                                <option value="">— Ninguno —</option>
                                {procesadores.filter(p => p.estado).map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Tipo de tarjeta</Label>
                            <select value={data.tipo_tarjeta} onChange={e => setData('tipo_tarjeta', e.target.value)}
                                className="input-field select-field">
                                <option value="">— Seleccionar —</option>
                                {TIPOS_TARJETA.map(t => <option key={t.v} value={t.v}>{t.n}</option>)}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div className="space-y-1.5">
                            <Label>N° Lote <span className="text-red-400">*</span></Label>
                            <Input value={data.numero_lote} onChange={e => setData('numero_lote', e.target.value)}
                                error={errors.numero_lote} placeholder="LOT-20260601" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Fecha <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha} onChange={e => setData('fecha', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Bruto según el reporte <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min="0.01" value={data.bruto_reporte}
                                onChange={e => setData('bruto_reporte', e.target.value)}
                                error={errors.bruto_reporte} placeholder="0.00" />
                        </div>
                    </div>

                    {/* Ventas con tarjeta que el procesador incluyó en el depósito (02-07 §2.2) */}
                    <div className="space-y-1.5">
                        <Label>Ventas incluidas en el lote <span className="text-red-400">*</span></Label>
                        <div className="border rounded-xl overflow-auto" style={{ borderColor: 'var(--border)', maxHeight: 220 }}>
                            {visibles.length === 0 && (
                                <p className="text-xs px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                                    No hay ventas con tarjeta pendientes de lote{(data.procesador_id || data.tipo_tarjeta) ? ' para este procesador / tipo de tarjeta' : ''}.
                                </p>
                            )}
                            {visibles.map(v => (
                                <label key={v.id} className="flex items-center gap-3 px-3 py-1.5 border-b last:border-b-0 text-xs cursor-pointer"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                    <input type="checkbox" checked={data.pagos.includes(v.id)} onChange={() => alternar(v.id)} />
                                    <span className="font-mono w-20">{v.fecha}</span>
                                    <span className="w-36 truncate">{v.factura ?? '—'}</span>
                                    <span className="flex-1 truncate" style={{ color: 'var(--text-muted)' }}>
                                        {v.cliente ?? ''}{v.autorizacion ? ` · aut. ${v.autorizacion}` : ''}{v.lote_venta ? ` · lote ${v.lote_venta}` : ''}
                                    </span>
                                    <span className="font-mono font-semibold">{fmt(v.valor)}</span>
                                </label>
                            ))}
                        </div>
                        {errors.pagos && <p className="text-red-400 text-xs">{errors.pagos}</p>}
                    </div>

                    <div className="flex justify-between items-center rounded-xl px-4 py-2.5 text-sm"
                        style={{
                            background: cuadra ? 'rgba(16,185,129,0.08)' : data.pagos.length ? 'rgba(239,68,68,0.06)' : 'rgba(245,158,11,0.08)',
                            border: `1px solid ${cuadra ? 'rgba(16,185,129,0.25)' : data.pagos.length ? 'rgba(239,68,68,0.3)' : 'var(--border)'}`,
                        }}>
                        <span style={{ color: 'var(--text-main)' }}>
                            {data.pagos.length} venta(s) seleccionada(s): <strong className="font-mono">{fmt(suma)}</strong>
                        </span>
                        <span className={cn('font-mono font-semibold', cuadra ? 'text-green-600 dark:text-green-400' : data.pagos.length ? 'text-red-600 dark:text-red-400' : '')}>
                            {data.pagos.length === 0 ? 'Seleccione las ventas'
                                : cuadra ? 'Cuadra con el reporte' : `Diferencia ${fmt(Math.round((suma - bruto) * 100) / 100)}`}
                        </span>
                    </div>
                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing || !cuadra} className="btn-primary">
                        <Plus className="w-4 h-4" /> Registrar Lote
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Liquidar ───────────────────────────────────────────────────────────
// Valores del reporte del procesador: el reporte siempre manda; los porcentajes solo pre-llenan.

function LiquidarModal({ lote, bancos, procesadores, onClose }: {
    lote: LoteRow; bancos: Props['bancos']; procesadores: Procesador[]; onClose: () => void
}) {
    const bancosDestino = bancos.filter(b => b.tipo === 'banco')
    const proc = procesadores.find(p => p.id === lote.procesador_id)
    const bruto = Number(lote.total_vouchers)

    const { data, setData, patch, processing, errors } = useForm({
        fecha_deposito:    new Date().toISOString().split('T')[0],
        comision_datafast: '',
        iva_comision:      '',
        retencion_iva:     '',
        retencion_ir:      '',
        intereses_diferido: '',
        valor_neto:        '',
        banco_destino_id:  proc?.banco_destino_id ? String(proc.banco_destino_id) : '',
        observacion:       '',
    })

    const n = (v: string) => Number(v || 0)
    const esperado = Math.round((bruto - n(data.comision_datafast) - n(data.iva_comision) - n(data.retencion_iva) - n(data.retencion_ir) - n(data.intereses_diferido)) * 100) / 100
    const diferencia = data.valor_neto === '' ? 0 : Math.round((n(data.valor_neto) - esperado) * 100) / 100
    const hayDif = Math.abs(diferencia) > 0.01

    // Pre-llenado con los porcentajes del procesador (comisión + IVA de la comisión + retenciones); el reporte siempre manda
    function calcularConPorcentajes() {
        if (!proc) return
        const pctCom = Number(lote.tipo_tarjeta === 'debito' ? proc.comision_debito
            : lote.tipo_tarjeta === 'diferido' ? proc.comision_diferido : proc.comision_credito)
        const com = Math.round(bruto * pctCom) / 100
        const iva = Math.round(com * Number(proc.iva_comision_pct ?? 0)) / 100
        const rIva = Math.round(bruto * Number(proc.retencion_iva_pct)) / 100
        const rIr = Math.round(bruto * Number(proc.retencion_ir_pct)) / 100
        setData(d => ({
            ...d,
            comision_datafast: com.toFixed(2), iva_comision: iva.toFixed(2),
            retencion_iva: rIva.toFixed(2), retencion_ir: rIr.toFixed(2),
            valor_neto: (bruto - com - iva - rIva - rIr).toFixed(2),
        }))
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (hayDif && data.observacion.trim().length < 5) {
            notify.error('El neto no coincide con el esperado: escriba la observación.')
            return
        }
        patch(route('bancos.datafast.liquidar', lote.id), {
            onSuccess: () => onClose(),
            onError: (errs) => notify.error(Object.values(errs).join(' | ')),
        })
    }

    const campo = (label: string, key: 'comision_datafast' | 'iva_comision' | 'retencion_iva' | 'retencion_ir' | 'intereses_diferido', req = false) => (
        <div className="space-y-1.5">
            <Label>{label} {req && <span className="text-red-400">*</span>}</Label>
            <Input type="number" step="0.01" min="0" value={data[key]}
                onChange={e => setData(key, e.target.value)} placeholder="0.00" />
        </div>
    )

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-lg" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Liquidar lote {lote.numero_lote}</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <div className="flex justify-between items-center rounded-xl px-4 py-2.5"
                        style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid var(--border)' }}>
                        <span className="text-sm" style={{ color: 'var(--text-main)' }}>
                            Bruto del lote {lote.procesador && <span className="text-xs" style={{ color: 'var(--text-muted)' }}>· {lote.procesador}</span>}
                        </span>
                        <span className="text-lg font-bold font-mono" style={{ color: 'var(--text-main)' }}>{fmt(bruto)}</span>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha depósito <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha_deposito} onChange={e => setData('fecha_deposito', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Banco destino <span className="text-red-400">*</span></Label>
                            <select value={data.banco_destino_id} onChange={e => setData('banco_destino_id', e.target.value)}
                                className="input-field select-field">
                                <option value="">— Seleccionar banco —</option>
                                {bancosDestino.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                            </select>
                            {errors.banco_destino_id && <p className="text-red-400 text-xs">{errors.banco_destino_id}</p>}
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Valores del reporte del procesador</p>
                        {proc && (
                            <button type="button" onClick={calcularConPorcentajes}
                                className="text-xs underline hover:opacity-70" style={{ color: 'var(--primary)' }}>
                                Pre-llenar con los porcentajes de {proc.nombre}
                            </button>
                        )}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {campo('Comisión (sin IVA)', 'comision_datafast', true)}
                        {campo('IVA de la comisión', 'iva_comision')}
                        {campo('Retención IVA', 'retencion_iva')}
                        {campo('Retención IR', 'retencion_ir')}
                        {lote.tipo_tarjeta === 'diferido' && campo('Intereses del diferido', 'intereses_diferido')}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Neto depositado (según el reporte) <span className="text-red-400">*</span></Label>
                        <Input type="number" step="0.01" min="0" value={data.valor_neto}
                            onChange={e => setData('valor_neto', e.target.value)} placeholder="0.00" />
                        {errors.valor_neto && <p className="text-red-400 text-xs">{errors.valor_neto}</p>}
                    </div>

                    <div className="rounded-xl p-4 space-y-1"
                        style={{
                            background: hayDif ? 'rgba(239,68,68,0.06)' : 'rgba(16,185,129,0.08)',
                            border: `1px solid ${hayDif ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.25)'}`,
                        }}>
                        <div className="flex justify-between text-sm">
                            <span style={{ color: 'var(--text-muted)' }}>Neto esperado (bruto − comisión − IVA − retenciones)</span>
                            <span className="font-mono font-medium" style={{ color: 'var(--text-main)' }}>{fmt(esperado)}</span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span className="font-semibold" style={{ color: 'var(--text-main)' }}>Diferencia</span>
                            <span className={cn('font-mono font-bold', hayDif ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                                {fmt(diferencia)}
                            </span>
                        </div>
                        {hayDif && (
                            <p className="text-xs text-red-500 flex items-center gap-1">
                                <AlertTriangle className="w-3.5 h-3.5" />
                                {diferencia > 0 ? 'Sobrante' : 'Faltante'}: va a 6.01.14 y queda marcado para revisión.
                            </p>
                        )}
                    </div>

                    {hayDif && (
                        <div className="space-y-1.5">
                            <Label>Observación <span className="text-red-400">* obligatoria</span></Label>
                            <textarea value={data.observacion} onChange={e => setData('observacion', e.target.value)}
                                rows={2} placeholder="Explique la diferencia…" className="input-field textarea-field" />
                            {errors.observacion && <p className="text-red-400 text-xs">{errors.observacion}</p>}
                        </div>
                    )}
                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing} className="btn-primary">
                        <CheckCircle className="w-4 h-4" /> Liquidar
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Procesadores (configuración, 02-07 §2.3) ───────────────────────────

const PROC_VACIO = {
    nombre: '', banco_destino_id: '', comision_credito: '', comision_debito: '', comision_diferido: '',
    retencion_iva_pct: '', retencion_ir_pct: '', iva_comision_pct: '15', plazo_dias: '3', estado: true,
}

function ProcesadoresModal({ procesadores, bancos, puedeEditar, onClose }: {
    procesadores: Procesador[]; bancos: Props['bancos']; puedeEditar: boolean; onClose: () => void
}) {
    const [editId, setEditId] = useState<number | null>(null)
    const { data, setData, post, put, processing, reset, errors } = useForm({ ...PROC_VACIO })
    const bancosDestino = bancos.filter(b => b.tipo === 'banco')

    function editar(p: Procesador) {
        setEditId(p.id)
        setData({
            nombre: p.nombre, banco_destino_id: p.banco_destino_id ? String(p.banco_destino_id) : '',
            comision_credito: p.comision_credito, comision_debito: p.comision_debito, comision_diferido: p.comision_diferido,
            retencion_iva_pct: p.retencion_iva_pct, retencion_ir_pct: p.retencion_ir_pct, iva_comision_pct: String(p.iva_comision_pct ?? '15'),
            plazo_dias: String(p.plazo_dias), estado: p.estado,
        })
    }
    function nuevo() { setEditId(null); reset() }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        const opts = { onSuccess: () => nuevo(), onError: (errs: Record<string, string>) => notify.error(Object.values(errs).join(' | ')) }
        editId ? put(route('bancos.datafast.procesador.update', editId), opts) : post(route('bancos.datafast.procesador.store'), opts)
    }

    const num = (label: string, key: 'comision_credito' | 'comision_debito' | 'comision_diferido' | 'retencion_iva_pct' | 'retencion_ir_pct' | 'iva_comision_pct' | 'plazo_dias', step = '0.001') => (
        <div className="space-y-1">
            <Label>{label}</Label>
            <Input type="number" step={step} min="0" value={data[key]} onChange={e => setData(key, e.target.value)} placeholder="0" />
        </div>
    )

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-2xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Procesadores de tarjeta</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        Los porcentajes solo pre-llenan la liquidación: el reporte real del procesador siempre manda.
                        El plazo (días entre venta y depósito) sirve para marcar lotes atrasados.
                    </p>

                    <div className="border rounded-xl overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                        {procesadores.length === 0 && (
                            <p className="text-xs px-4 py-3" style={{ color: 'var(--text-muted)' }}>Aún no hay procesadores registrados.</p>
                        )}
                        {procesadores.map(p => (
                            <div key={p.id} className="flex items-center justify-between px-4 py-2 border-b last:border-b-0 text-xs"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                <span className="font-semibold">{p.nombre} {!p.estado && <em className="font-normal" style={{ color: 'var(--text-muted)' }}>(inactivo)</em>}</span>
                                <span style={{ color: 'var(--text-muted)' }}>
                                    Com. {Number(p.comision_credito)}% / {Number(p.comision_debito)}% / {Number(p.comision_diferido)}% · plazo {p.plazo_dias} d
                                </span>
                                {puedeEditar && <button onClick={() => editar(p)} className="underline" style={{ color: 'var(--primary)' }}>Editar</button>}
                            </div>
                        ))}
                    </div>

                    {puedeEditar && (
                        <form onSubmit={submit} className="space-y-3 border rounded-xl p-4" style={{ borderColor: 'var(--border)' }}>
                            <p className="text-xs font-semibold" style={{ color: 'var(--text-main)' }}>{editId ? 'Editar procesador' : 'Nuevo procesador'}</p>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <Label>Nombre <span className="text-red-400">*</span></Label>
                                    <Input value={data.nombre} onChange={e => setData('nombre', e.target.value)} error={errors.nombre} placeholder="Datafast, Deuna…" />
                                </div>
                                <div className="space-y-1">
                                    <Label>Banco de destino</Label>
                                    <select value={data.banco_destino_id} onChange={e => setData('banco_destino_id', e.target.value)} className="input-field select-field">
                                        <option value="">— Seleccionar —</option>
                                        {bancosDestino.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                {num('Comisión crédito %', 'comision_credito')}
                                {num('Comisión débito %', 'comision_debito')}
                                {num('Comisión diferido %', 'comision_diferido')}
                                {num('Retención IVA %', 'retencion_iva_pct')}
                                {num('Retención IR %', 'retencion_ir_pct')}
                                {num('IVA de la comisión %', 'iva_comision_pct')}
                                {num('Plazo (días)', 'plazo_dias', '1')}
                            </div>
                            {editId && (
                                <label className="flex items-center gap-2 text-xs" style={{ color: 'var(--text-main)' }}>
                                    <input type="checkbox" checked={data.estado} onChange={e => setData('estado', e.target.checked)} /> Activo
                                </label>
                            )}
                            <div className="flex gap-2">
                                <button type="submit" disabled={processing} className="btn-primary">
                                    <CheckCircle className="w-4 h-4" /> {editId ? 'Guardar cambios' : 'Agregar procesador'}
                                </button>
                                {editId && <button type="button" onClick={nuevo} className="btn-secondary">Nuevo</button>}
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function DatafastIndex() {
    const { lotes, bancos, procesadores, pendientes, puedeLiquidar, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('bancos')
    const [showLote, setShowLote] = useState(false)
    const [showProc, setShowProc] = useState(false)
    const [liquidarLote, setLiquidarLote] = useState<LoteRow | null>(null)

    const [filtro, setFiltro] = useState({
        ...filtros,
        fecha_desde: filtros.fecha_desde ?? (lotes !== null ? '' : fechaHoy()),
        fecha_hasta: filtros.fecha_hasta ?? (lotes !== null ? '' : fechaHoy()),
    })

    // Cambiar cualquier filtro después de haber buscado no vacía la tabla —
    // solo la atenúa (opacity-60) hasta que se presione Buscar de nuevo.
    // Mismo patrón ya usado en Movimientos Bancarios/Anticipos/Devoluciones/
    // Importaciones/Cajas.
    const [filtrosSucios, setFiltrosSucios] = useState(false)

    // Carga bajo demanda: `lotes` viene null hasta la primera búsqueda.
    const haBuscado = lotes !== null

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    function cambiarFiltro<K extends keyof typeof filtro>(campo: K, valor: string) {
        setFiltro(f => ({ ...f, [campo]: valor }))
        setFiltrosSucios(true)
    }

    function buscar() {
        router.get(route('bancos.datafast.index'), { ...filtro, buscado: '1' } as any, {
            preserveState: true,
            replace: true,
            onSuccess: () => setFiltrosSucios(false),
        })
    }

    return (
        <AppLayout title="Tarjetas" suppressFlash>
            <Head title="Tarjetas" />

            <PageHeader
                title="Tarjetas"
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Tarjetas' }]}
                actions={
                    <div className="flex items-center gap-2 flex-wrap">
                        <button onClick={() => setShowProc(true)}
                            className="btn-secondary flex items-center gap-2 whitespace-nowrap shrink-0">
                            <Settings2 size={15} /> Procesadores
                        </button>
                        {puede('crear') && (
                            <button onClick={() => setShowLote(true)}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-black whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5 shrink-0"
                                style={{ background: 'var(--primary)' }}>
                                <Plus size={15} /> Nuevo Lote
                            </button>
                        )}
                    </div>
                }
            />

            <div className="px-6 pt-6 mb-2">
                <FilterToolbar
                    search={{
                        value: filtro.buscar ?? '',
                        onChange: v => cambiarFiltro('buscar', v),
                        onSearch: buscar,
                        placeholder: 'N° lote...',
                    }}
                    searchWidth="w-[140px]"
                >
                    <select value={filtro.banco_caja_id ?? ''} onChange={e => cambiarFiltro('banco_caja_id', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '150px' }}>
                        <option value="">Terminal</option>
                        {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <select value={filtro.procesador_id ?? ''} onChange={e => cambiarFiltro('procesador_id', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '140px' }}>
                        <option value="">Procesador</option>
                        {procesadores.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                    </select>

                    <select value={filtro.estado ?? ''} onChange={e => cambiarFiltro('estado', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '120px' }}>
                        <option value="">Estado</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="liquidado">Liquidado</option>
                    </select>

                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Desde</label>
                        <input type="date" value={filtro.fecha_desde ?? ''}
                            onChange={e => cambiarFiltro('fecha_desde', e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '140px' }} />
                    </div>
                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                        <input type="date" value={filtro.fecha_hasta ?? ''}
                            onChange={e => cambiarFiltro('fecha_hasta', e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '140px' }} />
                    </div>
                </FilterToolbar>
            </div>

            {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
            {!haBuscado && (
                <div className="px-6 pb-8">
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar los lotes de tarjetas.
                        </p>
                    </div>
                </div>
            )}

            {/* Tabla */}
            {haBuscado && lotes && (
            <div className={cn('px-6 pb-8', filtrosSucios && 'opacity-60 transition-opacity')}>
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-2">N° Lote</span>
                        <span className="col-span-1">Fecha</span>
                        <span className="col-span-2">Procesador / terminal</span>
                        <span className="col-span-2 text-right">Total Vouchers</span>
                        <span className="col-span-2">Liquidación</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-2 text-right">Acción</span>
                    </div>

                    {lotes.length === 0 && (
                        <div className="py-20 text-center">
                            <CreditCard className="w-12 h-12 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron lotes con estos filtros</p>
                        </div>
                    )}

                    {lotes.map(l => (
                        <div key={l.id}
                            className="group grid grid-cols-12 gap-2 px-4 py-3 border-b items-center text-sm transition-colors"
                            style={{ borderColor: 'var(--border)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                            <div className="col-span-2">
                                <p className="font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>{l.numero_lote}</p>
                            </div>
                            <div className="col-span-1">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{l.fecha}</p>
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-main)' }}>{l.procesador ?? l.banco ?? '—'}</p>
                                <p className="text-[10px] truncate" style={{ color: 'var(--text-muted)' }}>
                                    {l.tipo_tarjeta ? TIPOS_TARJETA.find(t => t.v === l.tipo_tarjeta)?.n : ''}{l.procesador && l.banco ? ` · ${l.banco}` : ''}
                                </p>
                            </div>
                            <div className="col-span-2 text-right">
                                <p className="text-sm font-bold" style={{ color: 'var(--text-main)' }}>{fmt(l.total_vouchers)}</p>
                            </div>
                            <div className="col-span-2 min-w-0">
                                {l.liquidacion ? (
                                    <div>
                                        <p className="text-xs text-green-600 dark:text-green-400 font-medium">{fmt(l.liquidacion.valor_neto)}</p>
                                        <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>
                                            Dep. {l.liquidacion.fecha_deposito} · Com. {fmt(l.liquidacion.comision_datafast)}
                                        </p>
                                        {l.liquidacion.requiere_revision && (
                                            <p className="text-[10px] font-semibold text-red-500">
                                                Revisar: diferencia {fmt(l.liquidacion.diferencia)}
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <span className="text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                )}
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {l.estado === 'pendiente'
                                    ? <span className={cn('px-1.5 py-0.5 rounded-full text-[10px] font-semibold',
                                            l.atrasado ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                                       : 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400')}
                                            title={`${l.dias} día(s) sin liquidar`}>
                                            {l.atrasado ? `Atrasado ${l.dias} d` : `Pendiente ${l.dias} d`}
                                        </span>
                                    : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Liquidado</span>
                                }
                            </div>
                            <div className="col-span-2 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                {l.estado === 'pendiente' && puede('editar') && puedeLiquidar && (
                                    <button onClick={() => setLiquidarLote(l)}
                                        className="flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium text-black"
                                        style={{ background: 'var(--primary)' }}>
                                        <CheckCircle className="w-3.5 h-3.5" /> Liquidar
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            )}

            {showLote && <LoteModal bancos={bancos} procesadores={procesadores} pendientes={pendientes} onClose={() => setShowLote(false)} />}
            {liquidarLote && <LiquidarModal lote={liquidarLote} bancos={bancos} procesadores={procesadores} onClose={() => setLiquidarLote(null)} />}
            {showProc && <ProcesadoresModal procesadores={procesadores} bancos={bancos} puedeEditar={puede('editar')} onClose={() => setShowProc(false)} />}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
