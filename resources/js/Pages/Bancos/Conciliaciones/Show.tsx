import { useEffect, useRef, useState } from 'react'
import { router, usePage, Head, Link } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import { cn } from '@/lib/utils'
import {
    ChevronLeft, CheckCircle, AlertTriangle, GitMerge,
    Upload, ArrowLeftRight, Wand2, FileText,
} from 'lucide-react'
import type { PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Partida {
    id: number
    tipo: 'sistema' | 'banco'
    fecha: string | null
    descripcion: string | null
    monto: number
    conciliada: boolean
    movimiento?: { descripcion: string; monto: number; tipo: string } | null
}

interface Conciliacion {
    id: number
    banco_caja: { nombre: string; saldo_actual: number }
    banco_caja_id: number
    fecha_corte: string
    saldo_banco: number
    saldo_sistema: number
    diferencia: number
    descripcion: string | null
    estado: string
    partidas: Partida[]
}

interface Props extends PageProps {
    conciliacion: Conciliacion
}

interface UploadResult {
    match_auto: number
    match_probable: number
    sin_match: number
    total: number
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:   (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    warn: (msg: string) => toast.warn(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    err:  (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const fmt = (n: number) =>
    '$' + Number(n ?? 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function csrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function ConciliacionShow() {
    const { conciliacion, flash } = usePage<Props>().props

    const tieneDif = Math.abs(Number(conciliacion.diferencia)) > 0.01
    const esConciliada = conciliacion.estado === 'conciliada'

    // Partition partidas
    const sistemaPendientes = conciliacion.partidas.filter(p => p.tipo === 'sistema' && !p.conciliada)
    const bancoPendientes   = conciliacion.partidas.filter(p => p.tipo === 'banco'   && !p.conciliada)
    const conciliadas       = conciliacion.partidas.filter(p => p.conciliada)

    // ── Upload state ──────────────────────────────────────────────────────────
    const fileRef   = useRef<HTMLInputElement>(null)
    const [uploadFile, setUploadFile]         = useState<File | null>(null)
    const [uploading, setUploading]           = useState(false)
    const [uploadResult, setUploadResult]     = useState<UploadResult | null>(null)

    // ── Manual cruce state ────────────────────────────────────────────────────
    const [selSistema, setSelSistema]         = useState<number | null>(null)
    const [selBanco,   setSelBanco]           = useState<number | null>(null)
    const [cruzando,   setCruzando]           = useState(false)

    // ── Asiento ajuste ────────────────────────────────────────────────────────
    const [genAsiento, setGenAsiento]         = useState(false)

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.warning) notify.warn(flash.warning as string)
        if (flash?.error)   notify.err(flash.error as string)
    }, [flash?.success, flash?.warning, flash?.error])

    // ── Upload CSV ────────────────────────────────────────────────────────────
    async function handleUpload() {
        if (!uploadFile) return
        setUploading(true)
        setUploadResult(null)

        const fd = new FormData()
        fd.append('archivo', uploadFile)
        fd.append('_token', csrf())

        try {
            const res = await fetch(
                route('bancos.conciliaciones.upload-csv', conciliacion.id),
                { method: 'POST', body: fd }
            )
            const data: UploadResult & { error?: string } = await res.json()

            if (!res.ok) {
                notify.err(data.error ?? 'Error al procesar el archivo.')
            } else {
                setUploadResult(data)
                notify.ok(
                    `Auto-conciliados: ${data.match_auto} | Probables: ${data.match_probable} | Sin match: ${data.sin_match}`
                )
                router.reload({ only: ['conciliacion'] })
            }
        } catch {
            notify.err('Error de red al subir el archivo.')
        } finally {
            setUploading(false)
            setUploadFile(null)
            if (fileRef.current) fileRef.current.value = ''
        }
    }

    // ── Manual cruce ──────────────────────────────────────────────────────────
    async function handleCruzar() {
        if (!selSistema || !selBanco) return
        setCruzando(true)

        try {
            const res = await fetch(
                route('bancos.conciliaciones.conciliar-partida', conciliacion.id),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ partida_sistema_id: selSistema, partida_banco_id: selBanco }),
                }
            )
            const data: { ok?: boolean; error?: string } = await res.json()

            if (!res.ok) {
                notify.err(data.error ?? 'Error al cruzar partidas.')
            } else {
                notify.ok('Partidas cruzadas correctamente.')
                setSelSistema(null)
                setSelBanco(null)
                router.reload({ only: ['conciliacion'] })
            }
        } catch {
            notify.err('Error de red.')
        } finally {
            setCruzando(false)
        }
    }

    // ── Generar asiento de ajuste ─────────────────────────────────────────────
    async function handleGenerarAsiento() {
        setGenAsiento(true)
        try {
            const res = await fetch(
                route('bancos.conciliaciones.generar-asiento-ajuste', conciliacion.id),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ fecha: new Date().toISOString().slice(0, 10) }),
                }
            )
            const data: { ok?: boolean; asiento_id?: number; error?: string } = await res.json()

            if (!res.ok) {
                notify.err(data.error ?? 'Error al generar el asiento.')
            } else {
                notify.ok(`Asiento de ajuste #${data.asiento_id} generado correctamente.`)
            }
        } catch {
            notify.err('Error de red.')
        } finally {
            setGenAsiento(false)
        }
    }

    // ── Marcar conciliada ─────────────────────────────────────────────────────
    function marcarConciliada() {
        router.patch(route('bancos.conciliaciones.conciliar', conciliacion.id), {}, {
            onSuccess: () => notify.ok('Conciliación cerrada correctamente.'),
            onError:   () => notify.err('Error al cerrar la conciliación.'),
        })
    }

    // ─────────────────────────────────────────────────────────────────────────
    return (
        <AppLayout title="Detalle Conciliación" suppressFlash>
            <Head title={`Conciliación — ${conciliacion.banco_caja?.nombre}`} />

            {/* Header ─────────────────────────────────────────────────── */}
            <div className="px-6 pt-6 mb-6">
                <Link href={route('bancos.conciliaciones.index')}
                    className="inline-flex items-center gap-1.5 text-sm mb-4 hover:opacity-70 transition-opacity"
                    style={{ color: 'var(--text-muted)' }}>
                    <ChevronLeft className="w-4 h-4" /> Volver
                </Link>

                <div className="flex items-start justify-between flex-wrap gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2 rounded-xl"
                            style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                            <GitMerge size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                                {conciliacion.banco_caja?.nombre}
                            </h1>
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Corte al {conciliacion.fecha_corte}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap">
                        {!esConciliada && tieneDif && (
                            <button onClick={handleGenerarAsiento} disabled={genAsiento}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium border"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                                <Wand2 className="w-4 h-4" />
                                {genAsiento ? 'Generando…' : 'Asiento de Ajuste'}
                            </button>
                        )}
                        {!esConciliada && (
                            <button onClick={marcarConciliada}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white"
                                style={{ background: 'var(--primary)' }}>
                                <CheckCircle className="w-4 h-4" /> Cerrar Conciliación
                            </button>
                        )}
                        {esConciliada && (
                            <span className="px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                ✓ Conciliada
                            </span>
                        )}
                    </div>
                </div>
            </div>

            {/* Saldos ─────────────────────────────────────────────────── */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 px-6 mb-6">
                <div className="rounded-xl border p-4"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Saldo estado de cuenta</p>
                    <p className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                        {fmt(conciliacion.saldo_banco)}
                    </p>
                </div>
                <div className="rounded-xl border p-4"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Saldo sistema</p>
                    <p className="text-2xl font-bold" style={{ color: 'var(--text-main)' }}>
                        {fmt(conciliacion.saldo_sistema)}
                    </p>
                </div>
                <div className="rounded-xl border p-4" style={{
                    background:   tieneDif ? 'rgba(239,68,68,0.08)'  : 'rgba(16,185,129,0.08)',
                    borderColor:  tieneDif ? 'rgba(239,68,68,0.3)'   : 'rgba(16,185,129,0.3)',
                }}>
                    <div className="flex items-center gap-2 mb-1">
                        {tieneDif
                            ? <AlertTriangle className="w-4 h-4 text-red-500" />
                            : <CheckCircle   className="w-4 h-4 text-green-500" />
                        }
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Diferencia</p>
                    </div>
                    <p className={cn('text-2xl font-bold',
                        tieneDif ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400')}>
                        {fmt(conciliacion.diferencia)}
                    </p>
                    {!tieneDif && (
                        <p className="text-xs text-green-600 dark:text-green-400 mt-1">Cuentas cuadradas</p>
                    )}
                </div>
            </div>

            {/* Upload CSV ─────────────────────────────────────────────── */}
            {!esConciliada && (
                <div className="px-6 mb-6">
                    <div className="rounded-xl border p-4"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <p className="text-sm font-semibold mb-3" style={{ color: 'var(--text-main)' }}>
                            <Upload className="inline w-4 h-4 mr-1.5" />
                            Importar estado de cuenta bancario
                        </p>
                        <div className="flex items-center gap-3 flex-wrap">
                            <label className="flex-1 min-w-55">
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept=".csv,.txt,.xlsx,.xls"
                                    className="hidden"
                                    onChange={e => setUploadFile(e.target.files?.[0] ?? null)}
                                />
                                <div className={cn(
                                    'flex items-center gap-2 px-3 py-2 rounded-xl border text-sm cursor-pointer transition-colors',
                                    uploadFile
                                        ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20'
                                        : 'border-dashed hover:opacity-80'
                                )} style={{ borderColor: uploadFile ? undefined : 'var(--border)', color: 'var(--text-muted)' }}
                                    onClick={() => fileRef.current?.click()}>
                                    <FileText className="w-4 h-4 shrink-0" />
                                    <span className="truncate">
                                        {uploadFile ? uploadFile.name : 'Seleccionar archivo CSV / XLSX…'}
                                    </span>
                                </div>
                            </label>

                            <button
                                onClick={handleUpload}
                                disabled={!uploadFile || uploading}
                                className="px-4 py-2 rounded-xl text-sm font-medium text-white disabled:opacity-50"
                                style={{ background: 'var(--primary)' }}>
                                {uploading ? 'Procesando…' : 'Auto-conciliar'}
                            </button>
                        </div>

                        {uploadResult && (
                            <div className="mt-3 flex items-center gap-4 text-sm flex-wrap">
                                <span className="text-green-600 dark:text-green-400 font-medium">
                                    ✓ Auto: {uploadResult.match_auto}
                                </span>
                                <span className="text-amber-600 dark:text-amber-400 font-medium">
                                    ~ Probables: {uploadResult.match_probable}
                                </span>
                                <span style={{ color: 'var(--text-muted)' }}>
                                    ✗ Sin match: {uploadResult.sin_match}
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Panel doble: cruce manual ──────────────────────────────── */}
            {!esConciliada && (sistemaPendientes.length > 0 || bancoPendientes.length > 0) && (
                <div className="px-6 mb-6">
                    <p className="text-sm font-semibold mb-3 uppercase tracking-wider"
                        style={{ color: 'var(--text-muted)' }}>
                        Cruce manual — selecciona una de cada columna
                    </p>

                    <div className="grid grid-cols-[1fr_auto_1fr] gap-4 items-start">
                        {/* Sistema */}
                        <PanelPartidas
                            titulo="Sistema"
                            partidas={sistemaPendientes}
                            selected={selSistema}
                            onSelect={setSelSistema}
                            color="blue"
                        />

                        {/* Botón cruzar */}
                        <div className="flex flex-col items-center justify-start pt-8">
                            <button
                                onClick={handleCruzar}
                                disabled={!selSistema || !selBanco || cruzando}
                                title="Cruzar partidas seleccionadas"
                                className="flex flex-col items-center gap-1 px-3 py-3 rounded-xl border text-xs font-medium disabled:opacity-40 transition-all"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                <ArrowLeftRight className="w-5 h-5" />
                                {cruzando ? '…' : 'Cruzar'}
                            </button>
                        </div>

                        {/* Banco */}
                        <PanelPartidas
                            titulo="Banco (estado de cuenta)"
                            partidas={bancoPendientes}
                            selected={selBanco}
                            onSelect={setSelBanco}
                            color="purple"
                        />
                    </div>
                </div>
            )}

            {/* Partidas conciliadas ───────────────────────────────────── */}
            <div className="px-6 pb-8">
                <h2 className="text-sm font-semibold mb-3 uppercase tracking-wider"
                    style={{ color: 'var(--text-muted)' }}>
                    Partidas conciliadas ({conciliadas.length})
                </h2>

                {conciliadas.length === 0 ? (
                    <div className="rounded-xl border p-6 text-center"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Sin partidas conciliadas aún
                        </p>
                    </div>
                ) : (
                    <div className="border rounded-xl overflow-hidden"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <div className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                            style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                            <span className="col-span-1">Tipo</span>
                            <span className="col-span-2">Fecha</span>
                            <span className="col-span-7">Descripción</span>
                            <span className="col-span-2 text-right">Monto</span>
                        </div>
                        {conciliadas.map(p => (
                            <div key={p.id}
                                className="grid grid-cols-12 gap-2 px-4 py-2.5 border-b items-center text-sm last:border-b-0"
                                style={{ borderColor: 'var(--border)' }}>
                                <div className="col-span-1">
                                    <span className={cn(
                                        'px-1.5 py-0.5 rounded text-[10px] font-medium',
                                        p.tipo === 'sistema'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                    )}>
                                        {p.tipo === 'sistema' ? 'Sist.' : 'Banco'}
                                    </span>
                                </div>
                                <div className="col-span-2 text-xs font-mono" style={{ color: 'var(--text-muted)' }}>
                                    {p.fecha ?? '—'}
                                </div>
                                <div className="col-span-7 text-xs truncate" style={{ color: 'var(--text-main)' }}>
                                    {p.descripcion ?? '—'}
                                </div>
                                <div className="col-span-2 text-right text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                    {p.monto != null ? fmt(p.monto) : '—'}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}

// ─── Sub-componente PanelPartidas ─────────────────────────────────────────────

interface PanelPartidasProps {
    titulo: string
    partidas: Partida[]
    selected: number | null
    onSelect: (id: number | null) => void
    color: 'blue' | 'purple'
}

function PanelPartidas({ titulo, partidas, selected, onSelect, color }: PanelPartidasProps) {
    const fmt = (n: number) =>
        '$' + Number(n ?? 0).toLocaleString('es-EC', { minimumFractionDigits: 2 })

    const colorMap = {
        blue:   { header: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                  sel:    'border-blue-400 bg-blue-50 dark:bg-blue-900/20' },
        purple: { header: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
                  sel:    'border-purple-400 bg-purple-50 dark:bg-purple-900/20' },
    }[color]

    return (
        <div className="rounded-xl border overflow-hidden"
            style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
            <div className="px-3 py-2 border-b flex items-center justify-between"
                style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)' }}>
                <span className={cn('px-2 py-0.5 rounded text-[11px] font-semibold', colorMap.header)}>
                    {titulo}
                </span>
                <span className="text-[11px]" style={{ color: 'var(--text-muted)' }}>
                    {partidas.length} pendientes
                </span>
            </div>

            {partidas.length === 0 ? (
                <div className="p-4 text-center text-xs" style={{ color: 'var(--text-muted)' }}>
                    Sin partidas pendientes
                </div>
            ) : (
                <div className="max-h-72 overflow-y-auto divide-y" style={{ borderColor: 'var(--border)' }}>
                    {partidas.map(p => (
                        <div key={p.id}
                            onClick={() => onSelect(selected === p.id ? null : p.id)}
                            className={cn(
                                'px-3 py-2.5 cursor-pointer transition-colors border-l-2 hover:opacity-80',
                                selected === p.id
                                    ? colorMap.sel + ' border-l-current'
                                    : 'border-l-transparent'
                            )}
                            style={{ borderBottomColor: 'var(--border)' }}>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-[11px] font-mono" style={{ color: 'var(--text-muted)' }}>
                                    {p.fecha ?? '—'}
                                </span>
                                <span className="text-xs font-semibold shrink-0" style={{ color: 'var(--text-main)' }}>
                                    {fmt(p.monto)}
                                </span>
                            </div>
                            <p className="text-xs mt-0.5 truncate" style={{ color: 'var(--text-main)' }}>
                                {p.descripcion ?? '—'}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}
