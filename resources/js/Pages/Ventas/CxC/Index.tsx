import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Search, Eye, FileText, ChevronLeft, ChevronRight, DollarSign, AlertTriangle, X } from 'lucide-react'
import type { PageProps, PaginatedData, AuthUser } from '@/types'

interface CxCItem {
    id: number
    cliente_razon: string
    documento_tipo: string
    documento_numero: string
    fecha_emision: string
    fecha_vencimiento: string
    monto: number
    saldo: number
    dias_vencido: number
    estado: 'pendiente' | 'parcial' | 'cobrada' | 'vencida' | 'castigada'
}

interface CxCMetricas {
    total_cartera: number
    por_vencer: number
    vencido_30: number
    vencido_60: number
    vencido_90: number
}

interface Filtros {
    cliente?: string
    estado?: string
    vencimiento_desde?: string
    vencimiento_hasta?: string
}

interface ModalCobro {
    id: number
    saldo: number
}

interface Props extends PageProps {
    cuentas: PaginatedData<CxCItem>
    metricas: CxCMetricas
    filtros: Filtros
    auth: { user: AuthUser | null }
}

const ESTADO_CONFIG = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' as const },
    parcial:    { label: 'Parcial',    variant: 'info'      as const },
    cobrada:    { label: 'Cobrada',    variant: 'success'   as const },
    vencida:    { label: 'Vencida',    variant: 'danger'    as const },
    castigada:  { label: 'Castigada',  variant: 'outline'   as const },
}

function rowBg(dias: number): string {
    if (dias <= 0) return ''
    if (dias <= 30) return 'rgba(234,179,8,.08)'
    if (dias <= 60) return 'rgba(249,115,22,.08)'
    return 'rgba(239,68,68,.08)'
}

function MetricaCard({ label, valor, color }: { label: string; valor: number; color?: string }) {
    return (
        <div
            className="rounded-xl p-4 border flex flex-col gap-1"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
        >
            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{label}</p>
            <p className="text-lg font-bold" style={{ color: color ?? 'var(--text-main)' }}>
                {formatMoneda(valor)}
            </p>
        </div>
    )
}

export default function Index() {
    const { cuentas, metricas, filtros, auth } = usePage<Props>().props

    const [filtro, setFiltro] = useState<Filtros>({
        cliente:           filtros.cliente           ?? '',
        estado:            filtros.estado            ?? '',
        vencimiento_desde: filtros.vencimiento_desde ?? '',
        vencimiento_hasta: filtros.vencimiento_hasta ?? '',
    })

    const [modalCobro, setModalCobro] = useState<ModalCobro | null>(null)
    const [cobroValor, setCobroValor] = useState('')
    const [cobroFormaPago, setCobroFormaPago] = useState('efectivo')
    const [cobroObservacion, setCobroObservacion] = useState('')
    const [cobrandoId, setCobrandoId] = useState<number | null>(null)

    const aplicarFiltros = () => {
        router.get(route('ventas.cxc.index'), filtro, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { cliente: '', estado: '', vencimiento_desde: '', vencimiento_hasta: '' }
        setFiltro(limpio)
        router.get(route('ventas.cxc.index'), {}, { preserveState: false })
    }

    const abrirModalCobro = (cuenta: CxCItem) => {
        setModalCobro({ id: cuenta.id, saldo: cuenta.saldo })
        setCobroValor(String(cuenta.saldo))
        setCobroFormaPago('efectivo')
        setCobroObservacion('')
    }

    const cerrarModalCobro = () => {
        setModalCobro(null)
        setCobroValor('')
        setCobroFormaPago('efectivo')
        setCobroObservacion('')
    }

    const handleCobrar = () => {
        if (!modalCobro) return
        const valor = parseFloat(cobroValor)
        if (!valor || valor <= 0 || valor > modalCobro.saldo) return
        setCobrandoId(modalCobro.id)
        router.post(
            route('ventas.cxc.cobrar', modalCobro.id),
            { valor, forma_pago: cobroFormaPago, observacion: cobroObservacion },
            {
                onSuccess: () => {
                    cerrarModalCobro()
                    setCobrandoId(null)
                },
                onError: () => setCobrandoId(null),
                preserveState: true,
            }
        )
    }

    const handleCastigar = async (cuenta: CxCItem) => {
        const result = await Swal.fire({
            title: 'Castigar deuda',
            text: `¿Desea castigar la deuda de ${cuenta.cliente_razon} (${formatMoneda(cuenta.saldo)})?`,
            icon: 'warning',
            input: 'password',
            inputLabel: 'Código de aprobación (SuperAdmin)',
            inputPlaceholder: '••••••••',
            showCancelButton: true,
            confirmButtonText: 'Castigar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1e293b',
            inputValidator: v => !v ? 'Ingrese el código de aprobación' : undefined,
        })
        if (!result.isConfirmed || !result.value) return
        router.post(
            route('ventas.cxc.castigar', cuenta.id),
            { codigo_aprobacion: result.value as string },
            { preserveState: true }
        )
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')
    const esSuperAdmin = auth.user?.perfil === 'Super Admin'

    return (
        <AppLayout>
            <Head title="Cuentas por Cobrar" />
            <PageHeader
                title="Cuentas por Cobrar"
                description="Control de cartera y cobros"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Cuentas por Cobrar' }]}
            />

            <div className="p-6 space-y-4">
                {/* Métricas */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <MetricaCard label="Total cartera" valor={metricas.total_cartera} />
                    <MetricaCard label="Por vencer" valor={metricas.por_vencer} color="var(--text-main)" />
                    <MetricaCard label="Vencido 1–30 días" valor={metricas.vencido_30} color="rgb(234,179,8)" />
                    <MetricaCard label="Vencido 31–60 días" valor={metricas.vencido_60} color="rgb(249,115,22)" />
                    <MetricaCard label="Vencido 60+ días" valor={metricas.vencido_90} color="rgb(239,68,68)" />
                </div>

                {/* Filtros */}
                <div
                    className="rounded-xl p-4 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Cliente</label>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none" style={{ color: 'var(--text-muted)' }} />
                                <Input
                                    className="pl-8"
                                    placeholder="Nombre o RUC..."
                                    value={filtro.cliente}
                                    onChange={e => setFiltro(p => ({ ...p, cliente: e.target.value }))}
                                    onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                                />
                            </div>
                        </div>
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Estado</label>
                            <select
                                className="w-full h-9 rounded-md border px-3 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={filtro.estado}
                                onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                            >
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="parcial">Parcial</option>
                                <option value="cobrada">Cobrada</option>
                                <option value="vencida">Vencida</option>
                                <option value="castigada">Castigada</option>
                            </select>
                        </div>
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Vencimiento desde</label>
                            <Input
                                type="date"
                                value={filtro.vencimiento_desde}
                                onChange={e => setFiltro(p => ({ ...p, vencimiento_desde: e.target.value }))}
                            />
                        </div>
                        <div>
                            <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Vencimiento hasta</label>
                            <Input
                                type="date"
                                value={filtro.vencimiento_hasta}
                                onChange={e => setFiltro(p => ({ ...p, vencimiento_hasta: e.target.value }))}
                            />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 mt-3">
                        {hayFiltros && (
                            <Button type="button" variant="ghost" size="sm" onClick={limpiarFiltros}>
                                Limpiar
                            </Button>
                        )}
                        <Button type="button" size="sm" onClick={aplicarFiltros}>
                            <Search className="w-4 h-4" />
                            Buscar
                        </Button>
                    </div>
                </div>

                {/* Tabla */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    {cuentas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron cuentas por cobrar</p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Cliente', 'Documento', 'Emisión', 'Vencimiento', 'Monto', 'Saldo', 'Días vencido', 'Estado', 'Acciones'].map(h => (
                                            <th
                                                key={h}
                                                className="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide"
                                                style={{ color: 'var(--text-muted)' }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {cuentas.data.map(c => {
                                        const cfg = ESTADO_CONFIG[c.estado] ?? ESTADO_CONFIG.pendiente
                                        const puedeAccion = c.estado !== 'cobrada' && c.estado !== 'castigada'
                                        const puedeCastigar = esSuperAdmin && c.dias_vencido > 360

                                        return (
                                            <tr
                                                key={c.id}
                                                className="hover:brightness-95 transition-colors"
                                                style={{
                                                    borderBottom: '1px solid var(--border)',
                                                    background: rowBg(c.dias_vencido) || undefined,
                                                }}
                                            >
                                                <td className="px-4 py-3 text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {c.cliente_razon}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.documento_tipo}</p>
                                                    <p className="font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>{c.documento_numero}</p>
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(c.fecha_emision)}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(c.fecha_vencimiento)}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatMoneda(c.monto)}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: c.saldo > 0 ? 'var(--primary)' : 'var(--text-muted)' }}>
                                                    {formatMoneda(c.saldo)}
                                                </td>
                                                <td className="px-4 py-3 text-xs">
                                                    {c.dias_vencido > 0 ? (
                                                        <span className={cn(
                                                            'font-semibold',
                                                            c.dias_vencido <= 30 ? 'text-yellow-500' :
                                                            c.dias_vencido <= 60 ? 'text-orange-500' : 'text-red-400'
                                                        )}>
                                                            {c.dias_vencido}d
                                                        </span>
                                                    ) : (
                                                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={route('ventas.cxc.show', c.id)}>
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                                style={{ color: 'var(--primary)' }}
                                                            >
                                                                <Eye className="w-3.5 h-3.5" />
                                                                Ver
                                                            </button>
                                                        </Link>
                                                        {puedeAccion && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-emerald-500/10 text-emerald-400"
                                                                onClick={() => abrirModalCobro(c)}
                                                            >
                                                                <DollarSign className="w-3.5 h-3.5" />
                                                                Cobrar
                                                            </button>
                                                        )}
                                                        {puedeCastigar && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-slate-500/10"
                                                                style={{ color: 'var(--text-muted)' }}
                                                                onClick={() => void handleCastigar(c)}
                                                            >
                                                                <AlertTriangle className="w-3.5 h-3.5" />
                                                                Castigar
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {cuentas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {cuentas.from}–{cuentas.to} de {cuentas.total}</span>
                            <div className="flex gap-1">
                                {cuentas.links.map((link, i) => {
                                    const isPrev = link.label.includes('Prev') || link.label === '&laquo; Previous'
                                    const isNext = link.label.includes('Next') || link.label === 'Next &raquo;'
                                    const label = isPrev ? <ChevronLeft className="w-3.5 h-3.5" /> :
                                                  isNext ? <ChevronRight className="w-3.5 h-3.5" /> :
                                                  link.label
                                    return (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.visit(link.url)}
                                            className={cn(
                                                'min-w-[28px] h-7 px-1.5 rounded text-xs font-medium transition-colors',
                                                link.active ? 'bg-[var(--primary)] text-black' : 'hover:bg-amber-500/10',
                                                !link.url && 'opacity-40 cursor-not-allowed',
                                            )}
                                            style={!link.active ? { color: 'var(--text-muted)' } : {}}
                                        >
                                            {label}
                                        </button>
                                    )
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modal Registrar Cobro */}
            {modalCobro && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
                    <div
                        className="w-full max-w-md rounded-2xl p-6 shadow-2xl"
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                    >
                        <div className="flex items-center justify-between mb-5">
                            <h3 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                                Registrar Cobro
                            </h3>
                            <button type="button" onClick={cerrarModalCobro}>
                                <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            </button>
                        </div>

                        <p className="text-xs mb-4" style={{ color: 'var(--text-muted)' }}>
                            Saldo pendiente: <strong style={{ color: 'var(--primary)' }}>{formatMoneda(modalCobro.saldo)}</strong>
                        </p>

                        <div className="space-y-4">
                            <div>
                                <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Valor a cobrar *</label>
                                <Input
                                    type="number"
                                    min="0.01"
                                    max={modalCobro.saldo}
                                    step="0.01"
                                    value={cobroValor}
                                    onChange={e => setCobroValor(e.target.value)}
                                    autoFocus
                                />
                            </div>
                            <div>
                                <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Forma de pago *</label>
                                <select
                                    className="w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={cobroFormaPago}
                                    onChange={e => setCobroFormaPago(e.target.value)}
                                >
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="datafast">Datafast</option>
                                </select>
                            </div>
                            <div>
                                <label className="text-xs mb-1 block" style={{ color: 'var(--text-muted)' }}>Observación</label>
                                <Input
                                    placeholder="Opcional..."
                                    value={cobroObservacion}
                                    onChange={e => setCobroObservacion(e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="flex justify-end gap-3 mt-6">
                            <Button type="button" variant="outline" onClick={cerrarModalCobro} disabled={cobrandoId !== null}>
                                Cancelar
                            </Button>
                            <Button
                                type="button"
                                onClick={handleCobrar}
                                loading={cobrandoId !== null}
                                disabled={!cobroValor || parseFloat(cobroValor) <= 0}
                            >
                                <DollarSign className="w-4 h-4" />
                                Registrar Cobro
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
