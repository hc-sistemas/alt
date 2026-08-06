import { useState } from 'react'
import { Head, router, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { cn } from '@/lib/utils'
import { usePermiso } from '@/Hooks/usePermiso'
import type { Nomina, PageProps, PaginatedData } from '@/types'
import { Plus, ChevronLeft, ChevronRight, Trash2, Eye, Archive, FileText, Search } from 'lucide-react'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

function getBadgeEstado(estado: string): React.CSSProperties {
    switch (estado) {
        case 'borrador':  return { background: 'rgba(107,114,128,0.12)', color: '#6B7280' }
        case 'procesado': return { background: 'rgba(56,139,212,0.12)',  color: '#185FA5' }
        case 'pagado':    return { background: 'rgba(22,163,74,0.12)',   color: '#15803d' }
        default:          return { background: 'rgba(107,114,128,0.1)',  color: '#9CA3AF' }
    }
}

function fmt(n: number) {
    return new Intl.NumberFormat('es-EC', { minimumFractionDigits: 2 }).format(n)
}

// ─────────────────────────────────────────────────────────────────────────────
// Modal Generar Nómina
// ─────────────────────────────────────────────────────────────────────────────

interface GenerarModalProps { onClose: () => void }

function GenerarModal({ onClose }: GenerarModalProps) {
    const [form, setForm] = useState({
        periodo_tipo: 'mensual',
        anio: new Date().getFullYear(),
        mes: new Date().getMonth() + 1,
        quincena: '',
    })
    const [loading, setLoading] = useState(false)

    function set(k: string, v: string | number) {
        setForm(f => ({ ...f, [k]: v }))
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        setLoading(true)
        router.post(route('rrhh.nomina.generar'), form, {
            onFinish: () => { setLoading(false); onClose() },
        })
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="modal-card w-full max-w-md">
                <div className="modal-header">
                    <h3 className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                        Generar Nómina
                    </h3>
                </div>
                <form onSubmit={submit}>
                    <div className="modal-body space-y-4">
                        <div>
                            <label className="input-label">Tipo de nómina</label>
                            <div className="flex gap-4 mt-1">
                                {(['mensual', 'quincenal'] as const).map(t => (
                                    <label key={t} className="flex items-center gap-2 text-sm cursor-pointer" style={{ color: 'var(--text-main)' }}>
                                        <input
                                            type="radio"
                                            name="periodo_tipo"
                                            value={t}
                                            checked={form.periodo_tipo === t}
                                            onChange={() => set('periodo_tipo', t)}
                                        />
                                        {t === 'mensual' ? 'Mensual' : 'Quincenal'}
                                    </label>
                                ))}
                            </div>
                        </div>

                        {form.periodo_tipo === 'quincenal' && (
                            <div>
                                <label className="input-label">Quincena</label>
                                <select className="input-field" value={form.quincena}
                                    onChange={e => set('quincena', e.target.value)} required>
                                    <option value="">Seleccionar…</option>
                                    <option value="1">1ª quincena (días 1–15)</option>
                                    <option value="2">2ª quincena (días 16–fin)</option>
                                </select>
                            </div>
                        )}

                        <div>
                            <label className="input-label">Año</label>
                            <select className="input-field" value={form.anio}
                                onChange={e => set('anio', parseInt(e.target.value))}>
                                {[0, 1, 2, 3].map(i => {
                                    const y = new Date().getFullYear() - i
                                    return <option key={y} value={y}>{y}</option>
                                })}
                            </select>
                        </div>

                        <div>
                            <label className="input-label">Mes</label>
                            <select className="input-field" value={form.mes}
                                onChange={e => set('mes', parseInt(e.target.value))}>
                                {MESES.slice(1).map((m, i) => (
                                    <option key={i + 1} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn-secondary" onClick={onClose}>Cancelar</button>
                        <button type="submit" className="btn-primary" disabled={loading}>
                            {loading ? 'Generando…' : 'Generar Nómina'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─────────────────────────────────────────────────────────────────────────────
// Página principal
// ─────────────────────────────────────────────────────────────────────────────

interface Props extends PageProps {
    nominas: PaginatedData<Nomina> | null
    filtros: { anio?: string; mes?: string; tipo?: string; estado?: string; buscar?: string }
    anios: number[]
}

export default function NominaIndex() {
    const { nominas, filtros, anios, flash } = usePage<Props>().props
    const { puede } = usePermiso('rrhh')

    const [showGenerar, setShowGenerar] = useState(false)
    const [filtro, setFiltro] = useState(filtros)

    // Cambiar cualquier filtro después de haber buscado no vacía la tabla —
    // solo la atenúa (opacity-60) hasta que se presione Buscar de nuevo.
    // Mismo patrón ya usado en el resto del sistema esta sesión.
    const [filtrosSucios, setFiltrosSucios] = useState(false)

    // Carga bajo demanda: `nominas` viene null hasta la primera búsqueda.
    const haBuscado = nominas !== null

    function cambiarFiltro<K extends keyof typeof filtro>(campo: K, valor: string) {
        setFiltro(f => ({ ...f, [campo]: valor }))
        setFiltrosSucios(true)
    }

    function aplicarFiltros() {
        router.get(route('rrhh.nomina.index'), { ...filtro, buscado: '1' }, {
            preserveState: true,
            replace: true,
            onSuccess: () => setFiltrosSucios(false),
        })
    }

    function eliminar(n: Nomina) {
        if (n.estado !== 'borrador') return
        if (!confirm(`¿Eliminar la nómina ${n.periodo_label}? Esta acción no se puede deshacer.`)) return
        router.delete(route('rrhh.nomina.destroy', n.id))
    }

    const periodoLabel = (n: Nomina) => {
        const m = MESES[n.mes] ?? ''
        if (n.periodo_tipo === 'quincenal') return `${n.quincena}ª quincena ${m} ${n.anio}`
        return `${m} ${n.anio}`
    }

    const netoTotal = haBuscado && nominas ? nominas.data.reduce((s, n) => s + Number(n.total_neto), 0) : 0

    return (
        <AppLayout>
            <Head title="Nómina — RRHH" />
            {showGenerar && <GenerarModal onClose={() => setShowGenerar(false)} />}

            <PageHeader
                title="Nómina"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Nómina' }]}
                actions={
                    <div className="flex items-center gap-3">
                        {haBuscado && nominas && nominas.data.length > 0 && (
                            <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                {nominas.total} nómina{nominas.total !== 1 ? 's' : ''} · Neto ${fmt(netoTotal)}
                            </span>
                        )}
                        {puede('crear') && (
                            <button
                                onClick={() => setShowGenerar(true)}
                                className="btn-primary flex items-center gap-1.5 whitespace-nowrap shrink-0"
                            >
                                <Plus size={15} /> Generar Nómina
                            </button>
                        )}
                    </div>
                }
            />

            <div className="p-6">

                {/* Flash */}
                {flash?.success && (
                    <div className="mb-4 px-4 py-2 border border-green-200 rounded text-sm font-medium bg-green-50 text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 px-4 py-2 border border-red-200 rounded text-sm font-medium bg-red-50 text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* Toolbar — filtros */}
                <FilterToolbar
                    search={{
                        value: filtro.buscar ?? '',
                        onChange: v => cambiarFiltro('buscar', v),
                        onSearch: aplicarFiltros,
                        placeholder: 'Año, mes, tipo...',
                    }}
                >
                    <select value={filtro.anio ?? ''} onChange={e => cambiarFiltro('anio', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '90px' }}>
                        <option value="">Año</option>
                        {anios.map(y => <option key={y} value={y}>{y}</option>)}
                    </select>

                    <select value={filtro.mes ?? ''} onChange={e => cambiarFiltro('mes', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '110px' }}>
                        <option value="">Mes</option>
                        {MESES.slice(1).map((m, i) => (
                            <option key={i + 1} value={i + 1}>{m}</option>
                        ))}
                    </select>

                    <select value={filtro.tipo ?? ''} onChange={e => cambiarFiltro('tipo', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '110px' }}>
                        <option value="">Tipo</option>
                        <option value="mensual">Mensual</option>
                        <option value="quincenal">Quincenal</option>
                    </select>

                    <select value={filtro.estado ?? ''} onChange={e => cambiarFiltro('estado', e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '110px' }}>
                        <option value="">Estado</option>
                        <option value="borrador">Borrador</option>
                        <option value="procesado">Procesado</option>
                        <option value="pagado">Pagado</option>
                    </select>
                </FilterToolbar>

                {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
                {!haBuscado && (
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar las nóminas.
                        </p>
                    </div>
                )}

                {/* Tabla */}
                {haBuscado && nominas && (
                <div className={cn(filtrosSucios && 'opacity-60 transition-opacity')}>
                <div style={{ background: 'var(--bg-card)', border: '1px solid var(--border)', borderRadius: '12px', overflow: 'hidden' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                {['Período', 'Tipo', 'Estado', 'Empleados', 'Total Ingresos', 'Total Egresos', 'Neto', 'Acciones'].map(h => (
                                    <th key={h} style={{
                                        padding: '0.75rem 1rem',
                                        textAlign: (h === 'Empleados' || h === 'Acciones') ? 'center' : 'left',
                                        fontSize: '0.72rem', fontWeight: 600,
                                        color: 'var(--text-muted)',
                                        textTransform: 'uppercase', letterSpacing: '0.05em',
                                        background: 'var(--bg-main)',
                                    }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {nominas.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} style={{ padding: '3rem', textAlign: 'center', color: 'var(--text-muted)' }}>
                                        <FileText size={36} style={{ opacity: 0.3, margin: '0 auto 0.75rem', display: 'block' }} />
                                        <p style={{ fontSize: '0.9rem', margin: 0 }}>No hay nóminas para los filtros seleccionados</p>
                                    </td>
                                </tr>
                            ) : nominas.data.map((n, i) => (
                                <tr key={n.id} style={{
                                    borderBottom: i < nominas.data.length - 1 ? '1px solid var(--border)' : 'none',
                                    background: i % 2 === 0 ? 'var(--bg-card)' : 'var(--bg-main)',
                                }}>
                                    {/* Período */}
                                    <td style={{ padding: '0.875rem 1rem' }}>
                                        <div style={{ fontWeight: 600, fontSize: '0.9rem', color: 'var(--text-main)' }}>
                                            {periodoLabel(n)}
                                        </div>
                                    </td>

                                    {/* Tipo */}
                                    <td style={{ padding: '0.875rem 1rem' }}>
                                        <span style={{
                                            fontSize: '0.78rem', fontWeight: 500,
                                            padding: '3px 8px', borderRadius: '5px',
                                            background: n.periodo_tipo === 'mensual' ? 'rgba(99,153,34,0.12)' : 'rgba(56,139,212,0.12)',
                                            color:      n.periodo_tipo === 'mensual' ? '#3B6D11'             : '#185FA5',
                                        }}>
                                            {n.periodo_tipo === 'mensual' ? 'Mensual' : 'Quincenal'}
                                        </span>
                                    </td>

                                    {/* Estado */}
                                    <td style={{ padding: '0.875rem 1rem' }}>
                                        <span style={{
                                            fontSize: '0.78rem', fontWeight: 600,
                                            padding: '3px 10px', borderRadius: '5px',
                                            ...getBadgeEstado(n.estado),
                                        }}>
                                            {n.estado.charAt(0).toUpperCase() + n.estado.slice(1)}
                                        </span>
                                    </td>

                                    {/* Empleados */}
                                    <td style={{ padding: '0.875rem 1rem', textAlign: 'center', fontSize: '0.875rem', color: 'var(--text-main)' }}>
                                        {n.detalles_count ?? 0}
                                    </td>

                                    {/* Total Ingresos */}
                                    <td style={{ padding: '0.875rem 1rem', fontSize: '0.875rem', color: 'var(--text-main)', fontVariantNumeric: 'tabular-nums' }}>
                                        ${fmt(n.total_ingresos)}
                                    </td>

                                    {/* Total Egresos — en paréntesis */}
                                    <td style={{ padding: '0.875rem 1rem', fontSize: '0.875rem', color: 'var(--text-muted)', fontVariantNumeric: 'tabular-nums' }}>
                                        (${fmt(n.total_egresos)})
                                    </td>

                                    {/* Neto */}
                                    <td style={{ padding: '0.875rem 1rem', fontWeight: 700, fontSize: '0.9rem', color: '#16a34a', fontVariantNumeric: 'tabular-nums' }}>
                                        ${fmt(n.total_neto)}
                                    </td>

                                    {/* Acciones */}
                                    <td style={{ padding: '0.875rem 1rem', textAlign: 'center' }}>
                                        <div className="flex items-center justify-center gap-1.5">
                                            <button
                                                onClick={() => router.visit(route('rrhh.nomina.show', n.id))}
                                                className="btn-secondary flex items-center gap-1 whitespace-nowrap"
                                                style={{ padding: '0.3rem 0.6rem', fontSize: '0.78rem' }}
                                                title="Ver detalle"
                                            >
                                                <Eye size={13} /> Ver
                                            </button>

                                            {(n.estado === 'procesado' || n.estado === 'pagado') && (
                                                <button
                                                    onClick={() => router.post(route('rrhh.nomina.pdf-masivo', n.id), {}, { preserveScroll: true, preserveState: true })}
                                                    className="btn-secondary flex items-center gap-1 whitespace-nowrap"
                                                    style={{ padding: '0.3rem 0.6rem', fontSize: '0.78rem' }}
                                                    title="Generar ZIP con todos los roles (en segundo plano)"
                                                >
                                                    <Archive size={13} /> ZIP
                                                </button>
                                            )}

                                            {n.estado === 'borrador' && puede('eliminar') && (
                                                <button
                                                    onClick={() => eliminar(n)}
                                                    className="flex items-center transition-colors hover:bg-red-50"
                                                    style={{ padding: '0.3rem 0.5rem', border: '1px solid var(--border)', borderRadius: '6px', background: 'none', cursor: 'pointer', color: '#dc2626' }}
                                                    title="Eliminar"
                                                >
                                                    <Trash2 size={13} />
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
                {nominas.last_page > 1 && (
                    <div className={cn('flex', 'justify-between', 'items-center', 'mt-4')}>
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {nominas.from}–{nominas.to} de {nominas.total} nóminas
                        </span>
                        <div className="flex gap-1">
                            <button
                                disabled={nominas.current_page === 1}
                                onClick={() => router.visit(nominas.prev_page_url ?? '')}
                                className="disabled:opacity-40 p-1.5 border rounded"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                            >
                                <ChevronLeft className="w-4 h-4" />
                            </button>
                            <button
                                disabled={nominas.current_page === nominas.last_page}
                                onClick={() => router.visit(nominas.next_page_url ?? '')}
                                className="disabled:opacity-40 p-1.5 border rounded"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                            >
                                <ChevronRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}
                </div>
                )}

            </div>
        </AppLayout>
    )
}
