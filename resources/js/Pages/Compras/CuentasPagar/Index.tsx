import { useState, useEffect } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { cn } from '@/lib/utils'
import { DollarSign, AlertTriangle, Clock, Search, X, FileText } from 'lucide-react'
import type { PageProps, Proveedor } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CxPRow {
    id: number
    proveedor: string | null
    num_documento: string | null
    monto: number
    saldo: number
    fecha_emision: string | null
    fecha_vencimiento: string | null
    estado: 'pendiente' | 'parcial' | 'pagada'
    urgencia: 'vencida' | 'critica' | 'proxima' | 'normal'
    color_urgencia: string
    dias_vencimiento: number
}

interface Resumen {
    total_pendiente: number
    vencidas: number
    por_vencer: number
}

interface Filtros {
    estado?: string
    proveedor_id?: string
}

interface Props extends PageProps {
    cxp: CxPRow[]
    proveedores: Pick<Proveedor, 'id' | 'razon_social'>[]
    filtros: Filtros
    resumen: Resumen
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    error: (msg: string) => toast.error(msg, { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── Urgencia helpers ─────────────────────────────────────────────────────────

const URGENCIA_BORDER: Record<string, string> = {
    vencida: 'border-l-4 border-l-red-500',
    critica: 'border-l-4 border-l-orange-500',
    proxima: 'border-l-4 border-l-yellow-500',
    normal:  'border-l-4 border-l-green-500',
}

const URGENCIA_CHIP: Record<string, { bg: string; text: string }> = {
    vencida: { bg: 'bg-red-100 dark:bg-red-900/30',    text: 'text-red-700 dark:text-red-400' },
    critica: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
    proxima: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400' },
    normal:  { bg: 'bg-green-100 dark:bg-green-900/30',  text: 'text-green-700 dark:text-green-400' },
}

function DiasChip({ dias, urgencia }: { dias: number; urgencia: string }) {
    const c = URGENCIA_CHIP[urgencia] ?? URGENCIA_CHIP.normal
    const label = dias < 0
        ? `${Math.abs(dias)}d VENCIDA`
        : dias === 0 ? 'HOY'
        : `En ${dias}d`
    return (
        <span className={cn('px-2 py-0.5 rounded-full text-xs font-semibold', c.bg, c.text)}>
            {label}
        </span>
    )
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: string | number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div className="min-w-0">
                <p className={cn('text-xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs leading-none" style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function CuentasPagarIndex() {
    const { cxp, proveedores, filtros, resumen, flash } = usePage<Props>().props

    const [buscar, setBuscar] = useState('')
    const [estado, setEstado] = useState(filtros.estado ?? '')
    const [proveedorId, setProveedorId] = useState(filtros.proveedor_id ?? '')

    useEffect(() => {
        if (flash?.error) notify.error(flash.error)
    }, [flash?.error])

    function aplicarFiltros() {
        router.get(route('compras.cxp.index'), {
            ...(estado       && { estado }),
            ...(proveedorId  && { proveedor_id: proveedorId }),
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setEstado('')
        setProveedorId('')
        router.get(route('compras.cxp.index'), {}, { preserveState: false })
    }

    const hayFiltros = estado || proveedorId

    const filtradas = cxp.filter(c => {
        if (!buscar.trim()) return true
        const q = buscar.toLowerCase()
        return (
            (c.proveedor ?? '').toLowerCase().includes(q) ||
            (c.num_documento ?? '').toLowerCase().includes(q)
        )
    })

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <AppLayout title="Cuentas por Pagar" suppressFlash>
            <Head title="Cuentas por Pagar" />

            <PageHeader
                title="Cuentas por Pagar"
                description="Obligaciones pendientes con proveedores ordenadas por vencimiento"
                breadcrumbs={[{ label: 'Compras' }, { label: 'Cuentas por Pagar' }]}
            />

            {/* Stats resumen */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 px-6 py-4">
                <StatCard
                    label="Total pendiente"
                    value={`$${Number(resumen.total_pendiente).toLocaleString('es-EC', { minimumFractionDigits: 2 })}`}
                    icon={DollarSign}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
                <StatCard
                    label="Facturas vencidas"
                    value={resumen.vencidas}
                    icon={AlertTriangle}
                    cls="bg-red-500/15 text-red-600 dark:text-red-400"
                    valueCls="text-red-600 dark:text-red-400" />
                <StatCard
                    label="Vencen en 15 días"
                    value={resumen.por_vencer}
                    icon={Clock}
                    cls="bg-orange-500/15 text-orange-600 dark:text-orange-400"
                    valueCls="text-orange-600 dark:text-orange-400" />
            </div>

            {/* Filtros */}
            <div className="flex flex-wrap items-center gap-2 px-6 pb-4">
                <div className="relative flex-1 min-w-48 max-w-xs">
                    <Search className="absolute top-1/2 left-2.5 w-4 h-4 -translate-y-1/2 pointer-events-none" style={{ color: 'var(--text-muted)' }} />
                    <Input className="pl-8 pr-8" placeholder="Buscar proveedor o documento…"
                        value={buscar} onChange={e => setBuscar(e.target.value)} />
                    {buscar && (
                        <button onClick={() => setBuscar('')}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 hover:opacity-70 transition-opacity"
                            style={{ color: 'var(--text-muted)' }}>
                            <X className="w-3.5 h-3.5" />
                        </button>
                    )}
                </div>

                <select value={estado} onChange={e => setEstado(e.target.value)}
                    className="h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}>
                    <option value="">Todas (pendiente + parcial)</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="parcial">Parcial</option>
                    <option value="pagada">Pagada</option>
                </select>

                <select value={proveedorId} onChange={e => setProveedorId(e.target.value)}
                    className="h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                    style={inputStyle}>
                    <option value="">Todos los proveedores</option>
                    {proveedores.map(p => (
                        <option key={p.id} value={p.id}>{p.razon_social}</option>
                    ))}
                </select>

                <button onClick={aplicarFiltros}
                    className="h-9 px-4 rounded-md text-sm font-medium text-white transition-colors"
                    style={{ background: 'var(--primary)' }}
                    onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                    onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}>
                    Filtrar
                </button>
                {hayFiltros && (
                    <button onClick={limpiar}
                        className="h-9 px-3 rounded-md text-sm font-medium border transition-colors hover:opacity-80"
                        style={{ color: 'var(--text-muted)', borderColor: 'var(--border)' }}>
                        Limpiar
                    </button>
                )}
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-3 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-3">Proveedor</span>
                        <span className="col-span-2">N° Documento</span>
                        <span className="col-span-1 text-right">Monto</span>
                        <span className="col-span-1 text-right">Saldo</span>
                        <span className="col-span-1 text-center">Emisión</span>
                        <span className="col-span-1 text-center">Vencimiento</span>
                        <span className="col-span-2 text-center">Días</span>
                        <span className="col-span-1 text-center">Estado</span>
                    </div>

                    {filtradas.length === 0 && (
                        <div className="py-20 text-center">
                            <FileText className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No hay cuentas por pagar
                            </p>
                        </div>
                    )}

                    {filtradas.map(c => (
                        <div key={c.id}
                            className={cn(
                                'group grid grid-cols-12 gap-3 px-4 py-3 border-b items-center text-sm transition-colors',
                                URGENCIA_BORDER[c.urgencia],
                            )}
                            style={{ borderBottomColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <div className="col-span-3 min-w-0">
                                <p className="font-medium truncate" style={{ color: 'var(--text-main)' }}>
                                    {c.proveedor ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-2">
                                <p className="font-mono text-xs" style={{ color: 'var(--text-main)' }}>
                                    {c.num_documento ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="font-medium text-xs" style={{ color: 'var(--text-main)' }}>
                                    ${Number(c.monto).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="font-bold text-xs" style={{ color: 'var(--primary)' }}>
                                    ${Number(c.saldo).toFixed(2)}
                                </p>
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.fecha_emision ?? '—'}</p>
                            </div>
                            <div className="col-span-1 text-center">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                    {c.fecha_vencimiento ?? '—'}
                                </p>
                            </div>
                            <div className="col-span-2 flex justify-center">
                                <DiasChip dias={c.dias_vencimiento} urgencia={c.urgencia} />
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {c.estado === 'pendiente' && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        Pendiente
                                    </span>
                                )}
                                {c.estado === 'parcial' && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        Parcial
                                    </span>
                                )}
                                {c.estado === 'pagada' && (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                        Pagada
                                    </span>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
