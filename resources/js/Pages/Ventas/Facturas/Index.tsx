import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import {
    Plus, Search, Eye, Ban, ChevronLeft, ChevronRight, FileText,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, PaginatedData } from '@/types'

// ── Tipos locales ─────────────────────────────────────────────────────────────

interface FacturaPago {
    forma_pago: string
    monto: number
}

interface FacturaCliente {
    razon_social: string
    identificacion: string
}

interface Factura {
    id: number
    numero_completo: string
    fecha_emision: string
    total: number
    estado: 'activa' | 'anulada'
    estado_sri: 'pendiente' | 'autorizada' | 'rechazada' | 'anulada'
    tiene_descuento_especial: boolean
    cliente: FacturaCliente | null
    pagos: FacturaPago[]
}

interface Filtros {
    [key: string]: string | undefined
    fecha_desde?: string
    fecha_hasta?: string
    cliente?: string
    estado?: string
    estado_sri?: string
}

interface Props extends PageProps {
    facturas: PaginatedData<Factura>
    filtros: Filtros
}

// ── Helpers ───────────────────────────────────────────────────────────────────

const SRI_CONFIG: Record<string, { label: string; variant: 'secondary' | 'success' | 'danger' | 'warning' }> = {
    pendiente:  { label: 'Pendiente',  variant: 'secondary' },
    autorizada: { label: 'Autorizada', variant: 'success' },
    rechazada:  { label: 'Rechazada',  variant: 'danger' },
    anulada:    { label: 'Anulada',    variant: 'warning' },
}

const ESTADO_CONFIG: Record<string, { label: string; variant: 'success' | 'danger' }> = {
    activa:  { label: 'Activa',  variant: 'success' },
    anulada: { label: 'Anulada', variant: 'danger' },
}

function formaPagoResumen(pagos: FacturaPago[]): string {
    if (pagos.length === 0) return '—'
    if (pagos.length === 1) return pagos[0].forma_pago
    return 'Múltiple'
}

function esMismoDia(fecha: string): boolean {
    const hoy = new Date()
    const hoyLocal = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`
    return fecha.startsWith(hoyLocal)
}

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
}

// ── Componente ────────────────────────────────────────────────────────────────

export default function Index() {
    const { facturas, filtros } = usePage<Props>().props
    const { puede } = usePermiso('ventas')

    const [filtro, setFiltro] = useState<Filtros>({
        fecha_desde: filtros.fecha_desde ?? '',
        fecha_hasta: filtros.fecha_hasta ?? '',
        cliente:     filtros.cliente     ?? '',
        estado:      filtros.estado      ?? '',
        estado_sri:  filtros.estado_sri  ?? '',
    })

    const aplicarFiltros = () => {
        router.get(route('ventas.facturas.index'), filtro as Record<string, string | undefined>, { preserveState: true })
    }

    const limpiarFiltros = () => {
        const limpio: Filtros = { fecha_desde: '', fecha_hasta: '', cliente: '', estado: '', estado_sri: '' }
        setFiltro(limpio)
        router.get(route('ventas.facturas.index'), {}, { preserveState: false })
    }

    const handleAnular = async (factura: Factura) => {
        const { value: formValues } = await Swal.fire({
            title: 'Anular factura',
            html:
                `<p style="margin-bottom:12px;font-size:14px;text-align:left;">` +
                `¿Desea anular la factura ${factura.numero_completo}? ` +
                `Requiere una aprobación especial de SuperAdmin.</p>` +
                `<input id="swal-codigo" type="password" class="swal2-input" placeholder="Código de aprobación">` +
                `<input id="swal-motivo" type="text" class="swal2-input" placeholder="Motivo de la anulación">`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Validar y anular',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
            focusConfirm: false,
            preConfirm: () => {
                const codigo = (document.getElementById('swal-codigo') as HTMLInputElement | null)?.value ?? ''
                const motivo = (document.getElementById('swal-motivo') as HTMLInputElement | null)?.value ?? ''
                if (!codigo.trim() || !motivo.trim()) {
                    Swal.showValidationMessage('Ingrese el código y el motivo.')
                    return false
                }
                return { codigo, motivo }
            },
        })
        if (!formValues) return

        try {
            const res = await fetch(route('ventas.aprobacion.validar'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    tipo: 'anulacion_factura',
                    codigo: formValues.codigo,
                    motivo: formValues.motivo,
                }),
            })
            const data = await res.json() as { valido: boolean; aprobacion_id?: number; mensaje?: string }
            if (!data.valido || !data.aprobacion_id) {
                void Swal.fire('Código inválido', data.mensaje ?? 'Código incorrecto.', 'error')
                return
            }
            router.patch(
                route('ventas.facturas.anular', factura.id),
                { aprobacion_especial_id: data.aprobacion_id },
                { preserveState: true },
            )
        } catch {
            void Swal.fire('Error', 'Error de conexión. Intente nuevamente.', 'error')
        }
    }

    const hayFiltros = Object.values(filtro).some(v => v !== '')

    return (
        <AppLayout>
            <Head title="Facturas" />
            <PageHeader
                title="Facturas"
                description="Gestión de facturas de venta"
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Facturas' },
                ]}
                actions={
                    <div className="flex items-center gap-3">
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {facturas.total} factura{facturas.total === 1 ? '' : 's'}
                        </span>
                        {puede('crear') && (
                            <Link href={route('ventas.facturas.create')}>
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
                    <div>
                        <Input
                            type="date"
                            value={filtro.fecha_desde}
                            onChange={e => setFiltro(p => ({ ...p, fecha_desde: e.target.value }))}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            className="shrink-0 w-36"
                            title="Desde"
                        />
                    </div>
                    <div>
                        <Input
                            type="date"
                            value={filtro.fecha_hasta}
                            onChange={e => setFiltro(p => ({ ...p, fecha_hasta: e.target.value }))}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            className="shrink-0 w-36"
                            title="Hasta"
                        />
                    </div>
                    <select
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}
                        value={filtro.estado}
                        onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                    >
                        <option value="">Todos los estados</option>
                        <option value="activa">Activa</option>
                        <option value="anulada">Anulada</option>
                    </select>
                    <select
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}
                        value={filtro.estado_sri}
                        onChange={e => setFiltro(p => ({ ...p, estado_sri: e.target.value }))}
                    >
                        <option value="">Todos los estados SRI</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="autorizada">Autorizada</option>
                        <option value="rechazada">Rechazada</option>
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
                    {facturas.data.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-20 gap-3">
                            <FileText className="w-12 h-12 opacity-20" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No se encontraron facturas
                            </p>
                            {puede('crear') && (
                                <Link href={route('ventas.facturas.create')}>
                                    <Button size="sm">
                                        <Plus className="w-4 h-4" />
                                        Nueva Factura
                                    </Button>
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr style={{ borderBottom: '1px solid var(--border)', background: 'rgba(0,0,0,.04)' }}>
                                        {['Número', 'Fecha', 'Cliente', 'Total', 'Forma pago', 'Estado SRI', 'Estado', 'Acciones'].map(h => (
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
                                    {facturas.data.map(f => {
                                        const sriCfg  = SRI_CONFIG[f.estado_sri]  ?? SRI_CONFIG.pendiente
                                        const estCfg  = ESTADO_CONFIG[f.estado]   ?? ESTADO_CONFIG.activa
                                        const puedeAnular = f.estado === 'activa' && esMismoDia(f.fecha_emision)

                                        return (
                                            <tr
                                                key={f.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                {/* Número — subrayado si tiene descuento especial */}
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={cn(
                                                            'font-mono text-xs font-medium',
                                                            f.tiene_descuento_especial && 'underline decoration-dotted decoration-amber-500'
                                                        )}
                                                        style={{ color: 'var(--text-main)' }}
                                                        title={f.tiene_descuento_especial ? 'Contiene descuento especial autorizado' : undefined}
                                                    >
                                                        {f.numero_completo}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(f.fecha_emision)}
                                                </td>

                                                <td className="px-4 py-3">
                                                    {f.cliente ? (
                                                        <>
                                                            <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                                                {f.cliente.razon_social}
                                                            </p>
                                                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                                                {f.cliente.identificacion}
                                                            </p>
                                                        </>
                                                    ) : (
                                                        <span style={{ color: 'var(--text-muted)' }}>—</span>
                                                    )}
                                                </td>

                                                <td className="px-4 py-3 text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(f.total)}
                                                </td>

                                                <td className="px-4 py-3 text-xs capitalize" style={{ color: 'var(--text-muted)' }}>
                                                    {formaPagoResumen(f.pagos)}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Badge variant={sriCfg.variant}>{sriCfg.label}</Badge>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <Badge variant={estCfg.variant}>{estCfg.label}</Badge>
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={route('ventas.facturas.show', f.id)}>
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                                style={{ color: 'var(--primary)' }}
                                                                title="Ver detalle"
                                                            >
                                                                <Eye className="w-3.5 h-3.5" />
                                                                Ver
                                                            </button>
                                                        </Link>
                                                        {puedeAnular && puede('anular') && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-red-500/10 text-red-400"
                                                                onClick={() => handleAnular(f)}
                                                                title="Anular factura"
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
                    {facturas.last_page > 1 && (
                        <div
                            className="flex items-center justify-between px-4 py-3 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            <span>
                                Mostrando {facturas.from}–{facturas.to} de {facturas.total}
                            </span>
                            <div className="flex gap-1">
                                {facturas.links.map((link, i) => {
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
                                                link.active
                                                    ? 'bg-(--primary) text-black'
                                                    : 'hover:bg-amber-500/10',
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
        </AppLayout>
    )
}
