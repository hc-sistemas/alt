import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, Package, Plane, Anchor, CheckCircle2,
    X, DollarSign,
} from 'lucide-react'
import type { Importacion, Proveedor, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ImportacionRow extends Omit<Importacion, 'proveedor'> {
    proveedor: string | null
}

interface ImportacionStats {
    total: number
    en_transito: number
    en_aduana: number
    liquidadas: number
}

interface Props extends PageProps {
    importaciones: ImportacionRow[]
    proveedores: Pick<Proveedor, 'id' | 'razon_social' | 'pais' | 'divisa'>[]
    stats: ImportacionStats
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    edit:  (msg: string) => toast.success(msg, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── SweetAlert ───────────────────────────────────────────────────────────────

const SWAL_CSS = `
    .swal-pop { border-radius:20px!important; padding:28px!important; box-shadow:0 25px 60px rgba(0,0,0,.25)!important; max-width:460px!important }
    .swal-title { font-size:1.1rem!important; font-weight:700!important; color:#1f2937!important; margin-bottom:16px!important }
    .swal-confirm { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
    .swal-cancel  { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
`
function injectSwalCss() {
    if (document.getElementById('swal-imp')) return
    const s = document.createElement('style'); s.id = 'swal-imp'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

// ─── Estado Badge ─────────────────────────────────────────────────────────────

const ESTADO_CFG: Record<string, { label: string; cls: string; icon: React.ElementType; pulse?: boolean }> = {
    en_transito: { label: 'En Tránsito', cls: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',   icon: Plane,         pulse: true },
    en_aduana:   { label: 'En Aduana',   cls: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300', icon: Anchor },
    liquidada:   { label: 'Liquidada',   cls: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',  icon: CheckCircle2 },
}

function EstadoBadge({ estado }: { estado: string }) {
    const cfg = ESTADO_CFG[estado] ?? { label: estado, cls: 'bg-gray-100 text-gray-800', icon: Package }
    const Icon = cfg.icon
    return (
        <span className={cn('inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold', cfg.cls)}>
            {cfg.pulse
                ? <span className="relative flex h-2 w-2">
                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75" />
                    <span className="relative inline-flex rounded-full h-2 w-2 bg-blue-500" />
                  </span>
                : <Icon className="w-3 h-3" />
            }
            {cfg.label}
        </span>
    )
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div>
                <p className={cn('text-2xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs leading-none" style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
        </div>
    )
}

// ─── Modal Crear ──────────────────────────────────────────────────────────────

function CrearModal({ proveedores, onClose }: {
    proveedores: Props['proveedores']
    onClose: () => void
}) {
    const { data, setData, post, processing, errors } = useForm({
        nombre:        '',
        proveedor_id:  '' as string | number,
        num_invoice:   '',
        agente_aduanero: '',
        pais_embarque: '',
        costo_fob:     '' as string | number,
        divisa:        'USD',
        fecha_partida: '',
        fecha_llegada: '',
        observaciones: '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('compras.importaciones.store'), {
            onSuccess: () => { notify.ok(`Importación "${data.nombre}" creada`); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-lg overflow-y-auto max-h-[90vh]" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Nueva importación</h2>
                    <button className="modal-close" onClick={onClose}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit}>
                <div className="modal-body">
                    <div className="space-y-1.5">
                        <Label>Nombre de la importación <span className="text-red-400">*</span></Label>
                        <Input value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                            error={errors.nombre} placeholder="ej: Importación Q2-2026 China" />
                        {errors.nombre && <p className="text-red-400 text-xs">{errors.nombre}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Proveedor internacional</Label>
                        <select value={data.proveedor_id}
                            onChange={e => setData('proveedor_id', e.target.value)}
                            className="input-field select-field">
                            <option value="">— Sin proveedor asignado —</option>
                            {proveedores.map(p => (
                                <option key={p.id} value={p.id}>{p.razon_social} ({p.pais})</option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>N° Invoice</Label>
                            <Input value={data.num_invoice}
                                onChange={e => setData('num_invoice', e.target.value)}
                                placeholder="INV-2026-001" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Agente aduanero</Label>
                            <Input value={data.agente_aduanero}
                                onChange={e => setData('agente_aduanero', e.target.value)}
                                placeholder="Nombre del agente" />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>País de embarque</Label>
                            <Input value={data.pais_embarque}
                                onChange={e => setData('pais_embarque', e.target.value)}
                                placeholder="China, USA, España..." />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Divisa</Label>
                            <Input value={data.divisa}
                                onChange={e => setData('divisa', e.target.value)}
                                placeholder="USD" />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Costo FOB <span className="text-red-400">*</span></Label>
                        <Input type="number" step="0.01" min={0}
                            value={data.costo_fob}
                            onChange={e => setData('costo_fob', e.target.value)}
                            error={errors.costo_fob}
                            placeholder="0.00" />
                        {errors.costo_fob && <p className="text-red-400 text-xs">{errors.costo_fob}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha de partida</Label>
                            <Input type="date" value={data.fecha_partida}
                                onChange={e => setData('fecha_partida', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Fecha estimada llegada</Label>
                            <Input type="date" value={data.fecha_llegada}
                                onChange={e => setData('fecha_llegada', e.target.value)} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Observaciones</Label>
                        <textarea value={data.observaciones}
                            onChange={e => setData('observaciones', e.target.value)}
                            rows={3}
                            className="input-field textarea-field"
                            placeholder="Notas adicionales..." />
                    </div>

                </div>
                <div className="modal-footer">
                    <Button type="submit" disabled={processing}>
                        <Plus className="w-4 h-4" /> Crear importación
                    </Button>
                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Actualizar Estado ──────────────────────────────────────────────────

function ActualizarModal({ importacion, onClose }: {
    importacion: ImportacionRow
    onClose: () => void
}) {
    const { data, setData, put, processing, errors } = useForm({
        nombre:         importacion.nombre,
        pais_embarque:  importacion.pais_embarque ?? '',
        costo_fob:      importacion.costo_fob,
        divisa:         importacion.divisa,
        agente_aduanero: importacion.agente_aduanero ?? '',
        fecha_partida:  importacion.fecha_partida ?? '',
        fecha_llegada:  importacion.fecha_llegada ?? '',
        estado:         importacion.estado,
        observaciones:  importacion.observaciones ?? '',
    })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        put(route('compras.importaciones.update', importacion.id), {
            onSuccess: () => { notify.edit('Importación actualizada'); onClose() },
            onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-lg overflow-y-auto max-h-[90vh]" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <div>
                        <h2>Actualizar importación</h2>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{importacion.nombre}</p>
                    </div>
                    <button className="modal-close" onClick={onClose}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit}>
                <div className="modal-body">
                    <div className="space-y-1.5">
                        <Label>Estado</Label>
                        <select value={data.estado}
                            onChange={e => setData('estado', e.target.value as ImportacionRow['estado'])}
                            className="input-field select-field">
                            <option value="en_transito">En Tránsito</option>
                            <option value="en_aduana">En Aduana</option>
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha partida</Label>
                            <Input type="date" value={data.fecha_partida}
                                onChange={e => setData('fecha_partida', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Fecha llegada</Label>
                            <Input type="date" value={data.fecha_llegada}
                                onChange={e => setData('fecha_llegada', e.target.value)} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Costo FOB</Label>
                        <Input type="number" step="0.01" min={0}
                            value={data.costo_fob}
                            onChange={e => setData('costo_fob', Number(e.target.value))} />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Observaciones</Label>
                        <textarea value={data.observaciones}
                            onChange={e => setData('observaciones', e.target.value)}
                            rows={3}
                            className="input-field textarea-field"
                            placeholder="Notas..." />
                    </div>

                </div>
                <div className="modal-footer">
                    <Button type="submit" disabled={processing}>
                        <Pencil className="w-4 h-4" /> Guardar cambios
                    </Button>
                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Liquidar ───────────────────────────────────────────────────────────

interface CostoExtra { descripcion: string; monto: string }

function LiquidarModal({ importacion, onClose }: {
    importacion: ImportacionRow
    onClose: () => void
}) {
    const [metodo,          setMetodo]    = useState<'cantidad' | 'precio' | 'peso'>('cantidad')
    const [fechaLiquidacion, setFecha]   = useState(new Date().toISOString().slice(0, 10))
    const [costos,          setCostos]   = useState<CostoExtra[]>([{ descripcion: '', monto: '' }])
    const [processing,      setProcessing] = useState(false)

    const totalCostosExtra = costos.reduce((acc, c) => acc + (parseFloat(c.monto) || 0), 0)
    const costoTotal       = Number(importacion.costo_fob) + totalCostosExtra

    function agregarCosto() {
        setCostos(prev => [...prev, { descripcion: '', monto: '' }])
    }

    function actualizarCosto(idx: number, field: keyof CostoExtra, value: string) {
        setCostos(prev => prev.map((c, i) => i === idx ? { ...c, [field]: value } : c))
    }

    function quitarCosto(idx: number) {
        setCostos(prev => prev.filter((_, i) => i !== idx))
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        const costosValidos = costos.filter(c => c.descripcion && parseFloat(c.monto) > 0)
        setProcessing(true)
        router.patch(route('compras.importaciones.liquidar', importacion.id), {
            metodo_prorrateo:  metodo,
            fecha_liquidacion: fechaLiquidacion,
            costos_extra:      costosValidos,
        }, {
            onSuccess: () => { notify.ok(`Importación "${importacion.nombre}" liquidada`); onClose() },
            onError:   (errs) => { notify.error('Error: ' + Object.values(errs).join(', ')); setProcessing(false) },
            onFinish:  () => setProcessing(false),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-lg overflow-y-auto max-h-[90vh]" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <div>
                        <h2>Liquidar importación</h2>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{importacion.nombre}</p>
                    </div>
                    <button className="modal-close" onClick={onClose}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit}>
                <div className="modal-body">
                    {/* Costos extra dinámicos */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label>Costos extra (flete, seguro, aduana…)</Label>
                            <button type="button" onClick={agregarCosto}
                                className="text-xs font-semibold px-2 py-1 rounded-lg transition-colors"
                                style={{ color: 'var(--primary)', background: 'rgba(245,158,11,0.1)' }}>
                                + Agregar
                            </button>
                        </div>
                        {costos.map((c, idx) => (
                            <div key={idx} className="flex gap-2 items-center">
                                <input
                                    value={c.descripcion}
                                    onChange={e => actualizarCosto(idx, 'descripcion', e.target.value)}
                                    placeholder="Descripción (flete, seguro…)"
                                    className="input-field flex-1 text-xs" />
                                <input
                                    type="number" step="0.01" min={0}
                                    value={c.monto}
                                    onChange={e => actualizarCosto(idx, 'monto', e.target.value)}
                                    placeholder="0.00"
                                    className="input-field text-xs text-right" style={{ width: '6rem' }} />
                                {costos.length > 1 && (
                                    <button type="button" onClick={() => quitarCosto(idx)}
                                        className="p-1 rounded hover:text-red-500 transition-colors"
                                        style={{ color: 'var(--text-muted)' }}>
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Resumen de costos */}
                    <div className="rounded-lg p-3 space-y-1.5"
                        style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}>
                        <div className="flex justify-between text-sm">
                            <span style={{ color: 'var(--text-muted)' }}>Costo FOB</span>
                            <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                                ${Number(importacion.costo_fob).toFixed(2)} {importacion.divisa}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm">
                            <span style={{ color: 'var(--text-muted)' }}>Costos extra ingresados</span>
                            <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                                ${totalCostosExtra.toFixed(2)}
                            </span>
                        </div>
                        <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                            style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                            <span>Costo total</span>
                            <span>${costoTotal.toFixed(2)}</span>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Método de prorrateo <span className="text-red-400">*</span></Label>
                        <select value={metodo} onChange={e => setMetodo(e.target.value as typeof metodo)}
                            className="input-field select-field">
                            <option value="cantidad">Por cantidad (unidades)</option>
                            <option value="precio">Por precio (valor)</option>
                            <option value="peso">Por peso</option>
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Fecha de liquidación <span className="text-red-400">*</span></Label>
                        <Input type="date" value={fechaLiquidacion}
                            onChange={e => setFecha(e.target.value)} />
                    </div>

                </div>
                <div className="modal-footer">
                    <Button type="submit" disabled={processing || totalCostosExtra <= 0}>
                        <CheckCircle2 className="w-4 h-4" /> Liquidar
                    </Button>
                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

type ModalState =
    | { type: 'none' }
    | { type: 'crear' }
    | { type: 'editar'; importacion: ImportacionRow }
    | { type: 'liquidar'; importacion: ImportacionRow }

export default function ImportacionesIndex() {
    const { importaciones, proveedores, stats, flash } = usePage<Props>().props

    const [modal, setModal] = useState<ModalState>({ type: 'none' })

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function cerrar() { setModal({ type: 'none' }) }

    return (
        <AppLayout title="Importaciones" suppressFlash>
            <Head title="Importaciones" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <Package size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Importaciones COMEX
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Seguimiento de importaciones internacionales y liquidación de costos
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <button onClick={() => setModal({ type: 'crear' })}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                        style={{ background: 'var(--primary)' }}>
                        <Plus size={15} /> Nueva Importación
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 px-6 py-4">
                <StatCard label="Total" value={stats.total} icon={Package}
                    cls="bg-slate-500/15 text-slate-600 dark:text-slate-400"
                    valueCls="text-slate-600 dark:text-slate-400" />
                <StatCard label="En tránsito" value={stats.en_transito} icon={Plane}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
                <StatCard label="En aduana" value={stats.en_aduana} icon={Anchor}
                    cls="bg-yellow-500/15 text-yellow-600 dark:text-yellow-400"
                    valueCls="text-yellow-600 dark:text-yellow-400" />
                <StatCard label="Liquidadas" value={stats.liquidadas} icon={CheckCircle2}
                    cls="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueCls="text-green-600 dark:text-green-400" />
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-3 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-3">Nombre</span>
                        <span className="col-span-2">Proveedor</span>
                        <span className="col-span-1">Invoice</span>
                        <span className="col-span-1 text-right">FOB</span>
                        <span className="col-span-1 text-right">Total</span>
                        <span className="col-span-1 text-center">Partida</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-2 text-right">Acciones</span>
                    </div>

                    {importaciones.length === 0 && (
                        <div className="py-20 text-center">
                            <Package className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay importaciones registradas
                            </p>
                        </div>
                    )}

                    {importaciones.map(i => (
                        <div key={i.id}
                            className="group grid grid-cols-12 gap-3 px-4 py-3 border-b items-center text-sm transition-colors"
                            style={{ borderColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <div className="col-span-3 min-w-0">
                                <p className="font-medium truncate" style={{ color: 'var(--text-main)' }}>{i.nombre}</p>
                                {i.pais_embarque && (
                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{i.pais_embarque}</p>
                                )}
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>
                                    {i.proveedor ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1">
                                <p className="font-mono text-xs" style={{ color: 'var(--text-main)' }}>
                                    {i.num_invoice ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                    ${Number(i.costo_fob).toFixed(2)}
                                </p>
                                <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>{i.divisa}</p>
                            </div>
                            <div className="col-span-1 text-right">
                                {Number(i.costo_total) > 0
                                    ? <p className="text-xs font-bold" style={{ color: 'var(--primary)' }}>
                                        ${Number(i.costo_total).toFixed(2)}
                                      </p>
                                    : <p className="text-xs" style={{ color: 'var(--text-muted)' }}>—</p>
                                }
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    {i.fecha_partida ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                <EstadoBadge estado={i.estado} />
                            </div>
                            <div className="col-span-2 flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                {!i.fecha_liquidacion && (
                                    <button
                                        onClick={() => setModal({ type: 'editar', importacion: i })}
                                        title="Actualizar"
                                        className="p-1.5 rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                        <Pencil className="w-3.5 h-3.5" />
                                    </button>
                                )}
                                {i.estado !== 'liquidada' && (
                                    <button
                                        onClick={() => setModal({ type: 'liquidar', importacion: i })}
                                        title="Liquidar importación"
                                        className="p-1.5 rounded transition-colors text-green-600 dark:text-green-400 hover:bg-green-500/20">
                                        <DollarSign className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modal.type === 'crear' && (
                <CrearModal proveedores={proveedores} onClose={cerrar} />
            )}
            {modal.type === 'editar' && (
                <ActualizarModal importacion={modal.importacion} onClose={cerrar} />
            )}
            {modal.type === 'liquidar' && (
                <LiquidarModal importacion={modal.importacion} onClose={cerrar} />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
