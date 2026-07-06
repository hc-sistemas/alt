import { useState } from 'react'
import { usePage } from '@inertiajs/react'
import { FileText, FileDown, Download, ClipboardList, Calculator, Percent } from 'lucide-react'
import AppLayout from '@/Layouts/AppLayout'
import type { PageProps } from '@/types'

interface Periodo {
    value: string
    label: string
}

interface Props extends PageProps {
    empresa: { id: number; razon_social: string; ruc: string }
    periodos: Periodo[]
}

export default function SriIndex() {
    const { empresa, periodos } = usePage<Props>().props

    const [atsPeriodo,  setAtsPeriodo]  = useState(periodos[0]?.value ?? '')
    const [f103Periodo, setF103Periodo] = useState(periodos[0]?.value ?? '')
    const [f104Periodo, setF104Periodo] = useState(periodos[0]?.value ?? '')
    const [icePeriodo,  setIcePeriodo]  = useState(periodos[0]?.value ?? '')

    const [modalPdf,    setModalPdf]    = useState(false)
    const [urlPdf,      setUrlPdf]      = useState('')
    const [tituloModal, setTituloModal] = useState('')

    const abrirPdf = (url: string, titulo: string) => {
        setUrlPdf(url)
        setTituloModal(titulo)
        setModalPdf(true)
    }

    const descargarXml = () => {
        if (!atsPeriodo) return
        window.open(`/reportes/sri/ats?periodo=${atsPeriodo}&formato=xml`, '_blank')
    }

    const generarAtsPdf = () => {
        if (!atsPeriodo) return
        const label = periodos.find(p => p.value === atsPeriodo)?.label ?? atsPeriodo
        abrirPdf(`/reportes/sri/ats?periodo=${atsPeriodo}&formato=pdf`, `ATS · ${label}`)
    }

    const generarF103 = () => {
        if (!f103Periodo) return
        const label = periodos.find(p => p.value === f103Periodo)?.label ?? f103Periodo
        abrirPdf(`/reportes/sri/f103?periodo=${f103Periodo}`, `Formulario 103 · ${label}`)
    }

    const generarF104 = () => {
        if (!f104Periodo) return
        const label = periodos.find(p => p.value === f104Periodo)?.label ?? f104Periodo
        abrirPdf(`/reportes/sri/f104?periodo=${f104Periodo}`, `Formulario 104 · ${label}`)
    }

    const generarAnexoIce = () => {
        if (!icePeriodo) return
        const label = periodos.find(p => p.value === icePeriodo)?.label ?? icePeriodo
        abrirPdf(`/reportes/sri/anexo-ice?periodo=${icePeriodo}`, `Anexo ICE · ${label}`)
    }

    return (
        <AppLayout>
            <div className="p-4 md:p-6 space-y-6"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* HEADER */}
                <div className="mb-6">
                    <div className="flex items-center gap-3 mb-2">
                        <div className="p-2 rounded-xl" style={{
                            background: 'color-mix(in srgb, var(--primary) 15%, transparent)',
                        }}>
                            <FileText size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                                Reportes SRI
                            </h1>
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                ATS, Formulario 103 (Retenciones IR) y Formulario 104 (IVA) · {empresa.ruc}
                            </p>
                        </div>
                    </div>
                </div>

                {/* GRID 2 COLUMNAS — ATS + F103 */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">

                    {/* ── ATS ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #B45309' }}>
                            <FileDown size={20} style={{ color: '#B45309' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                    ATS
                                </h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Anexo Transaccional Simplificado
                                </p>
                            </div>
                        </div>

                        <div className="p-5 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1"
                                       style={{ color: 'var(--text-muted)' }}>
                                    Período fiscal
                                </label>
                                <select
                                    value={atsPeriodo}
                                    onChange={e => setAtsPeriodo(e.target.value)}
                                    className="input-field select-field">
                                    {periodos.map(p => (
                                        <option key={p.value} value={p.value}>{p.label}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <button
                                    onClick={descargarXml}
                                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5
                                               rounded-xl text-sm font-semibold border transition-all
                                               hover:opacity-80 hover:-translate-y-0.5"
                                    style={{ borderColor: '#B45309', color: '#B45309', background: 'transparent' }}>
                                    <FileDown size={15} />
                                    Descargar XML
                                </button>
                                <button
                                    onClick={generarAtsPdf}
                                    className="w-full flex items-center justify-center gap-2 px-4 py-2.5
                                               rounded-xl text-sm font-semibold text-white transition-all
                                               hover:opacity-90 hover:-translate-y-0.5"
                                    style={{ background: '#B45309' }}>
                                    <FileText size={15} />
                                    Ver PDF
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* ── F103 ── */}
                    <div className="rounded-2xl border overflow-hidden"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                        <div className="px-5 py-4 border-b flex items-center gap-3"
                             style={{ borderColor: 'var(--border)', borderLeft: '4px solid #1A3A5C' }}>
                            <ClipboardList size={20} style={{ color: '#1A3A5C' }} />
                            <div>
                                <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                    Formulario 103
                                </h2>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Retenciones en la Fuente del IR
                                </p>
                            </div>
                        </div>

                        <div className="p-5 space-y-3">
                            <div>
                                <label className="block text-xs font-semibold mb-1"
                                       style={{ color: 'var(--text-muted)' }}>
                                    Período fiscal
                                </label>
                                <select
                                    value={f103Periodo}
                                    onChange={e => setF103Periodo(e.target.value)}
                                    className="input-field select-field">
                                    {periodos.map(p => (
                                        <option key={p.value} value={p.value}>{p.label}</option>
                                    ))}
                                </select>
                            </div>

                            <button
                                onClick={generarF103}
                                className="w-full flex items-center justify-center gap-2 px-4 py-2.5
                                           rounded-xl text-sm font-semibold text-white transition-all
                                           hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: '#1A3A5C' }}>
                                <ClipboardList size={15} />
                                Generar Formulario 103 PDF
                            </button>
                        </div>
                    </div>
                </div>

                {/* ── F104 — ANCHO COMPLETO ── */}
                <div className="rounded-2xl border overflow-hidden"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                    <div className="px-5 py-4 border-b flex items-center gap-3"
                         style={{ borderColor: 'var(--border)', borderLeft: '4px solid #166534' }}>
                        <Calculator size={20} style={{ color: '#166534' }} />
                        <div>
                            <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                Formulario 104
                            </h2>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Declaración del IVA — Liquidación mensual 15%
                            </p>
                        </div>
                    </div>

                    <div className="p-5 space-y-3">
                        <div>
                            <label className="block text-xs font-semibold mb-1"
                                   style={{ color: 'var(--text-muted)' }}>
                                Período fiscal
                            </label>
                            <select
                                value={f104Periodo}
                                onChange={e => setF104Periodo(e.target.value)}
                                className="input-field select-field">
                                {periodos.map(p => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                        </div>

                        <button
                            onClick={generarF104}
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5
                                       rounded-xl text-sm font-semibold text-white transition-all
                                       hover:opacity-90 hover:-translate-y-0.5"
                            style={{ background: '#166534' }}>
                            <Calculator size={15} />
                            Generar Formulario 104 PDF
                        </button>
                    </div>
                </div>

                {/* ── ANEXO ICE ── */}
                <div className="rounded-2xl border overflow-hidden"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                    <div className="px-5 py-4 border-b flex items-center gap-3"
                         style={{ borderColor: 'var(--border)', borderLeft: '4px solid #7C3AED' }}>
                        <Percent size={20} style={{ color: '#7C3AED' }} />
                        <div>
                            <h2 className="font-bold text-sm" style={{ color: 'var(--text-main)' }}>
                                Anexo ICE
                            </h2>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Impuesto a los Consumos Especiales — Compras y Ventas gravadas con ICE
                            </p>
                        </div>
                    </div>

                    <div className="p-5 space-y-3">
                        <div>
                            <label className="block text-xs font-semibold mb-1"
                                   style={{ color: 'var(--text-muted)' }}>
                                Período fiscal
                            </label>
                            <select
                                value={icePeriodo}
                                onChange={e => setIcePeriodo(e.target.value)}
                                className="input-field select-field">
                                {periodos.map(p => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                        </div>
                        <button
                            onClick={generarAnexoIce}
                            className="w-full flex items-center justify-center gap-2 px-4 py-2.5
                                       rounded-xl text-sm font-semibold text-white transition-all
                                       hover:opacity-90 hover:-translate-y-0.5"
                            style={{ background: '#7C3AED' }}>
                            <Percent size={15} />
                            Generar Anexo ICE PDF
                        </button>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            Si la empresa no tiene transacciones con ICE, el PDF mostrará "Sin registros".
                        </p>
                    </div>
                </div>

                {/* INFO */}
                <div className="rounded-2xl border p-5"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <h3 className="font-semibold text-sm mb-3" style={{ color: 'var(--text-main)' }}>
                        ℹ️ Sobre los reportes SRI
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p className="text-xs font-semibold mb-1" style={{ color: 'var(--text-main)' }}>ATS</p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Generado mensualmente para declarar compras, ventas y retenciones al SRI.
                                El XML puede subirse directamente al portal sri.gob.ec.
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold mb-1" style={{ color: 'var(--text-main)' }}>Formulario 103</p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Declaración mensual de retenciones en la fuente del IR agrupadas por
                                código SRI y porcentaje. Requiere comprobantes emitidos al día.
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold mb-1" style={{ color: 'var(--text-main)' }}>Formulario 104</p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Liquidación del IVA 15%: IVA causado en ventas menos crédito tributario
                                de compras y retenciones recibidas para calcular el saldo a pagar.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* MODAL PDF */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={() => setModalPdf(false)}>
                    <div className="w-full max-w-5xl rounded-2xl overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)', height: '90vh' }}
                         onClick={e => e.stopPropagation()}>

                        <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                {tituloModal}
                            </h3>
                            <div className="flex items-center gap-2">
                                <a href={urlPdf}
                                   download
                                   target="_blank"
                                   className="flex items-center gap-1 px-3 py-1.5 rounded-lg
                                              text-xs font-semibold text-white hover:opacity-90"
                                   style={{ background: '#1A3A5C' }}>
                                    <Download size={13} />
                                    Descargar
                                </a>
                                <button
                                    onClick={() => setModalPdf(false)}
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold
                                               border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
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
