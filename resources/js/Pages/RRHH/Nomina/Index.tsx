import { useState } from 'react'
import { Head, router, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import type { Nomina, PageProps, PaginatedData } from '@/types'
import { cn } from '@/lib/utils'
import { Plus, FileText, ChevronLeft, ChevronRight, Trash2 } from 'lucide-react'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const MESES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

function badgeEstado(estado: Nomina['estado']) {
    const map = {
        borrador:  { label: 'Borrador',  cls: 'bg-gray-100 text-gray-600 border-gray-300' },
        procesado: { label: 'Procesado', cls: 'bg-blue-50 text-blue-700 border-blue-200' },
        pagado:    { label: 'Pagado',    cls: 'bg-green-50 text-green-700 border-green-200' },
    } as const
    const b = map[estado] ?? map.borrador
    return (
        <span className={cn(
            'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border',
            b.cls
        )}>
            {b.label}
        </span>
    )
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
                        {/* Tipo */}
                        <div>
                            <label className="input-label">Tipo de nómina</label>
                            <div className="flex gap-4 mt-1">
                                {(['mensual', 'quincenal'] as const).map(t => (
                                    <label key={t} className="flex items-center gap-2 cursor-pointer text-sm" style={{ color: 'var(--text-main)' }}>
                                        <input
                                            type="radio"
                                            name="periodo_tipo"
                                            value={t}
                                            checked={form.periodo_tipo === t}
                                            onChange={() => set('periodo_tipo', t)}
                                            className="accent-[var(--primary)]"
                                        />
                                        {t === 'mensual' ? 'Mensual' : 'Quincenal'}
                                    </label>
                                ))}
                            </div>
                        </div>

                        {/* Quincena */}
                        {form.periodo_tipo === 'quincenal' && (
                            <div>
                                <label className="input-label">Quincena</label>
                                <select
                                    className="input-field"
                                    value={form.quincena}
                                    onChange={e => set('quincena', e.target.value)}
                                    required
                                >
                                    <option value="">Seleccionar…</option>
                                    <option value="1">1ª quincena (días 1–15)</option>
                                    <option value="2">2ª quincena (días 16–fin)</option>
                                </select>
                            </div>
                        )}

                        {/* Año */}
                        <div>
                            <label className="input-label">Año</label>
                            <select
                                className="input-field"
                                value={form.anio}
                                onChange={e => set('anio', parseInt(e.target.value))}
                            >
                                {[0, 1, 2, 3].map(i => {
                                    const y = new Date().getFullYear() - i
                                    return <option key={y} value={y}>{y}</option>
                                })}
                            </select>
                        </div>

                        {/* Mes */}
                        <div>
                            <label className="input-label">Mes</label>
                            <select
                                className="input-field"
                                value={form.mes}
                                onChange={e => set('mes', parseInt(e.target.value))}
                            >
                                {MESES.slice(1).map((m, i) => (
                                    <option key={i + 1} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn-secondary" onClick={onClose}>
                            Cancelar
                        </button>
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
    nominas: PaginatedData<Nomina>
    filtros: { anio?: string; mes?: string; tipo?: string; estado?: string }
    anios: number[]
}

export default function NominaIndex() {
    const { nominas, filtros, anios, flash } = usePage<Props>().props

    const [showGenerar, setShowGenerar] = useState(false)
    const [filtro, setFiltro] = useState({
        anio:   filtros.anio   ?? '',
        mes:    filtros.mes    ?? '',
        tipo:   filtros.tipo   ?? '',
        estado: filtros.estado ?? '',
    })

    function aplicarFiltros() {
        router.get(route('rrhh.nomina.index'), filtro, { preserveState: true })
    }

    function limpiarFiltros() {
        const vacio = { anio: '', mes: '', tipo: '', estado: '' }
        setFiltro(vacio)
        router.get(route('rrhh.nomina.index'), {})
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

    return (
        <AppLayout>
            <Head title="Nómina — RRHH" />

            {showGenerar && <GenerarModal onClose={() => setShowGenerar(false)} />}

            <PageHeader
                title="Nómina"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Nómina' }]}
            />

            <div className="px-6 py-4">
                {/* Flash */}
                {flash?.success && (
                    <div className="mb-4 px-4 py-2 rounded text-sm font-medium bg-green-50 text-green-700 border border-green-200">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="mb-4 px-4 py-2 rounded text-sm font-medium bg-red-50 text-red-700 border border-red-200">
                        {flash.error}
                    </div>
                )}

                {/* Barra de filtros y acciones */}
                <div className="flex flex-wrap items-end gap-3 mb-5">
                    {/* Botón generar */}
                    <button className="btn-primary flex items-center gap-1.5" onClick={() => setShowGenerar(true)}>
                        <Plus className="w-4 h-4" />
                        Generar Nómina
                    </button>

                    {/* Filtros */}
                    <select
                        className="input-field h-9 text-sm"
                        value={filtro.anio}
                        onChange={e => setFiltro(f => ({ ...f, anio: e.target.value }))}
                    >
                        <option value="">Todos los años</option>
                        {anios.map(y => <option key={y} value={y}>{y}</option>)}
                    </select>

                    <select
                        className="input-field h-9 text-sm"
                        value={filtro.mes}
                        onChange={e => setFiltro(f => ({ ...f, mes: e.target.value }))}
                    >
                        <option value="">Todos los meses</option>
                        {MESES.slice(1).map((m, i) => (
                            <option key={i + 1} value={i + 1}>{m}</option>
                        ))}
                    </select>

                    <select
                        className="input-field h-9 text-sm"
                        value={filtro.tipo}
                        onChange={e => setFiltro(f => ({ ...f, tipo: e.target.value }))}
                    >
                        <option value="">Tipo</option>
                        <option value="mensual">Mensual</option>
                        <option value="quincenal">Quincenal</option>
                    </select>

                    <select
                        className="input-field h-9 text-sm"
                        value={filtro.estado}
                        onChange={e => setFiltro(f => ({ ...f, estado: e.target.value }))}
                    >
                        <option value="">Estado</option>
                        <option value="borrador">Borrador</option>
                        <option value="procesado">Procesado</option>
                        <option value="pagado">Pagado</option>
                    </select>

                    <button className="btn-secondary h-9 text-sm" onClick={aplicarFiltros}>
                        Filtrar
                    </button>
                    {(filtro.anio || filtro.mes || filtro.tipo || filtro.estado) && (
                        <button className="h-9 text-sm px-3 rounded" style={{ color: 'var(--text-muted)' }} onClick={limpiarFiltros}>
                            Limpiar
                        </button>
                    )}
                </div>

                {/* Tabla */}
                <div className="rounded-lg border overflow-hidden" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <table className="w-full text-sm">
                        <thead>
                            <tr style={{ background: 'var(--bg-main)', borderBottom: '1px solid var(--border)' }}>
                                {['Período', 'Tipo', 'Estado', 'Empleados', 'Total Ingresos', 'Total Egresos', 'Neto', 'Acciones'].map(h => (
                                    <th key={h} className="px-4 py-3 text-left font-semibold text-xs uppercase tracking-wide" style={{ color: 'var(--text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {nominas.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-12 text-center text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay nóminas registradas. Genere la primera.
                                    </td>
                                </tr>
                            ) : nominas.data.map((n, idx) => (
                                <tr
                                    key={n.id}
                                    className="border-t transition-colors"
                                    style={{
                                        borderColor: 'var(--border)',
                                        background: idx % 2 === 0 ? 'var(--bg-card)' : 'var(--bg-main)',
                                    }}
                                >
                                    <td className="px-4 py-3 font-medium" style={{ color: 'var(--text-main)' }}>
                                        {periodoLabel(n)}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-muted)' }}>
                                        {n.periodo_tipo === 'mensual' ? 'Mensual' : 'Quincenal'}
                                    </td>
                                    <td className="px-4 py-3">{badgeEstado(n.estado)}</td>
                                    <td className="px-4 py-3 text-center" style={{ color: 'var(--text-muted)' }}>
                                        {n.detalles_count ?? 0}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs" style={{ color: 'var(--text-main)' }}>
                                        $ {fmt(n.total_ingresos)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                        $ {fmt(n.total_egresos)}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-xs font-semibold" style={{ color: 'var(--text-main)' }}>
                                        $ {fmt(n.total_neto)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-1">
                                            {/* Ver detalle */}
                                            <button
                                                onClick={() => router.visit(route('rrhh.nomina.show', n.id))}
                                                className="p-1.5 rounded hover:bg-blue-50 transition-colors"
                                                title="Ver detalle"
                                            >
                                                <FileText className="w-4 h-4 text-blue-600" />
                                            </button>
                                            {/* Eliminar solo borrador */}
                                            {n.estado === 'borrador' && (
                                                <button
                                                    onClick={() => eliminar(n)}
                                                    className="p-1.5 rounded hover:bg-red-50 transition-colors"
                                                    title="Eliminar"
                                                >
                                                    <Trash2 className="w-4 h-4 text-red-500" />
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
                    <div className="flex items-center justify-between mt-4">
                        <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            {nominas.from}–{nominas.to} de {nominas.total} nóminas
                        </span>
                        <div className="flex gap-1">
                            <button
                                disabled={nominas.current_page === 1}
                                onClick={() => router.visit(nominas.prev_page_url ?? '')}
                                className="p-1.5 rounded border disabled:opacity-40"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                            >
                                <ChevronLeft className="w-4 h-4" />
                            </button>
                            <button
                                disabled={nominas.current_page === nominas.last_page}
                                onClick={() => router.visit(nominas.next_page_url ?? '')}
                                className="p-1.5 rounded border disabled:opacity-40"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                            >
                                <ChevronRight className="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
