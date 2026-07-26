import { useState } from 'react'
import { createPortal } from 'react-dom'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, Ban, FileText, ChevronLeft, ChevronRight, ArrowRightLeft, X, Trash2 } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, PaginatedData } from '@/types'

interface ProformaCliente {
    razon_social: string
    identificacion: string
}

interface Proforma {
    id: number
    numero_completo: string
    fecha_emision: string
    fecha_vencimiento: string
    total: number
    estado: 'pendiente' | 'facturada' | 'vencida' | 'anulada'
    cliente: ProformaCliente | null
}

interface Filtros {
    estado?: string
    cliente?: string
    fecha_desde?: string
    fecha_hasta?: string
}

interface Props extends PageProps {
    proformas: PaginatedData<Proforma>
    filtros: Filtros
}

interface FormaPago {
    [key: string]: string | number
    forma: string
    monto: number
}

const FORMAS_PAGO = ['efectivo', 'transferencia', 'tarjeta', 'cheque', 'credito']

const ESTADO_CONFIG = {
    pendiente: { label: 'Pendiente', variant: 'secondary' as const },
    facturada: { label: 'Facturada', variant: 'success' as const },
    vencida: { label: 'Vencida', variant: 'danger' as const },
    anulada: { label: 'Anulada', variant: 'warning' as const },
}

export default function Index() {
    const { proformas, filtros } = usePage<Props>().props
    const { puede } = usePermiso('ventas')

    const [filtro, setFiltro] = useState<Filtros>({
        estado: filtros.estado ?? '',
        cliente: filtros.cliente ?? '',
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
    })
    const [modalConvertir, setModalConvertir] = useState<Proforma | null>(null)
    const [formasPago, setFormasPago] = useState<FormaPago[]>([])
    const [convirtiendo, setConvirtiendo] = useState(false)
    const [errorPago, setErrorPago] = useState('')

    const aplicarFiltros = () => {
        router.get(route('ventas.proformas.index'), filtro as Record<string, string | undefined>, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { estado: '', cliente: '', fecha_desde: '', fecha_hasta: '' }
        setFiltro(limpio)
        router.get(route('ventas.proformas.index'), {}, { preserveState: false })
    }

    const handleConvertir = (p: Proforma) => {
        setFormasPago([{ forma: 'efectivo', monto: p.total }])
        setErrorPago('')
        setModalConvertir(p)
    }

    const handleConfirmarConversion = () => {
        if (!modalConvertir) return
        const totalPagos = formasPago.reduce((sum, p) => sum + p.monto, 0)
        if (Math.abs(totalPagos - modalConvertir.total) > 0.01) {
            setErrorPago(`Las formas de pago deben sumar ${formatMoneda(modalConvertir.total)}. Actual: ${formatMoneda(totalPagos)}`)
            return
        }
        if (formasPago.some(p => !p.forma || p.monto <= 0)) {
            setErrorPago('Todas las formas de pago deben tener forma y monto válido.')
            return
        }
        setErrorPago('')
        setConvirtiendo(true)
        router.post(
            route('ventas.proformas.convertir', modalConvertir.id),
            { formas_pago: formasPago },
            {
                onError: () => setConvirtiendo(false),
                onFinish: () => { setConvirtiendo(false); setModalConvertir(null) },
            }
        )
    }

    const handleAnular = async (p: Proforma) => {
        const result = await Swal.fire({
            title: 'Anular proforma',
            text: `¿Desea anular la proforma ${p.numero_completo}? Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })
        if (!result.isConfirmed) return
        router.delete(route('ventas.proformas.destroy', p.id), { preserveState: true })
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    return (
        <AppLayout>
            <Head title="Proformas" />
            <PageHeader
                title="Proformas"
                description="Gestión de proformas y cotizaciones"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Proformas' }]}
                actions={
                    <div className="flex items-center gap-3">
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {proformas.total} proforma{proformas.total === 1 ? '' : 's'}
                        </span>
                        {puede('crear') && (
                            <Link href={route('ventas.proformas.create')}>
                                <Button>
                                    <Plus className="w-4 h-4" />
                                    Nueva
                                </Button>
                            </Link>
                        )}
                    </div>
                }
            />

            <div className="p-6 space-y-4">
                {/* Filtros */}
                <div className="flex items-center gap-3 flex-wrap">
                    <Input
                        type="date"
                        value={filtro.fecha_desde}
                        onChange={e => setFiltro(p => ({ ...p, fecha_desde: e.target.value }))}
                        onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                        className="shrink-0 w-36"
                        title="Desde"
                    />
                    <Input
                        type="date"
                        value={filtro.fecha_hasta}
                        onChange={e => setFiltro(p => ({ ...p, fecha_hasta: e.target.value }))}
                        onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                        className="shrink-0 w-36"
                        title="Hasta"
                    />
                    <select
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}
                        value={filtro.estado}
                        onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                    >
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="facturada">Facturada</option>
                        <option value="vencida">Vencida</option>
                        <option value="anulada">Anulada</option>
                    </select>

                    {hayFiltros && (
                        <Button type="button" variant="ghost" size="sm" onClick={limpiarFiltros} className="shrink-0">
                            Limpiar
                        </Button>
                    )}

                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={filtro.cliente}
                                onChange={e => setFiltro(p => ({ ...p, cliente: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                                placeholder="Cliente o RUC..."
                                className="pl-9 w-52 rounded-r-none border-r-0"
                            />
                        </div>
                        <button className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                            style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                            onClick={aplicarFiltros}
                            title="Buscar">
                            <Search className="w-4 h-4" />
                        </button>
                    </div>
                </div>

                {/* Tabla */}
                <div
                    className="rounded-xl border overflow-hidden"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    {proformas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron proformas</p>
                            {puede('crear') && (
                                <Link href={route('ventas.proformas.create')}>
                                    <Button size="sm">
                                        <Plus className="w-4 h-4" />
                                        Nueva Proforma
                                    </Button>
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Vencimiento', 'Cliente', 'Total', 'Estado', 'Acciones'].map(h => (
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
                                    {proformas.data.map(p => {
                                        const cfg = ESTADO_CONFIG[p.estado] ?? ESTADO_CONFIG.pendiente
                                        const esPendiente = p.estado === 'pendiente'
                                        return (
                                            <tr
                                                key={p.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className="px-4 py-3 font-mono text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                    {p.numero_completo}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(p.fecha_emision)}
                                                </td>
                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {p.fecha_vencimiento ? formatFecha(p.fecha_vencimiento) : '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {p.cliente ? (
                                                        <>
                                                            <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{p.cliente.razon_social}</p>
                                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{p.cliente.identificacion}</p>
                                                        </>
                                                    ) : (
                                                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(p.total)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={cfg.variant}>{cfg.label}</Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={route('ventas.proformas.show', p.id)}>
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                                style={{ color: 'var(--primary)' }}
                                                            >
                                                                <Eye className="w-3.5 h-3.5" />
                                                                Ver
                                                            </button>
                                                        </Link>
                                                        {esPendiente && puede('editar') && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-emerald-500/10 text-emerald-400"
                                                                onClick={() => handleConvertir(p)}
                                                            >
                                                                <ArrowRightLeft className="w-3.5 h-3.5" />
                                                                Convertir
                                                            </button>
                                                        )}
                                                        {esPendiente && puede('eliminar') && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-red-500/10 text-red-400"
                                                                onClick={() => void handleAnular(p)}
                                                            >
                                                                <Ban className="w-3.5 h-3.5" />
                                                                Anular
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

                    {/* Paginación */}
                    {proformas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {proformas.from}–{proformas.to} de {proformas.total}</span>
                            <div className="flex gap-1">
                                {proformas.links.map((link, i) => {
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
                                                'min-w-7 h-7 px-1.5 rounded text-xs font-medium transition-colors',
                                                link.active ? 'bg-(--primary) text-black' : 'hover:bg-amber-500/10',
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
            {modalConvertir && createPortal(
                <>
                    <div
                        className="fixed inset-0"
                        style={{ background: 'rgba(0,0,0,0.5)', zIndex: 50 }}
                        onClick={() => !convirtiendo && setModalConvertir(null)}
                    />
                    <div
                        className="fixed inset-0 flex items-center justify-center p-4"
                        style={{ zIndex: 51 }}
                    >
                        <div
                            className="w-full rounded-xl shadow-xl flex flex-col gap-4 p-5"
                            style={{ maxWidth: 480, background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Formas de Pago
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setModalConvertir(null)}
                                    className="p-1 rounded-md transition-colors"
                                    style={{ color: 'var(--text-muted)' }}
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>

                            <div
                                className="rounded-lg px-3 py-2 text-sm"
                                style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.3)' }}
                            >
                                <span style={{ color: 'var(--text-muted)' }}>Total a cobrar: </span>
                                <span className="font-bold" style={{ color: 'var(--primary)' }}>{formatMoneda(modalConvertir.total)}</span>
                            </div>

                            <div className="space-y-2">
                                {formasPago.map((p, idx) => (
                                    <div key={idx} className="flex items-center gap-2">
                                        <select
                                            className="h-8 rounded-md border px-2 text-sm flex-1"
                                            style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                            value={p.forma}
                                            onChange={e => {
                                                const next = [...formasPago]
                                                next[idx] = { ...next[idx], forma: e.target.value }
                                                setFormasPago(next)
                                            }}
                                        >
                                            {FORMAS_PAGO.map(f => (
                                                <option key={f} value={f}>{f}</option>
                                            ))}
                                        </select>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            className="h-8 rounded-md border px-2 text-sm w-28 text-right"
                                            style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                            value={p.monto}
                                            onChange={e => {
                                                const next = [...formasPago]
                                                next[idx] = { ...next[idx], monto: Number(e.target.value) }
                                                setFormasPago(next)
                                            }}
                                        />
                                        {formasPago.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => setFormasPago(prev => prev.filter((_, i) => i !== idx))}
                                                className="p-1 rounded hover:bg-red-500/10 transition-colors"
                                            >
                                                <Trash2 className="w-4 h-4 text-red-400" />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <button
                                type="button"
                                onClick={() => setFormasPago(prev => [...prev, { forma: 'efectivo', monto: 0 }])}
                                className="flex items-center gap-1 text-xs transition-colors hover:text-amber-500 w-fit"
                                style={{ color: 'var(--text-muted)' }}
                            >
                                <Plus className="w-3.5 h-3.5" />
                                Agregar forma de pago
                            </button>

                            {errorPago && (
                                <p className="text-xs" style={{ color: '#ef4444' }}>{errorPago}</p>
                            )}

                            <div className="flex justify-end gap-2 pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setModalConvertir(null)}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    loading={convirtiendo}
                                    onClick={handleConfirmarConversion}
                                >
                                    Convertir a Factura
                                </Button>
                            </div>
                        </div>
                    </div>
                </>,
                document.body
            )}
        </AppLayout>
    )
}
