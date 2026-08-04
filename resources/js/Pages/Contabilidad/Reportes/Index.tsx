import { useState, useMemo } from 'react'
import { toast, ToastContainer } from 'react-toastify'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import {
    BookOpen, TrendingUp, FileText,
    Search, Download, Scale, BarChart3, LineChart, Waves,
} from 'lucide-react'
import type { PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

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

    const generarLibroDiario = () => {
        const params = new URLSearchParams()
        if (ldEjercicio)  params.set('ejercicio_id', ldEjercicio)
        if (ldFechaDesde) params.set('fecha_desde',  ldFechaDesde)
        if (ldFechaHasta) params.set('fecha_hasta',  ldFechaHasta)
        abrirPdf(route('contabilidad.reportes.libro-diario') + '?' + params, 'Libro Diario')
    }

    const generarMayor = () => {
        if (!mayorCuentaId) return
        const params = new URLSearchParams({ cuenta_id: mayorCuentaId })
        if (mayorFechaDesde) params.set('fecha_desde', mayorFechaDesde)
        if (mayorFechaHasta) params.set('fecha_hasta', mayorFechaHasta)
        abrirPdf(route('contabilidad.reportes.mayor') + '?' + params, 'Mayor Contable')
    }

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
                                className="w-full flex items-center justify-center
                                           gap-2 px-4 py-2.5 rounded-xl text-sm
                                           font-semibold text-white transition-all
                                           hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: '#1A3A5C' }}>
                                <FileText size={15} /> Generar Libro Diario PDF
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
                                disabled={!mayorCuentaId}
                                className="w-full flex items-center justify-center
                                           gap-2 px-4 py-2.5 rounded-xl text-sm
                                           font-semibold text-white transition-all
                                           hover:opacity-90 hover:-translate-y-0.5
                                           disabled:opacity-50
                                           disabled:cursor-not-allowed
                                           disabled:transform-none"
                                style={{ background: '#2D6A4F' }}>
                                <TrendingUp size={15} /> Generar Mayor Contable PDF
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
