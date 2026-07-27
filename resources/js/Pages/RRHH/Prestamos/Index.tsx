import { useState, useEffect } from 'react'
import { router, usePage, Head } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Input } from '@/Components/ui/input'
import { cn } from '@/lib/utils'
import {
    Plus, Search, DollarSign, CheckCircle2, Trash2, Filter, X,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PrestamoEmpleado, Colaborador, PageProps, PaginatedData } from '@/types'

// ── tipos locales ──────────────────────────────────────────────────────────────

interface ColabItem {
    id: number; cedula_ruc: string; apellidos: string; nombres: string; sueldo_base: number
}

interface Props extends PageProps {
    prestamos: PaginatedData<PrestamoEmpleado>
    colaboradores: ColabItem[]
    filtros: { colaborador_id?: string; tipo?: string; estado?: string; anio?: string; mes?: string }
    anios: number[]
    flash?: { success?: string; error?: string }
}

// ── helpers ───────────────────────────────────────────────────────────────────

const fmt = (n: number) => `$${Number(n).toFixed(2)}`

function badgeEstado(estado: string): React.CSSProperties {
    if (estado === 'pagado') return { background: '#DCFCE7', color: '#166534', padding: '1px 8px', borderRadius: 12, fontSize: 11, fontWeight: 600 }
    return { background: '#DBEAFE', color: '#1E40AF', padding: '1px 8px', borderRadius: 12, fontSize: 11, fontWeight: 600 }
}

function badgeTipo(tipo: string): React.CSSProperties {
    if (tipo === 'anticipo') return { background: '#FEF3C7', color: '#78350F', padding: '1px 8px', borderRadius: 12, fontSize: 11, fontWeight: 600 }
    return { background: '#F3E8FF', color: '#6B21A8', padding: '1px 8px', borderRadius: 12, fontSize: 11, fontWeight: 600 }
}

// ── componente principal ───────────────────────────────────────────────────────

export default function PrestamosIndex({ prestamos, colaboradores, filtros, anios, flash }: Props) {
    const { auth } = usePage<Props>().props
    const { puede } = usePermiso('rrhh')

    // flash toasts
    const [flashMsg, setFlashMsg] = useState<{ ok?: string; err?: string }>({})
    useEffect(() => { if (flash?.success) setFlashMsg({ ok: flash.success }) }, [flash?.success])
    useEffect(() => { if (flash?.error)   setFlashMsg({ err: flash.error  }) }, [flash?.error])
    useEffect(() => {
        if (flashMsg.ok || flashMsg.err) {
            const t = setTimeout(() => setFlashMsg({}), 4500)
            return () => clearTimeout(t)
        }
    }, [flashMsg])

    // modal nuevo préstamo
    const [modalOpen, setModalOpen] = useState(false)
    const [form, setForm] = useState({
        colaborador_id: '',
        tipo: 'prestamo' as 'prestamo' | 'anticipo',
        monto_total: '',
        cuota: '',
        fecha: new Date().toISOString().slice(0, 10),
        descripcion: '',
    })
    const [saving, setSaving] = useState(false)

    // filtros
    const [filtroColab, setFiltroColab] = useState(filtros.colaborador_id ?? '')
    const [filtroTipo,  setFiltroTipo]  = useState(filtros.tipo    ?? '')
    const [filtroEst,   setFiltroEst]   = useState(filtros.estado  ?? '')
    const [filtroAnio,  setFiltroAnio]  = useState(filtros.anio    ?? '')

    const inputStyle: React.CSSProperties = {
        background: 'var(--bg-card)', color: 'var(--text-main)',
        border: '1px solid var(--border)', borderRadius: 6, padding: '5px 10px',
        fontSize: 13, outline: 'none',
    }

    function aplicarFiltros() {
        router.get(route('rrhh.prestamos.index'), {
            colaborador_id: filtroColab || undefined,
            tipo:  filtroTipo  || undefined,
            estado:filtroEst   || undefined,
            anio:  filtroAnio  || undefined,
        }, { preserveState: true, replace: true })
    }

    function resetFiltros() {
        setFiltroColab(''); setFiltroTipo(''); setFiltroEst(''); setFiltroAnio('')
        router.get(route('rrhh.prestamos.index'), {}, { preserveState: true, replace: true })
    }

    function submitNuevo(e: React.FormEvent) {
        e.preventDefault()
        setSaving(true)
        router.post(route('rrhh.prestamos.store'), form as Record<string, string>, {
            onFinish: () => { setSaving(false); setModalOpen(false); resetForm() },
        })
    }

    function resetForm() {
        setForm({ colaborador_id: '', tipo: 'prestamo', monto_total: '', cuota: '', fecha: new Date().toISOString().slice(0, 10), descripcion: '' })
    }

    function confirmarPagar(id: number) {
        if (!confirm('¿Marcar como pagado? Esta acción no se puede deshacer.')) return
        router.patch(route('rrhh.prestamos.pagar', id))
    }

    function confirmarEliminar(id: number) {
        if (!confirm('¿Eliminar este registro?')) return
        router.delete(route('rrhh.prestamos.destroy', id))
    }

    const colabLabel = (c: ColabItem) => `${c.apellidos} ${c.nombres} (${c.cedula_ruc})`

    return (
        <AppLayout>
            <Head title="Préstamos y Anticipos" />

            {/* Flash */}
            {(flashMsg.ok || flashMsg.err) && (
                <div style={{
                    position: 'fixed', top: 20, right: 20, zIndex: 9999,
                    padding: '10px 18px', borderRadius: 8,
                    background: flashMsg.ok ? '#DCFCE7' : '#FEE2E2',
                    color:      flashMsg.ok ? '#166534' : '#991B1B',
                    fontSize: 13, boxShadow: '0 2px 8px rgba(0,0,0,.15)',
                }}>
                    {flashMsg.ok ?? flashMsg.err}
                </div>
            )}

            <PageHeader
                title="Préstamos y Anticipos"
                description="Control de desembolsos a colaboradores con asiento contable automático"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Préstamos y Anticipos' }]}
                actions={
                    puede('crear') ? (
                        <button
                            onClick={() => setModalOpen(true)}
                            style={{ display: 'flex', alignItems: 'center', gap: 6, background: 'var(--primary)', color: '#000', border: 'none', borderRadius: 6, padding: '7px 14px', fontWeight: 600, fontSize: 13, cursor: 'pointer' }}
                        >
                            <Plus size={15} /> Nuevo
                        </button>
                    ) : undefined
                }
            />

            <div style={{ padding: '0 24px 24px' }}>

                {/* Toolbar — filtros */}
                <div style={{ marginTop: 16 }}>
                    <FilterToolbar
                        extraActions={
                            <>
                                <button onClick={aplicarFiltros} style={{ ...inputStyle, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4, background: 'var(--primary)', color: '#000', border: 'none', padding: '6px 12px' }}>
                                    <Filter size={13} /> Filtrar
                                </button>
                                <button onClick={resetFiltros} style={{ ...inputStyle, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 4 }}>
                                    <X size={13} /> Limpiar
                                </button>
                            </>
                        }
                    >
                        <select value={filtroColab} onChange={e => setFiltroColab(e.target.value)} style={inputStyle}>
                            <option value="">Todos los colaboradores</option>
                            {colaboradores.map(c => <option key={c.id} value={c.id}>{colabLabel(c)}</option>)}
                        </select>
                        <select value={filtroTipo} onChange={e => setFiltroTipo(e.target.value)} style={inputStyle}>
                            <option value="">Tipo: todos</option>
                            <option value="prestamo">Préstamo</option>
                            <option value="anticipo">Anticipo</option>
                        </select>
                        <select value={filtroEst} onChange={e => setFiltroEst(e.target.value)} style={inputStyle}>
                            <option value="">Estado: todos</option>
                            <option value="activo">Activo</option>
                            <option value="pagado">Pagado</option>
                        </select>
                        <select value={filtroAnio} onChange={e => setFiltroAnio(e.target.value)} style={inputStyle}>
                            <option value="">Año</option>
                            {anios.map(a => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </FilterToolbar>
                </div>

                {/* Tabla */}
                <div style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', borderRadius: 8, overflow: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 13 }}>
                        <thead>
                            <tr style={{ borderBottom: '2px solid var(--border)' }}>
                                {['#', 'Colaborador', 'Tipo', 'Fecha', 'Monto Total', 'Cuota', 'Saldo Pendiente', 'Estado', 'Acciones'].map(h => (
                                    <th key={h} style={{ padding: '10px 12px', textAlign: 'left', color: 'var(--text-muted)', fontWeight: 600, fontSize: 12, whiteSpace: 'nowrap' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {prestamos.data.length === 0 && (
                                <tr><td colSpan={9} style={{ padding: 32, textAlign: 'center', color: 'var(--text-muted)' }}>Sin registros</td></tr>
                            )}
                            {prestamos.data.map((p, i) => (
                                <tr key={p.id} style={{ borderBottom: '1px solid var(--border)', background: i % 2 === 0 ? 'transparent' : 'rgba(0,0,0,.02)' }}>
                                    <td style={{ padding: '8px 12px', color: 'var(--text-muted)', fontSize: 12 }}>{p.id}</td>
                                    <td style={{ padding: '8px 12px', fontWeight: 500 }}>
                                        {p.colaborador
                                            ? `${p.colaborador.apellidos} ${p.colaborador.nombres}`
                                            : `#${p.colaborador_id}`}
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <span style={badgeTipo(p.tipo)}>
                                            {p.tipo === 'anticipo' ? 'Anticipo' : 'Préstamo'}
                                        </span>
                                    </td>
                                    <td style={{ padding: '8px 12px', color: 'var(--text-muted)' }}>
                                        {new Date(p.fecha).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' })}
                                    </td>
                                    <td style={{ padding: '8px 12px', fontWeight: 600 }}>{fmt(p.monto_total)}</td>
                                    <td style={{ padding: '8px 12px' }}>
                                        {p.tipo === 'prestamo' && p.cuota > 0 ? fmt(p.cuota) : <span style={{ color: 'var(--text-muted)' }}>—</span>}
                                    </td>
                                    <td style={{ padding: '8px 12px', fontWeight: 600, color: p.saldo > 0 ? '#DC2626' : '#059669' }}>
                                        {fmt(p.saldo)}
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <span style={badgeEstado(p.estado)}>
                                            {p.estado === 'pagado' ? 'Pagado' : 'Activo'}
                                        </span>
                                    </td>
                                    <td style={{ padding: '8px 12px' }}>
                                        <div style={{ display: 'flex', gap: 4 }}>
                                            {p.estado === 'activo' && puede('editar') && (
                                                <button
                                                    onClick={() => confirmarPagar(p.id)}
                                                    title="Marcar como pagado"
                                                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#059669', padding: 4, borderRadius: 4 }}
                                                >
                                                    <CheckCircle2 size={16} />
                                                </button>
                                            )}
                                            {p.estado === 'activo' && puede('eliminar') && (
                                                <button
                                                    onClick={() => confirmarEliminar(p.id)}
                                                    title="Eliminar"
                                                    style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#DC2626', padding: 4, borderRadius: 4 }}
                                                >
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
                {prestamos.last_page > 1 && (
                    <div style={{ display: 'flex', gap: 6, justifyContent: 'center', marginTop: 16 }}>
                        {Array.from({ length: prestamos.last_page }, (_, i) => i + 1).map(p => (
                            <button
                                key={p}
                                onClick={() => router.get(route('rrhh.prestamos.index'), { ...filtros, page: p })}
                                style={{
                                    padding: '5px 10px', borderRadius: 5, border: '1px solid var(--border)',
                                    background: p === prestamos.current_page ? 'var(--primary)' : 'var(--bg-card)',
                                    color: p === prestamos.current_page ? '#fff' : 'var(--text-main)',
                                    cursor: 'pointer', fontWeight: p === prestamos.current_page ? 700 : 400,
                                }}
                            >{p}</button>
                        ))}
                    </div>
                )}
            </div>

            {/* Modal nuevo préstamo/anticipo */}
            {modalOpen && (
                <div style={{
                    position: 'fixed', inset: 0, background: 'rgba(0,0,0,.5)',
                    zIndex: 9000, display: 'flex', alignItems: 'center', justifyContent: 'center',
                }}>
                    <div style={{
                        background: 'var(--bg-card)', borderRadius: 10, padding: 24, width: 480,
                        maxHeight: '90vh', overflowY: 'auto', boxShadow: '0 8px 32px rgba(0,0,0,.25)',
                    }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                            <h3 style={{ fontWeight: 700, fontSize: 16, color: 'var(--text-main)' }}>
                                <DollarSign size={18} style={{ verticalAlign: 'middle', marginRight: 6, color: 'var(--primary)' }} />
                                Nuevo Préstamo / Anticipo
                            </h3>
                            <button onClick={() => { setModalOpen(false); resetForm() }} style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--text-muted)' }}>
                                <X size={18} />
                            </button>
                        </div>

                        <form onSubmit={submitNuevo} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            {/* Tipo */}
                            <div>
                                <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 6 }}>TIPO</label>
                                <div style={{ display: 'flex', gap: 8 }}>
                                    {(['prestamo', 'anticipo'] as const).map(t => (
                                        <label key={t} style={{
                                            flex: 1, textAlign: 'center', padding: '8px 0', borderRadius: 6, cursor: 'pointer',
                                            border: `2px solid ${form.tipo === t ? 'var(--primary)' : 'var(--border)'}`,
                                            background: form.tipo === t ? 'rgba(245,158,11,.1)' : 'transparent',
                                            fontWeight: form.tipo === t ? 700 : 400,
                                            color: form.tipo === t ? 'var(--primary)' : 'var(--text-muted)',
                                            fontSize: 13,
                                        }}>
                                            <input type="radio" name="tipo" value={t} checked={form.tipo === t}
                                                onChange={() => setForm({ ...form, tipo: t })}
                                                style={{ display: 'none' }} />
                                            {t === 'prestamo' ? 'Préstamo' : 'Anticipo'}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* Colaborador */}
                            <div>
                                <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>COLABORADOR *</label>
                                <select
                                    value={form.colaborador_id}
                                    onChange={e => setForm({ ...form, colaborador_id: e.target.value })}
                                    required style={{ ...inputStyle, width: '100%' }}
                                >
                                    <option value="">Seleccionar colaborador...</option>
                                    {colaboradores.map(c => (
                                        <option key={c.id} value={c.id}>{c.apellidos} {c.nombres} ({c.cedula_ruc})</option>
                                    ))}
                                </select>
                            </div>

                            {/* Monto */}
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
                                <div>
                                    <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>MONTO *</label>
                                    <Input
                                        type="number" step="0.01" min="1"
                                        value={form.monto_total}
                                        onChange={e => setForm({ ...form, monto_total: e.target.value })}
                                        required placeholder="0.00"
                                    />
                                </div>
                                {form.tipo === 'prestamo' && (
                                    <div>
                                        <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>CUOTA MENSUAL</label>
                                        <Input
                                            type="number" step="0.01" min="0"
                                            value={form.cuota}
                                            onChange={e => setForm({ ...form, cuota: e.target.value })}
                                            placeholder="0.00"
                                        />
                                    </div>
                                )}
                            </div>

                            {/* Fecha */}
                            <div>
                                <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>FECHA *</label>
                                <Input
                                    type="date"
                                    value={form.fecha}
                                    onChange={e => setForm({ ...form, fecha: e.target.value })}
                                    required
                                />
                            </div>

                            {/* Descripción */}
                            <div>
                                <label style={{ fontSize: 12, fontWeight: 600, color: 'var(--text-muted)', display: 'block', marginBottom: 4 }}>DESCRIPCIÓN</label>
                                <textarea
                                    value={form.descripcion}
                                    onChange={e => setForm({ ...form, descripcion: e.target.value })}
                                    rows={2}
                                    placeholder="Motivo o detalle del préstamo..."
                                    style={{ ...inputStyle, width: '100%', resize: 'vertical', fontFamily: 'inherit' }}
                                />
                            </div>

                            {/* Nota asiento */}
                            <p style={{ fontSize: 11, color: 'var(--text-muted)', background: 'rgba(245,158,11,.08)', padding: '6px 10px', borderRadius: 5, borderLeft: '3px solid var(--primary)' }}>
                                Al guardar se generará automáticamente el asiento contable:<br />
                                <strong>DEBE</strong> 1.1.3.04 Préstamos/Anticipos a Empleados → <strong>HABER</strong> 1.1.1.03 Bancos Locales
                            </p>

                            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
                                <button type="button" onClick={() => { setModalOpen(false); resetForm() }}
                                    style={{ padding: '8px 16px', borderRadius: 6, border: '1px solid var(--border)', background: 'transparent', color: 'var(--text-main)', cursor: 'pointer', fontSize: 13 }}>
                                    Cancelar
                                </button>
                                <button type="submit" disabled={saving}
                                    style={{ padding: '8px 16px', borderRadius: 6, border: 'none', background: 'var(--primary)', color: '#fff', cursor: 'pointer', fontWeight: 600, fontSize: 13, opacity: saving ? .7 : 1 }}>
                                    {saving ? 'Guardando…' : 'Registrar y Generar Asiento'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    )
}
