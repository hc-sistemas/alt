import { useState, useEffect, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, X, ArrowUpCircle, ArrowDownCircle, Search,
    Ban, DollarSign, Clock, Download,
} from 'lucide-react'
import type { MovimientoBancario, BancoCaja, PlanCuenta, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Paginated<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
    links: { url: string | null; label: string; active: boolean }[]
}

interface Props extends PageProps {
    movimientos: Paginated<MovimientoBancario & {
        banco_caja?: BancoCaja
        cuenta_contrapartida?: PlanCuenta
        creado_por?: { nombre: string }
    }>
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'tipo' | 'saldo_actual'>[]
    cuentas: Pick<PlanCuenta, 'id' | 'codigo' | 'nombre'>[]
    filtros: { banco_caja_id?: string; tipo?: string; fecha_desde?: string; fecha_hasta?: string; buscar?: string }
    stats: { total_ingresos: number; total_egresos: number; pendientes_conciliar: number }
}

// ─── Notify / Swal ───────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}
const SWAL_CSS = `.swal-pop{border-radius:20px!important;padding:28px!important;box-shadow:0 25px 60px rgba(0,0,0,.25)!important;max-width:480px!important}.swal-title{font-size:1.1rem!important;font-weight:700!important;margin-bottom:16px!important}.swal-confirm,.swal-cancel{border-radius:10px!important;padding:10px 20px!important;font-weight:600!important}`
function injectSwalCss() {
    if (document.getElementById('swal-mov')) return
    const s = document.createElement('style'); s.id = 'swal-mov'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })
const subTipoLabel: Record<string, string> = {
    transferencia: 'Transferencia', cheque: 'Cheque', efectivo: 'Efectivo', deposito: 'Depósito',
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: string | number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div><p className={cn('text-2xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{label}</p></div>
        </div>
    )
}

// ─── Modal Nuevo Movimiento ───────────────────────────────────────────────────

function MovimientoModal({ bancos, cuentas, onClose }: {
    bancos: Props['bancos']
    cuentas: Props['cuentas']
    onClose: () => void
}) {
    const [cuentaBusq, setCuentaBusq] = useState('')
    const [showCuentas, setShowCuentas] = useState(false)

    const { data, setData, post, processing, errors } = useForm({
        banco_caja_id:           '',
        tipo:                    'ingreso',
        sub_tipo:                'efectivo',
        fecha:                   new Date().toISOString().split('T')[0],
        monto:                   '',
        beneficiario:            '',
        num_documento:           '',
        num_cheque:              '',
        fecha_cheque:            '',
        descripcion:             '',
        cuenta_contrapartida_id: '',
        es_postfechado:          false,
    })

    const cuentasFiltradas = cuentas.filter(c =>
        !cuentaBusq || `${c.codigo} ${c.nombre}`.toLowerCase().includes(cuentaBusq.toLowerCase())
    ).slice(0, 30)
    const cuentaSel = cuentas.find(c => c.id === Number(data.cuenta_contrapartida_id))

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('bancos.movimientos.store'), {
            onSuccess: () => { notify.ok('Movimiento registrado correctamente'); onClose() },
            onError: (errs) => notify.error(Object.values(errs).join(' | ')),
        })
    }

    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                <div className="sticky top-0 z-10 flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                    <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>Nuevo Movimiento</h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70"
                        style={{ color: 'var(--text-muted)' }}><X className="w-4 h-4" /></button>
                </div>

                <form onSubmit={submit} className="p-6 space-y-4">
                    {/* Banco */}
                    <div className="space-y-1.5">
                        <Label>Banco/Caja <span className="text-red-400">*</span></Label>
                        <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={inp}>
                            <option value="">— Seleccionar —</option>
                            {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre} (${Number(b.saldo_actual).toFixed(2)})</option>)}
                        </select>
                        {errors.banco_caja_id && <p className="text-red-400 text-xs">{errors.banco_caja_id}</p>}
                    </div>

                    {/* Tipo ingreso/egreso */}
                    <div className="space-y-1.5">
                        <Label>Tipo <span className="text-red-400">*</span></Label>
                        <div className="grid grid-cols-2 gap-2">
                            {(['ingreso', 'egreso'] as const).map(t => (
                                <button key={t} type="button" onClick={() => setData('tipo', t)}
                                    className={cn(
                                        'py-3 rounded-xl text-sm font-bold border-2 transition-all flex items-center justify-center gap-2',
                                        data.tipo === t
                                            ? t === 'ingreso' ? 'bg-green-500/20 border-green-500 text-green-600 dark:text-green-400'
                                                              : 'bg-red-500/20 border-red-500 text-red-600 dark:text-red-400'
                                            : 'border-transparent'
                                    )}
                                    style={data.tipo !== t ? { borderColor: 'var(--border)', color: 'var(--text-muted)' } : {}}>
                                    {t === 'ingreso' ? <ArrowUpCircle className="w-4 h-4" /> : <ArrowDownCircle className="w-4 h-4" />}
                                    {t === 'ingreso' ? 'Ingreso' : 'Egreso'}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Sub_tipo */}
                    <div className="space-y-1.5">
                        <Label>Forma de pago <span className="text-red-400">*</span></Label>
                        <div className="grid grid-cols-4 gap-1.5">
                            {(['efectivo', 'transferencia', 'cheque', 'deposito'] as const).map(t => (
                                <button key={t} type="button" onClick={() => setData('sub_tipo', t)}
                                    className={cn(
                                        'py-2 px-1 rounded-lg text-xs font-medium border transition-colors',
                                        data.sub_tipo === t ? 'text-white border-transparent' : 'hover:opacity-80'
                                    )}
                                    style={data.sub_tipo === t
                                        ? { background: 'var(--primary)' }
                                        : { borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    {subTipoLabel[t]}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Fecha / Monto */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Fecha <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha} onChange={e => setData('fecha', e.target.value)} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Monto <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min="0.01" value={data.monto}
                                onChange={e => setData('monto', e.target.value)}
                                error={errors.monto} placeholder="0.00" />
                            {errors.monto && <p className="text-red-400 text-xs">{errors.monto}</p>}
                        </div>
                    </div>

                    {/* Cheque fields */}
                    {data.sub_tipo === 'cheque' && (
                        <div className="grid grid-cols-2 gap-3 p-3 rounded-lg"
                            style={{ background: 'rgba(245,158,11,0.05)', border: '1px solid var(--border)' }}>
                            <div className="space-y-1.5">
                                <Label>N° Cheque</Label>
                                <Input value={data.num_cheque} onChange={e => setData('num_cheque', e.target.value)} />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Fecha cheque</Label>
                                <Input type="date" value={data.fecha_cheque} onChange={e => setData('fecha_cheque', e.target.value)} />
                            </div>
                        </div>
                    )}

                    {/* Beneficiario / N° Documento */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Beneficiario</Label>
                            <Input value={data.beneficiario} onChange={e => setData('beneficiario', e.target.value)}
                                placeholder="Nombre…" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>N° Documento</Label>
                            <Input value={data.num_documento} onChange={e => setData('num_documento', e.target.value)}
                                placeholder="Ref…" />
                        </div>
                    </div>

                    {/* Cuenta contrapartida */}
                    <div className="space-y-1.5 relative">
                        <Label>Cuenta contrapartida <span className="text-red-400">*</span></Label>
                        <div className="relative">
                            <Input
                                value={cuentaBusq || (cuentaSel ? `${cuentaSel.codigo} — ${cuentaSel.nombre}` : '')}
                                onChange={e => { setCuentaBusq(e.target.value); setShowCuentas(true) }}
                                onFocus={() => setShowCuentas(true)}
                                error={errors.cuenta_contrapartida_id}
                                placeholder="Buscar cuenta…" />
                            {(data.cuenta_contrapartida_id || cuentaBusq) && (
                                <button type="button"
                                    onClick={() => { setData('cuenta_contrapartida_id', ''); setCuentaBusq(''); setShowCuentas(false) }}
                                    className="absolute top-1/2 right-2 -translate-y-1/2 hover:opacity-70"
                                    style={{ color: 'var(--text-muted)' }}><X className="w-3.5 h-3.5" /></button>
                            )}
                        </div>
                        {errors.cuenta_contrapartida_id && <p className="text-red-400 text-xs">{errors.cuenta_contrapartida_id}</p>}
                        {showCuentas && cuentasFiltradas.length > 0 && (
                            <div className="absolute z-20 w-full rounded-lg shadow-xl border overflow-hidden"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                                <div className="max-h-48 overflow-y-auto">
                                    {cuentasFiltradas.map(c => (
                                        <button key={c.id} type="button"
                                            onClick={() => { setData('cuenta_contrapartida_id', c.id as any); setCuentaBusq(''); setShowCuentas(false) }}
                                            className="w-full text-left px-3 py-2 text-xs transition-colors"
                                            style={{ color: 'var(--text-main)' }}
                                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.1)')}
                                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                            <span className="font-mono font-medium">{c.codigo}</span>
                                            <span className="ml-2" style={{ color: 'var(--text-muted)' }}>{c.nombre}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Descripción */}
                    <div className="space-y-1.5">
                        <Label>Descripción <span className="text-red-400">*</span></Label>
                        <Input value={data.descripcion} onChange={e => setData('descripcion', e.target.value)}
                            error={errors.descripcion} placeholder="Descripción del movimiento…" />
                        {errors.descripcion && <p className="text-red-400 text-xs">{errors.descripcion}</p>}
                    </div>

                    {/* Posfechado */}
                    {data.sub_tipo === 'cheque' && (
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.es_postfechado}
                                onChange={e => setData('es_postfechado', e.target.checked)}
                                className="rounded w-4 h-4 accent-amber-500" />
                            <span className="text-sm" style={{ color: 'var(--text-main)' }}>Cheque posfechado</span>
                        </label>
                    )}

                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <button type="submit" disabled={processing}
                            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50"
                            style={{ background: 'var(--primary)' }}>
                            <Plus className="w-4 h-4" /> Registrar Movimiento
                        </button>
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Modal Anular ─────────────────────────────────────────────────────────────

function AnularModal({ movimiento, onClose }: { movimiento: MovimientoBancario; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({ motivo: '' })

    function submit(e: React.FormEvent) {
        e.preventDefault()
        patch(route('bancos.movimientos.anular', movimiento.id), {
            onSuccess: () => { notify.warn('Movimiento anulado'); onClose() },
            onError: () => notify.error('Error al anular'),
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-sm rounded-xl shadow-2xl p-6"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>
                <h2 className="font-semibold text-base mb-4 flex items-center gap-2" style={{ color: 'var(--text-main)' }}>
                    <Ban className="w-5 h-5 text-red-500" /> Anular movimiento
                </h2>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Motivo de anulación <span className="text-red-400">*</span></Label>
                        <Input value={data.motivo} onChange={e => setData('motivo', e.target.value)}
                            error={errors.motivo} placeholder="Mínimo 10 caracteres…" />
                        {errors.motivo && <p className="text-red-400 text-xs">{errors.motivo}</p>}
                    </div>
                    <div className="flex gap-2">
                        <button type="submit" disabled={processing || data.motivo.length < 10}
                            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50 bg-red-500 hover:bg-red-600">
                            <Ban className="w-4 h-4" /> Anular
                        </button>
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function MovimientosIndex() {
    const { movimientos, bancos, cuentas, filtros, stats, flash } = usePage<Props>().props
    const [showModal, setShowModal] = useState(false)
    const [anularMov, setAnularMov] = useState<MovimientoBancario | null>(null)
    const [filtro, setFiltro] = useState(filtros)

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    function buscar() {
        router.get(route('bancos.movimientos.index'), filtro as any, { preserveState: true, replace: true })
    }
    function limpiar() {
        const empty = { banco_caja_id: '', tipo: '', fecha_desde: '', fecha_hasta: '', buscar: '' }
        setFiltro(empty)
        router.get(route('bancos.movimientos.index'), {}, { preserveState: true, replace: true })
    }

    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <AppLayout title="Movimientos Bancarios" suppressFlash>
            <Head title="Movimientos Bancarios" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl" style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <DollarSign size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>Movimientos Bancarios</h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Ingresos y egresos de bancos y cajas</p>
                    </div>
                </div>
                {/* Toolbar */}
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <button onClick={() => setShowModal(true)}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                        style={{ background: 'var(--primary)' }}>
                        <Plus size={15} /> Nuevo Movimiento
                    </button>

                    <div className="relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2"
                                style={{ color: 'var(--text-muted)' }} />
                        <input type="text" value={filtro.buscar ?? ''}
                            onChange={e => setFiltro(f => ({ ...f, buscar: e.target.value }))}
                            placeholder="Descripción, beneficiario…"
                            className="pl-9 pr-3 py-2 text-sm rounded-xl border focus:outline-none focus:ring-2 w-48 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)', color: 'var(--text-main)' }} />
                    </div>

                    <select value={filtro.banco_caja_id ?? ''} onChange={e => setFiltro(f => ({ ...f, banco_caja_id: e.target.value }))}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inp}>
                        <option value="">Todos los bancos</option>
                        {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <select value={filtro.tipo ?? ''} onChange={e => setFiltro(f => ({ ...f, tipo: e.target.value }))}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inp}>
                        <option value="">Todos</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="egreso">Egreso</option>
                    </select>

                    <input type="date" value={filtro.fecha_desde ?? ''}
                        onChange={e => setFiltro(f => ({ ...f, fecha_desde: e.target.value }))}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inp} />
                    <input type="date" value={filtro.fecha_hasta ?? ''}
                        onChange={e => setFiltro(f => ({ ...f, fecha_hasta: e.target.value }))}
                        className="py-2 px-3 text-sm rounded-xl border focus:outline-none dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                        style={inp} />

                    <button onClick={buscar}
                        className="px-4 py-2 rounded-xl text-sm font-semibold text-white whitespace-nowrap transition-all hover:opacity-90"
                        style={{ background: 'var(--primary)' }}>
                        Filtrar
                    </button>
                    <button onClick={limpiar}
                        className="px-3 py-2 rounded-xl text-sm font-medium border transition-all hover:opacity-80"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                        Limpiar
                    </button>

                    <div className="flex-1" />

                    <a href={route('bancos.movimientos.exportar-xml')}
                       className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90"
                       style={{ background: '#3b82f6' }}>
                        <Download size={15} /> XML
                    </a>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 px-6 py-4">
                <StatCard label="Total Ingresos" value={fmt(stats.total_ingresos)} icon={ArrowUpCircle}
                    cls="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueCls="text-green-600 dark:text-green-400" />
                <StatCard label="Total Egresos" value={fmt(stats.total_egresos)} icon={ArrowDownCircle}
                    cls="bg-red-500/15 text-red-600 dark:text-red-400"
                    valueCls="text-red-600 dark:text-red-400" />
                <StatCard label="Pendientes conciliar" value={stats.pendientes_conciliar} icon={Clock}
                    cls="bg-orange-500/15 text-orange-600 dark:text-orange-400"
                    valueCls="text-orange-600 dark:text-orange-400" />
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-1">Fecha</span>
                        <span className="col-span-2">Banco/Caja</span>
                        <span className="col-span-3">Descripción</span>
                        <span className="col-span-2">Beneficiario</span>
                        <span className="col-span-1">Forma</span>
                        <span className="col-span-1 text-right">Monto</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-right">Acción</span>
                    </div>

                    {movimientos.data.length === 0 && (
                        <div className="py-20 text-center">
                            <DollarSign className="w-12 h-12 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No hay movimientos registrados</p>
                        </div>
                    )}

                    {movimientos.data.map(m => (
                        <div key={m.id}
                            className={cn(
                                'group grid grid-cols-12 gap-2 px-4 py-3 border-b items-center transition-colors text-sm',
                                m.anulado && 'opacity-40 line-through'
                            )}
                            style={{ borderColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => !m.anulado && (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>

                            <div className="col-span-1">
                                <p className="text-xs font-mono" style={{ color: 'var(--text-main)' }}>{m.fecha}</p>
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate font-medium" style={{ color: 'var(--text-main)' }}>
                                    {(m as any).banco_caja?.nombre ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-3 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-main)' }}>{m.descripcion ?? '—'}</p>
                                {m.num_documento && <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>{m.num_documento}</p>}
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>{m.beneficiario ?? '—'}</p>
                            </div>
                            <div className="col-span-1">
                                <span className="px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-300">
                                    {subTipoLabel[m.sub_tipo ?? ''] ?? m.sub_tipo ?? '—'}
                                </span>
                            </div>
                            <div className="col-span-1 text-right">
                                <span className={cn(
                                    'text-sm font-bold',
                                    m.tipo === 'ingreso' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                                )}>
                                    {m.tipo === 'egreso' ? '-' : '+'}{fmt(m.monto)}
                                </span>
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {m.anulado
                                    ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Anulado</span>
                                    : m.conciliado
                                        ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Conciliado</span>
                                        : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-600">Pendiente</span>
                                }
                            </div>
                            <div className="col-span-1 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                {!m.anulado && !m.conciliado && (
                                    <button onClick={() => setAnularMov(m)} title="Anular"
                                        className="p-1.5 rounded hover:bg-red-500/20 text-red-500 transition-colors">
                                        <Ban className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Paginación */}
                {movimientos.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4">
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            Mostrando {movimientos.from ?? 0}–{movimientos.to ?? 0} de {movimientos.total}
                        </p>
                        <div className="flex gap-1">
                            {movimientos.links.map((link, i) => (
                                <button key={i} disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                    className={cn(
                                        'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                                        link.active ? 'text-white border-transparent' : 'hover:opacity-80',
                                        !link.url && 'opacity-30 cursor-not-allowed'
                                    )}
                                    style={link.active ? { background: 'var(--primary)' } : { borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                    dangerouslySetInnerHTML={{ __html: link.label }} />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {showModal && <MovimientoModal bancos={bancos} cuentas={cuentas} onClose={() => setShowModal(false)} />}
            {anularMov && <AnularModal movimiento={anularMov} onClose={() => setAnularMov(null)} />}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
