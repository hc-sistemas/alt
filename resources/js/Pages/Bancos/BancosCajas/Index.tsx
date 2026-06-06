import { useState, useMemo, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, ToggleLeft, ToggleRight, X, Trash2,
    Landmark, Wallet, CreditCard, PiggyBank, TrendingUp, Search,
} from 'lucide-react'
import type { BancoCaja, PlanCuenta, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Props extends PageProps {
    bancos: (BancoCaja & { cuenta: string | null })[]
    cuentas: Pick<PlanCuenta, 'id' | 'codigo' | 'nombre'>[]
    stats: { total_bancos: number; total_cajas: number; saldo_bancos: number; saldo_cajas: number }
}

// ─── Notify / Swal ───────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    edit:  (msg: string) => toast.success(msg, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '⚠️', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

const SWAL_CSS = `.swal-pop{border-radius:20px!important;padding:28px!important;box-shadow:0 25px 60px rgba(0,0,0,.25)!important;max-width:480px!important}.swal-title{font-size:1.1rem!important;font-weight:700!important;margin-bottom:16px!important}.swal-confirm,.swal-cancel{border-radius:10px!important;padding:10px 20px!important;font-weight:600!important}`
function injectSwalCss() {
    if (document.getElementById('swal-bancos')) return
    const s = document.createElement('style'); s.id = 'swal-bancos'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const tipoIcon: Record<string, React.ElementType> = {
    banco: Landmark, caja: Wallet, caja_chica: PiggyBank, tarjeta: CreditCard,
}
const tipoBg: Record<string, string> = {
    banco: 'bg-blue-500/10', caja: 'bg-green-500/10',
    caja_chica: 'bg-yellow-500/10', tarjeta: 'bg-purple-500/10',
}

function fmt(n: number) { return '$' + Number(n).toLocaleString('es-EC', { minimumFractionDigits: 2 }) }

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: string | number; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div><p className={cn('text-2xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{label}</p></div>
        </div>
    )
}

// ─── BancoCajaCard ────────────────────────────────────────────────────────────

function BancoCard({ banco, onEdit, onToggle, onDelete }: {
    banco: BancoCaja & { cuenta: string | null }
    onEdit: () => void
    onToggle: () => void
    onDelete: () => void
}) {
    const Icon = tipoIcon[banco.tipo] ?? Landmark
    const saldoCero = Math.abs(Number(banco.saldo_actual)) <= 0.01

    return (
        <div className={cn(
            'rounded-xl border p-4 flex flex-col gap-3 transition-all hover:shadow-lg',
            !banco.estado && 'opacity-50'
        )} style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className="flex items-start justify-between">
                <div className="flex items-center gap-2.5">
                    <div className={cn('p-2 rounded-lg', tipoBg[banco.tipo])}>
                        <Icon className="w-5 h-5" />
                    </div>
                    <div>
                        <p className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>{banco.nombre}</p>
                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{banco.tipo_label}</p>
                    </div>
                </div>
                <div className="flex gap-1">
                    <button onClick={onEdit} title="Editar"
                        className="p-1.5 rounded hover:bg-blue-500/20 text-blue-500 transition-colors">
                        <Pencil className="w-3.5 h-3.5" />
                    </button>
                    <button onClick={onToggle} title={banco.estado ? 'Desactivar' : 'Activar'}
                        className={cn('p-1.5 rounded transition-colors',
                            banco.estado ? 'hover:bg-red-500/20 text-red-500' : 'hover:bg-green-500/20 text-green-600')}>
                        {banco.estado ? <ToggleRight className="w-3.5 h-3.5" /> : <ToggleLeft className="w-3.5 h-3.5" />}
                    </button>
                    <button
                        onClick={saldoCero ? onDelete : undefined}
                        title={saldoCero ? 'Eliminar permanentemente' : `Tiene saldo de ${fmt(banco.saldo_actual)}. Solo puedes inactivarlo`}
                        disabled={!saldoCero}
                        className={cn(
                            'p-1.5 rounded transition-colors',
                            saldoCero
                                ? 'hover:bg-red-500/20 text-red-400 cursor-pointer'
                                : 'opacity-25 cursor-not-allowed text-gray-400'
                        )}>
                        <Trash2 className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            {banco.num_cuenta && (
                <p className="font-mono text-xs px-2 py-1 rounded-md"
                    style={{ background: 'rgba(0,0,0,0.05)', color: 'var(--text-muted)' }}>
                    {banco.num_cuenta} {banco.tipo_cuenta && `· ${banco.tipo_cuenta}`}
                </p>
            )}

            <div className="flex items-end justify-between border-t pt-3" style={{ borderColor: 'var(--border)' }}>
                <div>
                    <p className="text-xs" style={{ color: 'var(--text-muted)' }}>Saldo actual</p>
                    <p className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>{fmt(banco.saldo_actual)}</p>
                </div>
                {banco.estado
                    ? <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Activo</span>
                    : <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Inactivo</span>
                }
            </div>
        </div>
    )
}

// ─── Modal búsqueda cuenta ────────────────────────────────────────────────────

function CuentaSearchModal({ cuentas, onSelect, onClose }: {
    cuentas: Props['cuentas']
    onSelect: (c: Props['cuentas'][0] | null) => void
    onClose: () => void
}) {
    const [busq, setBusq] = useState('')

    const filtradas = useMemo(() => {
        const q = busq.toLowerCase().trim()
        if (!q) return cuentas.slice(0, 30)
        return cuentas.filter(c =>
            c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q)
        ).slice(0, 25)
    }, [busq, cuentas])

    return (
        <div className="fixed inset-0 z-60 flex items-center justify-center p-4"
            style={{ background: 'rgba(0,0,0,0.6)' }}
            onClick={onClose}>
            <div className="w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden"
                style={{ background: 'var(--bg-card)' }}
                onClick={e => e.stopPropagation()}>

                <div className="flex items-center justify-between px-4 py-3 border-b"
                    style={{ borderColor: 'var(--border)' }}>
                    <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                        Seleccionar cuenta contable
                    </h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
                </div>

                <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--border)' }}>
                    <div className="relative">
                        <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2"
                            style={{ color: 'var(--text-muted)' }} />
                        <input autoFocus type="text" value={busq}
                            onChange={e => setBusq(e.target.value)}
                            placeholder="Buscar por código o nombre…"
                            className="w-full pl-9 pr-3 py-2 text-sm rounded-lg border focus:outline-none focus:ring-1 focus:ring-amber-500"
                            style={{ borderColor: 'var(--border)', background: 'var(--bg-main)', color: 'var(--text-main)' }} />
                    </div>
                </div>

                <div className="overflow-y-auto" style={{ maxHeight: '360px' }}>
                    <button onClick={() => { onSelect(null); onClose() }}
                        className="w-full text-left px-4 py-2.5 text-sm border-b transition-colors hover:opacity-80"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                        — Sin cuenta contable —
                    </button>

                    {filtradas.map(c => (
                        <button key={c.id} onClick={() => { onSelect(c); onClose() }}
                            className="w-full text-left px-4 py-2.5 text-sm border-b flex items-center gap-3 transition-colors"
                            style={{ borderColor: 'var(--border)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.08)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                            <span className="font-mono font-bold text-xs shrink-0" style={{ color: 'var(--primary)' }}>
                                {c.codigo}
                            </span>
                            <span style={{ color: 'var(--text-main)' }}>{c.nombre}</span>
                        </button>
                    ))}

                    {filtradas.length === 0 && (
                        <div className="px-4 py-6 text-center text-sm" style={{ color: 'var(--text-muted)' }}>
                            Sin resultados para "{busq}"
                        </div>
                    )}
                </div>

                <div className="px-4 py-2 border-t text-xs" style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                    {filtradas.length} resultado(s)
                </div>
            </div>
        </div>
    )
}

// ─── Modal Banco/Caja ─────────────────────────────────────────────────────────

interface ModalProps {
    banco?: BancoCaja & { cuenta: string | null }
    cuentas: Props['cuentas']
    onClose: () => void
}

function BancoModal({ banco, cuentas, onClose }: ModalProps) {
    const isEdit = !!banco
    const [showCuentaModal, setShowCuentaModal] = useState(false)
    const [cuentaSeleccionada, setCuentaSeleccionada] = useState<Props['cuentas'][0] | null>(
        banco?.cuenta_id ? (cuentas.find(c => c.id === Number(banco.cuenta_id)) ?? null) : null
    )

    const { data, setData, post, put, processing, errors } = useForm({
        tipo:          banco?.tipo ?? 'banco',
        nombre:        banco?.nombre ?? '',
        num_cuenta:    banco?.num_cuenta ?? '',
        tipo_cuenta:   banco?.tipo_cuenta ?? '',
        cuenta_id:     banco?.cuenta_id ?? '',
        saldo_inicial: banco?.saldo_inicial ?? 0,
    })

    function handleCuentaSelect(c: Props['cuentas'][0] | null) {
        setCuentaSeleccionada(c)
        setData('cuenta_id', c ? (c.id as any) : '')
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (isEdit) {
            put(route('bancos.catalogo.update', banco!.id), {
                onSuccess: () => { notify.edit('Actualizado correctamente'); onClose() },
                onError: () => notify.error('Error al actualizar'),
            })
        } else {
            post(route('bancos.catalogo.store'), {
                onSuccess: () => { notify.ok('Creado correctamente'); onClose() },
                onError: () => notify.error('Error al crear'),
            })
        }
    }

    const inp = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <>
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
                <div className="relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl"
                    style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                    <div className="sticky top-0 z-10 flex items-center justify-between px-6 pt-5 pb-4"
                        style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                        <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                            {isEdit ? 'Editar banco/caja' : 'Nuevo banco/caja'}
                        </h2>
                        <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70"
                            style={{ color: 'var(--text-muted)' }}><X className="w-4 h-4" /></button>
                    </div>

                    <form onSubmit={submit} className="p-6 space-y-4">
                        {!isEdit && (
                            <div className="space-y-1.5">
                                <Label>Tipo <span className="text-red-400">*</span></Label>
                                <div className="grid grid-cols-2 gap-2">
                                    {(['banco', 'caja', 'caja_chica', 'tarjeta'] as const).map(t => (
                                        <button key={t} type="button"
                                            onClick={() => setData('tipo', t)}
                                            className={cn(
                                                'py-2 px-3 rounded-lg text-sm font-medium border transition-colors',
                                                data.tipo === t ? 'text-white border-transparent' : 'hover:opacity-80'
                                            )}
                                            style={data.tipo === t
                                                ? { background: 'var(--primary)' }
                                                : { color: 'var(--text-muted)', borderColor: 'var(--border)' }}>
                                            {{ banco: 'Banco', caja: 'Caja', caja_chica: 'Caja Chica', tarjeta: 'Tarjeta' }[t]}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <Label>Nombre <span className="text-red-400">*</span></Label>
                            <Input value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                                error={errors.nombre} placeholder="Ej: Banco Pichincha Cta. Cte." />
                            {errors.nombre && <p className="text-red-400 text-xs">{errors.nombre}</p>}
                        </div>

                        {(data.tipo === 'banco' || data.tipo === 'tarjeta') && (
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label>N° Cuenta</Label>
                                    <Input value={data.num_cuenta ?? ''} onChange={e => setData('num_cuenta', e.target.value)}
                                        placeholder="Número de cuenta" />
                                </div>
                                {data.tipo === 'banco' && (
                                    <div className="space-y-1.5">
                                        <Label>Tipo Cuenta</Label>
                                        <select value={data.tipo_cuenta ?? ''} onChange={e => setData('tipo_cuenta', e.target.value)}
                                            className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                                            style={inp}>
                                            <option value="">— Seleccionar —</option>
                                            <option value="corriente">Corriente</option>
                                            <option value="ahorros">Ahorros</option>
                                        </select>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Cuenta contable — búsqueda modal */}
                        <div className="space-y-1.5">
                            <Label>Cuenta contable</Label>
                            <button type="button" onClick={() => setShowCuentaModal(true)}
                                className="w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm border transition-colors hover:opacity-80 text-left"
                                style={{
                                    borderColor: 'var(--border)',
                                    background: 'var(--bg-main)',
                                    color: cuentaSeleccionada ? 'var(--text-main)' : 'var(--text-muted)',
                                }}>
                                <span className="truncate">
                                    {cuentaSeleccionada
                                        ? `${cuentaSeleccionada.codigo} — ${cuentaSeleccionada.nombre}`
                                        : '— Seleccionar cuenta —'}
                                </span>
                                <Search size={14} className="shrink-0 ml-2" style={{ color: 'var(--text-muted)' }} />
                            </button>
                            {cuentaSeleccionada && (
                                <button type="button" onClick={() => handleCuentaSelect(null)}
                                    className="text-xs hover:opacity-70 flex items-center gap-1"
                                    style={{ color: 'var(--text-muted)' }}>
                                    <X size={11} /> Quitar cuenta
                                </button>
                            )}
                        </div>

                        {!isEdit && (
                            <div className="space-y-1.5">
                                <Label>Saldo inicial</Label>
                                <Input type="number" step="0.01" min="0" value={data.saldo_inicial}
                                    onChange={e => setData('saldo_inicial', Number(e.target.value))} />
                            </div>
                        )}

                        <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                            <button type="submit" disabled={processing}
                                className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50"
                                style={{ background: 'var(--primary)' }}>
                                <Plus className="w-4 h-4" />
                                {isEdit ? 'Guardar cambios' : 'Crear'}
                            </button>
                            <button type="button" onClick={onClose}
                                className="px-4 py-2 rounded-lg text-sm font-medium border hover:opacity-80"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {showCuentaModal && (
                <CuentaSearchModal
                    cuentas={cuentas}
                    onSelect={handleCuentaSelect}
                    onClose={() => setShowCuentaModal(false)}
                />
            )}
        </>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function BancosCajasIndex() {
    const { bancos, cuentas, stats, flash } = usePage<Props>().props
    const [modal, setModal] = useState<{ open: boolean; banco?: Props['bancos'][0] }>({ open: false })

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) notify.warn(flash.warning as string)
    }, [flash?.success, flash?.error])

    async function confirmarToggle(b: Props['bancos'][0]) {
        if (b.estado && b.saldo_actual !== 0) {
            notify.error(`No se puede desactivar: tiene saldo de ${fmt(b.saldo_actual)}.`)
            return
        }
        const result = await Swal.fire({
            ...swalBase,
            title: b.estado ? 'Desactivar' : 'Activar',
            html: `<div style="text-align:center;padding:8px 0">
                <div style="font-size:3rem;margin-bottom:12px">${b.estado ? '🔴' : '🟢'}</div>
                <p style="color:#374151">¿Confirmar <strong>${b.estado ? 'desactivar' : 'activar'}</strong>?</p>
                <div style="margin-top:12px;background:#f3f4f6;border-radius:8px;padding:10px 14px;font-weight:700;color:#F59E0B">${b.nombre}</div>
            </div>`,
            icon: b.estado ? 'warning' : 'question',
            confirmButtonColor: b.estado ? '#f59e0b' : '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: b.estado ? 'Desactivar' : 'Activar',
            cancelButtonText: 'Cancelar',
        })
        if (result.isConfirmed) {
            router.patch(route('bancos.catalogo.toggle', b.id), {}, {
                onSuccess: () => b.estado ? notify.warn(`${b.nombre} desactivado`) : notify.ok(`${b.nombre} activado`),
                onError: () => notify.error('Error al cambiar estado'),
            })
        }
    }

    async function confirmarEliminar(b: Props['bancos'][0]) {
        const result = await Swal.fire({
            ...swalBase,
            title: `Eliminar ${b.tipo_label}`,
            html: `
                <div style="text-align:left">
                    <div style="background:#f9fafb;border-left:4px solid #F59E0B;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-weight:700;color:#1f2937;margin:0">${b.nombre}</p>
                        <p style="color:#6b7280;font-size:0.85rem;margin:4px 0 0 0">Saldo actual: $0.00</p>
                    </div>
                    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px">
                        <p style="font-weight:700;color:#dc2626;margin:0 0 4px 0;font-size:0.875rem">
                            ⚠️ Acción permanente e irreversible
                        </p>
                        <p style="color:#ef4444;font-size:0.82rem;margin:0">
                            Este banco/caja será eliminado permanentemente y no se podrá recuperar.
                        </p>
                    </div>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '🗑️ Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
        })

        if (result.isConfirmed) {
            router.delete(route('bancos.catalogo.destroy', b.id), {
                onSuccess: () => notify.ok(`${b.nombre} eliminado permanentemente`),
                onError: () => notify.error('Error al eliminar'),
            })
        }
    }

    const grupos = ['banco', 'caja', 'caja_chica', 'tarjeta']
    const labels: Record<string, string> = { banco: 'Bancos', caja: 'Cajas', caja_chica: 'Cajas Chicas', tarjeta: 'Terminales Tarjeta' }

    return (
        <AppLayout title="Bancos y Cajas" suppressFlash>
            <Head title="Bancos y Cajas" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl" style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <Landmark size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>Bancos y Cajas</h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>Catálogo de cuentas bancarias y cajas</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap mb-6">
                    <button onClick={() => setModal({ open: true })}
                        className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5"
                        style={{ background: 'var(--primary)' }}>
                        <Plus size={15} /> Nuevo Banco/Caja
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 px-6 py-4">
                <StatCard label="Bancos" value={stats.total_bancos} icon={Landmark}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
                <StatCard label="Cajas" value={stats.total_cajas} icon={Wallet}
                    cls="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueCls="text-green-600 dark:text-green-400" />
                <StatCard label="Saldo Bancos" value={fmt(stats.saldo_bancos)} icon={TrendingUp}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
                <StatCard label="Saldo Cajas" value={fmt(stats.saldo_cajas)} icon={PiggyBank}
                    cls="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueCls="text-green-600 dark:text-green-400" />
            </div>

            {/* Cards por grupo */}
            <div className="px-6 pb-8 space-y-6">
                {grupos.map(tipo => {
                    const lista = bancos.filter(b => b.tipo === tipo)
                    if (lista.length === 0) return null
                    return (
                        <div key={tipo}>
                            <h2 className="text-sm font-semibold mb-3 uppercase tracking-wider"
                                style={{ color: 'var(--text-muted)' }}>{labels[tipo]}</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                {lista.map(b => (
                                    <BancoCard key={b.id} banco={b}
                                        onEdit={() => setModal({ open: true, banco: b })}
                                        onToggle={() => confirmarToggle(b)}
                                        onDelete={() => confirmarEliminar(b)} />
                                ))}
                            </div>
                        </div>
                    )
                })}

                {bancos.length === 0 && (
                    <div className="py-20 text-center">
                        <Landmark className="w-12 h-12 opacity-20 mx-auto mb-3" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>No hay bancos ni cajas registradas</p>
                    </div>
                )}
            </div>

            {modal.open && (
                <BancoModal banco={modal.banco} cuentas={cuentas} onClose={() => setModal({ open: false })} />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
