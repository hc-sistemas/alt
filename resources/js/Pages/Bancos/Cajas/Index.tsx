import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn, fechaHoy } from '@/lib/utils'
import { Plus, X, Wallet, AlertTriangle, CheckCircle, Lock, Search, Clock } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { BancoCaja, CentroCosto, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface CierreRow {
    id: number; caja: string | null; centro_costo: string | null; fecha: string
    monto_inicial: number; total_cobrado: number; total_efectivo: number
    total_tarjeta: number; diferencia: number; estado: string
    hora_apertura: string | null; hora_cierre: string | null
    usuario_apertura: string | null; usuario_cierre: string | null
    tiene_diferencia: boolean
    dias_abierta: number
    sospechosa: boolean
    /** Efectivo del día (solo cajas abiertas): facturas cobradas, facturado, cobrado y saldo esperado */
    resumen?: Resumen | null
    saldo_esperado?: number
    facturas_efectivo?: number
    observaciones?: string | null
}

interface Resumen {
    facturas: number; facturado: number; cobrado: number; otros: number; inicial: number; esperado: number
}

interface Denominacion { clave: string; valor: number; tipo: 'Moneda' | 'Billete' }

interface AperturaInfo {
    primer_dia: boolean; saldo_inicial: number | null; anterior_abierto: boolean; fecha_anterior: string | null
}

interface Props extends PageProps {
    cierres: CierreRow[] | null
    cajas: BancoCaja[]
    centros: Pick<CentroCosto, 'id' | 'nombre'>[]
    cajaAbierta: {
        id: number
        banco_caja: { nombre: string } | null
        monto_inicial: number
        hora_apertura: string | null
        resumen: Resumen
    } | null
    aperturas: Record<string, AperturaInfo>
    denominaciones: Denominacion[]
    filtros: {
        banco_caja_id?: string; estado?: string; centro_costo_id?: string
        fecha_desde?: string; fecha_hasta?: string; buscar?: string
    }
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}
const SWAL_CSS = `.swal-pop{border-radius:20px!important;padding:28px!important;box-shadow:0 25px 60px rgba(0,0,0,.25)!important}.swal-title{font-size:1.1rem!important;font-weight:700!important}.swal-confirm,.swal-cancel{border-radius:10px!important;padding:10px 20px!important;font-weight:600!important}`
function injectCss() {
    if (document.getElementById('swal-cajas')) return
    const s = document.createElement('style'); s.id = 'swal-cajas'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectCss,
}

const fmt = (n: number) => '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── Tabla de denominaciones (igual en apertura y cierre) ─────────────────────

function ConteoTabla({ denominaciones, valores, onChange }: {
    denominaciones: Denominacion[]
    valores: Record<string, string>
    onChange: (clave: string, v: string) => void
}) {
    const subtotal = (d: Denominacion) => Math.round((parseInt(valores[d.clave] || '0', 10) || 0) * d.valor * 100) / 100
    const total = (tipo: 'Moneda' | 'Billete') =>
        denominaciones.filter(d => d.tipo === tipo).reduce((s, d) => s + subtotal(d), 0)
    const monedas = total('Moneda'), billetes = total('Billete')

    return (
        <div className="grid grid-cols-2 gap-3">
            {(['Moneda', 'Billete'] as const).map(tipo => (
                <div key={tipo} className="rounded-xl border p-3" style={{ borderColor: 'var(--border)' }}>
                    <p className="text-[11px] font-bold uppercase tracking-wider mb-2" style={{ color: 'var(--text-muted)' }}>
                        {tipo === 'Moneda' ? 'Monedas' : 'Billetes'}
                    </p>
                    <div className="space-y-1.5">
                        {denominaciones.filter(d => d.tipo === tipo).map(d => (
                            <div key={d.clave} className="flex items-center gap-2 text-xs">
                                <span className="w-14 font-mono" style={{ color: 'var(--text-main)' }}>{fmt(d.valor)}</span>
                                <span style={{ color: 'var(--text-muted)' }}>×</span>
                                <input type="number" min="0" step="1" inputMode="numeric"
                                    value={valores[d.clave] ?? ''} placeholder="0"
                                    onChange={e => onChange(d.clave, e.target.value)}
                                    className="input-field !py-1 !px-2 w-16 text-right" />
                                <span className="ml-auto font-mono" style={{ color: 'var(--text-muted)' }}>{fmt(subtotal(d))}</span>
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-between text-xs font-semibold border-t mt-2 pt-2" style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                        <span>Total {tipo === 'Moneda' ? 'monedas' : 'billetes'}</span>
                        <span className="font-mono">{fmt(tipo === 'Moneda' ? monedas : billetes)}</span>
                    </div>
                </div>
            ))}
            <div className="col-span-2 flex justify-between items-center rounded-xl px-4 py-2.5"
                style={{ background: 'rgba(245,158,11,0.08)', border: '1px solid var(--border)' }}>
                <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Total efectivo físico</span>
                <span className="text-lg font-bold font-mono" style={{ color: 'var(--text-main)' }}>{fmt(monedas + billetes)}</span>
            </div>
        </div>
    )
}

const conteoNumerico = (v: Record<string, string>) =>
    Object.fromEntries(Object.entries(v).map(([k, x]) => [k, parseInt(x || '0', 10) || 0]))

// ─── Modal Abrir Caja ─────────────────────────────────────────────────────────
// Primer día: conteo por denominación. Desde el segundo: saldo inicial automático (cierre anterior).

function AbrirModal({ cajas, centros, aperturas, denominaciones, onClose }: {
    cajas: BancoCaja[]; centros: Props['centros']; aperturas: Props['aperturas']
    denominaciones: Denominacion[]; onClose: () => void
}) {
    const { data, setData, post, processing, errors, transform } = useForm({
        banco_caja_id: cajas.length === 1 ? String(cajas[0].id) : '',
        centro_costo_id: '',
        conteo: {} as Record<string, string>,
    })
    transform(d => ({ ...d, conteo: conteoNumerico(d.conteo) }))

    const info = data.banco_caja_id ? aperturas[data.banco_caja_id] : undefined

    function submit(e: React.FormEvent) {
        e.preventDefault()
        post(route('bancos.cajas.abrir'), {
            onSuccess: () => onClose(),
            onError: () => notify.error('No se pudo abrir la caja'),
        })
    }
    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Abrir Caja</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <div className="space-y-1.5">
                        <Label>Caja <span className="text-red-400">*</span></Label>
                        <select value={data.banco_caja_id} onChange={e => setData('banco_caja_id', e.target.value)}
                            className="input-field select-field">
                            <option value="">— Seleccionar —</option>
                            {cajas.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                        </select>
                        {errors.banco_caja_id && <p className="text-red-400 text-xs">{errors.banco_caja_id}</p>}
                    </div>

                    {info?.anterior_abierto && (
                        <p className="text-xs px-3 py-2 rounded-md font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                            ⚠ El día {info.fecha_anterior} de esta caja sigue abierto. Ciérrelo antes de abrir hoy.
                        </p>
                    )}

                    {info && !info.anterior_abierto && info.primer_dia && (
                        <div className="space-y-2">
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Es el primer día de esta caja: cuente el efectivo por denominación. Ese conteo es el saldo inicial.
                            </p>
                            <ConteoTabla denominaciones={denominaciones} valores={data.conteo}
                                onChange={(k, v) => setData('conteo', { ...data.conteo, [k]: v })} />
                        </div>
                    )}

                    {info && !info.anterior_abierto && !info.primer_dia && (
                        <div className="flex justify-between items-center rounded-xl px-4 py-3"
                            style={{ background: 'rgba(16,185,129,0.08)', border: '1px solid var(--border)' }}>
                            <span className="text-sm" style={{ color: 'var(--text-main)' }}>
                                Saldo inicial automático <span className="text-xs" style={{ color: 'var(--text-muted)' }}>(cierre del día anterior)</span>
                            </span>
                            <span className="text-lg font-bold font-mono" style={{ color: 'var(--text-main)' }}>{fmt(info.saldo_inicial ?? 0)}</span>
                        </div>
                    )}

                    {centros.length > 0 && (
                        <div className="space-y-1.5">
                            <Label>Centro de costo</Label>
                            <select value={data.centro_costo_id} onChange={e => setData('centro_costo_id', e.target.value)}
                                className="input-field select-field">
                                <option value="">— El de la caja —</option>
                                {centros.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                            </select>
                        </div>
                    )}
                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing || !info || info.anterior_abierto} className="btn-primary">
                        <CheckCircle className="w-4 h-4" /> Abrir Caja
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

// ─── Modal Cerrar Caja ────────────────────────────────────────────────────────
// Resumen del día (solo efectivo), conteo físico por denominación y diferencia.

function CerrarModal({ cierre, denominaciones, onClose }: {
    cierre: CierreRow; denominaciones: Denominacion[]; onClose: () => void
}) {
    const { data, setData, patch, processing, errors, transform } = useForm({
        conteo: {} as Record<string, string>,
        observaciones: '',
    })
    transform(d => ({ ...d, conteo: conteoNumerico(d.conteo) }))

    const r = cierre.resumen
    const esperado = r?.esperado ?? 0
    const fisico = denominaciones.reduce(
        (s, d) => s + Math.round((parseInt(data.conteo[d.clave] || '0', 10) || 0) * d.valor * 100) / 100, 0)
    const diferencia = Math.round((fisico - esperado) * 100) / 100
    const hayDif = Math.abs(diferencia) > 0.01

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (hayDif && data.observaciones.trim().length < 5) {
            notify.error('Hay una diferencia: escriba la observación para poder cerrar.')
            return
        }
        patch(route('bancos.cajas.cerrar', cierre.id), {
            onSuccess: () => onClose(),
            onError: () => notify.error('No se pudo cerrar la caja'),
        })
    }

    const fila = (etq: string, val: string, fuerte = false) => (
        <div className={cn('flex justify-between text-sm', fuerte && 'border-t pt-2 font-semibold')} style={{ borderColor: 'var(--border)' }}>
            <span style={{ color: fuerte ? 'var(--text-main)' : 'var(--text-muted)' }}>{etq}</span>
            <span className="font-medium font-mono" style={{ color: 'var(--text-main)' }}>{val}</span>
        </div>
    )

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-xl" onClick={e => e.stopPropagation()}>
                <div className="modal-header">
                    <h2>Cerrar Caja — {cierre.caja}</h2>
                    <button className="modal-close" onClick={onClose}><X className="w-4 h-4" /></button>
                </div>
                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    <div className="rounded-xl p-4 space-y-2" style={{ border: '1px solid var(--border)' }}>
                        <p className="text-[11px] font-bold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Efectivo del día
                        </p>
                        {fila('Facturas cobradas en efectivo', String(r?.facturas ?? 0))}
                        {fila('Total facturado en efectivo', fmt(r?.facturado ?? 0))}
                        {fila('Total cobrado en efectivo', fmt(r?.cobrado ?? 0))}
                        {fila('Saldo inicial', fmt(r?.inicial ?? cierre.monto_inicial))}
                        {fila('Otros movimientos de efectivo (pagos, gastos, aportes, depósitos)', fmt(r?.otros ?? 0))}
                        {fila('Saldo esperado', fmt(esperado), true)}
                    </div>

                    <div className="space-y-2">
                        <Label>Conteo físico</Label>
                        <ConteoTabla denominaciones={denominaciones} valores={data.conteo}
                            onChange={(k, v) => setData('conteo', { ...data.conteo, [k]: v })} />
                    </div>

                    <div className="rounded-xl p-4 space-y-1"
                        style={{
                            background: hayDif ? 'rgba(239,68,68,0.06)' : 'rgba(16,185,129,0.06)',
                            border: `1px solid ${hayDif ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)'}`,
                        }}>
                        <div className="flex justify-between text-sm">
                            <span className="font-semibold" style={{ color: 'var(--text-main)' }}>Diferencia (conteo − esperado)</span>
                            <span className={cn('font-bold text-base font-mono', hayDif ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                                {fmt(diferencia)}
                            </span>
                        </div>
                        {hayDif && (
                            <p className="text-xs text-red-500 flex items-center gap-1">
                                <AlertTriangle className="w-3.5 h-3.5" />
                                {diferencia > 0 ? 'Sobrante' : 'Faltante'}: se registrará un asiento automático al cerrar.
                            </p>
                        )}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Observaciones {hayDif && <span className="text-red-400">* obligatoria</span>}</Label>
                        <textarea value={data.observaciones} onChange={e => setData('observaciones', e.target.value)}
                            rows={2} placeholder={hayDif ? 'Explique la diferencia…' : 'Novedades del día…'}
                            className="input-field textarea-field" />
                        {errors.observaciones && <p className="text-red-400 text-xs">{errors.observaciones}</p>}
                    </div>
                </div>
                <div className="modal-footer">
                    <button type="submit" disabled={processing || fisico <= 0 && esperado > 0}
                        className="btn-primary"
                        style={hayDif ? { background: '#F97316', color: '#fff', boxShadow: 'none' } : {}}>
                        <Lock className="w-4 h-4" /> Cerrar Caja
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

export default function CajasIndex() {
    const { cierres, cajas, centros, cajaAbierta, aperturas, denominaciones, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('bancos')
    const [showAbrir, setShowAbrir] = useState(false)
    const [cerrarCierre, setCerrarCierre] = useState<CierreRow | null>(null)

    const [filtro, setFiltro] = useState({
        ...filtros,
        fecha_desde: filtros.fecha_desde ?? (cierres !== null ? '' : fechaHoy()),
        fecha_hasta: filtros.fecha_hasta ?? (cierres !== null ? '' : fechaHoy()),
    })

    // Cambiar cualquier filtro después de haber buscado marca los resultados
    // como "obsoletos" respecto al filtro actual — la tabla NO se vacía (se
    // sigue mostrando la última búsqueda, atenuada) hasta que se presione
    // Buscar de nuevo (mismo patrón ya corregido en Movimientos Bancarios/
    // Anticipos/Devoluciones/Importaciones).
    const [filtrosSucios, setFiltrosSucios] = useState(false)

    // Carga bajo demanda: `cierres` viene null hasta que el usuario presiona
    // Buscar por primera vez.
    const haBuscado = cierres !== null

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
        router.get(route('bancos.cajas.index'), { ...filtro, buscado: '1' } as any, {
            preserveState: true,
            replace: true,
            onSuccess: () => setFiltrosSucios(false),
        })
    }

    return (
        <AppLayout title="Control de Cajas" suppressFlash>
            <Head title="Control de Cajas" />

            <PageHeader
                title="Control de Cajas"
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Cajas' }]}
                description={
                    cajaAbierta ? (
                        <span className="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                            <CheckCircle size={12} className="shrink-0" />
                            Caja abierta: {cajaAbierta.banco_caja?.nombre}
                            {' '}· Desde las {cajaAbierta.hora_apertura}
                            {' '}· Saldo inicial {fmt(Number(cajaAbierta.monto_inicial))}
                        </span>
                    ) : (
                        <span className="flex items-center gap-1.5 text-red-600 dark:text-red-400">
                            <AlertTriangle size={12} className="shrink-0" />
                            No hay caja abierta hoy · Abre una caja para registrar movimientos de efectivo.
                        </span>
                    )
                }
                actions={
                    <div className="flex items-center gap-2 flex-wrap shrink-0">
                        {!cajaAbierta && puede('crear') && (
                            <button onClick={() => setShowAbrir(true)}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-black whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5 shrink-0"
                                style={{ background: 'var(--primary)' }}>
                                <Plus size={15} /> Abrir Caja
                            </button>
                        )}
                        {cajaAbierta && puede('editar') && (
                            <button
                                onClick={() => setCerrarCierre({
                                    id: cajaAbierta.id,
                                    caja: cajaAbierta.banco_caja?.nombre ?? '',
                                    centro_costo: null, fecha: '',
                                    monto_inicial: Number(cajaAbierta.monto_inicial),
                                    total_cobrado: 0, total_efectivo: 0, total_tarjeta: 0,
                                    diferencia: 0, estado: 'abierto',
                                    hora_apertura: null, hora_cierre: null,
                                    usuario_apertura: null, usuario_cierre: null,
                                    tiene_diferencia: false,
                                    dias_abierta: 0, sospechosa: false,
                                    resumen: cajaAbierta.resumen,
                                })}
                                className="flex items-center gap-1.5 px-4 py-2 rounded-xl font-semibold text-sm text-black whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5 shrink-0 bg-green-600">
                                <Lock className="w-4 h-4" /> Cerrar Caja
                            </button>
                        )}
                    </div>
                }
            />

            <div className="px-6 pt-6 mb-2">
                {/*
                    Ancho vía `style.width` inline a propósito, NO clases Tailwind: `.input-field`
                    (app.css) declara `width:100%` fuera de cualquier @layer, y las utilidades de
                    Tailwind v4 viven dentro de su @layer utilities interno — por reglas de CSS
                    Cascade Layers, lo no-layereado siempre gana sobre lo layereado sin importar
                    especificidad ni orden, así que un w-XX de Tailwind nunca puede ganarle a
                    `.input-field`. Mismo hallazgo documentado en Movimientos Bancarios/Asientos/
                    Facturas de Compra/Proveedores/Cuentas por Pagar/Anticipos/Devoluciones/
                    Importaciones.
                */}
                <div className="overflow-x-auto">
                <div style={{ minWidth: '1050px' }}>
                <FilterToolbar
                    search={{
                        value: filtro.buscar ?? '',
                        onChange: v => cambiarFiltro('buscar', v),
                        onSearch: buscar,
                        placeholder: 'Usuario, observaciones...',
                    }}
                    searchWidth="w-[130px]"
                >
                    <select value={filtro.banco_caja_id ?? ''} onChange={e => cambiarFiltro('banco_caja_id', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '160px' }}>
                        <option value="">Caja</option>
                        {cajas.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>

                    <select value={filtro.estado ?? ''} onChange={e => cambiarFiltro('estado', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '110px' }}>
                        <option value="">Estado</option>
                        <option value="abierto">Abierta</option>
                        <option value="cerrado">Cerrada</option>
                    </select>

                    <select value={filtro.centro_costo_id ?? ''} onChange={e => cambiarFiltro('centro_costo_id', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '140px' }}>
                        <option value="">Centro</option>
                        {centros.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>

                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Desde</label>
                        <input type="date" value={filtro.fecha_desde ?? ''}
                            onChange={e => cambiarFiltro('fecha_desde', e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '140px' }} />
                    </div>
                    <div className="flex flex-col gap-1 shrink-0 self-end">
                        <label className="text-[11px] font-semibold" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                        <input type="date" value={filtro.fecha_hasta ?? ''}
                            onChange={e => cambiarFiltro('fecha_hasta', e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '140px' }} />
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
                            Ajusta los filtros y presiona Buscar para consultar el historial de cierres.
                        </p>
                    </div>
                </div>
            )}

            {/* Historial */}
            {haBuscado && cierres && (
            <div className={cn('px-6 pb-8', filtrosSucios && 'opacity-60 transition-opacity')}>
                <h2 className="text-sm font-semibold mb-3 uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                    Historial de cierres
                </h2>
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-2">Caja</span>
                        <span className="col-span-1">Fecha</span>
                        <span className="col-span-1">Apertura</span>
                        <span className="col-span-1">Cierre</span>
                        <span className="col-span-1 text-right">Inicial</span>
                        <span className="col-span-2 text-right">Total cobrado</span>
                        <span className="col-span-2 text-right">Diferencia</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-right">Acción</span>
                    </div>

                    {cierres.length === 0 && (
                        <div className="py-16 text-center">
                            <Wallet className="w-10 h-10 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No se encontraron cierres con estos filtros</p>
                        </div>
                    )}

                    {cierres.map(c => (
                        <div key={c.id}
                            className="group grid grid-cols-12 gap-2 px-4 py-3 border-b items-center text-sm transition-colors"
                            style={{ borderColor: 'var(--border)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs font-medium truncate" style={{ color: 'var(--text-main)' }}>{c.caja ?? '—'}</p>
                                {c.centro_costo && <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>{c.centro_costo}</p>}
                            </div>
                            <div className="col-span-1">
                                <p className="text-xs font-mono" style={{ color: 'var(--text-main)' }}>{c.fecha}</p>
                            </div>
                            <div className="col-span-1">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.hora_apertura ?? '—'}</p>
                            </div>
                            <div className="col-span-1">
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.hora_cierre ?? '—'}</p>
                            </div>
                            <div className="col-span-1 text-right">
                                <p className="text-xs" style={{ color: 'var(--text-main)' }}>{fmt(c.monto_inicial)}</p>
                            </div>
                            <div className="col-span-2 text-right">
                                <p className="text-xs font-medium" style={{ color: 'var(--text-main)' }}>{fmt(c.total_cobrado)}</p>
                            </div>
                            <div className="col-span-2 text-right">
                                <p className={cn('text-xs font-bold', c.tiene_diferencia ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                                    {fmt(c.diferencia)}
                                </p>
                            </div>
                            <div className="col-span-1 flex flex-col items-center gap-1">
                                {c.estado === 'abierto'
                                    ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Abierta</span>
                                    : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 dark:bg-slate-700/40 dark:text-slate-300">Cerrada</span>
                                }
                                {c.sospechosa && (
                                    <span title={`Abierta desde hace ${c.dias_abierta} día(s) sin cerrar — se supone que las cajas cierran el mismo día`}
                                        className="flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 whitespace-nowrap">
                                        <Clock className="w-2.5 h-2.5" /> {c.dias_abierta}d
                                    </span>
                                )}
                            </div>
                            <div className="col-span-1 flex justify-end opacity-0 group-hover:opacity-100 transition-opacity">
                                {c.estado === 'abierto' && puede('editar') && (
                                    <button onClick={() => setCerrarCierre(c)}
                                        className="p-1.5 rounded hover:bg-orange-500/20 text-orange-500 transition-colors" title="Cerrar caja">
                                        <Lock className="w-3.5 h-3.5" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            )}

            {showAbrir && <AbrirModal cajas={cajas} centros={centros} aperturas={aperturas} denominaciones={denominaciones} onClose={() => setShowAbrir(false)} />}
            {cerrarCierre && <CerrarModal cierre={cerrarCierre} denominaciones={denominaciones} onClose={() => setCerrarCierre(null)} />}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
