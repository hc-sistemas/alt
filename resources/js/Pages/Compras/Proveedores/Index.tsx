import { useState, useEffect } from 'react'
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
    Plus, Pencil, ToggleLeft, ToggleRight, Search, X,
    Users, Globe, Building2, AlertCircle, ShoppingCart,
} from 'lucide-react'
import type { Proveedor, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ProveedorStats {
    total: number
    nacionales: number
    internacionales: number
    con_saldo: number
}

interface Props extends PageProps {
    proveedores: Proveedor[]
    stats: ProveedorStats
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const

const notify = {
    ok:    (msg: string) => toast.success(msg, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    edit:  (msg: string) => toast.success(msg, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    warn:  (msg: string) => toast.info(msg,    { icon: () => '🟡', style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' } }),
    error: (msg: string) => toast.error(msg,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── SweetAlert helpers ───────────────────────────────────────────────────────

const SWAL_CSS = `
    .swal-pop { border-radius:20px!important; padding:28px!important; box-shadow:0 25px 60px rgba(0,0,0,.25)!important; max-width:440px!important }
    .swal-title { font-size:1.1rem!important; font-weight:700!important; color:#1f2937!important; margin-bottom:16px!important }
    .swal-confirm { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
    .swal-cancel  { border-radius:10px!important; padding:10px 20px!important; font-weight:600!important }
`
function injectSwalCss() {
    if (document.getElementById('swal-prov')) return
    const s = document.createElement('style')
    s.id = 'swal-prov'; s.textContent = SWAL_CSS
    document.head.appendChild(s)
}
const swalBase = {
    showCancelButton: true, reverseButtons: true, focusCancel: true,
    customClass: { popup: 'swal-pop', title: 'swal-title', confirmButton: 'swal-confirm', cancelButton: 'swal-cancel' },
    didOpen: injectSwalCss,
}

// ─── StatCard ─────────────────────────────────────────────────────────────────

function StatCard({ label, value, icon: Icon, cls, valueCls }: {
    label: string; value: number | string; icon: React.ElementType; cls: string; valueCls: string
}) {
    return (
        <div className="rounded-xl border p-4 flex items-center gap-3 hover:shadow-md transition-shadow"
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
            <div className={cn('rounded-lg p-2.5 shrink-0', cls)}><Icon className="w-5 h-5" /></div>
            <div className="min-w-0">
                <p className={cn('text-2xl font-bold leading-none mb-1', valueCls)}>{value}</p>
                <p className="text-xs leading-none" style={{ color: 'var(--text-muted)' }}>{label}</p>
            </div>
        </div>
    )
}

// ─── Badge Tipo ───────────────────────────────────────────────────────────────

function TipoBadge({ tipo }: { tipo: string }) {
    return tipo === 'nacional'
        ? <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">Nacional</span>
        : <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300">Internacional</span>
}

// ─── Modal Crear/Editar ───────────────────────────────────────────────────────

interface ModalProps {
    proveedor?: Proveedor
    onClose: () => void
}

function ProveedorModal({ proveedor, onClose }: ModalProps) {
    const isEditar = !!proveedor
    const [tab, setTab] = useState<'nacional' | 'internacional'>(
        proveedor ? proveedor.tipo : 'nacional'
    )

    const { data, setData, post, put, processing, errors } = useForm({
        tipo:              proveedor?.tipo ?? tab,
        tipo_identificacion: proveedor?.tipo_identificacion ?? 'RUC',
        identificacion:    proveedor?.identificacion ?? '',
        razon_social:      proveedor?.razon_social ?? '',
        nombre_comercial:  proveedor?.nombre_comercial ?? '',
        email:             proveedor?.email ?? '',
        telefono:          proveedor?.telefono ?? '',
        direccion:         proveedor?.direccion ?? '',
        ciudad:            proveedor?.ciudad ?? '',
        pais:              proveedor?.pais ?? 'Ecuador',
        divisa:            proveedor?.divisa ?? 'USD',
        tiene_credito:     proveedor?.tiene_credito ?? false,
        dias_credito:      proveedor?.dias_credito ?? 30,
    })

    function handleTabChange(t: 'nacional' | 'internacional') {
        setTab(t)
        setData(prev => ({
            ...prev,
            tipo: t,
            pais: t === 'nacional' ? 'Ecuador' : prev.pais,
            divisa: t === 'nacional' ? 'USD' : prev.divisa,
        }))
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        if (isEditar) {
            put(route('compras.proveedores.update', proveedor!.id), {
                onSuccess: () => { notify.edit('Proveedor actualizado correctamente'); onClose() },
                onError:   () => notify.error('Error al actualizar el proveedor.'),
            })
        } else {
            post(route('compras.proveedores.store'), {
                onSuccess: () => { notify.ok(`Proveedor ${data.razon_social} creado`); onClose() },
                onError:   (errs) => notify.error('Error: ' + Object.values(errs).join(', ')),
            })
        }
    }

    const inputStyle = { background: 'var(--bg-card)', color: 'var(--text-main)', borderColor: 'var(--border)' }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div className="relative w-full max-w-xl max-h-[90vh] overflow-y-auto rounded-xl shadow-2xl"
                style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                {/* Header */}
                <div className="sticky top-0 z-10 flex items-center justify-between px-6 pt-5 pb-4"
                    style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                    <div>
                        <h2 className="font-semibold text-base" style={{ color: 'var(--text-main)' }}>
                            {isEditar ? 'Editar proveedor' : 'Nuevo proveedor'}
                        </h2>
                        {isEditar && (
                            <p className="text-xs mt-0.5" style={{ color: 'var(--text-muted)' }}>
                                {proveedor!.identificacion} — {proveedor!.razon_social}
                            </p>
                        )}
                    </div>
                    <button onClick={onClose} className="p-1.5 rounded-lg hover:opacity-70 transition-opacity"
                        style={{ color: 'var(--text-muted)' }}>
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Tabs tipo (solo crear) */}
                {!isEditar && (
                    <div className="px-6 pt-4 flex gap-2">
                        {(['nacional', 'internacional'] as const).map(t => (
                            <button key={t}
                                onClick={() => handleTabChange(t)}
                                className={cn(
                                    'px-4 py-1.5 rounded-lg text-sm font-medium transition-colors border',
                                    tab === t
                                        ? 'text-white border-transparent'
                                        : 'hover:opacity-80'
                                )}
                                style={tab === t
                                    ? { background: 'var(--primary)', borderColor: 'var(--primary)' }
                                    : { color: 'var(--text-muted)', borderColor: 'var(--border)', background: 'transparent' }
                                }
                            >
                                {t === 'nacional' ? '🇪🇨 Nacional' : '🌎 Internacional'}
                            </button>
                        ))}
                    </div>
                )}

                <form onSubmit={submit} className="p-6 space-y-4">
                    {/* Identificación */}
                    {!isEditar && (
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label>Tipo ID <span className="text-red-400">*</span></Label>
                                <select value={data.tipo_identificacion}
                                    onChange={e => setData('tipo_identificacion', e.target.value)}
                                    className="w-full h-9 px-3 border rounded-md text-sm focus:outline-none focus:ring-1 focus:ring-amber-500"
                                    style={inputStyle}>
                                    {tab === 'nacional'
                                        ? <>
                                            <option value="RUC">RUC</option>
                                            <option value="CED">Cédula</option>
                                            <option value="PAS">Pasaporte</option>
                                          </>
                                        : <>
                                            <option value="PAS">Pasaporte</option>
                                            <option value="EXT">Tax ID</option>
                                          </>
                                    }
                                </select>
                            </div>
                            <div className="col-span-2 space-y-1.5">
                                <Label>Identificación <span className="text-red-400">*</span></Label>
                                <Input value={data.identificacion}
                                    onChange={e => setData('identificacion', e.target.value)}
                                    error={errors.identificacion}
                                    placeholder={tab === 'nacional' ? '1711293454001' : 'Número de documento'} />
                                {errors.identificacion && <p className="text-red-400 text-xs">{errors.identificacion}</p>}
                            </div>
                        </div>
                    )}

                    {/* Razón social */}
                    <div className="space-y-1.5">
                        <Label>Razón social <span className="text-red-400">*</span></Label>
                        <Input value={data.razon_social}
                            onChange={e => setData('razon_social', e.target.value)}
                            error={errors.razon_social}
                            placeholder="Nombre legal completo" />
                        {errors.razon_social && <p className="text-red-400 text-xs">{errors.razon_social}</p>}
                    </div>

                    {/* Nombre comercial */}
                    <div className="space-y-1.5">
                        <Label>Nombre comercial</Label>
                        <Input value={data.nombre_comercial ?? ''}
                            onChange={e => setData('nombre_comercial', e.target.value)}
                            placeholder="Nombre por el que se conoce al proveedor" />
                    </div>

                    {/* Email / Teléfono */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Email</Label>
                            <Input type="email" value={data.email ?? ''}
                                onChange={e => setData('email', e.target.value)}
                                error={errors.email}
                                placeholder="correo@empresa.com" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Teléfono</Label>
                            <Input value={data.telefono ?? ''}
                                onChange={e => setData('telefono', e.target.value)}
                                placeholder="+593 99 999 9999" />
                        </div>
                    </div>

                    {/* Dirección */}
                    <div className="space-y-1.5">
                        <Label>Dirección</Label>
                        <Input value={data.direccion ?? ''}
                            onChange={e => setData('direccion', e.target.value)}
                            placeholder="Calle, número, sector..." />
                    </div>

                    {/* Ciudad / País / Divisa */}
                    <div className="grid grid-cols-3 gap-3">
                        <div className="space-y-1.5">
                            <Label>Ciudad</Label>
                            <Input value={data.ciudad ?? ''}
                                onChange={e => setData('ciudad', e.target.value)}
                                placeholder="Ciudad" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>País</Label>
                            <Input value={data.pais ?? ''}
                                onChange={e => setData('pais', e.target.value)}
                                placeholder="Ecuador" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Divisa</Label>
                            <Input value={data.divisa ?? ''}
                                onChange={e => setData('divisa', e.target.value)}
                                placeholder="USD" />
                        </div>
                    </div>

                    {/* Crédito */}
                    <div className="rounded-lg border p-4 space-y-3"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.04)' }}>
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.tiene_credito}
                                onChange={e => setData('tiene_credito', e.target.checked)}
                                className="rounded w-4 h-4 accent-amber-500" />
                            <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                Tiene crédito
                            </span>
                        </label>
                        {data.tiene_credito && (
                            <div className="space-y-1.5">
                                <Label>Días de crédito</Label>
                                <Input type="number" min={1} max={365}
                                    value={data.dias_credito}
                                    onChange={e => setData('dias_credito', Number(e.target.value))}
                                    className="w-32" />
                            </div>
                        )}
                    </div>

                    {/* Botones */}
                    <div className="flex gap-2 pt-2 border-t" style={{ borderColor: 'var(--border)' }}>
                        <Button type="submit" disabled={processing}>
                            {isEditar
                                ? <><Pencil className="w-4 h-4" /> Guardar cambios</>
                                : <><Plus className="w-4 h-4" /> Crear proveedor</>
                            }
                        </Button>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancelar
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function ProveedoresIndex() {
    const { proveedores, stats, flash } = usePage<Props>().props

    const [busqueda, setBusqueda] = useState('')
    const [modal, setModal] = useState<{ open: boolean; proveedor?: Proveedor }>({ open: false })

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    const filtrados = proveedores.filter(p => {
        if (!busqueda.trim()) return true
        const q = busqueda.toLowerCase()
        return (
            p.razon_social.toLowerCase().includes(q) ||
            p.identificacion.toLowerCase().includes(q) ||
            (p.nombre_comercial ?? '').toLowerCase().includes(q) ||
            (p.email ?? '').toLowerCase().includes(q)
        )
    })

    async function confirmarToggle(p: Proveedor) {
        if (!p.estado && (p.saldo_pendiente ?? 0) > 0) {
            notify.error(`No se puede desactivar: tiene $${p.saldo_pendiente} pendiente.`)
            return
        }
        const result = await Swal.fire({
            ...swalBase,
            title: p.estado ? 'Desactivar proveedor' : 'Activar proveedor',
            html: `<div style="text-align:center;padding:8px 0">
                <div style="font-size:3rem;margin-bottom:12px">${p.estado ? '🔴' : '🟢'}</div>
                <p style="color:#374151">¿Confirmas <strong>${p.estado ? 'desactivar' : 'activar'}</strong>?</p>
                <div style="margin-top:12px;background:#f3f4f6;border-radius:8px;padding:10px 14px;font-weight:700;color:#F59E0B">
                    ${p.razon_social}
                </div>
            </div>`,
            icon: p.estado ? 'warning' : 'question',
            confirmButtonColor: p.estado ? '#f59e0b' : '#10b981',
            cancelButtonColor: '#6b7280',
            confirmButtonText: p.estado ? '🔴 Desactivar' : '🟢 Activar',
            cancelButtonText: 'Cancelar',
        })
        if (result.isConfirmed) {
            router.patch(route('compras.proveedores.toggle', p.id), {}, {
                onSuccess: () => p.estado
                    ? notify.warn(`${p.razon_social} desactivado`)
                    : notify.ok(`${p.razon_social} activado`),
                onError: () => notify.error('Error al cambiar estado'),
            })
        }
    }

    return (
        <AppLayout title="Proveedores" suppressFlash>
            <Head title="Proveedores" />

            <div className="px-6 pt-6 mb-2">
                <div className="flex items-center gap-3 mb-4">
                    <div className="p-2 rounded-xl"
                         style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <Users size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Proveedores
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Gestión de proveedores nacionales e internacionales
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    <button onClick={() => setModal({ open: true })}
                        className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                        style={{ background: 'var(--primary)' }}
                        onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                        onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}>
                        <Plus size={15} /> Nuevo Proveedor
                    </button>
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 px-6 py-4">
                <StatCard label="Total" value={stats.total} icon={Users}
                    cls="bg-slate-500/15 text-slate-600 dark:text-slate-400"
                    valueCls="text-slate-600 dark:text-slate-400" />
                <StatCard label="Nacionales" value={stats.nacionales} icon={Building2}
                    cls="bg-blue-500/15 text-blue-600 dark:text-blue-400"
                    valueCls="text-blue-600 dark:text-blue-400" />
                <StatCard label="Internacionales" value={stats.internacionales} icon={Globe}
                    cls="bg-purple-500/15 text-purple-600 dark:text-purple-400"
                    valueCls="text-purple-600 dark:text-purple-400" />
                <StatCard label="Con saldo" value={stats.con_saldo} icon={AlertCircle}
                    cls="bg-orange-500/15 text-orange-600 dark:text-orange-400"
                    valueCls="text-orange-600 dark:text-orange-400" />
            </div>

            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-2 px-6 pb-4">
                <div className="relative flex-1 min-w-48 max-w-sm">
                    <Search className="absolute top-1/2 left-2.5 w-4 h-4 -translate-y-1/2 pointer-events-none"
                        style={{ color: 'var(--text-muted)' }} />
                    <Input className="pl-8 pr-8" placeholder="Buscar por nombre, RUC, email…"
                        value={busqueda} onChange={e => setBusqueda(e.target.value)} />
                    {busqueda && (
                        <button onClick={() => setBusqueda('')}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 hover:opacity-70 transition-opacity"
                            style={{ color: 'var(--text-muted)' }}>
                            <X className="w-3.5 h-3.5" />
                        </button>
                    )}
                </div>
            </div>

            {/* Tabla */}
            <div className="px-6 pb-8">
                <div className="border rounded-xl overflow-hidden"
                    style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>

                    {/* Cabecera */}
                    <div className="grid grid-cols-12 gap-3 px-4 py-2.5 border-b text-[11px] font-semibold uppercase tracking-wider"
                        style={{ borderColor: 'var(--border)', background: 'rgba(245,158,11,0.05)', color: 'var(--text-muted)' }}>
                        <span className="col-span-3">Razón social</span>
                        <span className="col-span-2">Identificación</span>
                        <span className="col-span-1">Tipo</span>
                        <span className="col-span-2">Email</span>
                        <span className="col-span-1 text-right">Crédito</span>
                        <span className="col-span-1 text-right">Saldo</span>
                        <span className="col-span-1 text-center">Estado</span>
                        <span className="col-span-1 text-right">Acción</span>
                    </div>

                    {filtrados.length === 0 && (
                        <div className="py-20 text-center">
                            <ShoppingCart className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                {busqueda ? 'No se encontraron proveedores' : 'No hay proveedores registrados'}
                            </p>
                        </div>
                    )}

                    {filtrados.map(p => (
                        <div key={p.id}
                            className={cn(
                                'group grid grid-cols-12 gap-3 px-4 py-3 border-b items-center transition-colors text-sm',
                                !p.estado && 'opacity-50',
                            )}
                            style={{ borderColor: 'var(--border)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.05)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <div className="col-span-3 min-w-0">
                                <p className="font-medium truncate" style={{ color: 'var(--text-main)' }}>{p.razon_social}</p>
                                {p.nombre_comercial && (
                                    <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>{p.nombre_comercial}</p>
                                )}
                            </div>
                            <div className="col-span-2">
                                <p className="font-mono text-xs" style={{ color: 'var(--text-main)' }}>{p.identificacion}</p>
                                <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{p.tipo_identificacion}</p>
                            </div>
                            <div className="col-span-1">
                                <TipoBadge tipo={p.tipo} />
                            </div>
                            <div className="col-span-2 min-w-0">
                                <p className="text-xs truncate" style={{ color: 'var(--text-muted)' }}>{p.email ?? '—'}</p>
                                {p.telefono && <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{p.telefono}</p>}
                            </div>
                            <div className="col-span-1 text-right">
                                {p.tiene_credito
                                    ? <span className="text-xs text-green-600 dark:text-green-400 font-medium">{p.dias_credito}d</span>
                                    : <span className="text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
                                }
                            </div>
                            <div className="col-span-1 text-right">
                                {(p.saldo_pendiente ?? 0) > 0
                                    ? <span className="text-xs font-semibold text-orange-600 dark:text-orange-400">
                                        ${Number(p.saldo_pendiente).toFixed(2)}
                                      </span>
                                    : <span className="text-xs" style={{ color: 'var(--text-muted)' }}>$0.00</span>
                                }
                            </div>
                            <div className="col-span-1 flex justify-center">
                                {p.estado
                                    ? <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Activo</span>
                                    : <span className="px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">Inactivo</span>
                                }
                            </div>
                            <div className="col-span-1 flex justify-end gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onClick={() => setModal({ open: true, proveedor: p })}
                                    title="Editar"
                                    className="p-1.5 rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                    <Pencil className="w-3.5 h-3.5" />
                                </button>
                                <button onClick={() => confirmarToggle(p)}
                                    title={p.estado ? 'Desactivar' : 'Activar'}
                                    className={cn(
                                        'p-1.5 rounded transition-colors',
                                        p.estado
                                            ? 'hover:bg-red-500/20 text-red-500 dark:text-red-400'
                                            : 'hover:bg-green-500/20 text-green-600 dark:text-green-400'
                                    )}>
                                    {p.estado
                                        ? <ToggleRight className="w-3.5 h-3.5" />
                                        : <ToggleLeft className="w-3.5 h-3.5" />
                                    }
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {modal.open && (
                <ProveedorModal
                    proveedor={modal.proveedor}
                    onClose={() => setModal({ open: false })}
                />
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
