import { useState } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import { isAxiosError } from 'axios'
import axios from '@/lib/axios'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import { cn, formatMoneda, formatFecha } from '@/lib/utils'
import {
    Plus, Search, Eye, Ban, Mail, Trash2, ChevronLeft, ChevronRight, FileText, AlertTriangle,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import { toastError } from '@/lib/toast'
import PdfIcon from '@/Components/shared/PdfIcon'
import PdfPreviewModal from '@/Components/shared/PdfPreviewModal'
import { ACCION_CLS, accionesFactura, enviarFacturaPorCorreo, enviarFacturaSri } from '@/lib/facturaAcciones'
import type { PageProps, PaginatedData } from '@/types'

// ── Tipos locales ─────────────────────────────────────────────────────────────

interface FacturaPago {
    forma_pago: string
    monto: number
}

interface FacturaCliente {
    razon_social: string
    identificacion: string
    email?: string | null
}

interface Factura {
    id: number
    numero_completo: string
    fecha_emision: string
    total: number
    estado: 'activa' | 'anulada'
    estado_sri: 'pendiente' | 'recibida' | 'autorizada' | 'rechazada' | 'anulada'
    email_enviado?: boolean
    tiene_descuento_especial: boolean
    email_cliente?: string | null
    identificacion?: string | null
    razon_social?: string | null
    tipo?: number
    cliente_nuevo?: boolean
    desc_pct?: number
    vendedor?: string | null
    // A9 (CHECKLIST_ERRORES_COMPLICACIONES.md): aviso, no bloqueo — factura
    // activa con algún producto físico y sin guía de remisión activa.
    sin_guia?: boolean
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

const SRI_CONFIG: Record<string, { label: string; variant: 'secondary' | 'info' | 'success' | 'danger' | 'warning' }> = {
    pendiente:  { label: 'No enviada', variant: 'secondary' },
    recibida:   { label: 'Recibida',   variant: 'info' },
    autorizada: { label: 'Autorizada', variant: 'success' },
    rechazada:  { label: 'Rechazada',  variant: 'danger' },
    anulada:    { label: 'Anulada',    variant: 'warning' },
}

const FORMA_PAGO_TEXTO: Record<string, string> = {
    efectivo:      'Efectivo',
    transferencia: 'Transferencia bancaria',
    tarjeta:       'Tarjeta de crédito',
    cheque:        'Cheque',
    credito:       'Crédito',
}

/** Formas de pago legibles; tarjeta y crédito en rojo subrayado. */
function formaPagoTexto(pagos: FacturaPago[]): React.ReactNode {
    if (pagos.length === 0) return '—'
    return pagos.map((p, i) => {
        const resaltar = p.forma_pago === 'tarjeta' || p.forma_pago === 'credito'
        return (
            <span key={i}>
                {i > 0 && ' / '}
                <span
                    className={resaltar ? 'font-semibold underline text-red-500' : undefined}
                    style={resaltar ? undefined : { color: 'var(--text-main)' }}
                >
                    {FORMA_PAGO_TEXTO[p.forma_pago] ?? p.forma_pago}
                </span>
            </span>
        )
    })
}

function esMismoDia(fecha: string): boolean {
    const hoy = new Date()
    const hoyLocal = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`
    return fecha.startsWith(hoyLocal)
}

// ── Componente ────────────────────────────────────────────────────────────────

export default function Index() {
    const { facturas, filtros, auth } = usePage<Props>().props
    const { puede } = usePermiso('ventas')
    const esSuperAdmin = auth.user?.perfil === 'super_admin'

    // Eliminación total (solo SuperAdmin): exige escribir el número de la factura.
    const handleEliminar = async (factura: Factura) => {
        const { value: confirmacion } = await Swal.fire({
            title: 'Eliminar factura',
            html:
                `<p style="margin-bottom:12px;font-size:14px;text-align:left;">` +
                `Se eliminará la factura <b>${factura.numero_completo}</b> con sus detalles, pagos y cuenta por cobrar, ` +
                `se devolverá el stock y se revertirá su asiento contable. <b>No se puede deshacer.</b></p>` +
                `<p style="margin-bottom:6px;font-size:13px;text-align:left;">Escriba el número de la factura para confirmar:</p>`,
            input: 'text',
            inputPlaceholder: factura.numero_completo,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Eliminar definitivamente',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            inputValidator: v => (v?.trim() === factura.numero_completo ? undefined : 'El número no coincide.'),
        })
        if (!confirmacion) return

        router.delete(route('ventas.facturas.destroy', factura.id), {
            data: { confirmacion },
            preserveScroll: true,
            onError: errors => Object.values(errors).forEach(msg => { if (msg) toastError(msg) }),
        })
    }

    const [ride, setRide] = useState<{ id: number; numero: string } | null>(null)

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
            const { data } = await axios.post<{ valido: boolean; aprobacion_id?: number; mensaje?: string }>(
                route('ventas.aprobacion.validar'),
                { tipo: 'anulacion_factura', codigo: formValues.codigo, motivo: formValues.motivo },
            )

            if (!data.valido || !data.aprobacion_id) {
                void Swal.fire('Código incorrecto', data.mensaje ?? 'La aprobación no es válida.', 'error')
                return
            }

            router.patch(
                route('ventas.facturas.anular', factura.id),
                { aprobacion_especial_id: data.aprobacion_id },
                { preserveState: true },
            )
        } catch (error) {
            // Con axios, un 4xx/5xx rechaza la promesa (a diferencia de fetch())
            // — el backend ya manda el motivo real en el body ({mensaje: "..."}),
            // así que se lee de ahí en vez de mostrar siempre el genérico. El
            // genérico queda solo para cuando no hay respuesta del servidor
            // (conexión caída), que es cuando error.response no existe.
            const mensaje = isAxiosError<{ mensaje?: string }>(error) && error.response
                ? error.response.data?.mensaje
                : undefined
            void Swal.fire('Error', mensaje ?? 'Error de conexión. Intente nuevamente.', 'error')
        }
    }

    return (
        <AppLayout>
            <Head title="Facturas" />
            <PageHeader
                title="Facturas"
                breadcrumbs={[
                    { label: 'Ventas' },
                    { label: 'Facturas' },
                ]}
                actions={
                    puede('crear') ? (
                        <Link href={route('ventas.facturas.create')}>
                            <Button>
                                <Plus className="w-4 h-4" />
                                Nueva Factura
                            </Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="p-6 space-y-4">
                {/* Barra de filtros */}
                <div className="filter-toolbar flex items-end gap-3 mb-4 flex-nowrap overflow-x-auto">
                    <select value={filtro.estado} onChange={e => setFiltro(p => ({ ...p, estado: e.target.value }))}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los estados</option>
                        <option value="activa">Activa</option>
                        <option value="anulada">Anulada</option>
                    </select>

                    <div className="flex items-center gap-1.5 shrink-0">
                        <label className="text-xs font-medium whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>DESDE:</label>
                        <input type="date" value={filtro.fecha_desde}
                            onChange={e => setFiltro(p => ({ ...p, fecha_desde: e.target.value }))}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }} />
                    </div>
                    <div className="flex items-center gap-1.5 shrink-0">
                        <label className="text-xs font-medium whitespace-nowrap" style={{ color: 'var(--text-muted)' }}>AL:</label>
                        <input type="date" value={filtro.fecha_hasta}
                            onChange={e => setFiltro(p => ({ ...p, fecha_hasta: e.target.value }))}
                            onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                            className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }} />
                    </div>

                    <div className="flex shrink-0 ml-auto" role="group">
                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={filtro.cliente}
                                onChange={e => setFiltro(p => ({ ...p, cliente: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                                placeholder="Cliente, RUC, producto, N° factura..."
                                className="pl-9 w-52 rounded-r-none border-r-0"
                            />
                        </div>
                        <button type="button"
                            className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
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
                                        {['No', 'Tipo', 'Fecha', 'Fac. No', 'Cliente', 'Nuevo', 'V. Total', 'Desc/Max', 'Vendedor', 'Forma de pago', 'SRI', 'E-mail', 'Acciones'].map(h => (
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
                                    {facturas.data.map((f, idx) => {
                                        const sriCfg  = SRI_CONFIG[f.estado_sri]  ?? SRI_CONFIG.pendiente
                                        const acc = accionesFactura(f)
                                        const puedeAnular = f.estado === 'activa' && esMismoDia(f.fecha_emision)
                                        const celda = 'px-3 py-3 text-[10px]'

                                        return (
                                            <tr
                                                key={f.id}
                                                className="hover:bg-amber-500/5 transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                            >
                                                <td className={celda} style={{ color: 'var(--text-muted)' }}>
                                                    {(facturas.from ?? 1) + idx}
                                                </td>

                                                <td
                                                    className={`${celda} font-semibold`}
                                                    style={{ color: 'var(--text-main)' }}
                                                    title={f.tipo === 2 ? 'Nota de venta' : 'Factura'}
                                                >
                                                    {f.tipo === 2 ? 'NV' : 'F'}
                                                </td>

                                                <td className={`${celda} whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                                    {formatFecha(f.fecha_emision)}
                                                </td>

                                                {/* Fac. No — subrayado si tiene descuento especial */}
                                                <td className={`${celda} whitespace-nowrap`}>
                                                    <span className="inline-flex items-center gap-1">
                                                        <span
                                                            className={cn(
                                                                'font-mono font-medium',
                                                                f.tiene_descuento_especial && 'underline decoration-dotted decoration-amber-500'
                                                            )}
                                                            style={{ color: 'var(--text-main)' }}
                                                            title={f.tiene_descuento_especial ? 'Contiene descuento especial autorizado' : undefined}
                                                        >
                                                            {f.numero_completo}
                                                        </span>
                                                        {f.sin_guia && (
                                                            <span title="Sin guía de remisión" className="shrink-0 cursor-help">
                                                                <AlertTriangle size={12} className="text-orange-500" />
                                                            </span>
                                                        )}
                                                    </span>
                                                </td>

                                                <td className={celda}>
                                                    <p className="font-medium uppercase" style={{ color: 'var(--text-main)' }}>
                                                        {f.razon_social ?? f.cliente?.razon_social ?? '—'}
                                                    </p>
                                                    <p style={{ color: 'var(--text-muted)' }}>
                                                        {f.identificacion ?? f.cliente?.identificacion ?? ''}
                                                    </p>
                                                </td>

                                                <td className={`${celda} font-semibold`} style={{ color: 'var(--text-main)' }}>
                                                    {f.cliente_nuevo ? 'SI' : ''}
                                                </td>

                                                <td className={`${celda} text-right font-bold whitespace-nowrap`} style={{ color: 'var(--text-main)' }}>
                                                    {formatMoneda(f.total)}
                                                </td>

                                                <td className={`${celda} text-right whitespace-nowrap`} style={{ color: 'var(--text-muted)' }}>
                                                    {Number(f.desc_pct ?? 0).toFixed(2)}%
                                                </td>

                                                <td className={`${celda} uppercase`} style={{ color: 'var(--text-main)' }}>
                                                    {f.vendedor ?? '—'}
                                                </td>

                                                <td className={`${celda} uppercase`}>
                                                    {formaPagoTexto(f.pagos)}
                                                </td>

                                                <td className={celda}>
                                                    <Badge variant={sriCfg.variant} className="text-[10px]">{sriCfg.label}</Badge>
                                                </td>

                                                <td className={`${celda} font-semibold`}>
                                                    {f.email_enviado
                                                        ? <span className="text-emerald-400">ENVIADO</span>
                                                        : <span style={{ color: 'var(--text-muted)' }}>—</span>}
                                                </td>

                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-1">
                                                        <Link href={route('ventas.facturas.show', f.id)}>
                                                            <button
                                                                type="button"
                                                                className="flex items-center gap-1 px-2 py-1 rounded text-xs transition-colors hover:bg-amber-500/10"
                                                                style={{ color: 'var(--primary)' }}
                                                                title="Ver detalle" aria-label="Ver detalle"
                                                            >
                                                                <Eye className="w-4 h-4" />
                                                            </button>
                                                        </Link>
                                                        {acc.sri && puede('editar') && (
                                                            <button
                                                                type="button"
                                                                className={`flex items-center justify-center p-1.5 rounded transition-colors ${ACCION_CLS.sri.texto} ${ACCION_CLS.sri.fondo}`}
                                                                onClick={() => void enviarFacturaSri(f.id)}
                                                                title="Enviar al SRI" aria-label="Enviar al SRI"
                                                            >
                                                                <span className="text-[10px] font-bold leading-4">SRI</span>
                                                            </button>
                                                        )}
                                                        {acc.correo && puede('editar') && (
                                                            <button
                                                                type="button"
                                                                className={`flex items-center justify-center p-1.5 rounded transition-colors ${ACCION_CLS.correo.texto} ${ACCION_CLS.correo.fondo}`}
                                                                onClick={() => void enviarFacturaPorCorreo(f.id, f.numero_completo, f.email_cliente ?? f.cliente?.email)}
                                                                title="Enviar por correo" aria-label="Enviar por correo"
                                                            >
                                                                <Mail className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                        <a
                                                            href={route('ventas.facturas.xml', f.id)}
                                                            className={`flex items-center justify-center p-1.5 rounded transition-colors ${ACCION_CLS.xml.texto} ${ACCION_CLS.xml.fondo}`}
                                                            title="Descargar XML" aria-label="Descargar XML"
                                                        >
                                                            <span className="text-[10px] font-bold leading-4">XML</span>
                                                        </a>
                                                        <button
                                                            type="button"
                                                            className={`flex items-center justify-center p-1.5 rounded transition-colors ${ACCION_CLS.ride.texto} ${ACCION_CLS.ride.fondo}`}
                                                            onClick={() => setRide({ id: f.id, numero: f.numero_completo })}
                                                            title="Ver RIDE (PDF)" aria-label="Ver RIDE (PDF)"
                                                        >
                                                            <PdfIcon className="w-5 h-5" />
                                                        </button>
                                                        {puedeAnular && puede('anular') && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center justify-center p-1.5 rounded transition-colors hover:bg-red-500/10 text-red-400"
                                                                onClick={() => handleAnular(f)}
                                                                title="Anular factura" aria-label="Anular factura"
                                                            >
                                                                <Ban className="w-4 h-4" />
                                                            </button>
                                                        )}
                                                        {esSuperAdmin && f.estado_sri !== 'autorizada' && f.estado_sri !== 'recibida' && (
                                                            <button
                                                                type="button"
                                                                className="flex items-center justify-center p-1.5 rounded transition-colors hover:bg-red-600/15 text-red-600"
                                                                onClick={() => void handleEliminar(f)}
                                                                title="Eliminar factura (solo SuperAdmin)" aria-label="Eliminar factura"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
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
            <PdfPreviewModal
                abierto={ride !== null}
                onCerrar={() => setRide(null)}
                url={ride ? route('ventas.facturas.ride', ride.id) : ''}
                titulo={`RIDE — Factura ${ride?.numero ?? ''}`}
                nombreDescarga={`RIDE-${ride?.numero ?? ''}.pdf`}
            />
        </AppLayout>
    )
}
