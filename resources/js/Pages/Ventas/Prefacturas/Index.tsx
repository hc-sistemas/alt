import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import { Plus, Search, Eye, FileText, ChevronLeft, ChevronRight, ArrowRightLeft, Ban, Trash2, HandCoins } from 'lucide-react'
import AccionIcono, { COLOR_ACCION } from '@/Components/shared/AccionIcono'
import PdfIcon from '@/Components/shared/PdfIcon'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { anularConPin, eliminarDocumento } from '@/lib/ventasDocumentos'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, PaginatedData } from '@/types'

interface PrefacturaCliente {
    razon_social: string
    identificacion: string
}

interface Prefactura {
    id: number
    numero: string
    fecha_emision: string
    total: number
    total_abonado: number
    saldo_pendiente: number
    estado: 'pendiente' | 'parcial' | 'liquidada' | 'anulada'
    factura_id?: number | null
    cliente_nuevo?: boolean
    desc_pct?: number
    vendedor?: string | null
    cliente: PrefacturaCliente | null
}

interface Filtros {
    estado?: string
    cliente?: string
    fecha_desde?: string
    fecha_hasta?: string
}

interface Props extends PageProps {
    prefacturas: PaginatedData<Prefactura>
    filtros: Filtros
}

const ESTADO_CONFIG = {
    pendiente: { label: 'Pendiente', variant: 'warning'   as const },
    parcial:   { label: 'Parcial',   variant: 'info'      as const },
    liquidada: { label: 'Liquidada', variant: 'success'   as const },
    anulada:   { label: 'Anulada',   variant: 'secondary' as const },
}

export default function Index() {
    const { prefacturas, filtros, auth } = usePage<Props>().props
    const { puede } = usePermiso('ventas')
    const esSuperAdmin = auth.user?.perfil === 'super_admin'
    const [pdf, setPdf] = useState<{ id: number; numero: string } | null>(null)

    const [filtro, setFiltro] = useState<Filtros>({
        estado:  filtros.estado  ?? '',
        cliente: filtros.cliente ?? '',
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.prefacturas.index'), filtro as Record<string, string | undefined>, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { estado: '', cliente: '', fecha_desde: '', fecha_hasta: '' }
        setFiltro(limpio)
        router.get(route('ventas.prefacturas.index'), limpio as Record<string, string>, { preserveState: false })
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    const handleConvertir = async (pf: Prefactura) => {
        const result = await Swal.fire({
            title: 'Crear Factura',
            text: `La prefactura ${pf.numero} está liquidada. ¿Desea generar la factura correspondiente?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, crear factura',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!result.isConfirmed) return
        router.post(route('ventas.prefacturas.convertir', pf.id))
    }

    const handleAnular = (pf: Prefactura) =>
        anularConPin({
            url: route('ventas.prefacturas.anular', pf.id),
            etiqueta: 'prefactura',
            numero: pf.numero,
            aviso: 'Se liberará el stock que tiene apartado.',
        })

    const handleEliminar = (pf: Prefactura) =>
        eliminarDocumento({
            url: route('ventas.prefacturas.destroy', pf.id),
            etiqueta: 'prefactura',
            numero: pf.numero,
            detalle: ' con sus detalles (se libera el stock apartado)',
        })

    return (
        <AppLayout>
            <Head title="Prefacturas" />
            <PageHeader
                title="Prefacturas / Reservas"
                description="Gestión de prefacturas y anticipos"
                breadcrumbs={[{ label: 'Ventas' }, { label: 'Prefacturas' }]}
                actions={
                    <div className="flex items-center gap-3">
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {prefacturas.total} prefactura{prefacturas.total === 1 ? '' : 's'}
                        </span>
                        {puede('crear') && (
                            <Link href={route('ventas.prefacturas.create')}>
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
                <div className="filter-toolbar flex items-end gap-3 flex-wrap">
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
                        <option value="parcial">Parcial</option>
                        <option value="liquidada">Liquidada</option>
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
                                placeholder="Cliente, RUC, producto, N° prefactura..."
                                className="pl-9 w-72 rounded-r-none border-r-0"
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
                    {prefacturas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron prefacturas</p>
                            {puede('crear') && (
                                <Link href={route('ventas.prefacturas.create')}>
                                    <Button size="sm">
                                        <Plus className="w-4 h-4" />
                                        Nueva Prefactura
                                    </Button>
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['No', 'Fecha', 'Pre. No', 'Cliente', 'Nuevo', 'V. Total', 'Desc/Max', 'Abonado', 'Saldo', 'Vendedor', 'Estado', 'Acciones'].map(h => (
                                            <th
                                                key={h}
                                                className="text-left px-3 py-3 text-[10px] font-semibold uppercase tracking-wide whitespace-nowrap"
                                                style={{ color: 'var(--text-muted)' }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {prefacturas.data.map((pf, idx) => {
                                        const cfg = ESTADO_CONFIG[pf.estado] ?? ESTADO_CONFIG.pendiente
                                        const celda = 'px-3 py-3 text-[10px]'
                                        const abierta = pf.estado === 'pendiente' || pf.estado === 'parcial'
                                        const puedeConvertir = Number(pf.saldo_pendiente) === 0 && pf.estado !== 'anulada' && !pf.factura_id
                                        return (
                                            <tr
                                                key={pf.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className={celda} style={{ color: 'var(--text-muted)' }}>
                                                    {(prefacturas.from ?? 1) + idx}
                                                </td>
                                                <td className={`${celda} whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(pf.fecha_emision)}
                                                </td>
                                                <td className={`${celda} font-mono font-medium whitespace-nowrap`} style={{ color: 'var(--text-main)' }}>
                                                    {pf.numero}
                                                </td>
                                                <td className={celda}>
                                                    {pf.cliente ? (
                                                        <>
                                                            <p className="font-medium uppercase" style={{ color: 'var(--text-main)' }}>{pf.cliente.razon_social}</p>
                                                            <p style={{ color: 'var(--text-muted)' }}>{pf.cliente.identificacion}</p>
                                                        </>
                                                    ) : (
                                                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                                                    )}
                                                </td>
                                                <td className={`${celda} font-semibold`} style={{ color: 'var(--text-main)' }}>
                                                    {pf.cliente_nuevo ? 'SI' : ''}
                                                </td>
                                                <td className={`${celda} text-right font-bold whitespace-nowrap`} style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(pf.total)}
                                                </td>
                                                <td className={`${celda} text-right whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                                    {Number(pf.desc_pct ?? 0).toFixed(2)}%
                                                </td>
                                                <td className={`${celda} text-right text-emerald-400 font-medium whitespace-nowrap`}>
                                                    {formatMoneda(pf.total_abonado)}
                                                </td>
                                                <td
                                                    className={`${celda} text-right font-semibold whitespace-nowrap`}
                                                    style={{ color: pf.saldo_pendiente > 0 ? 'var(--primary)' : 'var(--text-muted)' }}
                                                >
                                                    {formatMoneda(pf.saldo_pendiente)}
                                                </td>
                                                <td className={`${celda} uppercase`} style={{ color: 'var(--text-main)' }}>
                                                    {pf.vendedor ?? '—'}
                                                </td>
                                                <td className={celda}>
                                                    <Badge variant={cfg.variant} className="text-[10px]">{cfg.label}</Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex items-center gap-0.5">
                                                        <AccionIcono titulo="Ver detalle" color={COLOR_ACCION.ver} href={route('ventas.prefacturas.show', pf.id)}>
                                                            <Eye className="w-4 h-4" />
                                                        </AccionIcono>
                                                        <AccionIcono titulo="Ver PDF" color={COLOR_ACCION.pdf} onClick={() => setPdf({ id: pf.id, numero: pf.numero })}>
                                                            <PdfIcon className="w-5 h-5" />
                                                        </AccionIcono>
                                                        {abierta && puede('editar') && (
                                                            <AccionIcono
                                                                titulo="Registrar abono"
                                                                color={COLOR_ACCION.abonar}
                                                                href={route('ventas.prefacturas.show', { prefactura: pf.id, abonar: 1 })}
                                                            >
                                                                <HandCoins className="w-4 h-4" />
                                                            </AccionIcono>
                                                        )}
                                                        {puedeConvertir && puede('editar') && (
                                                            <AccionIcono titulo="Convertir a factura" color={COLOR_ACCION.convertir} onClick={() => void handleConvertir(pf)}>
                                                                <ArrowRightLeft className="w-4 h-4" />
                                                            </AccionIcono>
                                                        )}
                                                        {abierta && puede('anular') && (
                                                            <AccionIcono titulo="Anular prefactura" color={COLOR_ACCION.anular} onClick={() => void handleAnular(pf)}>
                                                                <Ban className="w-4 h-4" />
                                                            </AccionIcono>
                                                        )}
                                                        {esSuperAdmin && !pf.factura_id && (
                                                            <AccionIcono titulo="Eliminar prefactura (solo SuperAdmin)" color={COLOR_ACCION.eliminar} onClick={() => void handleEliminar(pf)}>
                                                                <Trash2 className="w-4 h-4" />
                                                            </AccionIcono>
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

                    {prefacturas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>Mostrando {prefacturas.from}–{prefacturas.to} de {prefacturas.total}</span>
                            <div className="flex gap-1">
                                {prefacturas.links.map((link, i) => {
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
            <PdfPreviewModal
                abierto={pdf !== null}
                onCerrar={() => setPdf(null)}
                url={pdf ? route('ventas.prefacturas.pdf', pdf.id) : ''}
                titulo={`Prefactura ${pdf?.numero ?? ''}`}
                nombreDescarga={`Prefactura-${pdf?.numero ?? ''}.pdf`}
            />
        </AppLayout>
    )
}
