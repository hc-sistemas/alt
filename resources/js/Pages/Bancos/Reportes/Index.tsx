import { useState } from 'react'
import { usePage, Head } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import { FileText, TrendingUp, Wallet, Landmark, X } from 'lucide-react'
import type { PageProps } from '@/types'

// ─── Types ────────────────────────────────────────────────────────────────────

interface BancoCajaSimple {
    id: number
    nombre: string
    tipo: string
    saldo_actual: number
    num_cuenta: string | null
}

interface Props extends PageProps {
    bancos: BancoCajaSimple[]
    cajas: BancoCajaSimple[]
}

// ─── Campo helper ─────────────────────────────────────────────────────────────

function Campo({ label, required, children }: {
    label: string
    required?: boolean
    children: React.ReactNode
}) {
    return (
        <div>
            <label className="input-label">
                {label}
                {required && <span className="text-red-500"> *</span>}
            </label>
            {children}
        </div>
    )
}

// ─── Modal PDF ────────────────────────────────────────────────────────────────

function ModalPdf({ url, titulo, onClose }: {
    url: string
    titulo: string
    onClose: () => void
}) {
    return (
        <div className="fixed inset-0 z-50 flex flex-col p-4"
             style={{ background: 'rgba(0,0,0,0.75)' }}>
            <div className="flex items-center justify-between mb-3">
                <h2 className="text-white font-semibold text-base">{titulo}</h2>
                <button onClick={onClose}
                    className="flex items-center justify-center w-8 h-8 rounded-full text-white transition-colors"
                    style={{ background: 'rgba(255,255,255,0.15)' }}
                    onMouseEnter={e => (e.currentTarget as HTMLButtonElement).style.background = 'rgba(255,255,255,0.25)'}
                    onMouseLeave={e => (e.currentTarget as HTMLButtonElement).style.background = 'rgba(255,255,255,0.15)'}>
                    <X className="w-4 h-4" />
                </button>
            </div>
            <iframe src={url} className="flex-1 rounded-xl border-0 w-full" title={titulo} />
        </div>
    )
}

// ─── Página ───────────────────────────────────────────────────────────────────

export default function BancosReportesIndex() {
    const { bancos, cajas } = usePage<Props>().props

    // Estado de cuenta
    const [ecBanco,  setEcBanco]  = useState('')
    const [ecDesde,  setEcDesde]  = useState('')
    const [ecHasta,  setEcHasta]  = useState('')

    // Movimientos
    const [movBanco, setMovBanco] = useState('')
    const [movTipo,  setMovTipo]  = useState('')
    const [movDesde, setMovDesde] = useState('')
    const [movHasta, setMovHasta] = useState('')

    // Caja chica
    const [cajaCaja,  setCajaCaja]  = useState('')
    const [cajaDesde, setCajaDesde] = useState('')
    const [cajaHasta, setCajaHasta] = useState('')

    const [modalPdf,    setModalPdf]    = useState(false)
    const [urlPdf,      setUrlPdf]      = useState('')
    const [tituloModal, setTituloModal] = useState('')

    const abrirPdf = (url: string, titulo: string) => {
        setUrlPdf(url)
        setTituloModal(titulo)
        setModalPdf(true)
    }

    const generarEstadoCuenta = () => {
        if (!ecBanco || !ecDesde || !ecHasta) return
        const p = new URLSearchParams({ banco_caja_id: ecBanco, fecha_desde: ecDesde, fecha_hasta: ecHasta })
        abrirPdf(route('bancos.reportes.estado-cuenta') + '?' + p, 'Estado de Cuenta')
    }

    const generarMovimientos = () => {
        const p = new URLSearchParams()
        if (movBanco) p.set('banco_caja_id', movBanco)
        if (movTipo)  p.set('tipo', movTipo)
        if (movDesde) p.set('fecha_desde', movDesde)
        if (movHasta) p.set('fecha_hasta', movHasta)
        abrirPdf(route('bancos.reportes.movimientos') + '?' + p, 'Movimientos Bancarios')
    }

    const generarCajaChica = () => {
        const p = new URLSearchParams()
        if (cajaCaja)  p.set('banco_caja_id', cajaCaja)
        if (cajaDesde) p.set('fecha_desde',   cajaDesde)
        if (cajaHasta) p.set('fecha_hasta',   cajaHasta)
        abrirPdf(route('bancos.reportes.caja-chica') + '?' + p, 'Reporte de Caja')
    }

    const allBancosYCajas = [...bancos, ...cajas]

    return (
        <AppLayout title="Reportes de Bancos" suppressFlash>
            <Head title="Reportes de Bancos" />

            <div className="p-4 md:p-6 space-y-6"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* Header */}
                <div className="flex items-center gap-3">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <FileText size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Reportes de Bancos
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Estado de cuenta, movimientos y caja
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">

                    {/* ── ESTADO DE CUENTA ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #1A3A5C' }}>
                            <Landmark size={18} style={{ color: '#1A3A5C' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                    Estado de Cuenta
                                </h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Movimientos con saldo acumulado
                                </p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <Campo label="Banco" required>
                                <select value={ecBanco} onChange={e => setEcBanco(e.target.value)}
                                    className="input-field select-field mt-1">
                                    <option value="">— Seleccionar banco —</option>
                                    {bancos.map(b => (
                                        <option key={b.id} value={b.id}>{b.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <div className="grid grid-cols-2 gap-2">
                                <Campo label="Desde" required>
                                    <input type="date" value={ecDesde}
                                        onChange={e => setEcDesde(e.target.value)}
                                        className="input-field mt-1" />
                                </Campo>
                                <Campo label="Hasta" required>
                                    <input type="date" value={ecHasta}
                                        onChange={e => setEcHasta(e.target.value)}
                                        className="input-field mt-1" />
                                </Campo>
                            </div>
                            <button onClick={generarEstadoCuenta}
                                disabled={!ecBanco || !ecDesde || !ecHasta}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                                           text-sm font-semibold text-white transition-all hover:opacity-90
                                           disabled:opacity-50 disabled:cursor-not-allowed"
                                style={{ background: '#1A3A5C' }}>
                                <FileText size={14} /> Generar PDF
                            </button>
                        </div>
                    </div>

                    {/* ── MOVIMIENTOS ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #2D6A4F' }}>
                            <TrendingUp size={18} style={{ color: '#2D6A4F' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                    Movimientos Bancarios
                                </h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Ingresos y egresos por banco/caja
                                </p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <Campo label="Banco / Caja">
                                <select value={movBanco} onChange={e => setMovBanco(e.target.value)}
                                    className="input-field select-field mt-1">
                                    <option value="">— Todos —</option>
                                    {allBancosYCajas.map(b => (
                                        <option key={b.id} value={b.id}>{b.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <Campo label="Tipo">
                                <select value={movTipo} onChange={e => setMovTipo(e.target.value)}
                                    className="input-field select-field mt-1">
                                    <option value="">— Todos —</option>
                                    <option value="ingreso">Ingresos</option>
                                    <option value="egreso">Egresos</option>
                                </select>
                            </Campo>
                            <div className="grid grid-cols-2 gap-2">
                                <Campo label="Desde">
                                    <input type="date" value={movDesde}
                                        onChange={e => setMovDesde(e.target.value)}
                                        className="input-field mt-1" />
                                </Campo>
                                <Campo label="Hasta">
                                    <input type="date" value={movHasta}
                                        onChange={e => setMovHasta(e.target.value)}
                                        className="input-field mt-1" />
                                </Campo>
                            </div>
                            <button onClick={generarMovimientos}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                                           text-sm font-semibold text-white transition-all hover:opacity-90"
                                style={{ background: '#2D6A4F' }}>
                                <FileText size={14} /> Generar PDF
                            </button>
                        </div>
                    </div>

                    {/* ── CAJA CHICA ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #7c3aed' }}>
                            <Wallet size={18} style={{ color: '#7c3aed' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                    Reporte de Caja
                                </h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Cierres de caja con diferencias
                                </p>
                            </div>
                        </div>
                        <div className="p-5 space-y-3">
                            <Campo label="Caja">
                                <select value={cajaCaja} onChange={e => setCajaCaja(e.target.value)}
                                    className="input-field select-field mt-1">
                                    <option value="">— Todas las cajas —</option>
                                    {cajas.map(c => (
                                        <option key={c.id} value={c.id}>{c.nombre}</option>
                                    ))}
                                </select>
                            </Campo>
                            <div className="grid grid-cols-2 gap-2">
                                <Campo label="Desde">
                                    <input type="date" value={cajaDesde}
                                        onChange={e => setCajaDesde(e.target.value)}
                                        className="input-field mt-1" />
                                </Campo>
                                <Campo label="Hasta">
                                    <input type="date" value={cajaHasta}
                                        onChange={e => setCajaHasta(e.target.value)}
                                        className="input-field mt-1" />
                                </Campo>
                            </div>
                            <button onClick={generarCajaChica}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                                           text-sm font-semibold text-white transition-all hover:opacity-90"
                                style={{ background: '#7c3aed' }}>
                                <FileText size={14} /> Generar PDF
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            {modalPdf && (
                <ModalPdf url={urlPdf} titulo={tituloModal} onClose={() => setModalPdf(false)} />
            )}
        </AppLayout>
    )
}
