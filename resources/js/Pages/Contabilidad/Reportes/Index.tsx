import { useState, useMemo, useEffect } from 'react'
import { router } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import {
    BookOpen, TrendingUp, FileText,
    Search, Download, Scale, BarChart3, LineChart, Waves, Loader2,
} from 'lucide-react'
import type { PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    success: (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    error:   (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const SWAL_CSS = `
    .swal-pop { border-radius:20px!important; padding:28px!important; box-shadow:0 25px 60px rgba(0,0,0,.25)!important; max-width:460px!important }
    .swal-title { font-size:1.1rem!important; font-weight:700!important; color:#1f2937!important; margin-bottom:16px!important }
    .swal-confirm { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
    .swal-cancel  { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
`
function injectSwalCss() {
    if (document.getElementById('swal-reportes')) return
    const s = document.createElement('style'); s.id = 'swal-reportes'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

interface Ejercicio {
    id: number
    anio: number
    mes: number
    descripcion: string | null
    estado: string
}

interface CuentaSimple {
    id: number
    codigo: string
    nombre: string
}

interface Props extends PageProps {
    ejercicios: Ejercicio[]
    cuentas:    CuentaSimple[]
}

export default function ReportesIndex({ ejercicios, cuentas }: Props) {

    // Estado filtros Libro Diario
    const [ldEjercicio,  setLdEjercicio]  = useState('')
    const [ldFechaDesde, setLdFechaDesde] = useState('')
    const [ldFechaHasta, setLdFechaHasta] = useState('')

    // Estado filtros Mayor
    const [mayorCuentaId,   setMayorCuentaId]   = useState('')
    const [mayorBusqueda,   setMayorBusqueda]   = useState('')
    const [mayorFechaDesde, setMayorFechaDesde] = useState('')
    const [mayorFechaHasta, setMayorFechaHasta] = useState('')

    // Estado filtros Balance Comprobación
    const [bcEjercicio,  setBcEjercicio]  = useState('')
    const [bcFechaDesde, setBcFechaDesde] = useState('')
    const [bcFechaHasta, setBcFechaHasta] = useState('')

    // Estado filtros Balance General
    const [bgEjercicio, setBgEjercicio] = useState('')

    // Estado filtros Estado Resultados
    const [erEjercicio,  setErEjercicio]  = useState('')
    const [erFechaDesde, setErFechaDesde] = useState('')
    const [erFechaHasta, setErFechaHasta] = useState('')

    // Estado filtros Flujo de Caja
    const [fcFechaDesde, setFcFechaDesde] = useState('')
    const [fcFechaHasta, setFcFechaHasta] = useState('')

    const [modalPdf,    setModalPdf]    = useState(false)
    const [urlPdf,      setUrlPdf]      = useState('')
    const [tituloModal, setTituloModal] = useState('')
    const [cargandoPdf, setCargandoPdf] = useState(false)
    const [errorPdf,    setErrorPdf]    = useState('')

    // Libro Diario y Mayor Contable son los únicos 2 reportes de esta
    // pantalla cuyo PDF puede volverse inherentemente pesado con rangos
    // amplios (una fila por línea de asiento, no por cuenta) — confirmado
    // con curl real que el SQL es rapidísimo en los 6 reportes; el costo
    // real es el renderizado de DomPDF, que escala peor que lineal. Los
    // otros 4 (Balance de Comprobación, Balance General, Estado de
    // Resultados, Flujo de Caja) tienen como mucho ~200 filas (una por
    // cuenta del plan de cuentas) sin importar el rango de fechas, así que
    // no necesitan este camino.
    const [verificando, setVerificando] = useState<'libro-diario' | 'mayor' | null>(null)
    const [exportandoFondo, setExportandoFondo] = useState<{ tipo: 'libro-diario' | 'mayor'; desde: number } | null>(null)

    const meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']

    const cuentasFiltradas = useMemo(() => {
        const q = mayorBusqueda.toLowerCase().trim()
        if (!q) return cuentas.slice(0, 30)
        return cuentas.filter(c =>
            c.codigo.toLowerCase().includes(q) ||
            c.nombre.toLowerCase().includes(q)
        ).slice(0, 25)
    }, [mayorBusqueda, cuentas])

    // Se trae el PDF como blob (fetch) en vez de apuntar el <iframe> directo
    // a la URL del backend — mismo patrón ya corregido en Compras/Asientos/
    // Proveedores/Cuentas por Pagar: un blob: URL siempre se muestra
    // embebido, sin depender de si el navegador decide forzar la descarga
    // en el iframe. Este modal es compartido por los 6 reportes de esta
    // pantalla, así que corregirlo aquí una sola vez corrige el PDF en
    // blanco en todos ellos (Libro Diario, Mayor, Balance de Comprobación,
    // Balance General, Estado de Resultados, Flujo de Caja).
    const abrirPdf = async (url: string, titulo: string) => {
        setModalPdf(true)
        setTituloModal(titulo)
        setCargandoPdf(true)
        setErrorPdf('')
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
            setErrorPdf(e instanceof Error ? e.message : 'No se pudo generar el PDF. Intenta de nuevo.')
        } finally {
            setCargandoPdf(false)
        }
    }

    const cerrarModalPdf = () => {
        if (urlPdf) URL.revokeObjectURL(urlPdf)
        setModalPdf(false)
        setUrlPdf('')
        setErrorPdf('')
    }

    // Ofrece procesar en segundo plano cuando el reporte supera el límite
    // síncrono — mismo patrón que Asientos/Compras/Proveedores/Cuentas por
    // Pagar: se verifica el tamaño real ANTES de intentar generar el PDF,
    // en vez de dejar que el usuario espere un "Generando…" que nunca
    // termina.
    const confirmarSegundoPlano = (tipo: 'libro-diario' | 'mayor', params: URLSearchParams) => {
        const ruta = tipo === 'libro-diario'
            ? route('contabilidad.reportes.libro-diario.segundo-plano')
            : route('contabilidad.reportes.mayor.segundo-plano')
        router.post(ruta, Object.fromEntries(params), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setExportandoFondo({ tipo, desde: Date.now() }),
        })
    }

    const generarConLimite = async (
        tipo: 'libro-diario' | 'mayor',
        params: URLSearchParams,
        titulo: string,
    ) => {
        setVerificando(tipo)
        try {
            const rutaContar = tipo === 'libro-diario'
                ? route('contabilidad.reportes.libro-diario.contar')
                : route('contabilidad.reportes.mayor.contar')
            const res = await fetch(rutaContar + '?' + params)
            if (!res.ok) throw new Error()
            const data = await res.json() as { total: number; limite: number; excede: boolean }

            if (!data.excede) {
                const rutaGenerar = tipo === 'libro-diario'
                    ? route('contabilidad.reportes.libro-diario')
                    : route('contabilidad.reportes.mayor')
                abrirPdf(rutaGenerar + '?' + params, titulo)
                return
            }

            const { isConfirmed } = await Swal.fire({
                ...swalBase,
                title: 'Reporte grande',
                html: `
                    <div style="text-align:left;color:#374151;font-size:0.875rem;line-height:1.5">
                        <p>Este reporte tiene <strong>${data.total.toLocaleString('es-EC')}</strong> líneas de detalle con
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

            if (isConfirmed) confirmarSegundoPlano(tipo, params)
        } catch {
            notify.error('No se pudo verificar el tamaño del reporte. Intenta de nuevo.')
        } finally {
            setVerificando(null)
        }
    }

    const generarLibroDiario = () => {
        const params = new URLSearchParams()
        if (ldEjercicio)  params.set('ejercicio_id', ldEjercicio)
        if (ldFechaDesde) params.set('fecha_desde',  ldFechaDesde)
        if (ldFechaHasta) params.set('fecha_hasta',  ldFechaHasta)
        generarConLimite('libro-diario', params, 'Libro Diario')
    }

    const generarMayor = () => {
        if (!mayorCuentaId) return
        const params = new URLSearchParams({ cuenta_id: mayorCuentaId })
        if (mayorFechaDesde) params.set('fecha_desde', mayorFechaDesde)
        if (mayorFechaHasta) params.set('fecha_hasta', mayorFechaHasta)
        generarConLimite('mayor', params, 'Mayor Contable')
    }

    // Sin websockets/polling en el backend — se consulta el mismo endpoint
    // que ya usa la campana de notificaciones (notificaciones.index) cada
    // 15s, mientras haya una exportación en curso, hasta encontrarla o 10
    // minutos.
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
                    (n.tipo === 'exportacion_reportes_contables' || n.tipo === 'exportacion_reportes_contables_error') &&
                    new Date(n.created_at).getTime() >= exportandoFondo.desde
                )
                if (lista) {
                    notify.success('Tu reporte terminó de procesarse — revisa la campana de notificaciones para descargarlo.')
                    setExportandoFondo(null)
                }
            } catch { /* red momentáneamente caída — se reintenta en el próximo tick */ }
        }, 15000)
        return () => clearInterval(intervalo)
    }, [exportandoFondo])

    const generarBalanceComprobacion = () => {
        const params = new URLSearchParams()
        if (bcEjercicio)  params.set('ejercicio_id', bcEjercicio)
        if (bcFechaDesde) params.set('fecha_desde',  bcFechaDesde)
        if (bcFechaHasta) params.set('fecha_hasta',  bcFechaHasta)
        abrirPdf(route('contabilidad.reportes.balance-comprobacion') + '?' + params, 'Balance de Comprobación')
    }

    const generarBalanceGeneral = () => {
        const params = new URLSearchParams()
        if (bgEjercicio) params.set('ejercicio_id', bgEjercicio)
        abrirPdf(route('contabilidad.reportes.balance-general') + '?' + params, 'Balance General')
    }

    const generarEstadoResultados = () => {
        const params = new URLSearchParams()
        if (erEjercicio)  params.set('ejercicio_id', erEjercicio)
        if (erFechaDesde) params.set('fecha_desde',  erFechaDesde)
        if (erFechaHasta) params.set('fecha_hasta',  erFechaHasta)
        abrirPdf(route('contabilidad.reportes.estado-resultados') + '?' + params, 'Estado de Resultados')
    }

    const generarFlujoCaja = () => {
        const params = new URLSearchParams()
        if (fcFechaDesde) params.set('fecha_desde', fcFechaDesde)
        if (fcFechaHasta) params.set('fecha_hasta', fcFechaHasta)
        abrirPdf(route('contabilidad.reportes.flujo-caja') + '?' + params, 'Flujo de Caja')
    }

    const descargarFlujoCajaExcel = () => {
        const params = new URLSearchParams()
        if (fcFechaDesde) params.set('fecha_desde', fcFechaDesde)
        if (fcFechaHasta) params.set('fecha_hasta', fcFechaHasta)
        window.location.href = route('contabilidad.reportes.flujo-caja-excel') + '?' + params
    }

    return (
        <AppLayout>
            <PageHeader
                title="Reportes Contables"
                breadcrumbs={[{ label: 'Contabilidad' }, { label: 'Reportes' }]}
            />

            {exportandoFondo && (
                <div className="flex items-center gap-2 text-xs rounded-lg px-3 py-2 mx-4 md:mx-6 mt-4"
                    style={{ background: 'color-mix(in srgb, var(--primary) 12%, var(--bg-main))', color: 'var(--text-main)' }}>
                    <Loader2 className="w-3.5 h-3.5 animate-spin shrink-0" style={{ color: 'var(--primary)' }} />
                    <span>
                        Tu {exportandoFondo.tipo === 'libro-diario' ? 'Libro Diario' : 'Mayor Contable'} se está procesando en
                        segundo plano — te avisaremos por notificación cuando esté listo.
                    </span>
                </div>
            )}

            <div className="p-4 md:p-6 space-y-6"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* GRID 2 COLUMNAS — TODOS LOS REPORTES */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {/* ── LIBRO DIARIO ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)',
                                  borderColor: 'var(--border)' }}>

                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{
                                 borderColor: 'var(--border)',
                                 borderLeft: '4px solid #1A3A5C',
                             }}>
                            <BookOpen size={20}
                                style={{ color: '#1A3A5C' }} />
                            <div>
                                <h2 className="font-bold text-sm"
                                    style={{ color: 'var(--text-main)' }}>
                                    Libro Diario
                                </h2>
                                <p className="text-xs"
                                   style={{ color: 'var(--text-muted)' }}>
                                    Todos los asientos ordenados por fecha
                                </p>
                            </div>
                        </div>

                        <div className="p-5 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1"
                                       style={{ color: 'var(--text-muted)' }}>
                                    Período contable
                                </label>
                                <select
                                    value={ldEjercicio}
                                    onChange={e => setLdEjercicio(e.target.value)}
                                    className="input-field select-field">
                                    <option value="">Todos los períodos</option>
                                    {ejercicios.map(e => (
                                        <option key={e.id} value={e.id}>
                                            {meses[e.mes]} {e.anio}
                                            {e.estado === 'abierto'
                                                ? ' (Abierto)' : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold mb-1"
                                           style={{ color: 'var(--text-muted)' }}>
                                        Desde
                                    </label>
                                    <input type="date"
                                        value={ldFechaDesde}
                                        onChange={e => setLdFechaDesde(e.target.value)}
                                        className="input-field"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold mb-1"
                                           style={{ color: 'var(--text-muted)' }}>
                                        Hasta
                                    </label>
                                    <input type="date"
                                        value={ldFechaHasta}
                                        onChange={e => setLdFechaHasta(e.target.value)}
                                        className="input-field"
                                    />
                                </div>
                            </div>

                            <button
                                onClick={generarLibroDiario}
                                disabled={verificando === 'libro-diario'}
                                className="w-full flex items-center justify-center
                                           gap-2 px-4 py-2.5 rounded-xl text-sm
                                           font-semibold text-white transition-all
                                           hover:opacity-90 hover:-translate-y-0.5
                                           disabled:opacity-50 disabled:cursor-not-allowed
                                           disabled:transform-none"
                                style={{ background: '#1A3A5C' }}>
                                {verificando === 'libro-diario'
                                    ? <><Loader2 size={15} className="animate-spin" /> Verificando tamaño…</>
                                    : <><FileText size={15} /> Generar Libro Diario PDF</>
                                }
                            </button>
                        </div>
                    </div>

                    {/* ── MAYOR CONTABLE ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)',
                                  borderColor: 'var(--border)' }}>

                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{
                                 borderColor: 'var(--border)',
                                 borderLeft: '4px solid #2D6A4F',
                             }}>
                            <TrendingUp size={20}
                                style={{ color: '#2D6A4F' }} />
                            <div>
                                <h2 className="font-bold text-sm"
                                    style={{ color: 'var(--text-main)' }}>
                                    Mayor Contable
                                </h2>
                                <p className="text-xs"
                                   style={{ color: 'var(--text-muted)' }}>
                                    Movimientos por cuenta con saldo
                                </p>
                            </div>
                        </div>

                        <div className="p-5 space-y-3">

                            <div>
                                <label className="block text-xs font-semibold mb-1"
                                       style={{ color: 'var(--text-muted)' }}>
                                    Cuenta contable
                                    <span className="text-red-500"> *</span>
                                </label>
                                <div className="input-with-icon">
                                    <Search size={14} className="input-icon" />
                                    <input
                                        type="text"
                                        value={mayorBusqueda}
                                        onChange={e => {
                                            setMayorBusqueda(e.target.value)
                                            setMayorCuentaId('')
                                        }}
                                        placeholder="Buscar por código o nombre..."
                                        className="input-field"
                                        style={mayorCuentaId ? { borderColor: '#2D6A4F' } : undefined}
                                    />
                                </div>
                                <p className="text-xs mt-1" style={{ color: 'var(--text-muted)' }}>
                                    Escribe parte del código (ej. 1.1.1) o del nombre de la cuenta.
                                </p>

                                {mayorBusqueda && !mayorCuentaId && (
                                    <div className="border rounded-lg mt-1
                                                    overflow-hidden shadow-lg"
                                         style={{ borderColor: 'var(--border)',
                                                  background: 'var(--bg-card)',
                                                  maxHeight: '180px',
                                                  overflowY: 'auto' }}>
                                        {cuentasFiltradas.length === 0 ? (
                                            <p className="px-3 py-2 text-xs"
                                               style={{ color: 'var(--text-muted)' }}>
                                                Sin resultados
                                            </p>
                                        ) : cuentasFiltradas.map(c => (
                                            <button
                                                key={c.id}
                                                onClick={() => {
                                                    setMayorCuentaId(String(c.id))
                                                    setMayorBusqueda(
                                                        `${c.codigo} — ${c.nombre}`
                                                    )
                                                }}
                                                className="w-full text-left px-3 py-2
                                                           text-xs border-b
                                                           hover:opacity-80 transition-opacity"
                                                style={{ borderColor: 'var(--border)' }}>
                                                <span className="font-mono font-bold"
                                                      style={{ color: '#1A3A5C' }}>
                                                    {c.codigo}
                                                </span>
                                                {' '}{c.nombre}
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {mayorCuentaId && (
                                    <p className="text-xs mt-1 flex items-center gap-1"
                                       style={{ color: '#2D6A4F' }}>
                                        ✓ Cuenta seleccionada
                                        <button
                                            onClick={() => {
                                                setMayorCuentaId('')
                                                setMayorBusqueda('')
                                            }}
                                            className="ml-1 text-gray-400
                                                       hover:text-gray-600">
                                            ×
                                        </button>
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold mb-1"
                                           style={{ color: 'var(--text-muted)' }}>
                                        Desde
                                    </label>
                                    <input type="date"
                                        value={mayorFechaDesde}
                                        onChange={e => setMayorFechaDesde(e.target.value)}
                                        className="input-field"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold mb-1"
                                           style={{ color: 'var(--text-muted)' }}>
                                        Hasta
                                    </label>
                                    <input type="date"
                                        value={mayorFechaHasta}
                                        onChange={e => setMayorFechaHasta(e.target.value)}
                                        className="input-field"
                                    />
                                </div>
                            </div>

                            <button
                                onClick={generarMayor}
                                disabled={!mayorCuentaId || verificando === 'mayor'}
                                className="w-full flex items-center justify-center
                                           gap-2 px-4 py-2.5 rounded-xl text-sm
                                           font-semibold text-white transition-all
                                           hover:opacity-90 hover:-translate-y-0.5
                                           disabled:opacity-50
                                           disabled:cursor-not-allowed
                                           disabled:transform-none"
                                style={{ background: '#2D6A4F' }}>
                                {verificando === 'mayor'
                                    ? <><Loader2 size={15} className="animate-spin" /> Verificando tamaño…</>
                                    : <><TrendingUp size={15} /> Generar Mayor Contable PDF</>
                                }
                            </button>
                        </div>
                    </div>

                    {/* ── BALANCE DE COMPROBACIÓN ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #7C3AED' }}>
                            <Scale size={20} style={{ color: '#7C3AED' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>Balance de Comprobación</h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Suma DEBE/HABER y saldos por cuenta</p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Período contable</label>
                                <select value={bcEjercicio} onChange={e => setBcEjercicio(e.target.value)} className="input-field select-field">
                                    <option value="">Todos los períodos</option>
                                    {ejercicios.map(e => (
                                        <option key={e.id} value={e.id}>{meses[e.mes]} {e.anio}{e.estado === 'abierto' ? ' (Abierto)' : ''}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Desde</label>
                                    <input type="date" value={bcFechaDesde} onChange={e => setBcFechaDesde(e.target.value)} className="input-field" />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                                    <input type="date" value={bcFechaHasta} onChange={e => setBcFechaHasta(e.target.value)} className="input-field" />
                                </div>
                            </div>
                            <button onClick={generarBalanceComprobacion}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: '#7C3AED' }}>
                                <Scale size={15} /> Generar Balance de Comprobación PDF
                            </button>
                        </div>
                    </div>

                    {/* ── BALANCE GENERAL ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #0891b2' }}>
                            <BarChart3 size={20} style={{ color: '#0891b2' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>Balance General</h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Estado de Situación Financiera</p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Período contable</label>
                                <select value={bgEjercicio} onChange={e => setBgEjercicio(e.target.value)} className="input-field select-field">
                                    <option value="">Todos los períodos</option>
                                    {ejercicios.map(e => (
                                        <option key={e.id} value={e.id}>{meses[e.mes]} {e.anio}{e.estado === 'abierto' ? ' (Abierto)' : ''}</option>
                                    ))}
                                </select>
                            </div>
                            <button onClick={generarBalanceGeneral}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: '#0891b2' }}>
                                <BarChart3 size={15} /> Generar Balance General PDF
                            </button>
                        </div>
                    </div>

                    {/* ── ESTADO DE RESULTADOS ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #059669' }}>
                            <LineChart size={20} style={{ color: '#059669' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>Estado de Resultados</h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Ingresos vs. Gastos — Utilidad o Pérdida del período</p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Período contable</label>
                                <select value={erEjercicio} onChange={e => setErEjercicio(e.target.value)} className="input-field select-field">
                                    <option value="">Todos los períodos</option>
                                    {ejercicios.map(e => (
                                        <option key={e.id} value={e.id}>{meses[e.mes]} {e.anio}{e.estado === 'abierto' ? ' (Abierto)' : ''}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Desde</label>
                                    <input type="date" value={erFechaDesde} onChange={e => setErFechaDesde(e.target.value)} className="input-field" />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>Hasta</label>
                                    <input type="date" value={erFechaHasta} onChange={e => setErFechaHasta(e.target.value)} className="input-field" />
                                </div>
                            </div>
                            <button onClick={generarEstadoResultados}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: '#059669' }}>
                                <LineChart size={15} /> Generar Estado de Resultados PDF
                            </button>
                        </div>
                    </div>

                    {/* ── FLUJO DE CAJA ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #dc2626' }}>
                            <Waves size={20} style={{ color: '#dc2626' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>Flujo de Caja</h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Método indirecto — Variación neta de efectivo en el período</p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>
                                        Desde
                                    </label>
                                    <input type="date" value={fcFechaDesde}
                                        onChange={e => setFcFechaDesde(e.target.value)}
                                        className="input-field" />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold mb-1" style={{ color: 'var(--text-muted)' }}>
                                        Hasta
                                    </label>
                                    <input type="date" value={fcFechaHasta}
                                        onChange={e => setFcFechaHasta(e.target.value)}
                                        className="input-field" />
                                </div>
                            </div>
                            <div className="flex gap-3">
                                <button onClick={generarFlujoCaja}
                                    className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                                    style={{ background: '#dc2626' }}>
                                    <FileText size={15} /> Ver PDF
                                </button>
                                <button onClick={descargarFlujoCajaExcel}
                                    className="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                                    style={{ background: '#059669' }}>
                                    <Download size={15} /> Descargar CSV
                                </button>
                            </div>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Sin fechas: usa el año en curso completo. El cálculo parte de la utilidad neta y suma variaciones de CxC, inventarios, CxP y obligaciones.
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            {/* MODAL PDF */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center
                                justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={cerrarModalPdf}>
                    <div className="w-full max-w-5xl rounded-2xl
                                    overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)',
                                  height: '90vh' }}
                         onClick={e => e.stopPropagation()}>

                        <div className="flex items-center justify-between
                                        px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm"
                                style={{ color: 'var(--text-main)' }}>
                                {tituloModal}
                            </h3>
                            <div className="flex items-center gap-2">
                                {urlPdf && (
                                    <a href={urlPdf}
                                       download={`${tituloModal.toLowerCase().replace(/\s+/g, '-')}-${new Date().toISOString().slice(0, 10)}.pdf`}
                                       className="flex items-center gap-1 px-3 py-1.5
                                                  rounded-lg text-xs font-semibold
                                                  text-white hover:opacity-90"
                                       style={{ background: '#1A3A5C' }}>
                                        <Download size={13} />
                                        Descargar
                                    </a>
                                )}
                                <button
                                    onClick={cerrarModalPdf}
                                    className="px-3 py-1.5 rounded-lg text-xs
                                               font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)',
                                             color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>

                        {cargandoPdf ? (
                            <div className="flex-1 flex items-center justify-center text-sm"
                                 style={{ color: 'var(--text-muted)' }}>
                                Generando PDF…
                            </div>
                        ) : errorPdf ? (
                            <div className="flex-1 flex items-center justify-center text-sm px-6 text-center"
                                 style={{ color: '#dc2626' }}>
                                {errorPdf}
                            </div>
                        ) : (
                            <iframe
                                src={urlPdf}
                                className="flex-1 w-full border-0"
                                title={tituloModal}
                            />
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
