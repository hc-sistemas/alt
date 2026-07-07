import { useState, useEffect } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { cn } from '@/lib/utils'
import {
    FileText, CheckCircle2, AlertTriangle, Trash2, Eye, ChevronRight, ChevronLeft,
    Download, PenLine, X,
} from 'lucide-react'
import type { Liquidacion, LiquidacionCalculo, Colaborador, PageProps, PaginatedData } from '@/types'

// ── tipos locales ──────────────────────────────────────────────────────────────

interface ColabItem {
    id: number; cedula_ruc: string; apellidos: string; nombres: string
    cargo?: string; sueldo_base: number; fecha_ingreso: string
}

interface Props extends PageProps {
    liquidaciones: PaginatedData<Liquidacion>
    colaboradores: ColabItem[]
    filtros: { estado?: string; colaborador_id?: string }
    flash?: { success?: string; error?: string }
}

// ── helpers ───────────────────────────────────────────────────────────────────

const fmt = (n: number | string) => `$${Number(n).toFixed(2)}`

const motivoLabels: Record<string, string> = {
    renuncia: 'Renuncia Voluntaria',
    despido: 'Despido Intempestivo',
    fin_contrato: 'Fin de Contrato',
}

function badgeEstado(estado: string) {
    if (estado === 'aprobada') return { background: '#DCFCE7', color: '#166534' }
    return { background: '#FEF3C7', color: '#92400E' }
}

function StepBadge({ n, active, done }: { n: number; active: boolean; done: boolean }) {
    const bg = done ? '#059669' : active ? 'var(--primary)' : 'var(--border)'
    const color = done || active ? '#fff' : 'var(--text-muted)'
    return (
        <div style={{
            width: 28, height: 28, borderRadius: '50%', background: bg, color,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 13, fontWeight: 700, flexShrink: 0,
        }}>{done ? '✓' : n}</div>
    )
}

// ── wizard state ──────────────────────────────────────────────────────────────

interface WizardState {
    paso: 1 | 2 | 3
    // paso 1
    colaborador_id: string
    motivo: string
    fecha_salida: string
    // paso 2 (calculo + edición)
    calculo: LiquidacionCalculo | null
    editado: boolean
    decimos_acumulados: string
    vacaciones: string
    fondos_reserva: string
    anticipos_descontar: string
    total_liquidacion: string
    // paso 3
    liquidacionId: number | null
    aprobada: boolean
}

const initWizard = (): WizardState => ({
    paso: 1,
    colaborador_id: '', motivo: '', fecha_salida: new Date().toISOString().slice(0, 10),
    calculo: null, editado: false,
    decimos_acumulados: '0', vacaciones: '0', fondos_reserva: '0',
    anticipos_descontar: '0', total_liquidacion: '0',
    liquidacionId: null, aprobada: false,
})

// ── componente principal ───────────────────────────────────────────────────────

export default function LiquidacionesIndex({ liquidaciones, colaboradores, filtros, flash }: Props) {
    const { auth } = usePage<Props>().props
    const isSuperAdmin = auth.user?.perfil_clave === 'super_admin'
    const isContador   = auth.user?.perfil_clave === 'contador'
    const canEdit      = isSuperAdmin || isContador
    const canAprobar   = isSuperAdmin

    // flash
    const [flashMsg, setFlashMsg] = useState<{ ok?: string; err?: string }>({})
    useEffect(() => { if (flash?.success) setFlashMsg({ ok: flash.success }) }, [flash?.success])
    useEffect(() => { if (flash?.error)   setFlashMsg({ err: flash.error  }) }, [flash?.error])
    useEffect(() => {
        if (flashMsg.ok || flashMsg.err) { const t = setTimeout(() => setFlashMsg({}), 4500); return () => clearTimeout(t) }
    }, [flashMsg])

    // modal wizard
    const [modalOpen, setModalOpen] = useState(false)
    const [wiz, setWiz] = useState<WizardState>(initWizard())
    const [calculando, setCalculando] = useState(false)
    const [guardando, setGuardando]   = useState(false)
    const [aprobando, setAprobando]   = useState(false)
    const [errCalc, setErrCalc]       = useState<string | null>(null)

    // filtros
    const [filtroEst, setFiltroEst]     = useState(filtros.estado ?? '')
    const [filtroColab, setFiltroColab] = useState(filtros.colaborador_id ?? '')

    // modal PDF
    const [pdfUrl, setPdfUrl] = useState<string | null>(null)

    const inputStyle: React.CSSProperties = {
        background: 'var(--bg-card)', color: 'var(--text-main)',
        border: '1px solid var(--border)', borderRadius: 6, padding: '5px 10px', fontSize: 13, outline: 'none',
    }

    function aplicarFiltros() {
        router.get(route('rrhh.liquidaciones.index'), {
            estado: filtroEst || undefined,
            colaborador_id: filtroColab || undefined,
        }, { preserveState: true, replace: true })
    }

    function resetFiltros() {
        setFiltroEst(''); setFiltroColab('')
        router.get(route('rrhh.liquidaciones.index'), {}, { preserveState: true, replace: true })
    }

    function abrirWizard() { setWiz(initWizard()); setErrCalc(null); setModalOpen(true) }
    function cerrarWizard() { setModalOpen(false); setWiz(initWizard()); setErrCalc(null) }

    // PASO 1 → calcular (axios — JSON puro, try/finally garantiza reset del loading)
    async function calcular() {
        if (!wiz.colaborador_id || !wiz.motivo || !wiz.fecha_salida) return
        setCalculando(true); setErrCalc(null)
        try {
            const res = await window.axios.post(route('rrhh.liquidaciones.calcular'), {
                colaborador_id: wiz.colaborador_id,
                motivo: wiz.motivo,
                fecha_salida: wiz.fecha_salida,
            })
            const calc = res.data as LiquidacionCalculo
            setWiz(w => ({
                ...w, paso: 2, calculo: calc,
                decimos_acumulados:  String(calc.decimos_acumulados),
                vacaciones:          String(calc.vacaciones),
                fondos_reserva:      String(calc.fondos_reserva),
                anticipos_descontar: String(calc.anticipos_descontar),
                total_liquidacion:   String(calc.total_liquidacion),
            }))
        } catch (err: unknown) {
            const d = (err as { response?: { data?: { errors?: Record<string, string[]>; error?: string; message?: string } } })?.response?.data
            const errs = d?.errors ?? {}
            const single = d?.error ?? d?.message
            setErrCalc(Object.values(errs).flat().join(' · ') || single || 'Error al calcular. Verifique los datos.')
        } finally {
            setCalculando(false)
        }
    }

    // recalcular total en paso 2 cuando cambia algún campo
    function recalcularTotal(campo: string, valor: string) {
        const upd = { ...wiz, [campo]: valor, editado: true }
        const t = (
            parseFloat(upd.decimos_acumulados || '0') +
            parseFloat(upd.vacaciones || '0') +
            parseFloat(upd.fondos_reserva || '0') -
            parseFloat(upd.anticipos_descontar || '0')
        )
        upd.total_liquidacion = String(Math.max(0, t).toFixed(2))
        setWiz(upd)
    }

    // PASO 2 → guardar borrador (axios — recibe liquidacion_id para paso 3)
    async function guardarBorrador() {
        setGuardando(true); setErrCalc(null)
        try {
            const res = await window.axios.post(route('rrhh.liquidaciones.store'), {
                colaborador_id:      wiz.colaborador_id,
                motivo:              wiz.motivo,
                fecha_salida:        wiz.fecha_salida,
                decimos_acumulados:  wiz.decimos_acumulados,
                vacaciones:          wiz.vacaciones,
                fondos_reserva:      wiz.fondos_reserva,
                anticipos_descontar: wiz.anticipos_descontar,
                total_liquidacion:   wiz.total_liquidacion,
            })
            setWiz(w => ({ ...w, paso: 3, liquidacionId: res.data.liquidacion_id ?? null }))
            router.reload({ only: ['liquidaciones'] })
        } catch (err: unknown) {
            const d = (err as { response?: { data?: { errors?: Record<string, string[]>; error?: string; message?: string } } })?.response?.data
            const errs = d?.errors ?? {}
            const single = d?.error ?? d?.message
            setErrCalc(Object.values(errs).flat().join(' · ') || single || 'Error al guardar.')
        } finally {
            setGuardando(false)
        }
    }

    // PASO 3 → aprobar
    function aprobarLiquidacion() {
        if (!wiz.liquidacionId) return
        if (!confirm('¿Aprobar esta liquidación? El colaborador será inactivado y no podrá iniciar sesión.')) return
        setAprobando(true)
        router.post(route('rrhh.liquidaciones.aprobar', wiz.liquidacionId), {}, {
            onSuccess: () => { setWiz(w => ({ ...w, aprobada: true })); setAprobando(false) },
            onError: errs => { setErrCalc(Object.values(errs).join(' · ')); setAprobando(false) },
            preserveState: true,
        })
    }

    function eliminarLiquidacion(id: number) {
        if (!confirm('¿Eliminar este borrador?')) return
        router.delete(route('rrhh.liquidaciones.destroy', id))
    }

    const pasoLabels = ['Calcular', 'Revisar', 'Confirmar']

    return (
        <AppLayout>
            <Head title="Liquidaciones" />

            {/* Flash */}
            {(flashMsg.ok || flashMsg.err) && (
                <div style={{
                    position: 'fixed', top: 20, right: 20, zIndex: 9999,
                    padding: '10px 18px', borderRadius: 8, fontSize: 13, boxShadow: '0 2px 8px rgba(0,0,0,.15)',
                    background: flashMsg.ok ? '#DCFCE7' : '#FEE2E2',
                    color:      flashMsg.ok ? '#166534' : '#991B1B',
                }}>
                    {flashMsg.ok ?? flashMsg.err}
                </div>
            )}

            {/* PDF modal */}
            {pdfUrl && (
                <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.6)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div style={{ background: '#fff', borderRadius: 10, width: '80%', height: '90vh', overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
                        <div style={{ display: 'flex', justifyContent: 'flex-end', padding: '8px 12px', borderBottom: '1px solid #E5E7EB' }}>
                            <button onClick={() => setPdfUrl(null)} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 20, color: '#374151' }}>×</button>
                        </div>
                        <iframe src={pdfUrl} style={{ flex: 1, border: 'none' }} />
                    </div>
                </div>
            )}

            <PageHeader title="Liquidaciones" subtitle="Finiquitos, cálculo de haberes y actas legales" />

            <div style={{ padding: '0 24px 24px' }}>

                {/* Toolbar */}
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center', marginBottom: 16 }}>
                    <button onClick={abrirWizard}
                        style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--primary)', color: '#fff', border: 'none', borderRadius: 6, padding: '7px 14px', fontWeight: 600, fontSize: 13, cursor: 'pointer' }}>
                        <FileText size={15} /> Nueva Liquidación
                    </button>
                    <div style={{ flex: 1 }} />
                    <select value={filtroColab} onChange={e => setFiltroColab(e.target.value)} style={inputStyle}>
                        <option value="">Todos los colaboradores</option>
                        {colaboradores.map(c => <option key={c.id} value={c.id}>{c.apellidos} {c.nombres}</option>)}
                    </select>
                    <select value={filtroEst} onChange={e => setFiltroEst(e.target.value)} style={inputStyle}>
                        <option value="">Estado: todos</option>
                        <option value="borrador">Borrador</option>
                        <option value="aprobada">Aprobada</option>
                    </select>
                    <button onClick={aplicarFiltros}
                        style={{ ...inputStyle, cursor: 'pointer', background: 'var(--primary)', color: '#fff', border: 'none', padding: '6px 12px' }}>
                        Filtrar
                    </button>
                    <button onClick={resetFiltros} style={{ ...inputStyle, cursor: 'pointer' }}>Limpiar</button>
                </div>

                {/* Tabla */}
                <div style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', borderRadius: 8, overflow: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                        <thead>
                            <tr style={{ borderBottom: '2px solid var(--border)' }}>
                                {['#', 'Colaborador', 'Motivo', 'Fecha Salida', 'Total Liquidación', 'Estado', 'Acciones'].map(h => (
                                    <th key={h} style={{ padding: '10px 12px', textAlign: 'left', color: 'var(--text-muted)', fontWeight: 600, fontSize: 12 }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {liquidaciones.data.length === 0 && (
                                <tr><td colSpan={7} style={{ padding: 32, textAlign: 'center', color: 'var(--text-muted)' }}>Sin registros</td></tr>
                            )}
                            {liquidaciones.data.map((liq, i) => (
                                <tr key={liq.id} style={{ borderBottom: '1px solid var(--border)', background: i % 2 === 0 ? 'transparent' : 'rgba(0,0,0,.02)' }}>
                                    <td style={{ padding: '8px 12px', color: 'var(--text-muted)', fontSize: 12 }}>{liq.id}</td>
                                    <td style={{ padding: '8px 12px', fontWeight: 500 }}>
                                        {liq.colaborador ? `${liq.colaborador.apellidos} ${liq.colaborador.nombres}` : `#${liq.colaborador_id}`}
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>{motivoLabels[liq.motivo] ?? liq.motivo}</td>
                                    <td style={{ padding: '8px 12px', color: 'var(--text-muted)' }}>
                                        {new Date(liq.fecha_salida + 'T12:00:00Z').toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' })}
                                    </td>
                                    <td style={{ padding: '8px 12px', fontWeight: 700, color: '#4C1D95' }}>{fmt(liq.total_liquidacion)}</td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <span style={{ ...badgeEstado(liq.estado), padding: '1px 8px', borderRadius: 12, fontSize: 11, fontWeight: 600 }}>
                                            {liq.estado === 'aprobada' ? 'Aprobada' : 'Borrador'}
                                        </span>
                                        {liq.modificado_manualmente && (
                                            <span style={{ marginLeft: 4, background: '#FEF3C7', color: '#92400E', padding: '1px 6px', borderRadius: 12, fontSize: 10, fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: 2 }}>
                                                <PenLine size={9} /> Manual
                                            </span>
                                        )}
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <div style={{ display: 'flex', gap: 4 }}>
                                            <button onClick={() => setPdfUrl(route('rrhh.liquidaciones.pdf', liq.id))}
                                                title="Ver finiquito PDF"
                                                style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#4C1D95', padding: 4, borderRadius: 4 }}>
                                                <Eye size={16} />
                                            </button>
                                            {liq.estado === 'borrador' && (
                                                <button onClick={() => eliminarLiquidacion(liq.id)}
                                                    title="Eliminar borrador"
                                                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#DC2626', padding: 4, borderRadius: 4 }}>
                                                    <Trash2 size={16} />
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
                {liquidaciones.last_page > 1 && (
                    <div style={{ display: 'flex', gap: 6, justifyContent: 'center', marginTop: 16 }}>
                        {Array.from({ length: liquidaciones.last_page }, (_, i) => i + 1).map(p => (
                            <button key={p}
                                onClick={() => router.get(route('rrhh.liquidaciones.index'), { ...filtros, page: p })}
                                style={{
                                    padding: '5px 10px', borderRadius: 5, border: '1px solid var(--border)',
                                    background: p === liquidaciones.current_page ? 'var(--primary)' : 'var(--bg-card)',
                                    color: p === liquidaciones.current_page ? '#fff' : 'var(--text-main)',
                                    cursor: 'pointer', fontWeight: p === liquidaciones.current_page ? 700 : 400,
                                }}>{p}</button>
                        ))}
                    </div>
                )}
            </div>

            {/* ── Modal wizard ── */}
            {modalOpen && (
                <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.5)', zIndex: 9000, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <div style={{
                        background: 'var(--bg-card)', borderRadius: 12, padding: 28, width: 560,
                        maxHeight: '92vh', overflowY: 'auto', boxShadow: '0 8px 32px rgba(0,0,0,.25)',
                    }}>
                        {/* Header wizard */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                            <h3 style={{ fontWeight: 700, fontSize: 16, color: 'var(--text-main)' }}>Nueva Liquidación</h3>
                            <button onClick={cerrarWizard} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}>
                                <X size={18} />
                            </button>
                        </div>

                        {/* Steps indicator */}
                        <div style={{ display: 'flex', alignItems: 'center', marginBottom: 24, gap: 0 }}>
                            {pasoLabels.map((label, idx) => {
                                const n = idx + 1
                                const active = wiz.paso === n
                                const done   = wiz.paso > n
                                return (
                                    <div key={n} style={{ display: 'flex', alignItems: 'center', flex: idx < 2 ? 1 : 'initial' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexShrink: 0 }}>
                                            <StepBadge n={n} active={active} done={done} />
                                            <span style={{ fontSize: 12, fontWeight: active ? 700 : 400, color: active ? 'var(--primary)' : done ? '#059669' : 'var(--text-muted)' }}>
                                                {label}
                                            </span>
                                        </div>
                                        {idx < 2 && <div style={{ flex: 1, height: 1, background: done ? '#059669' : 'var(--border)', margin: '0 8px' }} />}
                                    </div>
                                )
                            })}
                        </div>

                        {/* Error global */}
                        {errCalc && (
                            <div style={{ background: '#FEE2E2', color: '#991B1B', padding: '8px 12px', borderRadius: 6, fontSize: 12, marginBottom: 14 }}>
                                {errCalc}
                            </div>
                        )}

                        {/* ── PASO 1: Calcular ── */}
                        {wiz.paso === 1 && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                <div>
                                    <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>COLABORADOR *</label>
                                    <select value={wiz.colaborador_id} onChange={e => setWiz(w => ({ ...w, colaborador_id: e.target.value }))}
                                        required style={{ ...inputStyle, width: '100%' }}>
                                        <option value="">Seleccionar colaborador...</option>
                                        {colaboradores.map(c => (
                                            <option key={c.id} value={c.id}>{c.apellidos} {c.nombres} ({c.cedula_ruc})</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>MOTIVO DE SALIDA *</label>
                                    <div style={{ display: 'flex', gap: 8 }}>
                                        {(['renuncia', 'despido', 'fin_contrato'] as const).map(m => (
                                            <label key={m} style={{
                                                flex: 1, textAlign: 'center', padding: '8px 4px', borderRadius: 6, cursor: 'pointer',
                                                border: `2px solid ${wiz.motivo === m ? 'var(--primary)' : 'var(--border)'}`,
                                                background: wiz.motivo === m ? 'rgba(245,158,11,.1)' : 'transparent',
                                                fontWeight: wiz.motivo === m ? 700 : 400,
                                                color: wiz.motivo === m ? 'var(--primary)' : 'var(--text-muted)',
                                                fontSize: 11,
                                            }}>
                                                <input type="radio" name="motivo" value={m} checked={wiz.motivo === m}
                                                    onChange={() => setWiz(w => ({ ...w, motivo: m }))} style={{ display: 'none' }} />
                                                {motivoLabels[m]}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>FECHA DE SALIDA *</label>
                                    <Input type="date" value={wiz.fecha_salida}
                                        onChange={e => setWiz(w => ({ ...w, fecha_salida: e.target.value }))} required />
                                </div>
                                <p style={{ fontSize: 11, color: 'var(--text-muted)', background: 'rgba(245,158,11,.07)', padding: '6px 10px', borderRadius: 5, borderLeft: '3px solid var(--primary)' }}>
                                    Se calculará: décimos proporcionales, vacaciones no gozadas, fondos de reserva y anticipos pendientes.
                                </p>
                                <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                    <button onClick={calcular} disabled={calculando || !wiz.colaborador_id || !wiz.motivo || !wiz.fecha_salida}
                                        style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '8px 18px', borderRadius: 6, border: 'none', background: 'var(--primary)', color: '#fff', cursor: 'pointer', fontWeight: 600, fontSize: 13, opacity: calculando ? .7 : 1 }}>
                                        {calculando ? 'Calculando…' : <><ChevronRight size={15} /> Calcular</>}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* ── PASO 2: Revisar ── */}
                        {wiz.paso === 2 && wiz.calculo && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                                {/* Info colaborador */}
                                <div style={{ background: 'rgba(0,0,0,.03)', borderRadius: 8, padding: '10px 14px', fontSize: 12 }}>
                                    <div style={{ fontWeight: 700, fontSize: 14, marginBottom: 4 }}>
                                        {wiz.calculo.colaborador.apellidos} {wiz.calculo.colaborador.nombres}
                                    </div>
                                    <div style={{ color: 'var(--text-muted)' }}>
                                        {wiz.calculo.colaborador.cargo ?? 'Sin cargo'} · Sueldo: {fmt(wiz.calculo.colaborador.sueldo_base)} · Tiempo: {wiz.calculo.meses_laborados} meses ({wiz.calculo.dias_laborados} días)
                                    </div>
                                </div>

                                {wiz.editado && (
                                    <div style={{ display: 'flex', alignItems: 'center', gap: 6, background: '#FEF3C7', padding: '5px 10px', borderRadius: 5, fontSize: 11, color: '#78350F' }}>
                                        <PenLine size={12} /> <strong>[Modificado Manualmente]</strong> — Los valores han sido editados respecto al cálculo original.
                                    </div>
                                )}

                                {/* Tabla editable */}
                                {[
                                    { campo: 'decimos_acumulados' as const, label: 'Décimos acumulados proporcionales', color: '#059669', signo: '+' },
                                    { campo: 'vacaciones'          as const, label: 'Vacaciones no gozadas proporcionales', color: '#059669', signo: '+' },
                                    { campo: 'fondos_reserva'      as const, label: 'Fondos de reserva proporcionales', color: '#059669', signo: '+' },
                                    { campo: 'anticipos_descontar' as const, label: 'Préstamos/anticipos a descontar', color: '#DC2626', signo: '−' },
                                ].map(({ campo, label, color, signo }) => (
                                    <div key={campo} style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: 8, alignItems: 'center' }}>
                                        <div>
                                            <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)' }}>{label}</div>
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                                            <span style={{ fontSize: 13, color, fontWeight: 600 }}>{signo}</span>
                                            {canEdit ? (
                                                <Input
                                                    type="number" step="0.01" min="0" style={{ width: 110, textAlign: 'right' }}
                                                    value={wiz[campo]}
                                                    onChange={e => recalcularTotal(campo, e.target.value)}
                                                />
                                            ) : (
                                                <span style={{ fontSize: 14, fontWeight: 700, width: 110, textAlign: 'right', color }}>{fmt(wiz[campo])}</span>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                <div style={{ borderTop: '2px solid var(--border)', paddingTop: 10, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                    <span style={{ fontWeight: 700, fontSize: 14 }}>TOTAL NETO A PAGAR</span>
                                    <span style={{ fontWeight: 800, fontSize: 20, color: '#4C1D95' }}>{fmt(wiz.total_liquidacion)}</span>
                                </div>

                                {!canEdit && (
                                    <p style={{ fontSize: 11, color: 'var(--text-muted)', background: 'rgba(0,0,0,.04)', padding: '5px 10px', borderRadius: 5 }}>
                                        Solo Contador o Super Admin pueden editar los valores calculados.
                                    </p>
                                )}

                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <button onClick={() => setWiz(w => ({ ...w, paso: 1 }))}
                                        style={{ display: 'flex', alignItems: 'center', gap: 4, padding: '8px 14px', borderRadius: 6, border: '1px solid var(--border)', background: 'transparent', color: 'var(--text-main)', cursor: 'pointer', fontSize: 13 }}>
                                        <ChevronLeft size={14} /> Atrás
                                    </button>
                                    <button onClick={guardarBorrador} disabled={guardando}
                                        style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '8px 18px', borderRadius: 6, border: 'none', background: 'var(--primary)', color: '#fff', cursor: 'pointer', fontWeight: 600, fontSize: 13, opacity: guardando ? .7 : 1 }}>
                                        {guardando ? 'Guardando…' : <><ChevronRight size={15} /> Guardar y Continuar</>}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* ── PASO 3: Confirmar ── */}
                        {wiz.paso === 3 && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: 16, alignItems: 'center', textAlign: 'center' }}>
                                {wiz.aprobada ? (
                                    <>
                                        <CheckCircle2 size={48} style={{ color: '#059669' }} />
                                        <h4 style={{ fontSize: 17, fontWeight: 700, color: '#059669' }}>Liquidación Aprobada</h4>
                                        <p style={{ fontSize: 13, color: 'var(--text-muted)', maxWidth: 380 }}>
                                            El colaborador ha sido inactivado. Puede descargar el acta de finiquito.
                                        </p>
                                        {wiz.liquidacionId && (
                                            <button
                                                onClick={() => setPdfUrl(route('rrhh.liquidaciones.pdf', wiz.liquidacionId!))}
                                                style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '10px 20px', borderRadius: 6, border: 'none', background: '#4C1D95', color: '#fff', cursor: 'pointer', fontWeight: 600, fontSize: 13 }}>
                                                <Download size={15} /> Ver Acta de Finiquito (PDF)
                                            </button>
                                        )}
                                        <button onClick={cerrarWizard}
                                            style={{ padding: '7px 16px', borderRadius: 6, border: '1px solid var(--border)', background: 'transparent', color: 'var(--text-main)', cursor: 'pointer', fontSize: 13 }}>
                                            Cerrar
                                        </button>
                                    </>
                                ) : (
                                    <>
                                        <AlertTriangle size={40} style={{ color: '#F59E0B' }} />
                                        <h4 style={{ fontSize: 16, fontWeight: 700 }}>Confirmar Liquidación</h4>
                                        <p style={{ fontSize: 13, color: 'var(--text-muted)', maxWidth: 400 }}>
                                            El borrador ha sido guardado. Si eres <strong>Super Admin</strong>, puedes aprobar y generar el asiento contable.
                                            Esta acción inactiva al colaborador y bloquea su acceso al sistema.
                                        </p>

                                        {wiz.liquidacionId && (
                                            <button
                                                onClick={() => setPdfUrl(route('rrhh.liquidaciones.pdf', wiz.liquidacionId!))}
                                                style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '8px 16px', borderRadius: 6, border: '1px solid #4C1D95', background: 'transparent', color: '#4C1D95', cursor: 'pointer', fontWeight: 600, fontSize: 13 }}>
                                                <Eye size={14} /> Vista previa Finiquito
                                            </button>
                                        )}

                                        {canAprobar ? (
                                            <button onClick={aprobarLiquidacion} disabled={aprobando}
                                                style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '10px 24px', borderRadius: 6, border: 'none', background: '#DC2626', color: '#fff', cursor: 'pointer', fontWeight: 700, fontSize: 14, opacity: aprobando ? .7 : 1 }}>
                                                {aprobando ? 'Aprobando…' : <><CheckCircle2 size={16} /> Aprobar Liquidación</>}
                                            </button>
                                        ) : (
                                            <p style={{ fontSize: 12, color: 'var(--text-muted)', background: '#F3F4F6', padding: '8px 14px', borderRadius: 6 }}>
                                                La aprobación final requiere rol <strong>Super Admin</strong>.
                                            </p>
                                        )}

                                        <button onClick={cerrarWizard}
                                            style={{ padding: '7px 16px', borderRadius: 6, border: '1px solid var(--border)', background: 'transparent', color: 'var(--text-main)', cursor: 'pointer', fontSize: 13 }}>
                                            Cerrar y revisar luego
                                        </button>
                                    </>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
