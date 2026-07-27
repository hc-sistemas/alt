import { useState, useEffect } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Button } from '@/Components/ui/button'
import { cn } from '@/lib/utils'
import { X, FileText, Download, CreditCard, XCircle } from 'lucide-react'
import type { PageProps, Proveedor, BancoCaja } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CxPRow {
    id: number
    compra_id: number | null
    proveedor: string | null
    num_documento: string | null
    monto: number
    saldo: number
    fecha_emision: string | null
    fecha_vencimiento: string | null
    estado: 'pendiente' | 'parcial' | 'pagada'
    compra_anulada: boolean
    urgencia: 'vencida' | 'critica' | 'proxima' | 'normal'
    color_urgencia: string
    dias_vencimiento: number
}

interface Filtros {
    estado?: string
    proveedor_id?: string
    periodo?: string
    fecha_desde?: string
    fecha_hasta?: string
}

interface Props extends PageProps {
    cxp: CxPRow[]
    proveedores: Pick<Proveedor, 'id' | 'razon_social'>[]
    bancos: Pick<BancoCaja, 'id' | 'nombre' | 'tipo' | 'saldo_actual'>[]
    filtros: Filtros
}

type EscenarioAnulacion =
    | { escenario: 'A';       mensaje: string }
    | { escenario: 'B';       mensaje: string; monto: number; banco: string }
    | { escenario: 'C';       mensaje: string; productos_vendidos: Array<{ nombre: string; cantidad_salida: number }> }
    | { escenario: 'ANULADA'; mensaje: string }

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    success: (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error:   (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── SweetAlert ───────────────────────────────────────────────────────────────

const SWAL_CSS = `
    .swal-pop { border-radius:20px!important; padding:28px!important; box-shadow:0 25px 60px rgba(0,0,0,.25)!important; max-width:460px!important }
    .swal-title { font-size:1.1rem!important; font-weight:700!important; color:#1f2937!important; margin-bottom:16px!important }
    .swal-confirm { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
    .swal-cancel  { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
`
function injectSwalCss() {
    if (document.getElementById('swal-cxp')) return
    const s = document.createElement('style'); s.id = 'swal-cxp'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

// ─── Urgencia helpers ─────────────────────────────────────────────────────────

const URGENCIA_BORDER: Record<string, string> = {
    vencida: 'border-l-4 border-l-red-500',
    critica: 'border-l-4 border-l-orange-500',
    proxima: 'border-l-4 border-l-yellow-500',
    normal:  'border-l-4 border-l-green-500',
}

const URGENCIA_CHIP: Record<string, { bg: string; text: string }> = {
    vencida: { bg: 'bg-red-100 dark:bg-red-900/30',       text: 'text-red-700 dark:text-red-400' },
    critica: { bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
    proxima: { bg: 'bg-yellow-100 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400' },
    normal:  { bg: 'bg-green-100 dark:bg-green-900/30',   text: 'text-green-700 dark:text-green-400' },
}

function DiasChip({ dias, urgencia }: { dias: number; urgencia: string }) {
    const c = URGENCIA_CHIP[urgencia] ?? URGENCIA_CHIP.normal
    const label = dias < 0 ? `${Math.abs(dias)}d VENCIDA` : dias === 0 ? 'HOY' : `En ${dias}d`
    return (
        <span className={cn('px-2 py-0.5 rounded-full text-xs font-semibold', c.bg, c.text)}>
            {label}
        </span>
    )
}

// ─── Modal Pago ───────────────────────────────────────────────────────────────

interface FormPago {
    monto_pago: string
    banco_caja_id: string
    fecha_pago: string
    referencia: string
}

function ModalPago({ cxp, bancos, onClose }: {
    cxp: CxPRow
    bancos: Props['bancos']
    onClose: () => void
}) {
    const [form, setForm] = useState<FormPago>({
        monto_pago:    Number(cxp.saldo).toFixed(2),
        banco_caja_id: bancos[0]?.id?.toString() ?? '',
        fecha_pago:    new Date().toISOString().slice(0, 10),
        referencia:    '',
    })
    const [processing, setProcessing] = useState(false)

    const bancoBanco = bancos.find(b => b.id.toString() === form.banco_caja_id)

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setProcessing(true)
        router.post(route('compras.cxp.pagar', cxp.id), form as unknown as Record<string, string>, {
            onSuccess: () => { notify.success('Pago registrado correctamente'); onClose() },
            onError:   (errs) => { notify.error('Error: ' + Object.values(errs).join(', ')); setProcessing(false) },
            onFinish:  () => setProcessing(false),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-lg" onClick={e => e.stopPropagation()}>

                {/* Header */}
                <div className="modal-header">
                    <div>
                        <h2>Registrar pago</h2>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            {cxp.proveedor ?? '—'} · {cxp.num_documento ?? '—'}
                        </p>
                    </div>
                    <button className="modal-close" onClick={onClose}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Info panel */}
                <div className="mx-6 mt-4 rounded-lg p-3 space-y-1.5"
                    style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid rgba(245,158,11,0.25)' }}>
                    <div className="flex justify-between text-sm">
                        <span style={{ color: 'var(--text-muted)' }}>Monto original</span>
                        <span className="font-medium" style={{ color: 'var(--text-main)' }}>
                            ${Number(cxp.monto).toFixed(2)}
                        </span>
                    </div>
                    <div className="flex justify-between text-sm border-t pt-1.5" style={{ borderColor: 'rgba(245,158,11,0.3)' }}>
                        <span className="font-bold" style={{ color: 'var(--primary)' }}>Saldo pendiente</span>
                        <span className="font-bold" style={{ color: 'var(--primary)' }}>
                            ${Number(cxp.saldo).toFixed(2)}
                        </span>
                    </div>
                </div>

                <form onSubmit={submit}>
                <div className="modal-body">
                    <div className="space-y-1.5">
                        <Label>Monto a pagar <span className="text-red-400">*</span></Label>
                        <Input
                            type="number" step="0.01" min={0.01}
                            max={Number(cxp.saldo).toFixed(2)}
                            value={form.monto_pago}
                            onChange={e => setForm(f => ({ ...f, monto_pago: e.target.value }))}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Banco / Caja <span className="text-red-400">*</span></Label>
                        <select value={form.banco_caja_id}
                            onChange={e => setForm(f => ({ ...f, banco_caja_id: e.target.value }))}
                            className="input-field select-field">
                            {bancos.map(b => (
                                <option key={b.id} value={b.id}>
                                    {b.nombre} ({b.tipo}) — Saldo: ${Number(b.saldo_actual).toFixed(2)}
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
                        <Label>Fecha de pago <span className="text-red-400">*</span></Label>
                        <Input type="date" value={form.fecha_pago}
                            onChange={e => setForm(f => ({ ...f, fecha_pago: e.target.value }))} />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Referencia / N° transferencia</Label>
                        <Input value={form.referencia} placeholder="TRF-001, CHQ-001..."
                            onChange={e => setForm(f => ({ ...f, referencia: e.target.value }))} />
                    </div>

                </div>
                <div className="modal-footer">
                    <Button type="submit" disabled={processing}>
                        <CreditCard className="w-4 h-4" /> Registrar pago
                    </Button>
                    <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
                </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function CuentasPagarIndex() {
    const { cxp, proveedores, bancos, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('compras')

    const [buscar,      setBuscar]      = useState('')
    const [estado,      setEstado]      = useState(filtros.estado ?? '')
    const [proveedorId, setProveedorId] = useState(filtros.proveedor_id ?? '')
    const [periodo,     setPeriodo]     = useState(filtros.periodo ?? '')
    const [fechaDesde,  setFechaDesde]  = useState(filtros.fecha_desde ?? '')
    const [fechaHasta,  setFechaHasta]  = useState(filtros.fecha_hasta ?? '')
    const [modalPago,   setModalPago]   = useState<CxPRow | null>(null)
    const [modalPdf,    setModalPdf]    = useState(false)
    const [urlPdf,      setUrlPdf]      = useState('')
    const abrirPdf = (url: string) => { setUrlPdf(url); setModalPdf(true) }

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function aplicarFiltros() {
        router.get(route('compras.cxp.index'), {
            ...(estado      && { estado }),
            ...(proveedorId && { proveedor_id: proveedorId }),
            ...(periodo     && { periodo }),
            ...(fechaDesde  && { fecha_desde: fechaDesde }),
            ...(fechaHasta  && { fecha_hasta: fechaHasta }),
        }, { preserveState: true, replace: true })
    }

    function limpiar() {
        setEstado(''); setProveedorId(''); setPeriodo('')
        setFechaDesde(''); setFechaHasta('')
        router.get(route('compras.cxp.index'), {}, { preserveState: false })
    }

    async function iniciarAnulacion(c: CxPRow) {
        if (!c.compra_id) {
            notify.error('Esta cuenta por pagar no tiene compra asociada.')
            return
        }

        let escData: EscenarioAnulacion
        try {
            const res = await fetch(route('compras.facturas.verificar-anulacion', c.compra_id), {
                headers: { Accept: 'application/json' },
            })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: 'Error del servidor' })) as { message?: string }
                notify.error(err.message ?? 'Error al verificar la compra')
                return
            }
            escData = await res.json() as EscenarioAnulacion
        } catch {
            notify.error('Error al verificar el estado de la compra.')
            return
        }

        if (escData.escenario === 'ANULADA') {
            notify.error(escData.mensaje)
            return
        }

        if (escData.escenario === 'C') {
            const listaHtml = escData.productos_vendidos
                .map(d => `<li><b>${d.nombre}</b> — ${d.cantidad_salida} unid. vendidas</li>`)
                .join('')
            void Swal.fire({
                icon: 'error',
                title: 'No se puede anular',
                html: `<p style="color:#6b7280;font-size:13px;margin-bottom:12px">${escData.mensaje}</p>
                       <ul style="text-align:left;font-size:13px;color:#374151;line-height:1.8;padding-left:16px">
                           ${listaHtml}
                       </ul>`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#ef4444',
                showCancelButton: false,
                customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm' },
                didOpen: injectSwalCss,
            })
            return
        }

        if (escData.escenario === 'A') {
            const result = await Swal.fire({
                ...swalBase,
                title: 'Anular compra',
                html: `<p style="color:#6b7280;font-size:14px;margin-bottom:10px">
                           <strong>${c.num_documento ?? '—'}</strong> · ${c.proveedor ?? '—'}
                       </p>
                       <p style="color:#374151;font-size:13px">${escData.mensaje}</p>`,
                input: 'textarea',
                inputPlaceholder: 'Motivo de la anulación (mínimo 10 caracteres)…',
                inputAttributes: { rows: '3', style: 'font-size:13px' },
                confirmButtonText: 'Anular',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor:  '#6b7280',
                inputValidator: (v) => (!v || v.trim().length < 10) ? 'El motivo debe tener al menos 10 caracteres.' : null,
            })
            if (!result.isConfirmed) return
            router.patch(route('compras.facturas.anular', c.compra_id), { motivo: result.value as string }, {
                onSuccess: () => notify.success(`Compra ${c.num_documento ?? ''} anulada correctamente.`),
                onError:   (e)  => notify.error(Object.values(e)[0] ?? 'Error al anular'),
            })
            return
        }

        if (escData.escenario === 'B') {
            const result = await Swal.fire({
                ...swalBase,
                title: 'Anular compra con pago',
                html: `<p style="color:#6b7280;font-size:13px;margin-bottom:12px">
                           <strong>${c.num_documento ?? '—'}</strong> · ${c.proveedor ?? '—'}
                       </p>
                       <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:10px 14px;margin-bottom:12px;text-align:left">
                           <p style="font-size:12px;color:#92400e;font-weight:600;margin-bottom:4px">Pago registrado</p>
                           <p style="font-size:12px;color:#78350f">
                               Banco/Caja: <b>${escData.banco}</b><br>
                               Monto a reversar: <b>$${escData.monto.toFixed(2)}</b>
                           </p>
                       </div>
                       <p style="color:#374151;font-size:13px">${escData.mensaje}</p>`,
                input: 'textarea',
                inputPlaceholder: 'Motivo de la anulación (mínimo 10 caracteres)…',
                inputAttributes: { rows: '3', style: 'font-size:13px' },
                confirmButtonText: 'Anular y reversar pago',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: '#ef4444',
                cancelButtonColor:  '#6b7280',
                inputValidator: (v) => (!v || v.trim().length < 10) ? 'El motivo debe tener al menos 10 caracteres.' : null,
            })
            if (!result.isConfirmed) return
            router.patch(route('compras.facturas.anular', c.compra_id), { motivo: result.value as string }, {
                onSuccess: () => notify.success(`Compra ${c.num_documento ?? ''} anulada. Pago revertido.`),
                onError:   (e)  => notify.error(Object.values(e)[0] ?? 'Error al anular'),
            })
        }
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

    const pdfUrl   = `${route('compras.cxp.pdf')}?estado=${estado}&proveedor_id=${proveedorId}`
    const excelUrl = `${route('compras.cxp.excel')}?estado=${estado}&proveedor_id=${proveedorId}`

    return (
        <AppLayout title="Cuentas por Pagar" suppressFlash>
            <Head title="Cuentas por Pagar" />

            <PageHeader
                title="Cuentas por Pagar"
                description="Obligaciones pendientes con proveedores ordenadas por vencimiento"
                breadcrumbs={[{ label: 'Compras' }, { label: 'Cuentas por Pagar' }]}
            />

            <div className="px-6 pt-6 mb-2">
                <FilterToolbar
                    search={{
                        value: buscar,
                        onChange: setBuscar,
                        onSearch: aplicarFiltros,
                        placeholder: 'Proveedor o documento...',
                    }}
                    exportHref={excelUrl}
                    extraActions={
                        <button
                            onClick={() => abrirPdf(pdfUrl)}
                            title="PDF"
                            className="flex items-center justify-center w-9 h-9 rounded-md border text-sm font-medium shrink-0"
                            style={{ background: '#ef4444', color: 'white', borderColor: '#ef4444' }}>
                            <FileText className="w-4 h-4" />
                        </button>
                    }
                >
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todas (pendiente + parcial)</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="parcial">Parcial</option>
                        <option value="pagada">Pagada / Anulada</option>
                    </select>

                    {/* Filtro período */}
                    <div className="flex items-center gap-1 border rounded-lg p-0.5 shrink-0"
                         style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}>
                        {[
                            { val: '',        label: 'Todos' },
                            { val: 'vencidas', label: 'Vencidas' },
                            { val: 'hoy',     label: 'Hoy' },
                            { val: 'semana',  label: 'Semana' },
                            { val: 'mes',     label: 'Mes' },
                            { val: 'anio',    label: 'Año' },
                        ].map(({ val, label }) => (
                            <button key={val}
                                onClick={() => { setPeriodo(val); setTimeout(aplicarFiltros, 0) }}
                                className={cn('px-2 py-1 rounded text-xs font-semibold transition-colors whitespace-nowrap',
                                    periodo === val
                                        ? 'text-black'
                                        : 'hover:opacity-80'
                                )}
                                style={periodo === val
                                    ? { background: 'var(--primary)' }
                                    : { color: 'var(--text-muted)' }}
                            >
                                {label}
                            </button>
                        ))}
                    </div>

                    <select value={proveedorId} onChange={e => setProveedorId(e.target.value)}
                        className="input-field shrink-0"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: 'auto', display: 'inline-block' }}>
                        <option value="">Todos los proveedores</option>
                        {proveedores.map(p => (
                            <option key={p.id} value={p.id}>{p.razon_social}</option>
                        ))}
                    </select>

                    {hayFiltros && (
                        <button type="button" onClick={limpiar} className="text-sm underline shrink-0" style={{ color: 'var(--text-muted)' }}>
                            Limpiar
                        </button>
                    )}
                </FilterToolbar>
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
                        <span className="col-span-1 text-center">Días</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-center">Acciones</span>
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
                                c.compra_anulada ? 'border-l-4 border-l-gray-400' : URGENCIA_BORDER[c.urgencia],
                                (c.estado === 'pagada' || c.compra_anulada) && 'opacity-60',
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
                            <div className="col-span-1 flex justify-center">
                                <DiasChip dias={c.dias_vencimiento} urgencia={c.urgencia} />
                            </div>
                            <div className="col-span-1 flex flex-col items-center gap-1">
                                {c.compra_anulada ? (
                                    <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        Anulada
                                    </span>
                                ) : (
                                    <>
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
                                    </>
                                )}
                            </div>
                            {/* Acciones: Pagar + Anular (ocultas si la compra ya fue anulada) */}
                            <div className="col-span-1 flex justify-center items-center gap-1">
                                {!c.compra_anulada && c.estado !== 'pagada' && bancos.length > 0 && puede('editar') && (
                                    <button
                                        onClick={() => setModalPago(c)}
                                        title="Registrar pago"
                                        className="p-1.5 rounded-lg hover:bg-green-500/20 text-green-600 dark:text-green-400 transition-colors">
                                        <CreditCard className="w-4 h-4" />
                                    </button>
                                )}
                                {!c.compra_anulada && c.compra_id !== null && puede('anular') && (
                                    <button
                                        onClick={() => iniciarAnulacion(c)}
                                        title="Anular compra asociada"
                                        className="p-1.5 rounded-lg hover:bg-red-500/20 text-red-500 dark:text-red-400 transition-colors">
                                        <XCircle className="w-4 h-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modalPago && (
                <ModalPago
                    cxp={modalPago}
                    bancos={bancos}
                    onClose={() => setModalPago(null)}
                />
            )}

            {/* ── Modal PDF ── */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={() => setModalPdf(false)}>
                    <div className="w-full max-w-5xl rounded-2xl overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)', height: '90vh' }}
                         onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm flex items-center gap-2"
                                style={{ color: 'var(--text-main)' }}>
                                <FileText size={16} style={{ color: '#ef4444' }} />
                                Reporte de Cuentas por Pagar
                            </h3>
                            <div className="flex items-center gap-2">
                                <a href={urlPdf} download target="_blank"
                                   className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-all hover:opacity-90"
                                   style={{ background: '#ef4444' }}>
                                    <Download size={13} /> Descargar
                                </a>
                                <button onClick={() => setModalPdf(false)}
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>
                        <iframe src={urlPdf} className="flex-1 w-full border-0" title="Reporte PDF CxP" />
                    </div>
                </div>
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
