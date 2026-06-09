import { useState, useMemo } from 'react'
import AppLayout from '@/Layouts/AppLayout'
import {
    BookOpen, TrendingUp, FileText,
    Search, Download
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
    descripcion: string
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
    const [modalPdf,    setModalPdf]    = useState(false)
    const [urlPdf,      setUrlPdf]      = useState('')
    const [tituloModal, setTituloModal] = useState('')

    const meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
                   'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']

    const cuentasFiltradas = useMemo(() => {
        const q = mayorBusqueda.toLowerCase().trim()
        if (!q) return cuentas.slice(0, 30)
        return cuentas.filter(c =>
            c.codigo.toLowerCase().includes(q) ||
            c.descripcion.toLowerCase().includes(q)
        ).slice(0, 25)
    }, [mayorBusqueda, cuentas])

    const abrirPdf = (url: string, titulo: string) => {
        setUrlPdf(url)
        setTituloModal(titulo)
        setModalPdf(true)
    }

    const generarLibroDiario = () => {
        const params = new URLSearchParams()
        if (ldEjercicio)  params.set('ejercicio_id', ldEjercicio)
        if (ldFechaDesde) params.set('fecha_desde',  ldFechaDesde)
        if (ldFechaHasta) params.set('fecha_hasta',  ldFechaHasta)
        abrirPdf(
            route('contabilidad.reportes.libro-diario') + '?' + params,
            'Libro Diario'
        )
    }

    const generarMayor = () => {
        if (!mayorCuentaId) return
        const params = new URLSearchParams({ cuenta_id: mayorCuentaId })
        if (mayorFechaDesde) params.set('fecha_desde', mayorFechaDesde)
        if (mayorFechaHasta) params.set('fecha_hasta', mayorFechaHasta)
        abrirPdf(
            route('contabilidad.reportes.mayor') + '?' + params,
            'Mayor Contable'
        )
    }

    return (
        <AppLayout>
            <div className="p-4 md:p-6 space-y-6"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* HEADER */}
                <div className="mb-6">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="p-2 rounded-xl" style={{
                            background: 'color-mix(in srgb, var(--primary) 15%, transparent)'
                        }}>
                            <FileText size={24}
                                style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold"
                                style={{ color: 'var(--text-main)' }}>
                                Reportes Contables
                            </h1>
                            <p className="text-sm"
                               style={{ color: 'var(--text-muted)' }}>
                                Libro Diario y Mayor Contable
                            </p>
                        </div>
                    </div>
                </div>

                {/* GRID 2 COLUMNAS */}
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
                                <FileText size={15} />
                                Generar Libro Diario PDF
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
                                                        `${c.codigo} — ${c.descripcion}`
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
                                                {' '}{c.descripcion}
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
                                <TrendingUp size={15} />
                                Generar Mayor Contable PDF
                            </button>
                        </div>
                    </div>
                </div>

                {/* INFO REPORTES */}
                <div className="rounded-2xl border p-5"
                     style={{ background: 'var(--bg-card)',
                              borderColor: 'var(--border)' }}>
                    <h3 className="font-semibold text-sm mb-3"
                        style={{ color: 'var(--text-main)' }}>
                        ℹ️ Sobre los reportes
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p className="text-xs font-semibold mb-1"
                               style={{ color: 'var(--text-main)' }}>
                                Libro Diario
                            </p>
                            <p className="text-xs"
                               style={{ color: 'var(--text-muted)' }}>
                                Registro cronológico de todos los asientos
                                contables activos. Incluye el detalle de
                                partidas (debe/haber) de cada asiento.
                                Útil para auditorías y revisiones del período.
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold mb-1"
                               style={{ color: 'var(--text-main)' }}>
                                Mayor Contable
                            </p>
                            <p className="text-xs"
                               style={{ color: 'var(--text-muted)' }}>
                                Movimientos de una cuenta específica con
                                saldo acumulado. Permite ver el saldo
                                deudor o acreedor de cualquier cuenta
                                del Plan de Cuentas en un rango de fechas.
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
                     onClick={() => setModalPdf(false)}>
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
                                <a href={urlPdf}
                                   download
                                   target="_blank"
                                   className="flex items-center gap-1 px-3 py-1.5
                                              rounded-lg text-xs font-semibold
                                              text-white hover:opacity-90"
                                   style={{ background: '#1A3A5C' }}>
                                    <Download size={13} />
                                    Descargar
                                </a>
                                <button
                                    onClick={() => setModalPdf(false)}
                                    className="px-3 py-1.5 rounded-lg text-xs
                                               font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)',
                                             color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>

                        <iframe
                            src={urlPdf}
                            className="flex-1 w-full border-0"
                            title={tituloModal}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
