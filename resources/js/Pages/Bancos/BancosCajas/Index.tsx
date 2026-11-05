import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, ToggleLeft, ToggleRight, X, Trash2,
    Landmark, Wallet, CreditCard, PiggyBank, TrendingUp,
} from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { BancoCaja, CentroCosto, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Props extends PageProps {
    bancos: (BancoCaja & { cuenta: string | null; centro_costo: string | null })[]
    centrosCosto: Pick<CentroCosto, 'id' | 'nombre' | 'codigo'>[]
    reglasBanco: { patron: string; tipo_cuenta: string | null; etiqueta: string; cuenta: string | null }[]
    cuentasFijas: Record<string, string | null>
    tiposRequierenCentroCosto: string[]
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

function BancoCard({ banco, onEdit, onToggle, onDelete, puedeEditar, puedeEliminar: puedeEliminarPermiso }: {
    banco: BancoCaja & { cuenta: string | null; centro_costo: string | null }
    onEdit: () => void
    onToggle: () => void
    onDelete: () => void
    puedeEditar: boolean
    puedeEliminar: boolean
}) {
    const Icon = tipoIcon[banco.tipo] ?? Landmark
    const saldoCero = Math.abs(Number(banco.saldo_actual)) <= 0.01
    const requiereCentroCosto = banco.tipo === 'caja' || banco.tipo === 'caja_chica'

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
                <div className="flex items-center gap-1.5 shrink-0">
                    {puedeEditar && (
                        <button onClick={onEdit} title="Editar"
                            className="p-1.5 rounded hover:bg-blue-500/20 text-blue-500 transition-colors">
                            <Pencil className="w-4 h-4" />
                        </button>
                    )}
                    {puedeEditar && (
                        <button onClick={onToggle} title={banco.estado ? 'Desactivar' : 'Activar'}
                            className={cn('p-1.5 rounded transition-colors',
                                banco.estado ? 'hover:bg-red-500/20 text-red-500' : 'hover:bg-green-500/20 text-green-600')}>
                            {banco.estado ? <ToggleRight className="w-4 h-4" /> : <ToggleLeft className="w-4 h-4" />}
                        </button>
                    )}
                    {puedeEliminarPermiso && (
                        <button
                            onClick={saldoCero ? onDelete : undefined}
                            title={saldoCero ? 'Eliminar permanentemente' : `Tiene saldo de ${fmt(banco.saldo_actual)}. Solo puedes inactivarlo`}
                            disabled={!saldoCero}
                            className={cn('p-1.5 rounded transition-colors',
                                saldoCero ? 'hover:bg-red-500/20 text-red-400 cursor-pointer' : 'cursor-not-allowed')}
                            style={saldoCero ? undefined : { color: 'var(--text-muted)', opacity: 0.4 }}>
                            <Trash2 className="w-4 h-4" />
                        </button>
                    )}
                </div>
            </div>

            {banco.num_cuenta && (
                <p className="font-mono text-xs px-2 py-1 rounded-md"
                    style={{ background: 'var(--bg-main)', color: 'var(--text-muted)' }}>
                    {banco.num_cuenta} {banco.tipo_cuenta && `· ${banco.tipo_cuenta}`}
                </p>
            )}

            {requiereCentroCosto && (
                banco.centro_costo
                    ? <p className="text-xs px-2 py-1 rounded-md w-fit"
                        style={{ background: 'color-mix(in srgb, var(--primary) 12%, transparent)', color: 'var(--primary)' }}>
                        🏢 {banco.centro_costo}
                    </p>
                    : <p className="text-xs px-2 py-1 rounded-md w-fit font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                        ⚠ Centro de costo sin asignar
                    </p>
            )}

            <div className="flex items-end justify-between border-t pt-3 mt-auto" style={{ borderColor: 'var(--border)' }}>
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

// ─── Modal Banco/Caja ─────────────────────────────────────────────────────────

interface ModalProps {
    banco?: BancoCaja & { cuenta: string | null; centro_costo: string | null }
    centrosCosto: Props['centrosCosto']
    reglasBanco: Props['reglasBanco']
    cuentasFijas: Props['cuentasFijas']
    tiposRequierenCentroCosto: Props['tiposRequierenCentroCosto']
    onClose: () => void
}

function BancoModal({ banco, centrosCosto, reglasBanco, cuentasFijas, tiposRequierenCentroCosto, onClose }: ModalProps) {
    const isEdit = !!banco

    const initialTipo = banco?.tipo ?? 'banco'

    const { data, setData, post, put, processing, errors } = useForm({
        tipo:             initialTipo,
        nombre:           banco?.nombre ?? '',
        num_cuenta:       banco?.num_cuenta ?? '',
        tipo_cuenta:      banco?.tipo_cuenta ?? '',
        centro_costo_id:  (banco?.centro_costo_id ?? '') as string | number,
        saldo_inicial:    banco?.saldo_inicial ?? 0,
    })

    const requiereCentroCosto = tiposRequierenCentroCosto.includes(data.tipo)

    function handleTipoChange(tipo: string) {
        setData('tipo', tipo as typeof data.tipo)
        if (!tiposRequierenCentroCosto.includes(tipo)) {
            setData('centro_costo_id', '')
        }
    }

    // La cuenta contable se asigna sola según el tipo y, en bancos, el nombre y el
    // tipo de cuenta (02-06 §2.1). No se edita.
    const cuentaAuto: { cuenta: string | null; error: string | null } = (() => {
        if (data.tipo !== 'banco') {
            const c = cuentasFijas[data.tipo] ?? null
            return { cuenta: c, error: c ? null : 'No hay cuenta contable configurada para este tipo.' }
        }
        const nombre = data.nombre.toLowerCase()
        const regla = reglasBanco.find(r => nombre.includes(r.patron))
        if (!regla) {
            return { cuenta: null, error: 'Este banco no tiene cuenta contable definida: no se podrá guardar. Admitidos: ' + reglasBanco.map(r => r.etiqueta).join(', ') + '.' }
        }
        if (regla.tipo_cuenta && regla.tipo_cuenta !== data.tipo_cuenta) {
            return { cuenta: null, error: `${regla.etiqueta}: seleccione el tipo de cuenta ${regla.tipo_cuenta}.` }
        }
        return { cuenta: regla.cuenta, error: regla.cuenta ? null : 'El parámetro de este banco no está configurado.' }
    })()

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

    return (
        <>
            <div className="modal-overlay" onClick={onClose}>
                <div className="modal-card max-w-xl" onClick={e => e.stopPropagation()}>

                    <div className="modal-header">
                        <h2>{isEdit ? 'Editar banco/caja' : 'Nuevo banco/caja'}</h2>
                        <button className="modal-close" onClick={onClose}>
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <form onSubmit={submit}>
                    <div className="modal-body" style={{ gap: '1rem' }}>
                        {!isEdit && (
                            <div className="space-y-1.5">
                                <Label>Tipo <span className="text-red-400">*</span></Label>
                                <div className="grid grid-cols-2 gap-2">
                                    {(['banco', 'caja', 'caja_chica', 'tarjeta'] as const).map(t => (
                                        <button key={t} type="button"
                                            onClick={() => handleTipoChange(t)}
                                            className={cn(
                                                'py-2 px-3 rounded-lg text-sm font-medium border transition-colors',
                                                data.tipo === t ? 'text-black border-transparent' : 'hover:opacity-80'
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
                                            className="input-field select-field">
                                            <option value="">— Seleccionar —</option>
                                            <option value="corriente">Corriente</option>
                                            <option value="ahorros">Ahorros</option>
                                        </select>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Cuenta contable: automática, no editable */}
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <Label>Cuenta contable</Label>
                                <span className="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                    style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)', color: 'var(--primary)' }}>
                                    Automática
                                </span>
                            </div>
                            <div className="w-full rounded-lg px-3 py-2 text-sm border"
                                style={{
                                    borderColor: 'var(--border)',
                                    background: 'var(--bg-main)',
                                    color: cuentaAuto.cuenta ? 'var(--text-main)' : 'var(--text-muted)',
                                }}>
                                {cuentaAuto.cuenta ?? '—'}
                            </div>
                            {cuentaAuto.error && (
                                <p className="text-xs px-2.5 py-1.5 rounded-md font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                    ⚠ {cuentaAuto.error}
                                </p>
                            )}
                        </div>

                        {requiereCentroCosto && (
                            <div className="space-y-1.5">
                                <Label>Centro de Costo <span className="text-red-400">*</span></Label>
                                <select value={data.centro_costo_id} onChange={e => setData('centro_costo_id', e.target.value)}
                                    required className="input-field select-field">
                                    <option value="">— Seleccionar —</option>
                                    {centrosCosto.map(c => (
                                        <option key={c.id} value={c.id}>{c.nombre}</option>
                                    ))}
                                </select>
                                {errors.centro_costo_id && <p className="text-red-400 text-xs">{errors.centro_costo_id}</p>}
                            </div>
                        )}

                        {!isEdit && (
                            <div className="space-y-1.5">
                                <Label>Saldo inicial</Label>
                                <Input type="number" step="0.01" min="0" value={data.saldo_inicial}
                                    onChange={e => setData('saldo_inicial', Number(e.target.value))} />
                            </div>
                        )}

                    </div>
                    <div className="modal-footer">
                        <button type="submit" disabled={processing} className="btn-primary">
                            <Plus className="w-4 h-4" />
                            {isEdit ? 'Guardar cambios' : 'Crear'}
                        </button>
                        <button type="button" onClick={onClose} className="btn-secondary">
                            Cancelar
                        </button>
                    </div>
                    </form>
                </div>
            </div>

        </>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function BancosCajasIndex() {
    const { bancos, centrosCosto, reglasBanco, cuentasFijas, tiposRequierenCentroCosto, stats, flash } = usePage<Props>().props
    const { puede } = usePermiso('bancos')
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

            <PageHeader
                title="Bancos y Cajas"
                breadcrumbs={[{ label: 'Bancos' }, { label: 'Bancos y Cajas' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setModal({ open: true })}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-black whitespace-nowrap transition-all hover:opacity-90 hover:-translate-y-0.5 shrink-0"
                            style={{ background: 'var(--primary)' }}>
                            <Plus size={15} /> Nuevo
                        </button>
                    ) : undefined
                }
            />

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
                                        onDelete={() => confirmarEliminar(b)}
                                        puedeEditar={puede('editar')}
                                        puedeEliminar={puede('eliminar')} />
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
                <BancoModal banco={modal.banco} centrosCosto={centrosCosto}
                    reglasBanco={reglasBanco} cuentasFijas={cuentasFijas} tiposRequierenCentroCosto={tiposRequierenCentroCosto}
                    onClose={() => setModal({ open: false })} />
            )}


            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
