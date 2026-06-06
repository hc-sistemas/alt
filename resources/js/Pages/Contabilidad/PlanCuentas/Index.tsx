import { useState, useEffect, useMemo, useCallback } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    ChevronRight, ChevronDown, Plus, Pencil, Trash2,
    ToggleLeft, ToggleRight, Download, Search, X,
    ChevronsUpDown, ChevronsDownUp, BookOpen,
    TrendingUp, TrendingDown, DollarSign, Shield, BarChart3,
    CheckCircle, AlertCircle,
} from 'lucide-react'
import type { PlanCuenta, PlanCuentaStats, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types & Config ───────────────────────────────────────────────────────────

interface Props extends PageProps {
    cuentas: PlanCuenta[]
    stats: PlanCuentaStats
    todasLasCuentas: PlanCuenta[]
}

const TIPO_CONFIG = {
    activo:     { label: 'Activo',     icon: TrendingUp,   light: 'bg-blue-100 text-blue-800',    dark: 'dark:bg-blue-900/30 dark:text-blue-300',    border: 'border-l-4 border-l-blue-500',   text: 'text-blue-500'   },
    pasivo:     { label: 'Pasivo',     icon: TrendingDown, light: 'bg-red-100 text-red-800',      dark: 'dark:bg-red-900/30 dark:text-red-300',      border: 'border-l-4 border-l-red-500',    text: 'text-red-500'    },
    patrimonio: { label: 'Patrimonio', icon: Shield,       light: 'bg-purple-100 text-purple-800', dark: 'dark:bg-purple-900/30 dark:text-purple-300', border: 'border-l-4 border-l-purple-500', text: 'text-purple-500' },
    ingreso:    { label: 'Ingreso',    icon: DollarSign,   light: 'bg-green-100 text-green-800',  dark: 'dark:bg-green-900/30 dark:text-green-300',  border: 'border-l-4 border-l-green-500',  text: 'text-green-500'  },
    gasto:      { label: 'Gasto',      icon: BarChart3,    light: 'bg-orange-100 text-orange-800', dark: 'dark:bg-orange-900/30 dark:text-orange-300', border: 'border-l-4 border-l-orange-500', text: 'text-orange-500' },
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function flattenTree(items: PlanCuenta[]): PlanCuenta[] {
    return items.reduce<PlanCuenta[]>((acc, c) => {
        acc.push(c)
        if (c.hijos?.length) acc.push(...flattenTree(c.hijos))
        return acc
    }, [])
}

function filterTree(items: PlanCuenta[], q: string): PlanCuenta[] {
    return items.reduce<PlanCuenta[]>((acc, c) => {
        const hijos = filterTree(c.hijos ?? [], q)
        const match = c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q)
        if (match || hijos.length > 0) acc.push({ ...c, hijos })
        return acc
    }, [])
}

function getAllIds(items: PlanCuenta[]): number[] {
    return items.reduce<number[]>((acc, c) => {
        acc.push(c.id)
        if (c.hijos?.length) acc.push(...getAllIds(c.hijos))
        return acc
    }, [])
}

function suggestNextCodigo(padre: PlanCuenta, todos: PlanCuenta[]): string {
    const hijos = todos.filter(c => c.padre_id === padre.id)
    if (hijos.length === 0) return `${padre.codigo}.01`
    const ultimoCodigo = hijos[hijos.length - 1].codigo
    const parts = ultimoCodigo.split('.')
    const lastPart = parts[parts.length - 1] ?? '0'
    const num = (parseInt(lastPart) || 0) + 1
    const padded = lastPart.length >= 2 && lastPart.startsWith('0')
    return padded
        ? `${padre.codigo}.${String(num).padStart(lastPart.length, '0')}`
        : `${padre.codigo}.${num}`
}

function Highlight({ text, query }: { text: string; query: string }) {
    if (!query) return <>{text}</>
    const idx = text.toLowerCase().indexOf(query.toLowerCase())
    if (idx === -1) return <>{text}</>
    return (
        <>
            {text.slice(0, idx)}
            <mark className="bg-amber-300/40 px-0.5 rounded-sm not-italic">
                {text.slice(idx, idx + query.length)}
            </mark>
            {text.slice(idx + query.length)}
        </>
    )
}

// ─── Notify helper ───────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const

const notify = {
    crear:      (msg = 'Cuenta creada exitosamente') =>
        toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    editar:     (msg = 'Cambios guardados correctamente') =>
        toast.success(msg, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    eliminar:   (msg = 'Cuenta eliminada permanentemente') =>
        toast.success(msg, { icon: () => '🗑️', style: { ...S, background: 'linear-gradient(135deg,#6b7280,#4b5563)' } }),
    activar:    (msg = 'Cuenta activada correctamente') =>
        toast.success(msg, { icon: () => '🟢', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    desactivar: (msg = 'Cuenta desactivada correctamente') =>
        toast.info(msg,    { icon: () => '🔴', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error:      (msg: string) =>
        toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── SweetAlert2 config helper ────────────────────────────────────────────────

const SWAL_STYLES = `
    .swal-popup-custom {
        border-radius: 20px !important;
        padding: 28px !important;
        box-shadow: 0 25px 60px rgba(0,0,0,0.25) !important;
        max-width: 440px !important;
    }
    .swal-title-custom {
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        color: #1f2937 !important;
        margin-bottom: 16px !important;
    }
    .swal-confirm-custom {
        border-radius: 10px !important;
        padding: 10px 20px !important;
        font-weight: 600 !important;
        box-shadow: 0 4px 12px rgba(239,68,68,0.35) !important;
        transition: all 0.2s !important;
    }
    .swal-cancel-custom {
        border-radius: 10px !important;
        padding: 10px 20px !important;
        font-weight: 600 !important;
    }
    .swal-confirm-custom:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 16px rgba(239,68,68,0.45) !important;
    }
`

function injectSwalStyles() {
    if (!document.getElementById('swal-custom-styles')) {
        const style = document.createElement('style')
        style.id = 'swal-custom-styles'
        style.textContent = SWAL_STYLES
        document.head.appendChild(style)
    }
}

const swalConfig = {
    eliminar: (cuenta: PlanCuenta) => ({
        title: 'Eliminar cuenta contable',
        html: `
            <div style="text-align:left">
                <div style="display:flex;align-items:center;gap:12px;background:var(--bg-main,#f9fafb);border:1px solid #e5e7eb;border-left:4px solid #F59E0B;border-radius:10px;padding:14px;margin-bottom:16px">
                    <div>
                        <div style="font-family:monospace;font-weight:700;color:#F59E0B;font-size:1rem;letter-spacing:0.5px">${cuenta.codigo}</div>
                        <div style="font-weight:600;color:#1f2937;margin-top:2px">${cuenta.nombre}</div>
                        <div style="font-size:0.78rem;color:#6b7280;margin-top:2px">Nivel ${cuenta.nivel} · ${cuenta.tipo.charAt(0).toUpperCase() + cuenta.tipo.slice(1)}</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;align-items:flex-start;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px">
                    <span style="font-size:1.4rem;line-height:1">⚠️</span>
                    <div>
                        <p style="font-weight:700;color:#dc2626;margin:0 0 4px 0;font-size:0.9rem">Esta acción no se puede deshacer</p>
                        <p style="color:#ef4444;font-size:0.82rem;margin:0;line-height:1.5">La cuenta será eliminada permanentemente de la base de datos y no podrá ser recuperada bajo ningún concepto.</p>
                    </div>
                </div>
            </div>
        `,
        icon: 'warning' as const,
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<span style="font-size:0.9rem">🗑️ Sí, eliminar permanentemente</span>',
        cancelButtonText: '<span style="font-size:0.9rem">↩️ Cancelar, mantener cuenta</span>',
        reverseButtons: true,
        focusCancel: true,
        customClass: { popup: 'swal-popup-custom', title: 'swal-title-custom', confirmButton: 'swal-confirm-custom', cancelButton: 'swal-cancel-custom' },
        didOpen: injectSwalStyles,
    }),

    toggleEstado: (cuenta: PlanCuenta) => {
        const desactivando = cuenta.estado
        return {
            title: desactivando ? 'Desactivar cuenta' : 'Activar cuenta',
            html: `
                <div style="text-align:center;padding:8px 0">
                    <div style="font-size:3rem;margin-bottom:12px">${desactivando ? '🔴' : '🟢'}</div>
                    <p style="color:#374151;font-size:0.95rem;margin:0">
                        ¿Confirmas que deseas <strong>${desactivando ? 'desactivar' : 'activar'}</strong> la cuenta?
                    </p>
                    <div style="margin-top:14px;background:#f3f4f6;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:0.9rem;color:#F59E0B;font-weight:700">
                        ${cuenta.codigo} — ${cuenta.nombre}
                    </div>
                    ${desactivando ? '<p style="margin-top:12px;font-size:0.8rem;color:#9ca3af">La cuenta no aparecerá en los selectores de asientos.</p>' : ''}
                </div>
            `,
            icon: desactivando ? 'warning' as const : 'question' as const,
            showCancelButton: true,
            confirmButtonColor: desactivando ? '#f59e0b' : '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: desactivando ? '🔴 Sí, desactivar' : '🟢 Sí, activar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            customClass: { popup: 'swal-popup-custom', title: 'swal-title-custom' },
            didOpen: injectSwalStyles,
        }
    },
}

// ─── Stat Card ────────────────────────────────────────────────────────────────

interface StatCardProps {
    label: string
    value: number
    icon: React.ElementType
    iconClass: string
    valueClass: string
}

function StatCard({ label, value, icon: Icon, iconClass, valueClass }: StatCardProps) {
    return (
        <div
            className="rounded-xl border p-4 flex items-center gap-3 transition-shadow hover:shadow-md"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
        >
            <div className={cn('rounded-lg p-2.5 shrink-0', iconClass)}>
                <Icon className="w-5 h-5" />
            </div>
            <div className="min-w-0">
                <p className={cn('text-2xl font-bold leading-none mb-1', valueClass)}>{value}</p>
                <p className="text-xs leading-none" style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
        </div>
    )
}

// ─── Modal unificado crear / editar ──────────────────────────────────────────

interface CuentaModalProps {
    cuenta?: PlanCuenta
    padre?: PlanCuenta | null
    todosFlat: PlanCuenta[]
    todasLasCuentas: PlanCuenta[]
    onClose: () => void
}

function CuentaModal({ cuenta, padre, todosFlat, todasLasCuentas, onClose }: CuentaModalProps) {
    const isEditar = !!cuenta

    const [modalBuscarCuenta, setModalBuscarCuenta] = useState(false)
    const [busquedaCuenta, setBusquedaCuenta] = useState('')
    const [cuentaPadreSeleccionada, setCuentaPadreSeleccionada] = useState<PlanCuenta | null>(padre ?? null)

    const { data, setData, post, put, processing, errors, reset } = useForm({
        codigo:           cuenta?.codigo ?? (padre ? suggestNextCodigo(padre, todosFlat) : ''),
        nombre:           cuenta?.nombre ?? '',
        descripcion:      cuenta?.descripcion ?? '',
        tipo:             (cuenta?.tipo ?? padre?.tipo ?? 'activo') as PlanCuenta['tipo'],
        padre_id:         isEditar ? null : (padre?.id ?? null) as number | null,
        permite_asientos: cuenta?.permite_asientos ?? true,
    })

    const cuentasFiltradas = useMemo(() => {
        const q = busquedaCuenta.toLowerCase().trim()
        if (!q) return todasLasCuentas.slice(0, 40)
        return todasLasCuentas.filter(c =>
            c.codigo.toLowerCase().includes(q) ||
            c.nombre.toLowerCase().includes(q) ||
            c.tipo.toLowerCase().includes(q)
        ).slice(0, 50)
    }, [busquedaCuenta, todasLasCuentas])

    useEffect(() => {
        if (!isEditar && data.padre_id != null) {
            const p = todosFlat.find(c => c.id === data.padre_id)
            if (p) {
                setData(prev => ({
                    ...prev,
                    codigo: suggestNextCodigo(p, todosFlat),
                    tipo: p.tipo,
                }))
            }
        }
    }, [data.padre_id])

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (isEditar) {
            put(route('contabilidad.plan-cuentas.update', cuenta!.id), {
                onSuccess: () => {
                    notify.editar(`"${cuenta!.codigo} — ${data.nombre}" guardada`)
                    onClose()
                },
                onError: () => notify.error('Error al actualizar la cuenta. Verifica los datos.'),
            })
        } else {
            post(route('contabilidad.plan-cuentas.store'), {
                onSuccess: () => {
                    notify.crear(`Cuenta "${data.codigo} — ${data.nombre}" creada`)
                    reset()
                    onClose()
                },
                onError: (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
            })
        }
    }

    return (
        <div className="z-50 fixed inset-0 flex justify-center items-center p-4 sm:p-6">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div
                className="relative shadow-2xl rounded-xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}
            >
                {/* Header fijo */}
                <div
                    className="sticky top-0 z-10 flex justify-between items-start px-6 pt-5 pb-4"
                    style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}
                >
                    <div>
                        <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                            {isEditar ? 'Editar cuenta' : 'Nueva cuenta contable'}
                        </h2>
                        {!isEditar && cuentaPadreSeleccionada && (
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-muted)' }}>
                                Creando bajo:{' '}
                                <span className="font-mono font-bold" style={{ color: 'var(--primary)' }}>
                                    {cuentaPadreSeleccionada.codigo}
                                </span>{' '}
                                {cuentaPadreSeleccionada.nombre}
                            </p>
                        )}
                        {isEditar && (
                            <p className="mt-0.5 font-mono text-xs font-bold" style={{ color: 'var(--primary)' }}>
                                {cuenta!.codigo}
                            </p>
                        )}
                    </div>
                    <button
                        onClick={onClose}
                        className="hover:opacity-70 p-1.5 rounded-lg transition-opacity shrink-0"
                        style={{ color: 'var(--text-muted)' }}
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <form onSubmit={submit} className="p-6 space-y-4">
                    {/* Selector de padre (solo crear) */}
                    {!isEditar && (
                        <div className="space-y-1.5">
                            <Label>Cuenta padre</Label>
                            <button
                                type="button"
                                onClick={() => setModalBuscarCuenta(true)}
                                className="w-full flex items-center justify-between rounded-lg px-3 py-2 text-sm border transition-colors hover:opacity-80 text-left"
                                style={{
                                    borderColor: 'var(--border)',
                                    background: 'var(--bg-main)',
                                    color: cuentaPadreSeleccionada ? 'var(--text-main)' : 'var(--text-muted)',
                                }}
                            >
                                <span>
                                    {cuentaPadreSeleccionada
                                        ? `${cuentaPadreSeleccionada.codigo} — ${cuentaPadreSeleccionada.nombre}`
                                        : '— Cuenta raíz (nivel 1) —'}
                                </span>
                                <Search size={14} style={{ color: 'var(--text-muted)' }} />
                            </button>
                            {cuentaPadreSeleccionada && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setCuentaPadreSeleccionada(null)
                                        setData('padre_id', null)
                                    }}
                                    className="text-xs mt-1 hover:opacity-80"
                                    style={{ color: 'var(--text-muted)' }}
                                >
                                    × Quitar cuenta padre (crear como nivel 1)
                                </button>
                            )}
                        </div>
                    )}

                    {/* Código */}
                    <div className="space-y-1.5">
                        <Label>Código {!isEditar && <span className="text-red-400">*</span>}</Label>
                        {isEditar ? (
                            <>
                                <Input value={cuenta!.codigo} disabled className="opacity-60 font-mono" />
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    El código no es editable para mantener la integridad contable
                                </p>
                            </>
                        ) : (
                            <>
                                <Input
                                    value={data.codigo}
                                    onChange={e => setData('codigo', e.target.value)}
                                    placeholder={cuentaPadreSeleccionada ? `ej: ${cuentaPadreSeleccionada.codigo}.X` : 'ej: 1'}
                                    error={errors.codigo}
                                    className="font-mono"
                                />
                                {errors.codigo && <p className="text-red-400 text-xs">{errors.codigo}</p>}
                            </>
                        )}
                    </div>

                    {/* Nombre */}
                    <div className="space-y-1.5">
                        <Label>Nombre <span className="text-red-400">*</span></Label>
                        <Input
                            value={data.nombre}
                            onChange={e => setData('nombre', e.target.value)}
                            error={errors.nombre}
                            placeholder="Nombre de la cuenta"
                        />
                        {errors.nombre && <p className="text-red-400 text-xs">{errors.nombre}</p>}
                    </div>

                    {/* Descripción */}
                    <div className="space-y-1.5">
                        <Label>Descripción</Label>
                        <Input
                            value={data.descripcion}
                            onChange={e => setData('descripcion', e.target.value)}
                            placeholder="Descripción opcional..."
                        />
                    </div>

                    {/* Tipo (solo crear) */}
                    {!isEditar && (
                        <div className="space-y-1.5">
                            <Label>Tipo <span className="text-red-400">*</span></Label>
                            <select
                                value={data.tipo}
                                onChange={e => setData('tipo', e.target.value as PlanCuenta['tipo'])}
                                disabled={!!cuentaPadreSeleccionada}
                                className="disabled:opacity-60 px-3 border rounded-md w-full h-9 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                                style={{ background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }}
                            >
                                {(Object.keys(TIPO_CONFIG) as PlanCuenta['tipo'][]).map(t => (
                                    <option
                                        key={t}
                                        value={t}
                                        style={{ background: 'var(--bg-card)', color: 'var(--text-main)' }}
                                        className="dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        {TIPO_CONFIG[t].label}
                                    </option>
                                ))}
                            </select>
                            {cuentaPadreSeleccionada && (
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                    Heredado del padre: {TIPO_CONFIG[cuentaPadreSeleccionada.tipo].label}
                                </p>
                            )}
                        </div>
                    )}

                    {/* Permite asientos */}
                    <label className="flex items-center gap-2.5 cursor-pointer py-1">
                        <input
                            type="checkbox"
                            checked={data.permite_asientos}
                            onChange={e => setData('permite_asientos', e.target.checked)}
                            className="rounded w-4 h-4 accent-amber-500"
                        />
                        <span className="text-sm" style={{ color: 'var(--text-main)' }}>
                            Permite registrar asientos contables
                        </span>
                    </label>

                    {/* Botones */}
                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <Button type="submit" loading={processing}>
                            {isEditar
                                ? <><Pencil className="w-4 h-4" /> Guardar cambios</>
                                : <><Plus className="w-4 h-4" /> Crear cuenta</>
                            }
                        </Button>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            </div>

            {/* MODAL BÚSQUEDA DE CUENTA PADRE */}
            {modalBuscarCuenta && (
                <div
                    className="fixed inset-0 z-60 flex items-center justify-center p-4"
                    style={{ background: 'rgba(0,0,0,0.6)' }}
                    onClick={() => setModalBuscarCuenta(false)}
                >
                    <div
                        className="w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden"
                        style={{ background: 'var(--bg-card)' }}
                        onClick={e => e.stopPropagation()}
                    >
                        {/* Header modal búsqueda */}
                        <div
                            className="flex items-center justify-between px-4 py-3 border-b"
                            style={{ borderColor: 'var(--border)' }}
                        >
                            <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                Seleccionar cuenta padre
                            </h3>
                            <button
                                onClick={() => setModalBuscarCuenta(false)}
                                className="text-gray-400 hover:text-gray-600 text-lg leading-none"
                            >
                                ×
                            </button>
                        </div>

                        {/* Input búsqueda */}
                        <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--border)' }}>
                            <div className="relative">
                                <Search
                                    size={14}
                                    className="absolute left-3 top-1/2 -translate-y-1/2"
                                    style={{ color: 'var(--text-muted)' }}
                                />
                                <input
                                    autoFocus
                                    type="text"
                                    value={busquedaCuenta}
                                    onChange={e => setBusquedaCuenta(e.target.value)}
                                    placeholder="Buscar por código, nombre o tipo..."
                                    className="w-full pl-9 pr-3 py-2 text-sm rounded-lg border focus:outline-none focus:ring-2 focus:ring-amber-500 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-600"
                                    style={{
                                        borderColor: 'var(--border)',
                                        background: 'var(--bg-main)',
                                        color: 'var(--text-main)',
                                    }}
                                />
                            </div>
                            <p className="text-xs mt-1" style={{ color: 'var(--text-muted)' }}>
                                Busca por código (ej: 1.1.1), nombre o tipo (activo, pasivo...)
                            </p>
                        </div>

                        {/* Opción cuenta raíz */}
                        <button
                            onClick={() => {
                                setCuentaPadreSeleccionada(null)
                                setData('padre_id', null)
                                setModalBuscarCuenta(false)
                                setBusquedaCuenta('')
                            }}
                            className="w-full text-left px-4 py-3 text-sm font-semibold transition-colors hover:opacity-80 border-b"
                            style={{
                                borderColor: 'var(--border)',
                                color: 'var(--primary)',
                                background: 'color-mix(in srgb, var(--primary) 5%, transparent)',
                            }}
                        >
                            — Cuenta raíz (nivel 1) — sin padre
                        </button>

                        {/* Lista de cuentas filtradas */}
                        <div className="overflow-y-auto" style={{ maxHeight: '360px' }}>
                            {cuentasFiltradas.length === 0 ? (
                                <div className="text-center py-8" style={{ color: 'var(--text-muted)' }}>
                                    <Search size={28} className="mx-auto mb-2 opacity-30" />
                                    <p className="text-sm">Sin resultados para "{busquedaCuenta}"</p>
                                </div>
                            ) : cuentasFiltradas.map(c => (
                                <button
                                    key={c.id}
                                    onClick={() => {
                                        setCuentaPadreSeleccionada(c)
                                        setData('padre_id', c.id)
                                        setModalBuscarCuenta(false)
                                        setBusquedaCuenta('')
                                    }}
                                    className="w-full text-left py-2.5 text-sm transition-colors hover:opacity-80 border-b flex items-center gap-3"
                                    style={{
                                        borderColor: 'var(--border)',
                                        paddingLeft: `${(c.nivel - 1) * 16 + 16}px`,
                                        paddingRight: '16px',
                                    }}
                                >
                                    <span
                                        className="font-mono font-bold shrink-0 text-xs"
                                        style={{ color: 'var(--primary)' }}
                                    >
                                        {c.codigo}
                                    </span>
                                    <span style={{ color: 'var(--text-main)' }}>{c.nombre}</span>
                                    {c.nivel === 1 && (
                                        <span className={cn(
                                            'ml-auto shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold',
                                            c.tipo === 'activo'     && 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                            c.tipo === 'pasivo'     && 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                            c.tipo === 'patrimonio' && 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                            c.tipo === 'ingreso'    && 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                            c.tipo === 'gasto'      && 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
                                        )}>
                                            {c.tipo}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>

                        {/* Footer con total resultados */}
                        <div
                            className="px-4 py-2 border-t text-xs"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                        >
                            {cuentasFiltradas.length} resultado(s)
                            {busquedaCuenta && ` para "${busquedaCuenta}"`}
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}

// ─── Nodo del árbol ───────────────────────────────────────────────────────────

interface CuentaNodeProps {
    cuenta: PlanCuenta
    busqueda: string
    expandidos: Set<number>
    onToggleExpand: (id: number) => void
    onAgregar: (padre: PlanCuenta) => void
    onEditar: (cuenta: PlanCuenta) => void
    onToggleEstado: (cuenta: PlanCuenta) => void
    onEliminar: (cuenta: PlanCuenta) => void
}

function CuentaNode({
    cuenta, busqueda, expandidos,
    onToggleExpand, onAgregar, onEditar, onToggleEstado, onEliminar,
}: CuentaNodeProps) {
    const hasHijos   = (cuenta.hijos?.length ?? 0) > 0
    const isExpanded = !!busqueda || expandidos.has(cuenta.id)
    const config     = TIPO_CONFIG[cuenta.tipo]

    const toggleBlocked = cuenta.estado && cuenta.total_asientos > 0
    const puedeEliminar = cuenta.total_asientos === 0 && !hasHijos

    const tooltipEliminar = !puedeEliminar
        ? hasHijos
            ? `Tiene ${cuenta.hijos?.length ?? 0} cuenta(s) hija(s) — elimínalas primero`
            : `Tiene ${cuenta.total_asientos} asiento(s) registrado(s)`
        : 'Eliminar cuenta'

    return (
        <div>
            <div
                className={cn(
                    'group flex items-center gap-1.5 py-1.5 pr-2 border-b transition-colors',
                    !cuenta.estado && 'opacity-40',
                )}
                style={{ borderColor: 'var(--border)', background: 'transparent' }}
                onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.08)')}
                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
            >
                {/* Expander */}
                <button
                    onClick={() => hasHijos && onToggleExpand(cuenta.id)}
                    className={cn(
                        'flex justify-center items-center rounded w-5 h-5 shrink-0 transition-colors',
                        hasHijos ? 'hover:bg-white/10 cursor-pointer' : 'pointer-events-none opacity-0',
                    )}
                    style={{ color: 'var(--text-muted)' }}
                    tabIndex={hasHijos ? 0 : -1}
                >
                    {isExpanded
                        ? <ChevronDown className="w-3.5 h-3.5 transition-transform duration-150" />
                        : <ChevronRight className="w-3.5 h-3.5 transition-transform duration-150" />
                    }
                </button>

                {/* Código */}
                <span
                    className={cn(
                        'font-mono shrink-0 font-semibold',
                        cuenta.nivel === 1 && 'text-sm font-bold w-6',
                        cuenta.nivel === 2 && 'text-xs w-10',
                        cuenta.nivel === 3 && 'text-xs w-24',
                        cuenta.nivel >= 4 && 'text-xs w-32',
                    )}
                    style={{ color: 'var(--primary)' }}
                >
                    {cuenta.codigo}
                </span>

                {/* Nombre */}
                <span
                    className={cn(
                        'flex-1 min-w-0 truncate text-sm',
                        cuenta.nivel === 1 && 'font-bold',
                        cuenta.nivel === 2 && 'font-semibold',
                    )}
                    style={{ color: 'var(--text-main)' }}
                    title={cuenta.nombre}
                >
                    <Highlight text={cuenta.nombre} query={busqueda} />
                </span>

                {/* Badges */}
                <div className="flex items-center gap-1 shrink-0">
                    {cuenta.nivel === 1 && (
                        <span className={cn(
                            'px-1.5 py-0.5 rounded-full text-[10px] font-semibold border',
                            config.light, config.dark,
                        )}>
                            {config.label}
                        </span>
                    )}
                    {cuenta.permite_asientos && (
                        <span className="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-green-500/15 text-green-700 dark:text-green-400 border border-green-500/30">
                            Acepta asientos
                        </span>
                    )}
                    {cuenta.permite_asientos && cuenta.total_asientos === 0 && (
                        <span
                            className="hidden md:inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium border"
                            style={{ background: 'rgba(0,0,0,0.05)', color: 'var(--text-muted)', borderColor: 'var(--border)' }}
                        >
                            Sin uso
                        </span>
                    )}
                    {!cuenta.estado && (
                        <span className="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-red-500/15 text-red-700 dark:text-red-400 border border-red-500/30">
                            Inactiva
                        </span>
                    )}
                </div>

                {/* Acciones */}
                <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 ml-1 transition-opacity shrink-0">
                    <button
                        onClick={() => onAgregar(cuenta)}
                        title="Agregar cuenta hija"
                        className="hover:bg-amber-500/20 p-1 rounded transition-colors"
                        style={{ color: 'var(--primary)' }}
                    >
                        <Plus className="w-3.5 h-3.5" />
                    </button>
                    <button
                        onClick={() => onEditar(cuenta)}
                        title="Editar cuenta"
                        className="hover:bg-blue-500/20 p-1 rounded text-blue-500 dark:text-blue-400 transition-colors"
                    >
                        <Pencil className="w-3.5 h-3.5" />
                    </button>
                    <button
                        onClick={() => !toggleBlocked && onToggleEstado(cuenta)}
                        title={toggleBlocked
                            ? `Tiene ${cuenta.total_asientos} asiento(s) registrado(s), no se puede desactivar`
                            : cuenta.estado ? 'Desactivar cuenta' : 'Activar cuenta'}
                        disabled={toggleBlocked}
                        className={cn(
                            'p-1 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed',
                            cuenta.estado
                                ? 'hover:bg-red-500/20 text-red-500 dark:text-red-400'
                                : 'hover:bg-green-500/20 text-green-600 dark:text-green-400',
                        )}
                    >
                        {cuenta.estado
                            ? <ToggleRight className="w-3.5 h-3.5" />
                            : <ToggleLeft className="w-3.5 h-3.5" />
                        }
                    </button>
                    <button
                        onClick={() => puedeEliminar && onEliminar(cuenta)}
                        title={tooltipEliminar}
                        disabled={!puedeEliminar}
                        className="p-1 rounded transition-colors hover:bg-red-500/20 text-red-500 dark:text-red-400 disabled:opacity-25 disabled:cursor-not-allowed"
                    >
                        <Trash2 className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            {/* Hijos con línea de jerarquía */}
            {hasHijos && isExpanded && (
                <div className="border-l" style={{ borderColor: 'var(--border)', marginLeft: '20px' }}>
                    {cuenta.hijos!.map(hijo => (
                        <CuentaNode
                            key={hijo.id}
                            cuenta={hijo}
                            busqueda={busqueda}
                            expandidos={expandidos}
                            onToggleExpand={onToggleExpand}
                            onAgregar={onAgregar}
                            onEditar={onEditar}
                            onToggleEstado={onToggleEstado}
                            onEliminar={onEliminar}
                        />
                    ))}
                </div>
            )}
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function PlanCuentasIndex() {
    const { cuentas, stats, todasLasCuentas, flash } = usePage<Props>().props

    const [busqueda, setBusqueda]   = useState('')
    const [expandidos, setExpandidos] = useState<Set<number>>(() => new Set(cuentas.map(c => c.id)))
    const [modal, setModal] = useState<{
        open: boolean
        cuenta?: PlanCuenta
        padre?: PlanCuenta | null
    }>({ open: false })

    const todosFlat = useMemo(() => flattenTree(cuentas), [cuentas])

    const arbolVisible = useMemo(() => {
        if (!busqueda.trim()) return cuentas
        return filterTree(cuentas, busqueda.toLowerCase())
    }, [cuentas, busqueda])

    // Flash de Laravel → toast
    useEffect(() => {
        if (flash?.success) notify.crear(flash.success)
        if (flash?.error)   notify.error(flash.error)
        if (flash?.warning) toast.warning(flash.warning, {
            icon: () => '⚠️',
            autoClose: 5000,
            style: { borderRadius: '14px', fontWeight: '600', color: '#fff', background: 'linear-gradient(135deg,#f59e0b,#d97706)' },
        })
    }, [flash?.success, flash?.error, flash?.warning])

    const toggleExpand = useCallback((id: number) => {
        setExpandidos(prev => {
            const next = new Set(prev)
            next.has(id) ? next.delete(id) : next.add(id)
            return next
        })
    }, [])

    const expandirTodo = () => setExpandidos(new Set(getAllIds(cuentas)))
    const colapsarTodo = () => setExpandidos(new Set())

    async function confirmarToggle(cuenta: PlanCuenta) {
        const result = await Swal.fire(swalConfig.toggleEstado(cuenta))
        if (result.isConfirmed) {
            router.patch(route('contabilidad.plan-cuentas.toggle-estado', cuenta.id), {}, {
                onSuccess: () => cuenta.estado
                    ? notify.desactivar(`"${cuenta.nombre}" desactivada`)
                    : notify.activar(`"${cuenta.nombre}" activada`),
                onError: () => notify.error('Error al cambiar el estado'),
            })
        }
    }

    async function confirmarEliminar(cuenta: PlanCuenta) {
        const result = await Swal.fire(swalConfig.eliminar(cuenta))
        if (result.isConfirmed) {
            router.delete(route('contabilidad.plan-cuentas.destroy', cuenta.id), {
                onSuccess: () => notify.eliminar(`Cuenta ${cuenta.codigo} eliminada permanentemente`),
                onError:   () => notify.error('Error al eliminar la cuenta'),
            })
        }
    }

    return (
        <AppLayout title="Plan de Cuentas" suppressFlash>
            <Head title="Plan de Cuentas" />

            <PageHeader
                title="Plan de Cuentas"
                description="Estructura contable oficial de Altamira Light & Sound"
                breadcrumbs={[
                    { label: 'Contabilidad' },
                    { label: 'Plan de Cuentas' },
                ]}
            />

            {/* Stats — 2x2 mobile, 4x1 desktop */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 px-6 py-4">
                <StatCard
                    label="Total cuentas"
                    value={stats.total}
                    icon={BookOpen}
                    iconClass="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueClass="text-blue-600 dark:text-blue-400"
                />
                <StatCard
                    label="Activas"
                    value={stats.activas}
                    icon={CheckCircle}
                    iconClass="bg-green-500/15 text-green-600 dark:text-green-400"
                    valueClass="text-green-600 dark:text-green-400"
                />
                <StatCard
                    label="Con asientos"
                    value={stats.con_asientos}
                    icon={AlertCircle}
                    iconClass="bg-amber-500/15 text-amber-600 dark:text-amber-400"
                    valueClass="text-amber-600 dark:text-amber-400"
                />
                <StatCard
                    label="Sin uso"
                    value={stats.sin_uso}
                    icon={AlertCircle}
                    iconClass="bg-gray-500/15 text-gray-500 dark:text-gray-400"
                    valueClass="text-gray-500 dark:text-gray-400"
                />
            </div>

            {/* Toolbar: [Nueva Cuenta] [Buscar] [Expandir] [Colapsar] [Exportar] */}
            <div className="flex flex-wrap items-center gap-2 px-6 pb-4">
                <Button size="sm" onClick={() => setModal({ open: true, padre: null })}>
                    <Plus className="w-4 h-4" />
                    Nueva cuenta
                </Button>

                <div className="relative flex-1 min-w-48 max-w-sm">
                    <Search
                        className="absolute top-1/2 left-2.5 w-4 h-4 -translate-y-1/2 pointer-events-none"
                        style={{ color: 'var(--text-muted)' }}
                    />
                    <Input
                        className="pl-8 pr-8"
                        placeholder="Buscar por código o nombre…"
                        value={busqueda}
                        onChange={e => setBusqueda(e.target.value)}
                    />
                    {busqueda && (
                        <button
                            onClick={() => setBusqueda('')}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 hover:opacity-70 transition-opacity"
                            style={{ color: 'var(--text-muted)' }}
                        >
                            <X className="w-3.5 h-3.5" />
                        </button>
                    )}
                </div>

                <Button variant="outline" size="sm" onClick={expandirTodo} title="Expandir todo">
                    <ChevronsUpDown className="w-3.5 h-3.5" />
                    Expandir
                </Button>
                <Button variant="outline" size="sm" onClick={colapsarTodo} title="Colapsar todo">
                    <ChevronsDownUp className="w-3.5 h-3.5" />
                    Colapsar
                </Button>

                <div className="flex-1" />
                <a href={route('contabilidad.plan-cuentas.exportar')}
                   className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white whitespace-nowrap transition-all hover:opacity-90"
                   style={{ background: '#16a34a' }}>
                    <Download className="w-3.5 h-3.5" />
                    Excel
                </a>
            </div>

            {/* Árbol */}
            <div className="px-6 pb-8">
                <div
                    className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}
                >
                    {/* Cabecera de columnas */}
                    <div
                        className="flex items-center gap-3 px-3 py-2 border-b"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)' }}
                    >
                        <span className="w-5" />
                        <span
                            className="font-semibold text-[11px] uppercase tracking-wider shrink-0"
                            style={{ color: 'var(--text-muted)', width: '8rem' }}
                        >
                            Código
                        </span>
                        <span
                            className="font-semibold text-[11px] uppercase tracking-wider"
                            style={{ color: 'var(--text-muted)' }}
                        >
                            Nombre de la cuenta
                        </span>
                    </div>

                    {/* Estado vacío */}
                    {arbolVisible.length === 0 && (
                        <div className="py-20 text-center">
                            <BookOpen
                                className="opacity-20 mx-auto mb-3 w-10 h-10"
                                style={{ color: 'var(--text-muted)' }}
                            />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                {busqueda
                                    ? 'No se encontraron cuentas que coincidan'
                                    : 'El plan de cuentas está vacío'
                                }
                            </p>
                        </div>
                    )}

                    {/* Nodos raíz */}
                    {arbolVisible.map(cuenta => (
                        <CuentaNode
                            key={cuenta.id}
                            cuenta={cuenta}
                            busqueda={busqueda.trim().toLowerCase()}
                            expandidos={expandidos}
                            onToggleExpand={toggleExpand}
                            onAgregar={padre => setModal({ open: true, padre })}
                            onEditar={c => setModal({ open: true, cuenta: c })}
                            onToggleEstado={confirmarToggle}
                            onEliminar={confirmarEliminar}
                        />
                    ))}
                </div>
            </div>

            {/* Modal unificado */}
            {modal.open && (
                <CuentaModal
                    cuenta={modal.cuenta}
                    padre={modal.padre}
                    todosFlat={todosFlat}
                    todasLasCuentas={todasLasCuentas}
                    onClose={() => setModal({ open: false })}
                />
            )}

            {/* Toast container propio de esta página */}
            <ToastContainer
                position="top-right"
                autoClose={3500}
                hideProgressBar={false}
                newestOnTop
                closeOnClick
                pauseOnHover
                draggable
                theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{
                    borderRadius: '14px',
                    fontSize: '14px',
                    fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
                    padding: '14px 18px',
                    minWidth: '280px',
                }}
            />
        </AppLayout>
    )
}
