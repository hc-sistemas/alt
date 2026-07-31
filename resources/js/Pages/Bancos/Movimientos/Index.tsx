import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn, formatFecha } from '@/lib/utils'
import {
    Plus, X, ArrowUpCircle, ArrowDownCircle,
    Ban, DollarSign, Clock, Search, FileText, FileCode, Download, Loader2,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { MovimientoBancario, BancoCaja, PlanCuenta, CentroCosto, PageProps } from '@/types'
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

interface PersonaOpt { id: number; nombre: string; identificacion: string }

interface Props extends PageProps {
    movimientos: Paginated<MovimientoBancario & {
        banco_caja?: BancoCaja
        cuenta_contrapartida?: PlanCuenta
        creado_por?: { nombre: string }
    }> | null
    bancos:       Pick<BancoCaja, 'id' | 'nombre' | 'tipo' | 'saldo_actual'>[]
    cuentas:      Pick<PlanCuenta, 'id' | 'codigo' | 'nombre'>[]
    proveedores:  PersonaOpt[]
    clientes:     PersonaOpt[]
    centrosCosto: Pick<CentroCosto, 'id' | 'nombre'>[]
    filtros: {
        banco_caja_id?: string; tipo?: string; fecha_desde?: string; fecha_hasta?: string; buscar?: string
        centro_costo_id?: string
    }
    stats: { total_ingresos: number; total_egresos: number; pendientes_conciliar: number } | null
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

function MovimientoModal({ bancos, cuentas, proveedores, clientes, onClose }: {
    bancos:      Props['bancos']
    cuentas:     Props['cuentas']
    proveedores: Props['proveedores']
    clientes:    Props['clientes']
    onClose:     () => void
}) {
    const [cuentaBusq, setCuentaBusq] = useState('')
    const [showCuentas, setShowCuentas] = useState(false)
    const [personaTipo, setPersonaTipo] = useState<'manual' | 'cliente' | 'proveedor'>('manual')

    const { data, setData, post, processing, errors } = useForm({
        banco_caja_id:           '',
        tipo:                    'ingreso',
        sub_tipo:                'efectivo',
        fecha:                   new Date().toISOString().split('T')[0],
        monto:                   '',
        persona_tipo:            '' as string,
        persona_id:              '' as string,
        beneficiario:            '',
        num_documento:           '',
        num_cheque:              '',
        fecha_cheque:            '',
        descripcion:             '',
        cuenta_contrapartida_id: '',
        es_postfechado:          false,
    })

    const personasOpts = personaTipo === 'cliente' ? clientes : personaTipo === 'proveedor' ? proveedores : []

    function seleccionarPersona(p: PersonaOpt) {
        setData(d => ({ ...d, persona_id: String(p.id), beneficiario: p.nombre }))
    }

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

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-xl" onClick={e => e.stopPropagation()}>

                <div className="modal-header">
                    <h2>Nuevo Movimiento</h2>
                    <button className="modal-close" onClick={onClose}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>

                    {/* Banco/Caja + Tipo */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Banco/Caja <span className="text-red-400">*</span></Label>
                            <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                                className="input-field select-field">
                                <option value="">— Seleccionar —</option>
                                {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre} (${Number(b.saldo_actual).toFixed(2)})</option>)}
                            </select>
                            {errors.banco_caja_id && <p className="text-red-400 text-xs">{errors.banco_caja_id}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Tipo <span className="text-red-400">*</span></Label>
                            <div className="grid grid-cols-2 gap-2">
                                {(['ingreso', 'egreso'] as const).map(t => (
                                    <button key={t} type="button" onClick={() => setData('tipo', t)}
                                        className={cn(
                                            'py-2.5 rounded-xl text-sm font-bold border-2 transition-all flex items-center justify-center gap-1.5',
                                            data.tipo === t
                                                ? t === 'ingreso' ? 'bg-green-500/20 border-green-500 text-green-600 dark:text-green-400'
                                                                  : 'bg-red-500/20 border-red-500 text-red-600 dark:text-red-400'
                                                : 'border-transparent'
                                        )}
                                        style={data.tipo !== t ? { borderColor: 'var(--border)', color: 'var(--text-muted)' } : {}}>
                                        {t === 'ingreso' ? <ArrowUpCircle className="w-3.5 h-3.5" /> : <ArrowDownCircle className="w-3.5 h-3.5" />}
                                        {t === 'ingreso' ? 'Ingreso' : 'Egreso'}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Forma de pago + Fecha */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Forma de pago <span className="text-red-400">*</span></Label>
                            <div className="grid grid-cols-2 gap-1">
                                {(['efectivo', 'transferencia', 'cheque', 'deposito'] as const).map(t => (
                                    <button key={t} type="button" onClick={() => setData('sub_tipo', t)}
                                        className={cn(
                                            'py-1.5 px-1 rounded-lg text-xs font-medium border transition-colors',
                                            data.sub_tipo === t ? 'text-white border-transparent' : 'hover:opacity-80'
                                        )}
                                        style={data.sub_tipo === t
                                            ? { background: 'var(--primary)' }
                                            : { borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                        {{ efectivo: 'Efectivo', transferencia: 'Transf.', cheque: 'Cheque', deposito: 'Depósito' }[t]}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Fecha <span className="text-red-400">*</span></Label>
                            <Input type="date" value={data.fecha} onChange={e => setData('fecha', e.target.value)} />
                        </div>
                    </div>

                    {/* Monto + N° Documento */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Monto <span className="text-red-400">*</span></Label>
                            <Input type="number" step="0.01" min="0.01" value={data.monto}
                                onChange={e => setData('monto', e.target.value)}
                                error={errors.monto} placeholder="0.00" />
                            {errors.monto && <p className="text-red-400 text-xs">{errors.monto}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>N° Documento</Label>
                            <Input value={data.num_documento} onChange={e => setData('num_documento', e.target.value)}
                                placeholder="Ref…" />
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

                    {/* Beneficiario con selector de persona */}
                    <div className="space-y-1.5">
                        <Label>Beneficiario / Persona</Label>
                        <div className="grid grid-cols-3 gap-1 mb-2">
                            {(['manual', 'cliente', 'proveedor'] as const).map(t => (
                                <button key={t} type="button"
                                    onClick={() => {
                                        setPersonaTipo(t)
                                        setData(d => ({ ...d, persona_tipo: t === 'manual' ? '' : t, persona_id: '' }))
                                    }}
                                    className="py-1.5 rounded-lg text-xs font-medium border transition-colors"
                                    style={personaTipo === t
                                        ? { background: 'var(--primary)', color: '#fff', borderColor: 'var(--primary)' }
                                        : { borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    {{ manual: 'Manual', cliente: 'Cliente', proveedor: 'Proveedor' }[t]}
                                </button>
                            ))}
                        </div>
                        {personaTipo === 'manual' ? (
                            <Input value={data.beneficiario} onChange={e => setData('beneficiario', e.target.value)}
                                placeholder="Nombre del beneficiario…" />
                        ) : (
                            <select value={data.persona_id}
                                onChange={e => {
                                    const p = personasOpts.find(x => String(x.id) === e.target.value)
                                    if (p) seleccionarPersona(p)
                                    else setData(d => ({ ...d, persona_id: '', beneficiario: '' }))
                                }}
                                className="input-field select-field">
                                <option value="">— Seleccionar {personaTipo} —</option>
                                {personasOpts.map(p => (
                                    <option key={p.id} value={p.id}>{p.nombre} ({p.identificacion})</option>
                                ))}
                            </select>
                        )}
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
                        <textarea value={data.descripcion} onChange={e => setData('descripcion', e.target.value)}
                            rows={2}
                            className="input-field textarea-field"
                            placeholder="Descripción del movimiento…" />
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

                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing} className="btn-primary">
                        <Plus className="w-4 h-4" /> Registrar Movimiento
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">
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
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-sm" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2><Ban className="w-5 h-5 text-red-500" /> Anular movimiento</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body">
                    <div className="space-y-1.5">
                        <Label>Motivo de anulación <span className="text-red-400">*</span></Label>
                        <Input value={data.motivo} onChange={e => setData('motivo', e.target.value)}
                            error={errors.motivo} placeholder="Mínimo 10 caracteres…" />
                        {errors.motivo && <p className="text-red-400 text-xs">{errors.motivo}</p>}
                    </div>
                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing || data.motivo.length < 10}
                        className="btn-primary" style={{ background: '#EF4444', color: '#fff', boxShadow: 'none' }}>
                        <Ban className="w-4 h-4" /> Anular
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">
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
    const { movimientos, bancos, cuentas, proveedores, clientes, centrosCosto, filtros, stats, flash } = usePage<Props>().props
    const { puede } = usePermiso('bancos')
    const [showModal, setShowModal] = useState(false)
    const [anularMov, setAnularMov] = useState<MovimientoBancario | null>(null)
    const [filtro, setFiltro] = useState(filtros)

    // Cambiar cualquier filtro después de haber buscado marca los resultados
    // como "obsoletos" — la tabla vuelve al estado vacío hasta que se
    // presione Buscar de nuevo (mismo patrón que Importaciones/Cuentas por
    // Pagar/Anticipos/Devoluciones).
    const [filtrosSucios, setFiltrosSucios] = useState(false)

    // Carga bajo demanda: `movimientos`/`stats` vienen null hasta que el
    // usuario presiona Buscar.
    const haBuscado = movimientos !== null && !filtrosSucios

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    function cambiarFiltro<K extends keyof typeof filtro>(campo: K, valor: string) {
        setFiltro(f => ({ ...f, [campo]: valor }))
        setFiltrosSucios(true)
    }

    function buscar() {
        router.get(route('bancos.movimientos.index'), { ...filtro, buscado: '1' } as any, {
            preserveState: true,
            replace: true,
            onSuccess: () => setFiltrosSucios(false),
        })
    }

    const paramsFiltrosActuales = () =>
        Object.fromEntries(Object.entries(filtro).filter(([, v]) => v)) as Record<string, string>

    const exportUrl = route('bancos.movimientos.export-excel') + '?' + new URLSearchParams(paramsFiltrosActuales()).toString()
    const exportXmlUrl = route('bancos.movimientos.exportar-xml') + '?' + new URLSearchParams(paramsFiltrosActuales()).toString()

    // ── PDF: se trae como blob (fetch) en vez de apuntar el <iframe> directo a
    //    la URL del backend — mismo patrón que Asientos/Facturas de Compra/
    //    Proveedores/Cuentas por Pagar: un blob: URL siempre se muestra
    //    embebido, sin depender de si el navegador decide forzar la descarga. ──
    const [modalPdf, setModalPdf] = useState(false)
    const [cargandoPdf, setCargandoPdf] = useState(false)
    const [urlPdf, setUrlPdf] = useState('')

    const abrirPdf = async (url: string) => {
        setModalPdf(true)
        setCargandoPdf(true)
        setUrlPdf('')
        try {
            const res = await fetch(url, { headers: { Accept: 'application/pdf' } })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: null })) as { message?: string | null }
                throw new Error(err.message ?? 'No se pudo generar el PDF.')
            }
            const blob = await res.blob()
            setUrlPdf(URL.createObjectURL(blob))
        } catch (e) {
            notify.error(e instanceof Error ? e.message : 'No se pudo generar el PDF. Intenta de nuevo.')
            setModalPdf(false)
        } finally {
            setCargandoPdf(false)
        }
    }

    const cerrarModalPdf = () => {
        if (urlPdf) URL.revokeObjectURL(urlPdf)
        setModalPdf(false)
        setUrlPdf('')
    }

    // ── PDF grande (> MAX_FILAS_EXPORT): ofrecer generarlo en segundo plano en
    //    vez de solo bloquear — mismo patrón que Asientos/Facturas de Compra/
    //    Proveedores/Cuentas por Pagar. ──────────────────────────────────────
    const [verificandoPdf, setVerificandoPdf] = useState(false)
    const [exportandoFondo, setExportandoFondo] = useState<{ desde: number } | null>(null)

    const confirmarExportacionSegundoPlano = () => {
        router.post(route('bancos.movimientos.exportar-segundo-plano'), paramsFiltrosActuales(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setExportandoFondo({ desde: Date.now() }),
        })
    }

    const iniciarExportacionPdf = async () => {
        setVerificandoPdf(true)
        try {
            const params = new URLSearchParams(paramsFiltrosActuales())
            const res = await fetch(route('bancos.movimientos.contar-exportables') + '?' + params)
            if (!res.ok) throw new Error()
            const data = await res.json() as { total: number; limite: number; excede: boolean }

            if (!data.excede) {
                abrirPdf(route('bancos.movimientos.pdf') + '?' + params)
                return
            }

            const { isConfirmed } = await Swal.fire({
                ...swalBase,
                title: 'Reporte grande',
                html: `
                    <div style="text-align:left;color:#374151;font-size:0.875rem;line-height:1.5">
                        <p>Este reporte tiene <strong>${data.total.toLocaleString('es-EC')}</strong> movimientos con
                        estos filtros — muy grande para generarse al instante (límite: ${data.limite.toLocaleString('es-EC')}).</p>
                        <p style="margin-top:8px">Se procesará en segundo plano y te avisaremos por notificación
                        (campanita) cuando esté listo para descargar.</p>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#F59E0B',
                confirmButtonText: 'Procesar en segundo plano',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            })

            if (isConfirmed) confirmarExportacionSegundoPlano()
        } catch {
            notify.error('No se pudo verificar el tamaño del reporte. Intenta de nuevo.')
        } finally {
            setVerificandoPdf(false)
        }
    }

    // Sin websockets/polling en el backend — se consulta el mismo endpoint que
    // ya usa la campana de notificaciones (notificaciones.index) cada 15s,
    // mientras haya una exportación en curso, hasta encontrarla o 10 minutos.
    useEffect(() => {
        if (!exportandoFondo) return
        const intervalo = setInterval(async () => {
            if (Date.now() - exportandoFondo.desde > 10 * 60 * 1000) {
                setExportandoFondo(null)
                return
            }
            try {
                const res = await fetch(route('notificaciones.index'))
                if (!res.ok) return
                const data = await res.json() as { notificaciones: { tipo: string; created_at: string }[] }
                const lista = data.notificaciones.some(n =>
                    (n.tipo === 'exportacion_movimientos' || n.tipo === 'exportacion_movimientos_error') &&
                    new Date(n.created_at).getTime() >= exportandoFondo.desde
                )
                if (lista) {
                    notify.ok('Tu exportación terminó de procesarse — revisa la campana de notificaciones para descargarla.')
                    setExportandoFondo(null)
                }
            } catch { /* red momentáneamente caída — se reintenta en el próximo tick */ }
        }, 15000)
        return () => clearInterval(intervalo)
    }, [exportandoFondo])

    return (
        <AppLayout title="Movimientos Bancarios" suppressFlash>
            <Head title="Movimientos Bancarios" />

            <PageHeader
                title="Movimientos Bancarios"
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Movimientos' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setShowModal(true)} className="btn-primary flex items-center gap-2 whitespace-nowrap shrink-0">
                            <Plus size={15} /> Nuevo
                        </button>
                    ) : undefined
                }
            />

            {exportandoFondo && (
                <div className="flex items-center gap-2 text-xs rounded-lg px-3 py-2 mx-6 mt-4"
                    style={{ background: 'color-mix(in srgb, var(--primary) 12%, var(--bg-main))', color: 'var(--text-main)' }}>
                    <Loader2 className="w-3.5 h-3.5 animate-spin shrink-0" style={{ color: 'var(--primary)' }} />
                    <span>Tu PDF se está procesando en segundo plano — te avisaremos por notificación cuando esté listo.</span>
                </div>
            )}

            <div className="px-6 pt-6 mb-2">
                {/*
                    Ancho vía `style.width` inline a propósito, NO clases Tailwind: `.input-field`
                    (app.css) declara `width:100%` fuera de cualquier @layer, y las utilidades de
                    Tailwind v4 viven dentro de su @layer utilities interno — por reglas de CSS
                    Cascade Layers, lo no-layereado siempre gana sobre lo layereado sin importar
                    especificidad ni orden, así que un w-XX de Tailwind nunca puede ganarle a
                    `.input-field`. Mismo hallazgo documentado en Asientos/Facturas de Compra/
                    Proveedores/Cuentas por Pagar/Anticipos/Devoluciones/Importaciones.
                */}
                <div className="overflow-x-auto">
                <div style={{ minWidth: '950px' }}>
                <FilterToolbar
                    search={{
                        value: filtro.buscar ?? '',
                        onChange: v => cambiarFiltro('buscar', v),
                        onSearch: buscar,
                        placeholder: 'Descripción, beneficiario...',
                    }}
                    searchWidth="w-[120px]"
                    exportHref={exportUrl}
                    extraActions={
                        <>
                            <a href={exportXmlUrl}
                                className="flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0"
                                style={{ background: '#4F46E5', color: 'white', borderColor: '#4F46E5', transition: 'background 0.2s' }}
                                onMouseEnter={e => (e.currentTarget.style.background = '#4338CA')}
                                onMouseLeave={e => (e.currentTarget.style.background = '#4F46E5')}
                                title="Exportar a XML">
                                <FileCode className="w-4 h-4" />
                            </a>
                            <button type="button"
                                onClick={iniciarExportacionPdf}
                                disabled={verificandoPdf}
                                title={verificandoPdf ? 'Verificando tamaño…' : 'PDF'}
                                className="flex items-center justify-center w-9 h-9 rounded-md border text-sm font-medium shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                                style={{ background: '#ef4444', color: 'white', borderColor: '#ef4444' }}>
                                <FileText className="w-4 h-4" />
                            </button>
                        </>
                    }
                >
                    <select value={filtro.banco_caja_id ?? ''} onChange={e => cambiarFiltro('banco_caja_id', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '128px' }}>
                        <option value="">Bancos</option>
                        {bancos.map(b => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                    </select>

                    <select value={filtro.tipo ?? ''} onChange={e => cambiarFiltro('tipo', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '112px' }}>
                        <option value="">Tipos</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="egreso">Egreso</option>
                    </select>

                    <select value={filtro.centro_costo_id ?? ''} onChange={e => cambiarFiltro('centro_costo_id', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '132px' }}>
                        <option value="">Centros</option>
                        {centrosCosto.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>

                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Desde</label>
                        <input type="date" value={filtro.fecha_desde ?? ''}
                            onChange={e => cambiarFiltro('fecha_desde', e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '125px' }} />
                    </div>
                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                        <input type="date" value={filtro.fecha_hasta ?? ''}
                            onChange={e => cambiarFiltro('fecha_hasta', e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '125px' }} />
                    </div>
                </FilterToolbar>
                </div>
                </div>
            </div>

            {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
            {!haBuscado && (
                <div className="px-6 pb-8">
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar los movimientos.
                        </p>
                    </div>
                </div>
            )}

            {haBuscado && stats && movimientos && (
            <>
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
                                m.anulado && 'opacity-50'
                            )}
                            style={{ borderColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => !m.anulado && (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>

                            <div className="col-span-1">
                                <p className="text-xs font-mono" style={{ color: 'var(--text-main)' }}>{formatFecha(m.fecha)}</p>
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
                                {!m.anulado && !m.conciliado && puede('anular') && (
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
            </>
            )}

            {showModal && (
                <MovimientoModal
                    bancos={bancos}
                    cuentas={cuentas}
                    proveedores={proveedores}
                    clientes={clientes}
                    onClose={() => setShowModal(false)}
                />
            )}
            {anularMov && <AnularModal movimiento={anularMov} onClose={() => setAnularMov(null)} />}

            {/* ── Modal PDF ── */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={cerrarModalPdf}>
                    <div className="w-full max-w-5xl rounded-2xl overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)', height: '90vh' }}
                         onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm flex items-center gap-2"
                                style={{ color: 'var(--text-main)' }}>
                                <FileText size={16} style={{ color: '#ef4444' }} />
                                Reporte de Movimientos Bancarios
                            </h3>
                            <div className="flex items-center gap-2">
                                {urlPdf && (
                                    <a href={urlPdf} download={`movimientos-bancarios-${new Date().toISOString().slice(0, 10)}.pdf`}
                                       className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-all hover:opacity-90"
                                       style={{ background: '#ef4444' }}>
                                        <Download size={13} /> Descargar
                                    </a>
                                )}
                                <button onClick={cerrarModalPdf}
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>
                        {cargandoPdf ? (
                            <div className="flex-1 flex items-center justify-center text-sm" style={{ color: 'var(--text-muted)' }}>
                                Generando PDF…
                            </div>
                        ) : (
                            <iframe src={urlPdf} className="flex-1 w-full border-0" title="Reporte PDF Movimientos" />
                        )}
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
