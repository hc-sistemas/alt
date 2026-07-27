import { useState, useEffect, useMemo } from 'react'
import { router, usePage, Link } from '@inertiajs/react'
import { ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    BookOpen, Plus, Eye, XCircle, CheckCircle,
    AlertTriangle, User, X, FileText, Zap, Download, Search,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { usePermiso } from '@/Hooks/usePermiso'
import type { AsientoContable, CentroCosto, EjercicioContable, PlanCuenta, PageProps } from '@/types'
import { notify, formatMoney, formatFecha, swalBase, injectSwalStyles } from '@/utils/contabilidad'
import 'react-toastify/dist/ReactToastify.css'

interface Partida {
    cuenta_id:       string
    centro_costo_id: string
    descripcion:     string
    debe:            string
    haber:           string
}

// El paginador de Laravel pone current_page, last_page, total, etc. en el nivel raíz (no bajo meta)
interface PaginatedAsiento {
    data: AsientoContable[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

interface Props extends PageProps {
    asientos:      PaginatedAsiento | null
    ejercicios:    EjercicioContable[]
    cuentas:       PlanCuenta[]
    centros:       CentroCosto[]
    periodoActivo: EjercicioContable | null
    filtros:       Record<string, string>
}

const PARTIDA_VACIA: Partida = { cuenta_id: '', centro_costo_id: '', descripcion: '', debe: '', haber: '' }

const TIPO_BADGE = {
    activo: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    anulado: 'bg-red-100   text-red-800   dark:bg-red-900/30   dark:text-red-300',
}

export default function AsientosIndex() {
    const { asientos, ejercicios, cuentas, centros, periodoActivo, filtros, flash, auth }
        = usePage<Props>().props
    const { puede } = usePermiso('contabilidad')
    const puedeCrear = puede('crear')
    const puedeAnular = puede('anular')

    // Filtros
    const [buscar, setBuscar] = useState(filtros.buscar ?? '')
    const [tipo, setTipo] = useState(filtros.tipo ?? '')
    const [estado, setEstado] = useState(filtros.estado ?? '')
    const [ejercicioId, setEjercicioId] = useState(filtros.ejercicio_id ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')

    // Carga bajo demanda: `asientos` viene null hasta que se dispare una búsqueda explícita
    const haBuscado = asientos !== null

    // Modal PDF
    const [modalPdf, setModalPdf] = useState(false)
    const [urlPdf,   setUrlPdf]   = useState('')
    const [cargandoPdf, setCargandoPdf] = useState(false)

    // Se trae el PDF como blob (fetch) en vez de apuntar el <iframe> directo a la
    // URL del backend: aunque el backend ya responde con Content-Disposition:
    // inline, algunos navegadores igual fuerzan la descarga en una navegación de
    // iframe según su propia configuración de manejo de PDF. Un blob: URL siempre
    // se muestra embebido, sin depender de esa configuración.
    const abrirPdf = async (url: string) => {
        setModalPdf(true)
        setCargandoPdf(true)
        setUrlPdf('')
        try {
            const res = await fetch(url)
            if (!res.ok) throw new Error('No se pudo generar el PDF.')
            const blob = await res.blob()
            setUrlPdf(URL.createObjectURL(blob))
        } catch {
            notify.error('No se pudo generar el PDF. Intenta de nuevo.')
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

    // Modal nuevo asiento
    const [modalAbierto, setModalAbierto] = useState(false)
    const [processing, setProcessing] = useState(false)
    const [concepto, setConcepto] = useState('')
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0])
    const [partidas, setPartidas] = useState<Partida[]>([{ ...PARTIDA_VACIA }, { ...PARTIDA_VACIA }])
    const [buscarCuenta, setBuscarCuenta] = useState<Record<number, string>>({})
    const [dropdownOpen, setDropdownOpen] = useState<number | null>(null)

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.error) notify.error(flash.error)
        if (flash?.warning) notify.warning(flash.warning)
    }, [flash?.success, flash?.error, flash?.warning])

    // Totales en tiempo real
    const totalDebe = partidas.reduce((s, p) => s + (parseFloat(p.debe) || 0), 0)
    const totalHaber = partidas.reduce((s, p) => s + (parseFloat(p.haber) || 0), 0)
    const diferencia = Math.abs(totalDebe - totalHaber)
    const cuadrado = diferencia < 0.001

    // CORRECCIÓN 2: mostrar más resultados
    const cuentasFiltradas = (idx: number) => {
        const q = (buscarCuenta[idx] ?? '').toLowerCase().trim()
        if (!q) return cuentas.slice(0, 30)
        return cuentas
            .filter(c => c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q))
            .slice(0, 25)
    }

    const aplicarFiltros = () => {
        router.get(route('contabilidad.asientos.index'), {
            buscar, tipo, estado, ejercicio_id: ejercicioId,
            fecha_desde: fechaDesde, fecha_hasta: fechaHasta,
            buscado: '1',
        }, { preserveState: false })
    }

    const limpiarFiltros = () => {
        setBuscar(''); setTipo(''); setEstado('')
        setEjercicioId(''); setFechaDesde(''); setFechaHasta('')
        router.get(route('contabilidad.asientos.index'))
    }

    const hayFiltros = !!(buscar || tipo || estado || ejercicioId || fechaDesde || fechaHasta)

    // Con miles de asientos reales en producción, un PDF/Excel sin ningún filtro
    // intenta procesar el histórico completo (~11 mil registros) y no termina en
    // tiempo razonable — a diferencia del listado paginado, aquí exigimos al
    // menos un filtro real (no cuenta `buscar`, que ninguno de los dos endpoints
    // recibe) antes de permitir exportar.
    const hayFiltrosExportables = !!(tipo || estado || ejercicioId || fechaDesde || fechaHasta)

    const exportarExcel = () => {
        if (!hayFiltrosExportables) {
            notify.error('Aplica al menos un filtro (período, fechas, tipo o estado) antes de exportar a Excel.')
            return
        }
        const params = new URLSearchParams({
            ejercicio_id: ejercicioId,
            fecha_desde:  fechaDesde,
            fecha_hasta:  fechaHasta,
            tipo:         tipo,
            estado:       estado,
        })
        window.location.href = route('contabilidad.asientos.exportar-excel') + '?' + params
    }

    const actualizarPartida = (idx: number, campo: keyof Partida, valor: string) => {
        setPartidas(p => p.map((row, i) => i === idx ? { ...row, [campo]: valor } : row))
    }

    const seleccionarCuenta = (idx: number, cuenta: PlanCuenta) => {
        actualizarPartida(idx, 'cuenta_id', String(cuenta.id))
        setBuscarCuenta(prev => ({ ...prev, [idx]: `${cuenta.codigo} — ${cuenta.nombre}` }))
        setDropdownOpen(null)
    }

    const cerrarModal = () => {
        setModalAbierto(false)
        setConcepto('')
        setFecha(new Date().toISOString().split('T')[0])
        setPartidas([{ ...PARTIDA_VACIA }, { ...PARTIDA_VACIA }])
        setBuscarCuenta({})
        setDropdownOpen(null)
    }

    const guardarAsiento = () => {
        if (!concepto.trim()) { notify.error('El concepto es obligatorio'); return }
        if (!cuadrado) { notify.error(`No cuadra. Diferencia: ${formatMoney(diferencia)}`); return }
        const limpias = partidas.filter(p => p.cuenta_id)
        if (limpias.length < 2) { notify.error('Selecciona al menos 2 cuentas'); return }

        setProcessing(true)
        router.post(route('contabilidad.asientos.store'), {
            concepto,
            fecha,
            partidas: limpias.map(p => ({
                cuenta_id:       parseInt(p.cuenta_id),
                centro_costo_id: p.centro_costo_id ? parseInt(p.centro_costo_id) : null,
                descripcion:     p.descripcion || null,
                debe:            parseFloat(p.debe) || 0,
                haber:           parseFloat(p.haber) || 0,
            })),
        }, {
            onSuccess: () => cerrarModal(),
            onError: (errors) => notify.error(Object.values(errors).flat().join(' | ')),
            onFinish: () => setProcessing(false),
        })
    }

    const confirmarAnulacion = async (asiento: AsientoContable) => {
        injectSwalStyles()
        const { value: motivo } = await Swal.fire({
            ...swalBase,
            title: `Anular ${asiento.numero}`,
            html: `
                <div style="text-align:left">
                    <div style="background:var(--bg-main,#f9fafb);border-left:4px solid #F59E0B;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-family:monospace;font-weight:700;color:#F59E0B;margin:0 0 4px 0">
                            ${asiento.numero}
                        </p>
                        <p style="color:#374151;margin:0;font-size:0.875rem">${asiento.concepto}</p>
                        <p style="color:#9ca3af;margin:4px 0 0 0;font-size:0.8rem">
                            DEBE: ${formatMoney(asiento.total_debe)} ·
                            HABER: ${formatMoney(asiento.total_haber)}
                        </p>
                    </div>
                    <div style="background:#fef2f2;border:1px solid #fecaca;
                                border-radius:8px;padding:10px;margin-bottom:14px">
                        <p style="font-weight:700;color:#dc2626;margin:0 0 4px 0;font-size:0.85rem">
                            Se generará un asiento de reversión automáticamente
                        </p>
                        <p style="color:#ef4444;font-size:0.8rem;margin:0">
                            El original queda como ANULADO y no puede borrarse.
                        </p>
                    </div>
                    <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                  display:block;margin-bottom:6px">
                        Motivo <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="motivo-anulacion"
                        style="width:100%;border:2px solid #e5e7eb;border-radius:8px;
                               padding:10px;font-size:0.875rem;resize:vertical;
                               min-height:80px;box-sizing:border-box;font-family:inherit"
                        placeholder="ej: Error en el monto, documento incorrecto...">
                    </textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '❌ Anular asiento',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            preConfirm: () => {
                const m = (document.getElementById('motivo-anulacion') as HTMLTextAreaElement)?.value
                if (!m || m.length < 10) {
                    Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres')
                    return false
                }
                return m
            },
        })

        if (motivo) {
            router.patch(route('contabilidad.asientos.anular', asiento.id), { motivo })
        }
    }

    return (
        <AppLayout title="Asientos Contables" suppressFlash>
            <PageHeader
                title="Asientos Contables"
                breadcrumbs={[{ label: 'Contabilidad' }, { label: 'Asientos Contables' }]}
                description={
                    periodoActivo ? (
                        <span className="flex items-center gap-1.5 text-green-600 dark:text-green-400">
                            <CheckCircle size={12} className="shrink-0" />
                            Período activo: {periodoActivo.periodo_label}
                            {periodoActivo.fecha_apertura && (
                                <> · Abierto desde {formatFecha(periodoActivo.fecha_apertura)}</>
                            )}
                        </span>
                    ) : (
                        <span className="flex items-center gap-1.5 text-red-600 dark:text-red-400">
                            <AlertTriangle size={12} className="shrink-0" />
                            Sin período activo — no se pueden crear asientos.
                        </span>
                    )
                }
                actions={
                    puedeCrear ? (
                        <button
                            onClick={() => {
                                if (!periodoActivo) {
                                    notify.error('No hay período activo.')
                                    return
                                }
                                setModalAbierto(true)
                            }}
                            className="btn-primary flex items-center gap-2 whitespace-nowrap shrink-0"
                            style={{ color: '#000' }}
                        >
                            <Plus size={15} />
                            Nuevo Asiento
                        </button>
                    ) : undefined
                }
            />

            <div className={cn('space-y-5', 'p-6')}>

                {/*
                    searchWidth="w-[130px]": utilidad Tailwind de valor arbitrario (compila a
                    `width: 130px` literal, no la escala fija w-44/w-48/etc.) — mismo efecto que
                    un style inline, sin tocar FilterToolbar.tsx. El <Input> ahí se combina con
                    `cn()` (twMerge), que sí resuelve bien conflictos entre utilidades Tailwind
                    (a diferencia del bug de `.input-field` con las cascade layers, que no aplica
                    a este componente). Ancho recalculado más angosto que la ronda anterior (200px)
                    porque los botones PDF/Excel solo se habilitan cuando hay un filtro real
                    aplicado — y en ese escenario el botón "Limpiar" también está visible, sumando
                    ~67px extra a la fila que el cálculo anterior no contemplaba.
                */}
                <FilterToolbar
                    search={{
                        value: buscar,
                        onChange: setBuscar,
                        onSearch: aplicarFiltros,
                        placeholder: 'Buscar...',
                    }}
                    searchWidth="w-[130px]"
                    onExport={exportarExcel}
                    exportDisabled={!haBuscado || !hayFiltrosExportables}
                    extraActions={
                        <button
                            onClick={() => {
                                if (!hayFiltrosExportables) {
                                    notify.error('Aplica al menos un filtro (período, fechas, tipo o estado) antes de generar el PDF.')
                                    return
                                }
                                abrirPdf(
                                    `${route('contabilidad.asientos.reporte-pdf')}` +
                                    `?ejercicio_id=${ejercicioId}` +
                                    `&fecha_desde=${fechaDesde}` +
                                    `&fecha_hasta=${fechaHasta}` +
                                    `&tipo=${tipo}` +
                                    `&estado=${estado}`
                                )
                            }}
                            disabled={!haBuscado || !hayFiltrosExportables}
                            title="PDF"
                            className="flex items-center justify-center w-9 h-9 rounded-md border text-sm font-medium shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
                            style={{ background: '#ef4444', color: 'white', borderColor: '#ef4444' }}>
                            <FileText className="w-4 h-4" />
                        </button>
                    }
                >
                    {/*
                        Ancho vía `style.width` inline a propósito, NO clases Tailwind (w-28/w-32):
                        `.input-field` (app.css) declara `width:100%` fuera de cualquier @layer, y
                        las utilidades de Tailwind v4 viven dentro de su @layer utilities interno —
                        por reglas de CSS Cascade Layers, lo no-layereado siempre gana sobre lo
                        layereado sin importar especificidad ni orden, así que un w-28/w-32 de
                        Tailwind nunca puede ganarle a `.input-field`. Solo un estilo inline
                        (fuera de la cascada) lo puede sobreescribir de forma confiable.
                    */}
                    <select value={tipo} onChange={e => setTipo(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '145px' }}>
                        <option value="">Tipo</option>
                        <option value="manual">Manuales</option>
                        <option value="automatico">Automáticos</option>
                    </select>
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '125px' }}>
                        <option value="">Estado</option>
                        <option value="activo">Activos</option>
                        <option value="anulado">Anulados</option>
                    </select>
                    <select value={ejercicioId} onChange={e => setEjercicioId(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '175px' }}>
                        <option value="">Período</option>
                        {ejercicios.map(e => (
                            <option key={e.id} value={e.id}>{e.periodo_label}</option>
                        ))}
                    </select>
                    <div className="flex flex-col gap-0.5 shrink-0">
                        <span className="text-[11px] leading-none" style={{ color: 'var(--text-muted)' }}>Desde</span>
                        <input type="date" value={fechaDesde}
                            onChange={e => setFechaDesde(e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '136px' }} />
                    </div>
                    <div className="flex flex-col gap-0.5 shrink-0">
                        <span className="text-[11px] leading-none" style={{ color: 'var(--text-muted)' }}>Hasta</span>
                        <input type="date" value={fechaHasta}
                            onChange={e => setFechaHasta(e.target.value)}
                            className="input-field text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '136px' }} />
                    </div>
                    {hayFiltros && (
                        <button type="button" onClick={limpiarFiltros} className="text-sm underline shrink-0" style={{ color: 'var(--text-muted)' }}>
                            Limpiar
                        </button>
                    )}
                </FilterToolbar>

                {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar los asientos.
                        </p>
                    </div>
                )}

                {/* Tabla */}
                {haBuscado && asientos && (
                <div className={cn('border', 'rounded-xl', 'overflow-hidden')}
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="overflow-x-auto">
                        <table className={cn('w-full', 'text-sm')}>
                            <thead>
                                <tr style={{
                                    borderBottom: '1px solid var(--border)',
                                    background: 'rgba(245,158,11,0.05)'
                                }}>
                                    {['Número', 'Fecha', 'Concepto', 'Tipo', 'Debe', 'Haber', 'Estado', ''].map(h => (
                                        <th key={h}
                                            className={cn('px-4', 'py-3', 'font-semibold', 'text-xs', 'text-left', 'uppercase', 'tracking-wider')}
                                            style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {asientos.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className={cn('py-16', 'text-center')}
                                            style={{ color: 'var(--text-muted)' }}>
                                            <BookOpen size={40} className={cn('opacity-20', 'mx-auto', 'mb-3')} />
                                            <p className="font-medium">No hay asientos registrados</p>
                                            <p className={cn('mt-1', 'text-xs')}>Ajusta los filtros o crea el primer asiento.</p>
                                        </td>
                                    </tr>
                                ) : asientos.data.map(a => (
                                    <tr key={a.id}
                                        className="transition-colors"
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                        <td className={cn('px-4', 'py-3')}>
                                            <span className={cn('font-mono', 'font-bold', 'text-xs')}
                                                style={{ color: 'var(--primary)' }}>
                                                {a.numero}
                                            </span>
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'whitespace-nowrap')}
                                            style={{ color: 'var(--text-muted)' }}>
                                            {formatFecha(a.fecha)}
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'max-w-55')}>
                                            <p className={cn('font-medium', 'text-xs', 'truncate')}
                                                style={{ color: 'var(--text-main)' }}
                                                title={a.concepto}>
                                                {a.concepto}
                                            </p>
                                            {a.documento_ref && (
                                                <p className={cn('mt-0.5', 'text-xs', 'truncate')}
                                                    style={{ color: 'var(--text-muted)' }}>
                                                    {a.documento_tipo} · {a.documento_ref}
                                                </p>
                                            )}
                                        </td>
                                        <td className={cn('px-4', 'py-3')}>
                                            {a.es_automatico ? (
                                                <span className={cn('inline-flex', 'items-center', 'gap-1', 'bg-blue-100', 'dark:bg-blue-900/30', 'px-2', 'py-0.5', 'rounded-full', 'font-semibold', 'text-blue-800', 'dark:text-blue-300', 'text-xs')}>
                                                    <Zap size={10} /> Auto
                                                </span>
                                            ) : (
                                                <span className={cn('inline-flex', 'items-center', 'gap-1', 'bg-amber-100', 'dark:bg-amber-900/30', 'px-2', 'py-0.5', 'rounded-full', 'font-semibold', 'text-amber-800', 'dark:text-amber-300', 'text-xs')}>
                                                    <User size={10} /> Manual
                                                </span>
                                            )}
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'font-medium', 'tabular-nums', 'text-right')}
                                            style={{ color: 'var(--text-main)' }}>
                                            {formatMoney(a.total_debe)}
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'font-medium', 'tabular-nums', 'text-right')}
                                            style={{ color: 'var(--text-main)' }}>
                                            {formatMoney(a.total_haber)}
                                        </td>
                                        <td className={cn('px-4', 'py-3')}>
                                            <span className={cn(
                                                'inline-flex items-center gap-1 px-2 py-0.5',
                                                'rounded-full text-xs font-semibold',
                                                a.estado === 1
                                                    ? TIPO_BADGE.activo
                                                    : TIPO_BADGE.anulado,
                                            )}>
                                                {a.estado === 1
                                                    ? <><CheckCircle size={10} /> Activo</>
                                                    : <><XCircle size={10} /> Anulado</>
                                                }
                                            </span>
                                        </td>
                                        <td className={cn('px-4', 'py-3')}>
                                            <div className={cn('flex', 'items-center', 'gap-1')}>
                                                <Link
                                                    href={route('contabilidad.asientos.show', a.id)}
                                                    title="Ver detalle"
                                                    className={cn('hover:bg-blue-100', 'dark:hover:bg-blue-900/30', 'p-1.5', 'rounded-lg', 'transition-colors')}>
                                                    <Eye size={14} className="text-blue-500" />
                                                </Link>
                                                {puedeAnular && a.estado === 1 && (
                                                    <button
                                                        onClick={() => confirmarAnulacion(a)}
                                                        title="Anular asiento"
                                                        className={cn('hover:bg-red-100', 'dark:hover:bg-red-900/30', 'p-1.5', 'rounded-lg', 'transition-colors')}>
                                                        <XCircle size={14} className="text-red-500" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación */}
                    {asientos.last_page > 1 && (
                        <div className={cn('flex', 'justify-between', 'items-center', 'px-4', 'py-3', 'border-t')}
                            style={{ borderColor: 'var(--border)' }}>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Mostrando {asientos.from}–{asientos.to} de {asientos.total}
                            </p>
                            <div className={cn('flex', 'gap-1')}>
                                {asientos.links.map((link, i) => (
                                    link.url ? (
                                        <Link key={i} href={link.url}
                                            className={cn(
                                                'px-3 py-1 rounded font-medium text-xs transition-colors',
                                                link.active
                                                    ? 'text-white'
                                                    : 'hover:opacity-80',
                                            )}
                                            style={link.active
                                                ? { background: 'var(--primary)', color: '#fff' }
                                                : {
                                                    background: 'var(--bg-main)', color: 'var(--text-muted)',
                                                    border: '1px solid var(--border)'
                                                }
                                            }
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span key={i}
                                            className={cn('opacity-40', 'px-3', 'py-1', 'rounded', 'text-xs')}
                                            style={{ color: 'var(--text-muted)' }}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                ))}
                            </div>
                        </div>
                    )}
                </div>
                )}
            </div>

            {/* MODAL NUEVO ASIENTO MANUAL */}
            {modalAbierto && (
                <div className={cn('z-50', 'fixed', 'inset-0', 'flex', 'justify-center', 'items-center', 'p-4')}
                    style={{ background: 'rgba(0,0,0,0.6)' }}>
                    <div className={cn('flex', 'flex-col', 'shadow-2xl', 'rounded-2xl', 'w-full', 'max-w-4xl', 'max-h-[90vh]')}
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                        {/* Header modal */}
                        <div className={cn('flex', 'justify-between', 'items-center', 'px-6', 'py-4', 'shrink-0')}
                            style={{ borderBottom: '2px solid var(--primary)' }}>
                            <div>
                                <h2 className={cn('flex', 'items-center', 'gap-2', 'font-bold', 'text-lg')}
                                    style={{ color: 'var(--text-main)' }}>
                                    <BookOpen size={20} style={{ color: 'var(--primary)' }} />
                                    Nuevo Asiento Manual
                                </h2>
                                {periodoActivo && (
                                    <p className={cn('mt-0.5', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                                        Período: <strong>{periodoActivo.periodo_label}</strong>
                                    </p>
                                )}
                            </div>
                            <button onClick={cerrarModal}
                                className={cn('flex', 'justify-center', 'items-center', 'rounded-full', 'w-8', 'h-8', 'text-lg', 'leading-none', 'transition-colors')}
                                style={{ color: 'var(--text-muted)' }}
                                onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--border)'; }}
                                onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'transparent'; }}>×</button>
                        </div>

                        <div className={cn('flex-1', 'space-y-4', 'px-6', 'py-4', 'overflow-y-auto')}>
                            {/* Concepto + Fecha */}
                            <div className={cn('gap-4', 'grid', 'grid-cols-1', 'md:grid-cols-3')}>
                                <div className="md:col-span-2">
                                    <Label>Concepto <span className="text-red-500">*</span></Label>
                                    <Input
                                        className="mt-1"
                                        value={concepto}
                                        onChange={e => setConcepto(e.target.value)}
                                        placeholder="Descripción del asiento contable"
                                        maxLength={500}
                                    />
                                </div>
                                <div>
                                    <Label>Fecha <span className="text-red-500">*</span></Label>
                                    <input
                                        type="date"
                                        value={fecha}
                                        onChange={e => setFecha(e.target.value)}
                                        className="input-field mt-1"
                                    />
                                </div>
                            </div>

                            {/* Partidas */}
                            <div>
                                <div className={cn('flex', 'justify-between', 'items-center', 'mb-2')}>
                                    <Label>Partidas <span className="text-red-500">*</span></Label>
                                    <button
                                        onClick={() => setPartidas(p => [...p, { ...PARTIDA_VACIA }])}
                                        disabled={partidas.length >= 20}
                                        className={cn('flex', 'items-center', 'gap-1', 'disabled:opacity-40', 'px-2', 'py-1', 'rounded-lg', 'font-semibold', 'text-xs', 'transition-colors')}
                                        style={{ color: 'var(--primary)' }}>
                                        <Plus size={12} /> Agregar fila
                                    </button>
                                </div>

                                {/* Cabecera partidas */}
                                <div className={cn('gap-2', 'grid', 'grid-cols-12', 'mb-1', 'px-1')}>
                                    <span className={cn('col-span-3', 'font-semibold', 'text-xs', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Cuenta</span>
                                    <span className={cn('col-span-2', 'font-semibold', 'text-xs', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Centro Costo</span>
                                    <span className={cn('col-span-2', 'font-semibold', 'text-xs', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Descripción</span>
                                    <span className={cn('col-span-2', 'font-semibold', 'text-xs', 'text-right', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Debe</span>
                                    <span className={cn('col-span-2', 'font-semibold', 'text-xs', 'text-right', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Haber</span>
                                    <span className="col-span-1" />
                                </div>

                                <div className="space-y-2">
                                    {partidas.map((p, idx) => (
                                        <div key={idx} className={cn('items-center', 'gap-2', 'grid', 'grid-cols-12')}>
                                            {/* Selector de cuenta con búsqueda */}
                                            <div className={cn('relative', 'col-span-3')}>
                                                <input
                                                    type="text"
                                                    value={buscarCuenta[idx] ?? ''}
                                                    onChange={e => {
                                                        setBuscarCuenta(prev => ({ ...prev, [idx]: e.target.value }))
                                                        if (!e.target.value) actualizarPartida(idx, 'cuenta_id', '')
                                                        setDropdownOpen(idx)
                                                    }}
                                                    onFocus={() => setDropdownOpen(idx)}
                                                    placeholder="Buscar cuenta..."
                                                    className="input-field text-xs"
                                                />
                                                {dropdownOpen === idx && (
                                                    <div className={cn('top-full', 'right-0', 'left-0', 'z-50', 'absolute', 'shadow-xl', 'mt-1', 'border', 'rounded-lg', 'overflow-hidden')}
                                                        style={{
                                                            background: 'var(--bg-card)',
                                                            borderColor: 'var(--border)',
                                                            maxHeight: '180px', overflowY: 'auto'
                                                        }}>
                                                        {cuentasFiltradas(idx).length === 0 ? (
                                                            <p className={cn('px-3', 'py-2', 'text-xs')}
                                                                style={{ color: 'var(--text-muted)' }}>
                                                                Sin resultados
                                                            </p>
                                                        ) : cuentasFiltradas(idx).map(c => (
                                                            <button key={c.id}
                                                                type="button"
                                                                onClick={() => seleccionarCuenta(idx, c)}
                                                                className={cn('hover:opacity-80', 'px-3', 'py-2', 'w-full', 'text-xs', 'text-left', 'transition-colors')}
                                                                style={{ borderBottom: '1px solid var(--border)' }}
                                                                onMouseEnter={e =>
                                                                (e.currentTarget.style.background =
                                                                    'rgba(245,158,11,0.08)')}
                                                                onMouseLeave={e =>
                                                                    (e.currentTarget.style.background = 'transparent')}>
                                                                <span className={cn('font-mono', 'font-bold')}
                                                                    style={{ color: 'var(--primary)' }}>
                                                                    {c.codigo}
                                                                </span>
                                                                <span className="ml-2"
                                                                    style={{ color: 'var(--text-main)' }}>
                                                                    {c.nombre}
                                                                </span>
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Centro de costo */}
                                            <select
                                                value={p.centro_costo_id}
                                                onChange={e => actualizarPartida(idx, 'centro_costo_id', e.target.value)}
                                                className="input-field col-span-2 text-xs"
                                            >
                                                <option value="">— Sin centro —</option>
                                                {centros.map(c => (
                                                    <option key={c.id} value={String(c.id)}>
                                                        {c.nombre}
                                                    </option>
                                                ))}
                                            </select>

                                            {/* Descripción */}
                                            <input
                                                type="text"
                                                value={p.descripcion}
                                                onChange={e => actualizarPartida(idx, 'descripcion', e.target.value)}
                                                placeholder="Detalle..."
                                                className="input-field col-span-2 text-xs"
                                            />

                                            {/* Debe */}
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={p.debe}
                                                onChange={e => {
                                                    actualizarPartida(idx, 'debe', e.target.value)
                                                    if (parseFloat(e.target.value) > 0)
                                                        actualizarPartida(idx, 'haber', '')
                                                }}
                                                placeholder="0.00"
                                                className="input-field col-span-2 tabular-nums text-xs text-right"
                                            />

                                            {/* Haber */}
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={p.haber}
                                                onChange={e => {
                                                    actualizarPartida(idx, 'haber', e.target.value)
                                                    if (parseFloat(e.target.value) > 0)
                                                        actualizarPartida(idx, 'debe', '')
                                                }}
                                                placeholder="0.00"
                                                className="input-field col-span-2 tabular-nums text-xs text-right"
                                            />

                                            {/* Eliminar fila */}
                                            <button
                                                type="button"
                                                onClick={() => setPartidas(p => p.filter((_, i) => i !== idx))}
                                                disabled={partidas.length <= 2}
                                                className={cn('flex', 'justify-center', 'col-span-1', 'hover:bg-red-100', 'dark:hover:bg-red-900/30', 'disabled:opacity-20', 'p-1', 'rounded', 'transition-colors', 'disabled:cursor-not-allowed')}>
                                                <X size={13} className="text-red-500" />
                                            </button>
                                        </div>
                                    ))}
                                </div>

                                {/* Totales */}
                                <div className={cn('flex', 'justify-end', 'mt-3')}>
                                    <div className={cn('px-4', 'py-3', 'border', 'rounded-lg', 'min-w-70')}
                                        style={{
                                            background: cuadrado
                                                ? 'color-mix(in srgb, #10b981 8%, var(--bg-main))'
                                                : 'color-mix(in srgb, #ef4444 8%, var(--bg-main))',
                                            borderColor: cuadrado ? '#10b981' : '#ef4444',
                                        }}>
                                        <div className={cn('flex', 'justify-between', 'mb-1', 'text-sm')}>
                                            <span style={{ color: 'var(--text-muted)' }}>Total DEBE</span>
                                            <span className={cn('font-bold', 'tabular-nums')}
                                                style={{ color: 'var(--text-main)' }}>
                                                {formatMoney(totalDebe)}
                                            </span>
                                        </div>
                                        <div className={cn('flex', 'justify-between', 'mb-2', 'text-sm')}>
                                            <span style={{ color: 'var(--text-muted)' }}>Total HABER</span>
                                            <span className={cn('font-bold', 'tabular-nums')}
                                                style={{ color: 'var(--text-main)' }}>
                                                {formatMoney(totalHaber)}
                                            </span>
                                        </div>
                                        <div className={cn('flex', 'items-center', 'gap-2', 'pt-2', 'border-t')}
                                            style={{ borderColor: cuadrado ? '#10b981' : '#ef4444' }}>
                                            {cuadrado ? (
                                                <>
                                                    <CheckCircle size={14} className="text-green-500" />
                                                    <span className={cn('font-semibold', 'text-green-600', 'text-xs')}>
                                                        Asiento cuadrado ✓
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <AlertTriangle size={14} className="text-red-500" />
                                                    <span className={cn('font-semibold', 'text-red-600', 'text-xs')}>
                                                        Diferencia: {formatMoney(diferencia)}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Footer modal */}
                        <div className={cn('flex', 'gap-3', 'px-6', 'py-4', 'border-t', 'rounded-b-2xl', 'shrink-0')}
                            style={{ borderColor: 'var(--border)', background: 'color-mix(in srgb, var(--bg-main) 60%, var(--bg-card))' }}>
                            <Button variant="outline" onClick={cerrarModal} className="flex-1">
                                Cancelar
                            </Button>
                            <Button
                                onClick={guardarAsiento}
                                disabled={!cuadrado || processing}
                                loading={processing}
                                className="flex-1"
                            >
                                Guardar Asiento
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Click-outside para cerrar dropdowns */}
            {dropdownOpen !== null && (
                <div className={cn('z-40', 'fixed', 'inset-0')} onClick={() => setDropdownOpen(null)} />
            )}

            <ToastContainer position="top-right" autoClose={3500}
                hideProgressBar={false} newestOnTop closeOnClick
                pauseOnHover draggable theme="colored" style={{ zIndex: 9999 }}
                toastStyle={{
                    borderRadius: '14px', fontSize: '14px',
                    fontWeight: '500', boxShadow: '0 8px 32px rgba(0,0,0,0.18)'
                }}
            />

            {/* ── Modal PDF ── */}
            {modalPdf && (
                <div className="modal-overlay" style={{ background: 'rgba(0,0,0,0.85)' }} onClick={cerrarModalPdf}>
                    <div className="modal-card max-w-5xl flex flex-col" style={{ height: '90vh' }} onClick={e => e.stopPropagation()}>
                        <div className="modal-header shrink-0">
                            <h2>
                                <FileText size={16} style={{ color: '#ef4444' }} />
                                Reporte de Asientos Contables
                            </h2>
                            <div className="flex items-center gap-2">
                                {urlPdf && (
                                    <a href={urlPdf} download={`reporte-asientos-${new Date().toISOString().slice(0, 10)}.pdf`}
                                       className="btn-primary text-xs py-1.5 px-3"
                                       style={{ background: '#ef4444', boxShadow: 'none', textDecoration: 'none' }}>
                                        <Download size={13} /> Descargar
                                    </a>
                                )}
                                <button onClick={cerrarModalPdf} className="btn-secondary text-xs py-1.5 px-3">
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>
                        {cargandoPdf ? (
                            <div className="flex-1 flex items-center justify-center text-sm" style={{ color: 'var(--text-muted)' }}>
                                Generando PDF…
                            </div>
                        ) : (
                            <iframe src={urlPdf} className="flex-1 w-full border-0" title="Reporte PDF Asientos" />
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
