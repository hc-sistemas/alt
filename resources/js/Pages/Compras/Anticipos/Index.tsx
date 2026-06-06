import { useState, useEffect } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Button } from '@/Components/ui/button'
import { cn } from '@/lib/utils'
import {
    CreditCard, Plus, Search, CheckCircle,
    Clock, AlertTriangle, DollarSign, X, ArrowLeftRight
} from 'lucide-react'
import type { PageProps, Proveedor, BancoCaja } from '@/types'
import { notify, formatMoney, swalBase, injectSwalStyles } from '@/utils/contabilidad'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Anticipo {
    id: number
    proveedor_id: number
    proveedor: string | null
    importacion: string | null
    importacion_id: number | null
    fecha: string
    monto: number
    saldo: number
    num_transferencia: string | null
    estado: 'pendiente' | 'cruzado'
    asiento_id: number | null
}

interface ImportacionRow {
    id: number
    nombre: string
    estado: string
    costo_fob: number
}

interface Props extends PageProps {
    anticipos:     Anticipo[]
    proveedores:   Pick<Proveedor, 'id' | 'razon_social' | 'tipo'>[]
    importaciones: ImportacionRow[]
    bancos:        Pick<BancoCaja, 'id' | 'nombre' | 'tipo' | 'saldo_actual'>[]
    filtros:       Record<string, string>
    stats: {
        total:       number
        pendientes:  number
        cruzados:    number
        monto_total: number
    }
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, color, icon: Icon }: {
    label: string; value: string | number; color: string; icon: React.ElementType
}) {
    return (
        <div className="rounded-2xl p-4 border"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className="flex items-center gap-2 mb-2">
                <Icon className="w-4 h-4" style={{ color }} />
                <p className="text-xs font-semibold uppercase tracking-wider"
                    style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
            <p className="text-2xl font-bold" style={{ color }}>{value}</p>
        </div>
    )
}

// ─── Modal Nuevo Anticipo ─────────────────────────────────────────────────────

function ModalNuevo({ proveedores, importaciones, bancos, onClose }: {
    proveedores:   Props['proveedores']
    importaciones: Props['importaciones']
    bancos:        Props['bancos']
    onClose:       () => void
}) {
    const [form, setForm] = useState({
        proveedor_id:     '',
        importacion_id:   '',
        fecha:            new Date().toISOString().slice(0, 10),
        monto:            '',
        banco_id:         bancos[0]?.id?.toString() ?? '',
        num_transferencia:'',
    })
    const [processing, setProcessing] = useState(false)

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }
    const bancoBanco = bancos.find(b => b.id.toString() === form.banco_id)

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setProcessing(true)
        router.post(route('compras.anticipos.store'), form, {
            onSuccess: () => { notify.success('Anticipo registrado correctamente'); onClose() },
            onError:   (errs) => { notify.error(Object.values(errs).flat().join(' | ')); setProcessing(false) },
            onFinish:  () => setProcessing(false),
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-2xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                <div className="sticky top-0 z-10 flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                    <h2 className="font-bold text-base flex items-center gap-2"
                        style={{ color: 'var(--text-main)' }}>
                        <CreditCard className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        Nuevo Anticipo
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit} className="p-6 space-y-4">
                    <div className="space-y-1.5">
                        <Label>Proveedor <span className="text-red-400">*</span></Label>
                        <select value={form.proveedor_id}
                            onChange={e => setForm(f => ({ ...f, proveedor_id: e.target.value }))}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inputStyle}>
                            <option value="">— Seleccionar proveedor —</option>
                            {proveedores.map(p => (
                                <option key={p.id} value={p.id}>{p.razon_social}</option>
                            ))}
                        </select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Importación asociada
                            <span className="text-xs font-normal ml-1" style={{ color: 'var(--text-muted)' }}>(opcional)</span>
                        </Label>
                        <select value={form.importacion_id}
                            onChange={e => setForm(f => ({ ...f, importacion_id: e.target.value }))}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inputStyle}>
                            <option value="">— Sin importación —</option>
                            {importaciones.map(i => (
                                <option key={i.id} value={i.id}>
                                    {i.nombre} — ${Number(i.costo_fob).toFixed(2)}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha <span className="text-red-400">*</span></Label>
                            <Input type="date" value={form.fecha}
                                onChange={e => setForm(f => ({ ...f, fecha: e.target.value }))} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Monto ($) <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min={0.01} placeholder="0.00"
                                value={form.monto}
                                onChange={e => setForm(f => ({ ...f, monto: e.target.value }))} />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Pagar desde <span className="text-red-400">*</span></Label>
                        <select value={form.banco_id}
                            onChange={e => setForm(f => ({ ...f, banco_id: e.target.value }))}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inputStyle}>
                            <option value="">— Seleccionar banco/caja —</option>
                            {bancos.map(b => (
                                <option key={b.id} value={b.id}>
                                    {b.nombre} — Saldo: ${Number(b.saldo_actual).toFixed(2)}
                                </option>
                            ))}
                        </select>
                        {bancoBanco && (
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Saldo disponible: <strong style={{ color: 'var(--primary)' }}>
                                    ${Number(bancoBanco.saldo_actual).toFixed(2)}
                                </strong>
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label>N° Transferencia / Referencia</Label>
                        <Input value={form.num_transferencia} placeholder="ej: TRF-2026-001"
                            maxLength={50}
                            onChange={e => setForm(f => ({ ...f, num_transferencia: e.target.value }))} />
                    </div>

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <Button type="submit"
                            disabled={processing || !form.proveedor_id || !form.monto || !form.banco_id || !form.fecha}>
                            <CreditCard className="w-4 h-4" /> Registrar Anticipo
                        </Button>
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Cruzar ─────────────────────────────────────────────────────────────

function ModalCruzar({ anticipo, onClose }: {
    anticipo: Anticipo
    onClose:  () => void
}) {
    const [form, setForm] = useState({
        compra_id: '',
        monto:     Number(anticipo.saldo).toFixed(2),
    })
    const [processing, setProcessing] = useState(false)

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setProcessing(true)
        router.patch(route('compras.anticipos.cruzar', anticipo.id), form, {
            onSuccess: () => { notify.success('Anticipo cruzado correctamente'); onClose() },
            onError:   (errs) => { notify.error(Object.values(errs).flat().join(' | ')); setProcessing(false) },
            onFinish:  () => setProcessing(false),
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-md rounded-2xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                <div className="flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ borderBottom: '1px solid var(--border)' }}>
                    <h2 className="font-bold text-base flex items-center gap-2"
                        style={{ color: 'var(--text-main)' }}>
                        <ArrowLeftRight className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        Cruzar con Factura
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Info anticipo */}
                <div className="mx-6 mt-4 rounded-lg p-3 space-y-1.5"
                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.25)' }}>
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--text-muted)' }}>Proveedor</span>
                        <span className="font-semibold" style={{ color: 'var(--text-main)' }}>
                            {anticipo.proveedor ?? '—'}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--text-muted)' }}>Fecha anticipo</span>
                        <span style={{ color: 'var(--text-main)' }}>{anticipo.fecha}</span>
                    </div>
                    <div className="flex justify-between text-sm font-bold border-t pt-1.5"
                        style={{ borderColor: 'rgba(245,158,11,0.3)', color: 'var(--primary)' }}>
                        <span>Saldo disponible</span>
                        <span>{formatMoney(anticipo.saldo)}</span>
                    </div>
                </div>

                <form onSubmit={submit} className="p-6 space-y-4">
                    <div className="space-y-1.5">
                        <Label>ID Factura de Compra <span className="text-red-400">*</span></Label>
                        <Input
                            type="number" min={1} placeholder="ID de la factura..."
                            value={form.compra_id}
                            onChange={e => setForm(f => ({ ...f, compra_id: e.target.value }))}
                        />
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            Ingrese el ID interno de la factura de compra a cruzar.
                        </p>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Monto a cruzar ($) <span className="text-red-400">*</span></Label>
                        <Input
                            type="number" step="0.01" min={0.01}
                            max={Number(anticipo.saldo).toFixed(2)}
                            value={form.monto}
                            onChange={e => setForm(f => ({ ...f, monto: e.target.value }))}
                        />
                    </div>

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <Button type="submit" disabled={processing || !form.compra_id || !form.monto}>
                            <ArrowLeftRight className="w-4 h-4" /> Confirmar cruce
                        </Button>
                        <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function AnticiposIndex() {
    const { anticipos, proveedores, importaciones, bancos, filtros, stats, flash } = usePage<Props>().props

    const [buscar,      setBuscar]      = useState(filtros.buscar       ?? '')
    const [estado,      setEstado]      = useState(filtros.estado       ?? '')
    const [proveedorId, setProveedorId] = useState(filtros.proveedor_id ?? '')
    const [modalNuevo,  setModalNuevo]  = useState(false)
    const [cruzarActivo, setCruzarActivo] = useState<Anticipo | null>(null)

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function aplicarFiltros() {
        router.get(route('compras.anticipos.index'), {
            buscar, estado,
            ...(proveedorId && { proveedor_id: proveedorId }),
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setBuscar(''); setEstado(''); setProveedorId('')
        router.get(route('compras.anticipos.index'), {}, { preserveState: false })
    }

    async function confirmarAnulacion(a: Anticipo) {
        const { value: motivo } = await Swal.fire({
            ...swalBase,
            title: 'Anular anticipo',
            html: `
                <div style="text-align:left">
                    <div style="background:#f9fafb;border-left:4px solid #F59E0B;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-weight:700;color:#1f2937;margin:0">${a.proveedor ?? '—'}</p>
                        <p style="color:#6b7280;font-size:0.85rem;margin:4px 0 0">
                            Saldo: $${Number(a.saldo).toFixed(2)} · Fecha: ${a.fecha}
                        </p>
                    </div>
                    <div style="background:#fef2f2;border:1px solid #fecaca;
                                border-radius:8px;padding:10px;margin-bottom:14px">
                        <p style="font-weight:700;color:#dc2626;margin:0;font-size:0.85rem">
                            ⚠️ El saldo se revertirá al banco de origen
                        </p>
                    </div>
                    <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                  display:block;margin-bottom:6px">
                        Motivo <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="mot-anulacion"
                        style="width:100%;border:2px solid #e5e7eb;border-radius:8px;
                               padding:10px;font-size:0.875rem;resize:vertical;
                               min-height:70px;box-sizing:border-box;font-family:inherit"
                        placeholder="ej: Error en el monto, duplicado..."></textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Anular anticipo',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            didOpen: injectSwalStyles,
            preConfirm: () => {
                const m = (document.getElementById('mot-anulacion') as HTMLTextAreaElement)?.value
                if (!m || m.length < 10) {
                    Swal.showValidationMessage('Mínimo 10 caracteres')
                    return false
                }
                return m
            },
        })

        if (motivo) {
            router.patch(route('compras.anticipos.anular', a.id), { motivo }, {
                onSuccess: () => notify.success('Anticipo anulado y saldo revertido'),
                onError:   () => notify.error('Error al anular el anticipo'),
            })
        }
    }

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <AppLayout title="Anticipos Proveedores" suppressFlash>
            <Head title="Anticipos Proveedores" />

            <div className="px-6 pt-6 mb-2">
                {/* Header */}
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <CreditCard size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Anticipos a Proveedores
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Pagos adelantados antes de recibir la factura formal
                        </p>
                    </div>
                </div>

                {/* Toolbar */}
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <button onClick={() => setModalNuevo(true)}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                        style={{ background: 'var(--primary)' }}>
                        <Plus size={15} /> Nuevo Anticipo
                    </button>

                    <div className="relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2"
                                style={{ color: 'var(--text-muted)' }} />
                        <input type="text" value={buscar}
                            onChange={e => setBuscar(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            placeholder="Proveedor o transferencia…"
                            className="pl-9 pr-3 py-2 text-sm rounded-xl border focus:outline-none w-52 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)', color: 'var(--text-main)' }} />
                    </div>

                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inputStyle}>
                        <option value="">Todos</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="cruzado">Cruzados</option>
                    </select>

                    <select value={proveedorId} onChange={e => setProveedorId(e.target.value)}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inputStyle}>
                        <option value="">Todos los proveedores</option>
                        {proveedores.map(p => (
                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                        ))}
                    </select>

                    <button onClick={aplicarFiltros}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white whitespace-nowrap transition-all hover:opacity-90"
                        style={{ background: 'var(--primary)' }}>
                        Filtrar
                    </button>
                    <button onClick={limpiar}
                        className="p-2 rounded-xl border transition-all hover:opacity-80"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                        <X size={14} />
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 px-6 pb-4">
                <StatCard label="Total" value={stats.total} color="#3b82f6" icon={CreditCard} />
                <StatCard label="Pendientes" value={stats.pendientes} color="#f59e0b" icon={Clock} />
                <StatCard label="Cruzados" value={stats.cruzados} color="#10b981" icon={CheckCircle} />
                <StatCard label="Saldo Pendiente" value={formatMoney(stats.monto_total)} color="#ef4444" icon={DollarSign} />
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-3 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-1">Fecha</span>
                        <span className="col-span-3">Proveedor</span>
                        <span className="col-span-2">Importación</span>
                        <span className="col-span-2">N° Transferencia</span>
                        <span className="col-span-1 text-right">Monto</span>
                        <span className="col-span-1 text-right">Saldo</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-center">Acción</span>
                    </div>

                    {anticipos.length === 0 && (
                        <div className="py-20 text-center">
                            <CreditCard className="opacity-20 mx-auto mb-3 w-10 h-10"
                                style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay anticipos registrados
                            </p>
                        </div>
                    )}

                    {anticipos.map(a => (
                        <div key={a.id}
                            className={cn(
                                'grid grid-cols-12 gap-3 px-4 py-3 border-b items-center text-sm transition-colors',
                                a.estado === 'cruzado' && 'opacity-60'
                            )}
                            style={{ borderBottomColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <div className="col-span-1">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{a.fecha}</p>
                            </div>
                            <div className="col-span-3 min-w-0">
                                <p className="font-semibold truncate" style={{ color: 'var(--text-main)' }}>
                                    {a.proveedor ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>
                                    {a.importacion ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-2">
                                <p className="font-mono text-xs" style={{ color: 'var(--primary)' }}>
                                    {a.num_transferencia ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="font-medium text-xs" style={{ color: 'var(--text-main)' }}>
                                    {formatMoney(a.monto)}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="font-bold text-xs"
                                    style={{ color: Number(a.saldo) > 0 ? '#f59e0b' : '#10b981' }}>
                                    {formatMoney(a.saldo)}
                                </p>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {a.estado === 'pendiente' ? (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                        <Clock className="w-2.5 h-2.5" /> Pendiente
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                        <CheckCircle className="w-2.5 h-2.5" /> Cruzado
                                    </span>
                                )}
                            </div>
                            <div className="col-span-1 flex justify-center gap-1">
                                {a.estado === 'pendiente' && (
                                    <>
                                        <button onClick={() => setCruzarActivo(a)}
                                            title="Cruzar con factura"
                                            className="p-1.5 rounded-lg hover:bg-blue-500/20 text-blue-500 transition-colors">
                                            <ArrowLeftRight className="w-3.5 h-3.5" />
                                        </button>
                                        <button onClick={() => confirmarAnulacion(a)}
                                            title="Anular anticipo"
                                            className="p-1.5 rounded-lg hover:bg-red-500/20 text-red-500 transition-colors">
                                            <X className="w-3.5 h-3.5" />
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modalNuevo && (
                <ModalNuevo
                    proveedores={proveedores}
                    importaciones={importaciones}
                    bancos={bancos}
                    onClose={() => setModalNuevo(false)}
                />
            )}

            {cruzarActivo && (
                <ModalCruzar
                    anticipo={cruzarActivo}
                    onClose={() => setCruzarActivo(null)}
                />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
