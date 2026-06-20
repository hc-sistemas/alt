import { Head, router, usePage } from '@inertiajs/react'
import { useCallback, useEffect, useRef, useState } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { PackageCheck, ScanBarcode, PenLine } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import type { RecepcionBodega, PageProps } from '@/types'

interface Props extends PageProps {
    recepcion: RecepcionBodega
}

type Modo = 'pistoleo' | 'manual'

interface ScanFeedback {
    tipo: 'ok' | 'error'
    mensaje: string
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

export default function RecepcionShow() {
    const { recepcion, auth } = usePage<Props>().props
    const isPendiente = recepcion.estado === 'pendiente'
    const detalles = recepcion.detalles ?? []

    const perfilesPermitidos = ['super_admin', 'admin', 'bodeguero']
    const puedeOperar = perfilesPermitidos.includes(
        (auth.user?.perfil ?? '').toLowerCase()
    )

    const [modo, setModo] = useState<Modo>('pistoleo')

    const [cantidades, setCantidades] = useState<Record<number, number>>(() => {
        const init: Record<number, number> = {}
        detalles.forEach(d => { init[d.id] = Number(d.cantidad_recibida) })
        return init
    })

    const [confirmando, setConfirmando] = useState(false)
    const scanRef = useRef<HTMLInputElement>(null)
    const [scanValue, setScanValue] = useState('')
    const [scanFeedback, setScanFeedback] = useState<ScanFeedback | null>(null)
    const feedbackTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

    const totalEsperado = detalles.reduce((acc, d) => acc + Number(d.cantidad_esperada), 0)
    const totalRecibido = detalles.reduce((acc, d) => acc + (cantidades[d.id] ?? 0), 0)
    const porcentaje = totalEsperado > 0 ? Math.min((totalRecibido / totalEsperado) * 100, 100) : 0

    function getEstadoLinea(detalleId: number, cantEsperada: number): string {
        const recibida = cantidades[detalleId] ?? 0
        if (recibida >= cantEsperada) return 'completado'
        if (recibida > 0) return 'parcial'
        return 'pendiente'
    }

    const mostrarFeedback = useCallback((tipo: 'ok' | 'error', mensaje: string) => {
        if (feedbackTimer.current) clearTimeout(feedbackTimer.current)
        setScanFeedback({ tipo, mensaje })
        feedbackTimer.current = setTimeout(() => setScanFeedback(null), 2500)
    }, [])

    useEffect(() => {
        return () => {
            if (feedbackTimer.current) clearTimeout(feedbackTimer.current)
        }
    }, [])

    useEffect(() => {
        if (modo === 'pistoleo' && isPendiente) {
            setTimeout(() => scanRef.current?.focus(), 50)
        }
    }, [modo, isPendiente])

    async function buscarPorCodigo(codigo: string) {
        if (!codigo.trim()) return

        try {
            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
            const res = await fetch(route('inventario.recepciones.buscarProducto') + '?codigo=' + encodeURIComponent(codigo.trim()), {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            })
            const json = await res.json() as { encontrado: boolean; producto: { id: number; nombre: string } | null }

            if (!json.encontrado || !json.producto) {
                mostrarFeedback('error', `Codigo no reconocido: "${codigo}"`)
                setScanValue('')
                scanRef.current?.focus()
                return
            }

            const detalle = detalles.find(d => d.producto_id === json.producto!.id)
            if (!detalle) {
                mostrarFeedback('error', `Producto no pertenece a esta recepcion`)
                setScanValue('')
                scanRef.current?.focus()
                return
            }

            const maxEsperada = Number(detalle.cantidad_esperada)
            const cantActual = cantidades[detalle.id] ?? 0

            if (cantActual >= maxEsperada) {
                mostrarFeedback('error', `"${json.producto.nombre}" ya alcanzo la cantidad esperada (${maxEsperada})`)
                setScanValue('')
                scanRef.current?.focus()
                return
            }

            setCantidades(prev => ({
                ...prev,
                [detalle.id]: Math.min((prev[detalle.id] ?? 0) + 1, maxEsperada),
            }))

            mostrarFeedback('ok', `+1 ${json.producto.nombre}`)
            setScanValue('')
            scanRef.current?.focus()
        } catch {
            mostrarFeedback('error', 'Error al buscar producto')
            setScanValue('')
            scanRef.current?.focus()
        }
    }

    function handleScanKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            e.preventDefault()
            buscarPorCodigo(scanValue)
        }
    }

    async function ejecutarConfirmar() {
        const hayAlguno = detalles.some(d => (cantidades[d.id] ?? 0) > 0)
        if (!hayAlguno) {
            toastError('Debes registrar al menos una cantidad recibida antes de confirmar.')
            return
        }

        setConfirmando(true)
        try {
            const payload = {
                detalles: detalles.map(d => ({
                    id: d.id,
                    cantidad_recibida: cantidades[d.id] ?? 0,
                })),
            }

            const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? ''
            const res = await fetch(route('inventario.recepciones.confirmar', recepcion.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            })

            if (!res.ok) {
                const json = await res.json().catch(() => null) as { message?: string } | null
                toastError(json?.message ?? 'Error al confirmar la recepcion')
                return
            }

            toastExito('Recepcion confirmada correctamente')
            router.reload()
        } catch {
            toastError('Error al confirmar la recepcion')
        } finally {
            setConfirmando(false)
        }
    }

    const fmtCantidad = (n: number) => Number.isInteger(n) ? String(n) : n.toString()

    const formatFecha = (dt: string | null) =>
        dt ? new Date(dt).toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'

    return (
        <AppLayout title={`Recepcion #${recepcion.id}`}>
            <Head title={`Recepcion #${recepcion.id}`} />
            <PageHeader
                title={`Recepcion #${recepcion.id}`}
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
                            <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>Fecha recepcion</p>
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

                {/* Toggle de modo */}
                {isPendiente && puedeOperar && (
                    <div className="flex gap-1 rounded-lg p-1" style={{ background: 'var(--border)' }}>
                        <button
                            onClick={() => setModo('pistoleo')}
                            className="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all flex-1 justify-center"
                            style={{
                                background: modo === 'pistoleo' ? 'var(--bg-card)' : 'transparent',
                                color: modo === 'pistoleo' ? 'var(--primary)' : 'var(--text-muted)',
                                boxShadow: modo === 'pistoleo' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
                            }}
                        >
                            <ScanBarcode className="w-4 h-4" />
                            Modo pistoleo
                        </button>
                        <button
                            onClick={() => setModo('manual')}
                            className="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all flex-1 justify-center"
                            style={{
                                background: modo === 'manual' ? 'var(--bg-card)' : 'transparent',
                                color: modo === 'manual' ? 'var(--primary)' : 'var(--text-muted)',
                                boxShadow: modo === 'manual' ? '0 1px 3px rgba(0,0,0,0.1)' : 'none',
                            }}
                        >
                            <PenLine className="w-4 h-4" />
                            Modo manual
                        </button>
                    </div>
                )}

                {/* Campo de escaneo (modo pistoleo) */}
                {isPendiente && modo === 'pistoleo' && (
                    <div className="rounded-xl border p-6 space-y-3" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <div className="flex items-center gap-3">
                            <ScanBarcode className="w-6 h-6 shrink-0" style={{ color: 'var(--primary)' }} />
                            <input
                                ref={scanRef}
                                type="text"
                                value={scanValue}
                                onChange={e => setScanValue(e.target.value)}
                                onKeyDown={handleScanKeyDown}
                                placeholder="Escanea o escribe el codigo de barras..."
                                className="flex-1 h-12 rounded-lg border bg-transparent px-4 text-base font-mono focus:outline-none focus:ring-2"
                                style={{
                                    borderColor: 'var(--border)',
                                    color: 'var(--text-main)',
                                    '--tw-ring-color': 'var(--primary)',
                                } as React.CSSProperties}
                                autoFocus
                            />
                        </div>
                        {scanFeedback && (
                            <div
                                className="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium animate-in fade-in duration-200"
                                style={{
                                    background: scanFeedback.tipo === 'ok'
                                        ? 'rgba(16, 185, 129, 0.12)'
                                        : 'rgba(239, 68, 68, 0.12)',
                                    color: scanFeedback.tipo === 'ok'
                                        ? 'rgb(5, 150, 105)'
                                        : 'rgb(220, 38, 38)',
                                }}
                            >
                                {scanFeedback.mensaje}
                            </div>
                        )}
                    </div>
                )}

                {/* Barra de progreso */}
                <div className="rounded-xl border p-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>Progreso de recepcion</span>
                        <span className="text-sm font-mono" style={{ color: 'var(--text-muted)' }}>
                            {totalRecibido} / {totalEsperado} unidades ({porcentaje.toFixed(0)}%)
                        </span>
                    </div>
                    <div className="w-full h-3 rounded-full overflow-hidden" style={{ background: 'var(--border)' }}>
                        <div
                            className="h-full rounded-full transition-all duration-300"
                            style={{ width: `${porcentaje}%`, background: 'var(--primary)' }}
                        />
                    </div>
                </div>

                {/* Tabla de productos */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>Productos</h3>
                    </div>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Codigo</th>
                                <th className="text-left px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Producto</th>
                                <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Esperado</th>
                                <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Recibidos</th>
                                <th className="text-center px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Estado</th>
                                {isPendiente && modo === 'manual' && (
                                    <th className="text-right px-4 py-2.5 font-medium text-xs" style={{ color: 'var(--text-muted)' }}>Ajuste</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {detalles.map(d => {
                                const cantRecibida = cantidades[d.id] ?? 0
                                const cantEsperada = Number(d.cantidad_esperada)
                                const estadoLinea = getEstadoLinea(d.id, cantEsperada)

                                return (
                                    <tr key={d.id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                        <td className="px-4 py-2.5 font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                            {d.producto?.codigo ?? '—'}
                                        </td>
                                        <td className="px-4 py-2.5 font-medium" style={{ color: 'var(--text-main)' }}>
                                            {d.producto?.nombre ?? `#${d.producto_id}`}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                            {fmtCantidad(cantEsperada)}
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-mono" style={{ color: 'var(--text-main)' }}>
                                            {fmtCantidad(cantRecibida)}
                                        </td>
                                        <td className="px-4 py-2.5 text-center">
                                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${ESTADO_COLORES[estadoLinea] ?? ''}`}>
                                                {ESTADO_LABELS[estadoLinea] ?? estadoLinea}
                                            </span>
                                        </td>
                                        {isPendiente && modo === 'manual' && (
                                            <td className="px-4 py-2.5 text-right w-32">
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={cantEsperada}
                                                    step="1"
                                                    value={cantRecibida}
                                                    onChange={e => {
                                                        const val = Math.min(parseFloat(e.target.value) || 0, cantEsperada)
                                                        setCantidades(prev => ({ ...prev, [d.id]: val }))
                                                    }}
                                                    className="w-full h-8 rounded-md border bg-transparent px-2 text-sm text-right font-mono"
                                                    style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                                />
                                            </td>
                                        )}
                                    </tr>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Botones de accion */}
                <div className="flex gap-3 pt-2">
                    {isPendiente && puedeOperar && (
                        <Button onClick={ejecutarConfirmar} loading={confirmando}>
                            <PackageCheck className="w-4 h-4" />
                            Confirmar recepcion
                        </Button>
                    )}
                    <Button variant="outline" onClick={() => router.visit(route('inventario.recepciones.index'))}>
                        Volver
                    </Button>
                </div>
            </div>
        </AppLayout>
    )
}
