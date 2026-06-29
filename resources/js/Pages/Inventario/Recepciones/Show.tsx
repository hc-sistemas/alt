import { Head, router, usePage } from '@inertiajs/react'
import { useCallback, useEffect, useRef, useState } from 'react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { PackageCheck, ScanBarcode, Check, Clock } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { RecepcionBodega, PageProps } from '@/types'

interface Props extends PageProps {
    recepcion: RecepcionBodega
}

interface EtiquetaItem {
    detalle_id: number
    producto_id: number
    producto_nombre: string
    codigo_escaneado: string
    verificado: boolean
}

interface GrupoProducto {
    detalle_id: number
    producto_id: number
    producto_nombre: string
    etiquetas: EtiquetaItem[]
    verificadas: number
    total: number
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente:  'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    completada: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    completado: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    parcial:    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
}

const ESTADO_LABELS: Record<string, string> = {
    pendiente: 'Pendiente', completada: 'Completada', completado: 'Completado', parcial: 'Parcial',
}

function agruparPorProducto(etiquetas: EtiquetaItem[]): GrupoProducto[] {
    const mapa = new Map<number, GrupoProducto>()

    for (const et of etiquetas) {
        let grupo = mapa.get(et.detalle_id)
        if (!grupo) {
            grupo = {
                detalle_id: et.detalle_id,
                producto_id: et.producto_id,
                producto_nombre: et.producto_nombre,
                etiquetas: [],
                verificadas: 0,
                total: 0,
            }
            mapa.set(et.detalle_id, grupo)
        }
        grupo.etiquetas.push(et)
        grupo.total++
        if (et.verificado) grupo.verificadas++
    }

    for (const grupo of mapa.values()) {
        grupo.etiquetas.sort((a, b) => {
            if (a.verificado !== b.verificado) return a.verificado ? 1 : -1
            return a.codigo_escaneado.localeCompare(b.codigo_escaneado)
        })
    }

    return Array.from(mapa.values())
}

export default function RecepcionShow() {
    const { recepcion, flash } = usePage<Props>().props
    const isPendiente = recepcion.estado === 'pendiente'

    const [etiquetas, setEtiquetas] = useState<EtiquetaItem[]>([])
    const [cargando, setCargando] = useState(true)
    const [confirmando, setConfirmando] = useState(false)
    const [scanValue, setScanValue] = useState('')
    const [scanError, setScanError] = useState<string | null>(null)
    const [codigoReciente, setCodigoReciente] = useState<string | null>(null)

    const scanRef = useRef<HTMLInputElement>(null)
    const feedbackTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
    const recienteTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

    const cargarEtiquetas = useCallback(async () => {
        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
            const res = await fetch(route('inventario.recepciones.etiquetas', recepcion.id), {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            })
            if (res.ok) {
                const data = await res.json() as EtiquetaItem[]
                setEtiquetas(data)
            }
        } catch {
            // silenciar — la lista queda vacía
        } finally {
            setCargando(false)
        }
    }, [recepcion.id])

    const isFirstRender = useRef(true)
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false
            cargarEtiquetas()
            return
        }
    }, [cargarEtiquetas])

    useEffect(() => {
        return () => {
            if (feedbackTimer.current) clearTimeout(feedbackTimer.current)
            if (recienteTimer.current) clearTimeout(recienteTimer.current)
        }
    }, [])

    useEffect(() => {
        if (isPendiente) {
            setTimeout(() => scanRef.current?.focus(), 50)
        }
    }, [isPendiente])

    function mostrarErrorEscaneo(mensaje: string) {
        if (feedbackTimer.current) clearTimeout(feedbackTimer.current)
        setScanError(mensaje)
        feedbackTimer.current = setTimeout(() => setScanError(null), 4000)
    }

    function marcarReciente(codigo: string) {
        if (recienteTimer.current) clearTimeout(recienteTimer.current)
        setCodigoReciente(codigo)
        recienteTimer.current = setTimeout(() => setCodigoReciente(null), 2000)
    }

    function handleScanKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            e.preventDefault()
            const codigo = scanValue.trim()
            if (!codigo) return

            setScanError(null)
            setScanValue('')

            router.post(
                route('inventario.recepciones.escanear', recepcion.id),
                { codigo },
                {
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const pageFlash = (page.props as Props['flash'] & { escaneo?: { detalle_id: number; cantidad_verificada: number; detalle_completo: boolean } }).escaneo
                        const errorFlash = (page.props as Props).flash?.error

                        if (errorFlash) {
                            mostrarErrorEscaneo(errorFlash)
                        } else if (pageFlash) {
                            marcarReciente(codigo)
                        }

                        cargarEtiquetas()
                        scanRef.current?.focus()
                    },
                    onError: () => {
                        mostrarErrorEscaneo('Error al procesar el escaneo.')
                        scanRef.current?.focus()
                    },
                }
            )
        }
    }

    async function ejecutarConfirmar() {
        const isDark = document.documentElement.classList.contains('dark')

        const result = await Swal.fire({
            title: '¿Confirmar recepción?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
            cancelButtonColor: '#64748B',
            iconColor: '#F59E0B',
            background: isDark ? '#1E293B' : '#FFFFFF',
            color: isDark ? '#F1F5F9' : '#0F172A',
        })

        if (!result.isConfirmed) return

        setConfirmando(true)
        router.post(
            route('inventario.recepciones.confirmar', recepcion.id),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const successFlash = (page.props as Props).flash?.success
                    const errorFlash = (page.props as Props).flash?.error
                    if (errorFlash) {
                        toastError(errorFlash)
                    } else {
                        toastExito(successFlash ?? 'Recepción confirmada correctamente.')
                    }
                    setConfirmando(false)
                },
                onError: () => {
                    toastError('Error al confirmar la recepción.')
                    setConfirmando(false)
                },
            }
        )
    }

    const grupos = agruparPorProducto(etiquetas)
    const totalVerificadas = etiquetas.filter(e => e.verificado).length
    const totalEtiquetas = etiquetas.length
    const porcentaje = totalEtiquetas > 0 ? Math.min((totalVerificadas / totalEtiquetas) * 100, 100) : 0
    const todasVerificadas = totalEtiquetas > 0 && totalVerificadas === totalEtiquetas

    const formatFecha = (dt: string | null) =>
        dt ? new Date(dt).toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'

    // Handle flash from initial page load
    useEffect(() => {
        if (flash?.error) mostrarErrorEscaneo(flash.error)
    }, [flash?.error])

    return (
        <AppLayout title={`Recepción #${recepcion.id}`}>
            <Head title={`Recepción #${recepcion.id}`} />
            <PageHeader
                title={`Recepción #${recepcion.id}`}
                breadcrumbs={[
                    { label: 'Inventario' },
                    { label: 'Recepciones', href: route('inventario.recepciones.index') },
                    { label: `#${recepcion.id}` },
                ]}
            />

            <div className="p-6 max-w-4xl space-y-6">
                {/* Header info */}
                <div className="rounded-xl border p-5 space-y-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center gap-3 flex-wrap">
                        <span className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                            Factura: {recepcion.compra?.num_documento ?? '—'}
                        </span>
                        <span className={`ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${ESTADO_COLORES[recepcion.estado] ?? ''}`}>
                            {ESTADO_LABELS[recepcion.estado] ?? recepcion.estado}
                        </span>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Proveedor</p>
                            <p style={{ color: 'var(--text-main)' }}>{recepcion.compra?.proveedor?.razon_social ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Bodega</p>
                            <p style={{ color: 'var(--text-main)' }}>{recepcion.bodega?.nombre ?? '—'}</p>
                        </div>
                        <div>
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha recepción</p>
                            <p style={{ color: 'var(--text-main)' }}>{formatFecha(recepcion.fecha_recepcion)}</p>
                        </div>
                        {recepcion.recibidoPor && (
                            <div>
                                <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Recibido por</p>
                                <p style={{ color: 'var(--text-main)' }}>{recepcion.recibidoPor.nombre}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Campo de escaneo */}
                {isPendiente && (
                    <div className="rounded-xl border p-6 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <div className="flex items-center gap-3">
                            <ScanBarcode className="w-6 h-6 shrink-0" style={{ color: 'var(--primary)' }} />
                            <input
                                ref={scanRef}
                                type="text"
                                value={scanValue}
                                onChange={e => setScanValue(e.target.value)}
                                onKeyDown={handleScanKeyDown}
                                placeholder="Escanear código de barras..."
                                className="flex-1 h-12 rounded-lg border bg-transparent px-4 text-base font-mono focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--border)',
                                    color: 'var(--text-main)',
                                    '--tw-ring-color': 'var(--primary)',
                                } as React.CSSProperties}
                                autoFocus
                            />
                        </div>
                        {scanError && (
                            <div
                                className="px-4 py-2.5 rounded-lg text-sm font-medium"
                                style={{ background: 'rgba(239, 68, 68, 0.12)', color: 'rgb(220, 38, 38)' }}
                            >
                                {scanError}
                            </div>
                        )}
                    </div>
                )}

                {/* Barra de progreso */}
                <div className="rounded-xl border p-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                            {totalVerificadas} de {totalEtiquetas} etiquetas verificadas
                        </span>
                        <span className="text-sm font-mono" style={{ color: 'var(--text-muted)' }}>
                            {porcentaje.toFixed(0)}%
                        </span>
                    </div>
                    <div className="w-full h-3 rounded-full overflow-hidden" style={{ background: 'var(--border)' }}>
                        <div
                            className="h-full rounded-full transition-all duration-300"
                            style={{ width: `${porcentaje}%`, background: 'var(--primary)' }}
                        />
                    </div>
                </div>

                {/* Lista de etiquetas agrupadas */}
                {cargando ? (
                    <div className="rounded-xl border p-8 text-center" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Cargando etiquetas...</p>
                    </div>
                ) : grupos.length === 0 ? (
                    <div className="rounded-xl border p-8 text-center" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No hay etiquetas generadas para esta compra.</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {grupos.map(grupo => (
                            <div
                                key={grupo.detalle_id}
                                className="rounded-xl border overflow-hidden"
                                style={{ borderColor: 'var(--border)' }}
                            >
                                <div
                                    className="px-4 py-3 flex items-center justify-between"
                                    style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}
                                >
                                    <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                        {grupo.producto_nombre}
                                    </span>
                                    <span className="text-xs font-mono" style={{
                                        color: grupo.verificadas === grupo.total ? 'rgb(5, 150, 105)' : 'var(--text-muted)',
                                    }}>
                                        {grupo.verificadas} / {grupo.total} verificadas
                                    </span>
                                </div>
                                <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                                    {grupo.etiquetas.map(et => {
                                        const esReciente = codigoReciente === et.codigo_escaneado

                                        return (
                                            <div
                                                key={et.codigo_escaneado}
                                                className="flex items-center justify-between px-4 py-2 text-sm transition-colors duration-500"
                                                style={{
                                                    background: esReciente
                                                        ? 'rgba(245, 158, 11, 0.12)'
                                                        : 'transparent',
                                                    borderColor: 'var(--border)',
                                                }}
                                            >
                                                <span
                                                    className="font-mono text-xs"
                                                    style={{ color: et.verificado ? 'var(--text-main)' : 'var(--text-muted)' }}
                                                >
                                                    {et.codigo_escaneado}
                                                </span>
                                                {et.verificado ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                        <Check className="w-3 h-3" />
                                                        OK
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                        <Clock className="w-3 h-3" />
                                                        Pendiente
                                                    </span>
                                                )}
                                            </div>
                                        )
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Botones de acción */}
                <div className="flex gap-3 pt-2">
                    {isPendiente && (
                        <Button
                            onClick={ejecutarConfirmar}
                            loading={confirmando}
                            disabled={!todasVerificadas}
                        >
                            <PackageCheck className="w-4 h-4" />
                            Confirmar recepción
                        </Button>
                    )}
                    <Button variant="outline" onClick={() => {
                        const redirectTo = new URLSearchParams(window.location.search).get('redirect_to')
                        router.visit(redirectTo ?? route('inventario.recepciones.index'))
                    }}>
                        Volver
                    </Button>
                </div>
            </div>
        </AppLayout>
    )
}
