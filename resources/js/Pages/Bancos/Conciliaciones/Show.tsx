import { useEffect, useRef, useState } from 'react'
import { router, usePage, Head, Link } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    ChevronLeft, CheckCircle, AlertTriangle, GitMerge,
    Upload, ArrowLeftRight, PlusCircle, Lock, Trash2, FilePlus2, X,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Partida {
    id: number
    tipo: 'sistema' | 'banco'
    fecha: string
    descripcion: string
    monto: number
    conciliada: boolean
    movimiento?: {
        id: number
        descripcion: string
        monto: number
        tipo: string
        sub_tipo: string
    } | null
}

interface CuentaSimple { id: number; codigo: string; nombre: string }

interface ConciliacionData {
    id: number
    estado: string
    fecha_corte: string
    saldo_banco: number
    saldo_sistema: number
    diferencia: number
    descripcion: string | null
    banco_caja: { nombre: string; saldo_actual: number }
}

interface Props extends PageProps {
    conciliacion: ConciliacionData
    partidas_sistema: Partida[]
    partidas_banco: Partida[]
    resumen: {
        total_sistema: number
        total_banco: number
        conciliadas: number
        pendientes: number
    }
    cuentas: CuentaSimple[]
    cuenta_comision_sugerida_id: number | null
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn:  (msg: string) => toast.warning(msg, { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number | string | null | undefined) =>
    '$' + Number(n ?? 0).toLocaleString('es-EC', { minimumFractionDigits: 2 })

// ─── Subcomponente: tabla de partidas ─────────────────────────────────────────

function TablaPartidas({
    partidas,
    titulo,
    color,
    seleccionada,
    onSeleccionar,
    isCerrada,
    onGenerarAsiento,
}: {
    partidas: Partida[]
    titulo: string
    color: string
    seleccionada: number | null
    onSeleccionar: (id: number | null) => void
    isCerrada: boolean
    onGenerarAsiento?: (p: Partida) => void
}) {
    const pendientes = partidas.filter(p => !p.conciliada)
    const conciliadas = partidas.filter(p => p.conciliada)

    return (
        <div className="rounded-xl border overflow-hidden flex flex-col"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            {/* Header */}
            <div className="px-4 py-3 border-b flex items-center justify-between"
                style={{ borderColor: 'var(--border)', borderLeft: `4px solid ${color}` }}>
                <div>
                    <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>{titulo}</h3>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        {pendientes.length} pendientes · {conciliadas.length} conciliadas
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs font-mono font-semibold" style={{ color }}>
                        {fmt(partidas.reduce((s, p) => s + Number(p.monto), 0))}
                    </p>
                    <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>total</p>
                </div>
            </div>

            {/* Filas */}
            <div className="overflow-y-auto" style={{ maxHeight: '420px' }}>
                {partidas.length === 0 ? (
                    <div className="py-10 text-center text-sm" style={{ color: 'var(--text-muted)' }}>
                        Sin partidas
                    </div>
                ) : (
                    partidas.map(p => (
                        <div key={p.id}
                            onClick={() => !isCerrada && !p.conciliada && onSeleccionar(seleccionada === p.id ? null : p.id)}
                            className={cn(
                                'flex items-center gap-3 px-4 py-2.5 border-b cursor-pointer transition-colors text-sm',
                                p.conciliada && 'opacity-50',
                                !p.conciliada && !isCerrada && seleccionada === p.id && 'bg-amber-50 dark:bg-amber-900/20',
                                !p.conciliada && !isCerrada && seleccionada !== p.id && 'hover:bg-gray-50 dark:hover:bg-white/5',
                            )}
                            style={{ borderColor: 'var(--border)' }}>
                            <div className="flex-1 min-w-0">
                                <p className="truncate text-xs font-medium" style={{ color: 'var(--text-main)' }}>
                                    {p.descripcion}
                                </p>
                                <p className="text-[10px] font-mono" style={{ color: 'var(--text-muted)' }}>
                                    {p.fecha}
                                </p>
                            </div>
                            <div className="text-right shrink-0">
                                <p className="text-xs font-semibold font-mono" style={{ color }}>
                                    {fmt(p.monto)}
                                </p>
                            </div>
                            {onGenerarAsiento && !p.conciliada && !isCerrada && (
                                <button
                                    onClick={e => { e.stopPropagation(); onGenerarAsiento(p) }}
                                    title="Generar asiento de esta partida"
                                    className="shrink-0 p-1 rounded hover:bg-purple-500/10 text-purple-500 transition-colors">
                                    <FilePlus2 className="w-3.5 h-3.5" />
                                </button>
                            )}
                            <div className="shrink-0">
                                {p.conciliada
                                    ? <CheckCircle className="w-4 h-4 text-green-500" />
                                    : <div className={cn(
                                        'w-4 h-4 rounded-full border-2',
                                        seleccionada === p.id ? 'border-amber-500 bg-amber-500' : 'border-gray-300'
                                    )} />
                                }
                            </div>
                        </div>
                    ))
                )}
            </div>
        </div>
    )
}

// ─── Modal: generar asiento de una partida puntual sin contraparte ───────────

function GenerarAsientoPartidaModal({
    conciliacionId, partida, cuentas, cuentaSugeridaId, onClose,
}: {
    conciliacionId: number
    partida: Partida
    cuentas: CuentaSimple[]
    cuentaSugeridaId: number | null
    onClose: () => void
}) {
    const [tipo, setTipo] = useState<'ingreso' | 'egreso'>('egreso')
    const [cuentaId, setCuentaId] = useState(cuentaSugeridaId ? String(cuentaSugeridaId) : '')
    const [cuentaBusq, setCuentaBusq] = useState('')
    const [showCuentas, setShowCuentas] = useState(false)
    const [descripcion, setDescripcion] = useState(partida.descripcion)
    const [processing, setProcessing] = useState(false)

    const cuentaSel = cuentas.find(c => c.id === Number(cuentaId))
    const cuentasFiltradas = cuentas.filter(c =>
        !cuentaBusq || `${c.codigo} ${c.nombre}`.toLowerCase().includes(cuentaBusq.toLowerCase())
    ).slice(0, 30)

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (!cuentaId) { notify.error('Selecciona la cuenta contable de destino.'); return }
        setProcessing(true)
        router.post(route('bancos.conciliaciones.generar-asiento-partida', [conciliacionId, partida.id]), {
            tipo,
            cuenta_contrapartida_id: cuentaId,
            descripcion,
        }, {
            onSuccess: () => { notify.ok('Movimiento y asiento generados. Partida conciliada.'); onClose() },
            onError: (errs) => { notify.error(Object.values(errs).join(' | ')); setProcessing(false) },
            onFinish: () => setProcessing(false),
        })
    }

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-md" onClick={e => e.stopPropagation()}>
                <div className="modal-header flex items-center justify-between px-6 py-4">
                    <h2 className="text-base font-semibold flex items-center gap-2" style={{ color: 'var(--text-main)' }}>
                        <FilePlus2 className="w-4 h-4" style={{ color: 'var(--primary)' }} />
                        Generar asiento de esta partida
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:bg-black/10">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                <form onSubmit={submit}>
                    <div className="modal-body px-6 py-5 space-y-4">
                        <div className="rounded-lg p-3 text-sm"
                            style={{ background: 'var(--bg-main)', border: '1px solid var(--border)' }}>
                            <p className="font-medium" style={{ color: 'var(--text-main)' }}>{partida.descripcion}</p>
                            <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                {partida.fecha} · Monto del extracto: <strong>{fmt(partida.monto)}</strong>
                            </p>
                        </div>

                        <div>
                            <Label className="input-label">Tipo</Label>
                            <div className="grid grid-cols-2 gap-2 mt-1">
                                {(['ingreso', 'egreso'] as const).map(t => (
                                    <button key={t} type="button" onClick={() => setTipo(t)}
                                        className={cn(
                                            'py-2 rounded-lg text-sm font-semibold border-2 transition-colors',
                                            tipo === t
                                                ? t === 'ingreso' ? 'bg-green-500/20 border-green-500 text-green-600 dark:text-green-400'
                                                                  : 'bg-red-500/20 border-red-500 text-red-600 dark:text-red-400'
                                                : 'border-transparent'
                                        )}
                                        style={tipo !== t ? { borderColor: 'var(--border)', color: 'var(--text-muted)' } : {}}>
                                        {t === 'ingreso' ? 'Ingreso' : 'Egreso'}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="relative">
                            <Label className="input-label">Cuenta contable de destino *</Label>
                            <div className="relative mt-1">
                                <input
                                    className="input-field w-full"
                                    value={cuentaBusq || (cuentaSel ? `${cuentaSel.codigo} — ${cuentaSel.nombre}` : '')}
                                    onChange={e => { setCuentaBusq(e.target.value); setShowCuentas(true) }}
                                    onFocus={() => setShowCuentas(true)}
                                    placeholder="Buscar cuenta…" />
                            </div>
                            {showCuentas && cuentasFiltradas.length > 0 && (
                                <div className="absolute z-20 w-full rounded-lg shadow-xl border overflow-hidden mt-1"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                                    <div className="max-h-48 overflow-y-auto">
                                        {cuentasFiltradas.map(c => (
                                            <button key={c.id} type="button"
                                                onClick={() => { setCuentaId(String(c.id)); setCuentaBusq(''); setShowCuentas(false) }}
                                                className="w-full text-left px-3 py-2 text-xs transition-colors"
                                                style={{ color: 'var(--text-main)' }}
                                                onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,.1)')}
                                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                                <span className="font-mono font-medium">{c.codigo}</span>
                                                <span className="ml-2" style={{ color: 'var(--text-muted)' }}>{c.nombre}</span>
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <p className="text-xs mt-1" style={{ color: 'var(--text-muted)' }}>
                                Sugerida por defecto: Comisiones Bancarias y Pasarelas de Pago — puedes cambiarla.
                            </p>
                        </div>

                        <div>
                            <Label className="input-label">Descripción</Label>
                            <textarea value={descripcion} onChange={e => setDescripcion(e.target.value)}
                                rows={2} className="input-field textarea-field w-full mt-1" />
                        </div>
                    </div>

                    <div className="modal-footer flex items-center justify-end gap-3 px-6 py-4">
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Generando…' : 'Generar movimiento y asiento'}
                        </button>
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function ConciliacionShow() {
    const { conciliacion, partidas_sistema, partidas_banco, resumen, flash, cuentas, cuenta_comision_sugerida_id } = usePage<Props>().props
    const { puede } = usePermiso('bancos')
    const tieneDif   = Math.abs(Number(conciliacion.diferencia)) > 0.01
    const isCerrada  = conciliacion.estado === 'conciliada'

    const [selSistema, setSelSistema] = useState<number | null>(null)
    const [selBanco,   setSelBanco]   = useState<number | null>(null)
    const [uploading,  setUploading]  = useState(false)
    const [partidaAsiento, setPartidaAsiento] = useState<Partida | null>(null)
    const [ajusteDesc, setAjusteDesc] = useState('')
    const [showAjuste, setShowAjuste] = useState(false)
    const fileRef = useRef<HTMLInputElement>(null)

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success as string)
        if (flash?.warning) notify.warn(flash.warning as string)
        if (flash?.error)   notify.error(flash.error as string)
    }, [flash])

    // ── Upload CSV ─────────────────────────────────────────────────────────────
    function handleUpload(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0]
        if (!file) return
        setUploading(true)
        const data = new FormData()
        data.append('archivo', file)
        router.post(route('bancos.conciliaciones.upload-csv', conciliacion.id), data, {
            forceFormData: true,
            onFinish: () => { setUploading(false); if (fileRef.current) fileRef.current.value = '' },
        })
    }

    // ── Cruce manual ───────────────────────────────────────────────────────────
    function enviarCruce(generarAjuste: boolean) {
        router.post(route('bancos.conciliaciones.conciliar-partida', conciliacion.id), {
            partida_sistema_id: selSistema,
            partida_banco_id:   selBanco,
            generar_ajuste:     generarAjuste,
        }, {
            onSuccess: () => { setSelSistema(null); setSelBanco(null) },
        })
    }

    async function cruzarPartidas() {
        if (!selSistema || !selBanco) return
        const pSis = partidas_sistema.find(p => p.id === selSistema)
        const pBan = partidas_banco.find(p => p.id === selBanco)
        const diferencia = pSis && pBan ? Number(pBan.monto) - Number(pSis.monto) : 0

        if (Math.abs(diferencia) > 0.01) {
            const res = await Swal.fire({
                title: 'Montos distintos',
                html: `Sistema: <strong>${fmt(pSis!.monto)}</strong> · Banco: <strong>${fmt(pBan!.monto)}</strong><br/>` +
                      `Diferencia: <strong>${fmt(Math.abs(diferencia))}</strong><br/><br/>` +
                      `¿Deseas generar un asiento de ajuste por esta diferencia, o confirmar el cruce de todas formas?`,
                icon: 'warning',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'Generar ajuste y cruzar',
                denyButtonText: 'Cruzar sin ajuste',
                cancelButtonText: 'Cancelar',
            })
            if (res.isConfirmed) { enviarCruce(true); return }
            if (res.isDenied)    { enviarCruce(false); return }
            return // cancelado
        }

        enviarCruce(false)
    }

    // ── Asiento de ajuste ──────────────────────────────────────────────────────
    function generarAjuste() {
        router.post(route('bancos.conciliaciones.generar-asiento-ajuste', conciliacion.id), {
            descripcion: ajusteDesc || undefined,
        }, {
            onSuccess: () => { setAjusteDesc(''); setShowAjuste(false) },
        })
    }

    // ── Cerrar conciliación ────────────────────────────────────────────────────
    function cerrarConciliacion() {
        if (!confirm('¿Cerrar esta conciliación? No se podrá reabrir.')) return
        router.patch(route('bancos.conciliaciones.cerrar', conciliacion.id))
    }

    const puedenCruzarse = selSistema !== null && selBanco !== null

    return (
        <AppLayout title="Detalle Conciliación" suppressFlash>
            <Head title={`Conciliación — ${conciliacion.banco_caja?.nombre}`} />

            <PageHeader
                title={conciliacion.banco_caja?.nombre}
                description={`Corte al ${conciliacion.fecha_corte}`}
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Conciliaciones' }, { label: conciliacion.banco_caja?.nombre }]}
                actions={
                    <div className="flex items-center gap-2 flex-wrap">
                        <Link href={route('bancos.conciliaciones.index')}
                            className="inline-flex items-center gap-1.5 text-sm hover:opacity-70 transition-opacity shrink-0"
                            style={{ color: 'var(--text-muted)' }}>
                            <ChevronLeft className="w-4 h-4" /> Volver
                        </Link>

                        {!isCerrada && (
                            <>
                                {/* Upload CSV */}
                                {puede('crear') && (
                                    <label className={cn(
                                        'flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium cursor-pointer transition-opacity',
                                        uploading && 'opacity-50 cursor-not-allowed'
                                    )} style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', color: 'var(--text-main)' }}>
                                        <Upload className="w-3.5 h-3.5" />
                                        {uploading ? 'Cargando…' : 'Cargar CSV banco'}
                                        <input ref={fileRef} type="file" accept=".csv,.txt" className="hidden"
                                            onChange={handleUpload} disabled={uploading} />
                                    </label>
                                )}

                                {/* Ajuste */}
                                {tieneDif && puede('editar') && (
                                    <button onClick={() => setShowAjuste(v => !v)}
                                        className="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium text-white"
                                        style={{ background: '#7c3aed' }}>
                                        <PlusCircle className="w-3.5 h-3.5" /> Asiento ajuste
                                    </button>
                                )}

                                {/* Cerrar */}
                                {puede('anular') && (
                                    <button onClick={cerrarConciliacion}
                                        disabled={resumen.pendientes > 0}
                                        title={resumen.pendientes > 0 ? 'Hay partidas pendientes sin conciliar' : ''}
                                        className="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium text-white disabled:opacity-40"
                                        style={{ background: '#1d4ed8' }}>
                                        <Lock className="w-3.5 h-3.5" /> Cerrar conciliación
                                    </button>
                                )}
                            </>
                        )}
                    </div>
                }
            />

            {/* ── Panel ajuste ─────────────────────────────────────────────── */}
            {showAjuste && (
                <div className="mx-6 mb-4 rounded-xl border p-4 flex items-end gap-3"
                    style={{ background: 'rgba(124,58,237,0.06)', borderColor: 'rgba(124,58,237,0.3)' }}>
                    <div className="flex-1">
                        <label className="text-xs font-medium block mb-1" style={{ color: 'var(--text-muted)' }}>
                            Descripción del ajuste (opcional)
                        </label>
                        <input type="text" value={ajusteDesc} onChange={e => setAjusteDesc(e.target.value)}
                            placeholder={`Ajuste conciliación ${conciliacion.banco_caja?.nombre}`}
                            className="input-field w-full" />
                    </div>
                    {puede('editar') && (
                        <button onClick={generarAjuste}
                            className="px-4 py-2 rounded-xl text-sm font-medium text-white shrink-0"
                            style={{ background: '#7c3aed' }}>
                            Generar
                        </button>
                    )}
                    <button onClick={() => setShowAjuste(false)}
                        className="px-3 py-2 rounded-xl text-sm text-gray-500 hover:text-gray-700 shrink-0">
                        <Trash2 className="w-4 h-4" />
                    </button>
                </div>
            )}

            {/* ── Resumen saldos ────────────────────────────────────────────── */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 px-6 mb-5">
                <div className="rounded-xl border p-3"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-[10px] uppercase tracking-wider mb-1" style={{ color: 'var(--text-muted)' }}>Saldo banco</p>
                    <p className="text-lg font-bold" style={{ color: 'var(--text-main)' }}>{fmt(conciliacion.saldo_banco)}</p>
                </div>
                <div className="rounded-xl border p-3"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-[10px] uppercase tracking-wider mb-1" style={{ color: 'var(--text-muted)' }}>Saldo sistema</p>
                    <p className="text-lg font-bold" style={{ color: 'var(--text-main)' }}>{fmt(conciliacion.saldo_sistema)}</p>
                </div>
                <div className="rounded-xl border p-3"
                    style={{
                        background: tieneDif ? 'rgba(239,68,68,0.06)' : 'rgba(16,185,129,0.06)',
                        borderColor: tieneDif ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)',
                    }}>
                    <div className="flex items-center gap-1.5 mb-1">
                        {tieneDif
                            ? <AlertTriangle className="w-3.5 h-3.5 text-red-500" />
                            : <CheckCircle  className="w-3.5 h-3.5 text-green-500" />
                        }
                        <p className="text-[10px] uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>Diferencia</p>
                    </div>
                    <p className={cn('text-lg font-bold', tieneDif ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                        {fmt(conciliacion.diferencia)}
                    </p>
                </div>
                <div className="rounded-xl border p-3"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-[10px] uppercase tracking-wider mb-1" style={{ color: 'var(--text-muted)' }}>Estado</p>
                    <div className="flex items-center gap-2">
                        <span className={cn(
                            'px-2 py-0.5 rounded-full text-xs font-semibold',
                            isCerrada
                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-600'
                        )}>
                            {isCerrada ? 'Conciliada' : 'Pendiente'}
                        </span>
                    </div>
                    <p className="text-[10px] mt-1" style={{ color: 'var(--text-muted)' }}>
                        {resumen.conciliadas} cruzadas · {resumen.pendientes} pendientes
                    </p>
                </div>
            </div>

            {/* ── Botón cruce ──────────────────────────────────────────────── */}
            {!isCerrada && puede('editar') && (
                <div className="px-6 mb-4 flex items-center gap-3">
                    <div className="text-xs" style={{ color: 'var(--text-muted)' }}>
                        {selSistema && selBanco
                            ? 'Partidas seleccionadas — listo para cruzar'
                            : 'Selecciona una partida de cada panel para cruzarlas'}
                    </div>
                    <button onClick={cruzarPartidas}
                        disabled={!puedenCruzarse}
                        className={cn(
                            'flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium disabled:opacity-40 transition-opacity',
                            puedenCruzarse ? 'text-black' : 'text-white',
                        )}
                        style={{ background: puedenCruzarse ? 'var(--primary)' : 'gray' }}>
                        <ArrowLeftRight className="w-4 h-4" /> Cruzar partidas
                    </button>
                </div>
            )}

            {/* ── Dos paneles ──────────────────────────────────────────────── */}
            <div className="px-6 pb-8 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <TablaPartidas
                    partidas={partidas_sistema}
                    titulo="Partidas del sistema"
                    color="#1A3A5C"
                    seleccionada={selSistema}
                    onSeleccionar={setSelSistema}
                    isCerrada={isCerrada}
                />
                <TablaPartidas
                    partidas={partidas_banco}
                    titulo="Partidas del banco (CSV)"
                    color="#2D6A4F"
                    seleccionada={selBanco}
                    onSeleccionar={setSelBanco}
                    isCerrada={isCerrada}
                    onGenerarAsiento={puede('editar') ? setPartidaAsiento : undefined}
                />
            </div>

            {partidaAsiento && (
                <GenerarAsientoPartidaModal
                    conciliacionId={conciliacion.id}
                    partida={partidaAsiento}
                    cuentas={cuentas}
                    cuentaSugeridaId={cuenta_comision_sugerida_id}
                    onClose={() => setPartidaAsiento(null)}
                />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
