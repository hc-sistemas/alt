import { useState, useEffect } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import FilterToolbar from '@/Components/shared/FilterToolbar'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, ToggleLeft, ToggleRight, X,
    FileText, Download, ShoppingCart, Search, Loader2,
} from 'lucide-react'
import type { Proveedor, PageProps } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface Filtros {
    buscar?: string
    tipo?: string
    estado?: string
    credito?: string
}

interface Props extends PageProps {
    proveedores: Proveedor[] | null
    filtros: Filtros
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

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-2xl" onClick={e => e.stopPropagation()}>

                {/* Header */}
                <div className="modal-header">
                    <div>
                        <h2>{isEditar ? 'Editar proveedor' : 'Nuevo proveedor'}</h2>
                        {isEditar && (
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                {proveedor!.identificacion} — {proveedor!.razon_social}
                            </p>
                        )}
                    </div>
                    <button className="modal-close" onClick={onClose}>
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

                <form onSubmit={submit}>
                <div className="modal-body" style={{ gap: '1rem' }}>
                    {/* Identificación */}
                    {!isEditar && (
                        <div className="grid grid-cols-3 gap-3">
                            <div className="space-y-1.5">
                                <Label>Tipo ID <span className="text-red-400">*</span></Label>
                                <select value={data.tipo_identificacion}
                                    onChange={e => setData('tipo_identificacion', e.target.value)}
                                    className="input-field select-field">
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

                </div>
                <div className="modal-footer">
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
    const { proveedores, filtros, flash } = usePage<Props>().props
    const { puede } = usePermiso('compras')

    const [busqueda, setBusqueda] = useState(filtros.buscar ?? '')
    const [tipo,     setTipo]     = useState(filtros.tipo ?? '')
    const [estado,   setEstado]   = useState(filtros.estado ?? '')
    const [credito,  setCredito]  = useState(filtros.credito ?? '')
    const [modal, setModal] = useState<{ open: boolean; proveedor?: Proveedor }>({ open: false })
    const [modalPdf, setModalPdf] = useState(false)
    const [urlPdf,   setUrlPdf]   = useState('')
    const [cargandoPdf, setCargandoPdf] = useState(false)

    // Carga bajo demanda: `proveedores` viene null hasta que el usuario
    // presiona Buscar (aplicarFiltros manda buscado=1) — mismo patrón que
    // Asientos/Plan de Cuentas/Facturas de Compra.
    const haBuscado = proveedores !== null

    useEffect(() => {
        if (flash?.success) notify.ok(flash.success)
        if (flash?.error)   notify.error(flash.error)
    }, [flash?.success, flash?.error])

    function aplicarFiltros() {
        router.get(route('compras.proveedores.index'), {
            ...(busqueda && { buscar: busqueda }),
            ...(tipo     && { tipo }),
            ...(estado   && { estado }),
            ...(credito  && { credito }),
            buscado: '1',
        }, { preserveState: true, replace: true })
    }

    const paramsFiltrosActuales = () => ({
        ...(busqueda && { buscar: busqueda }),
        ...(tipo     && { tipo }),
        ...(estado   && { estado }),
        ...(credito  && { credito }),
    })

    // Se trae el PDF como blob (fetch) en vez de apuntar el <iframe> directo
    // a la URL del backend — mismo patrón que Asientos/Facturas de Compra:
    // un blob: URL siempre se muestra embebido, sin depender de si el
    // navegador decide forzar la descarga en el iframe.
    const abrirPdf = async (url: string) => {
        setModalPdf(true)
        setCargandoPdf(true)
        setUrlPdf('')
        try {
            const res = await fetch(url, { headers: { Accept: 'application/pdf' } })
            if (!res.ok) {
                const err = await res.json().catch(() => ({ message: null })) as { message?: string | null }
                throw new Error(err.message ?? 'No se pudo generar el PDF.')
            }
            const blob = await res.blob()
            setUrlPdf(URL.createObjectURL(blob))
        } catch (e) {
            notify.error(e instanceof Error ? e.message : 'No se pudo generar el PDF. Intenta de nuevo.')
            setModalPdf(false)
        } finally {
            setCargandoPdf(false)
        }
    }

    const cerrarModalPdf = () => {
        if (urlPdf) URL.revokeObjectURL(urlPdf)
        setModalPdf(false)
        setUrlPdf('')
    }

    // ── Excel/PDF grandes (> MAX_FILAS_EXPORT): ofrecer generarlos en
    //    segundo plano en vez de solo bloquear — mismo patrón que Asientos
    //    Contables / Facturas de Compra. ──────────────────────────────────
    const [verificandoExport, setVerificandoExport] = useState<'excel' | 'pdf' | null>(null)
    const [exportandoFondo, setExportandoFondo] = useState<{ formato: 'excel' | 'pdf'; desde: number } | null>(null)

    const confirmarExportacionSegundoPlano = (formato: 'excel' | 'pdf') => {
        router.post(route('compras.proveedores.exportar-segundo-plano'), {
            formato, ...paramsFiltrosActuales(),
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setExportandoFondo({ formato, desde: Date.now() }),
        })
    }

    const iniciarExportacion = async (formato: 'excel' | 'pdf') => {
        setVerificandoExport(formato)
        try {
            const params = new URLSearchParams(paramsFiltrosActuales())
            const res = await fetch(route('compras.proveedores.contar-exportables') + '?' + params)
            if (!res.ok) throw new Error()
            const data = await res.json() as { total: number; limite: number; excede: boolean }

            if (!data.excede) {
                if (formato === 'excel') {
                    window.location.href = route('compras.proveedores.excel') + '?' + params
                } else {
                    abrirPdf(route('compras.proveedores.pdf') + '?' + params)
                }
                return
            }

            const { isConfirmed } = await Swal.fire({
                ...swalBase,
                title: 'Reporte grande',
                html: `
                    <div style="text-align:left;color:#374151;font-size:0.875rem;line-height:1.5">
                        <p>Este reporte tiene <strong>${data.total.toLocaleString('es-EC')}</strong> proveedores con
                        estos filtros — muy grande para generarse al instante (límite: ${data.limite.toLocaleString('es-EC')}).</p>
                        <p style="margin-top:8px">Se procesará en segundo plano y te avisaremos por notificación
                        (campanita) cuando esté listo para descargar.</p>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#F59E0B',
                confirmButtonText: 'Procesar en segundo plano',
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
            })

            if (isConfirmed) confirmarExportacionSegundoPlano(formato)
        } catch {
            notify.error('No se pudo verificar el tamaño del reporte. Intenta de nuevo.')
        } finally {
            setVerificandoExport(null)
        }
    }

    // Sin websockets/polling en el backend — se consulta el mismo endpoint
    // que ya usa la campana de notificaciones (notificaciones.index) cada
    // 15s, mientras haya una exportación en curso, hasta encontrarla o 10
    // minutos.
    useEffect(() => {
        if (!exportandoFondo) return
        const intervalo = setInterval(async () => {
            if (Date.now() - exportandoFondo.desde > 10 * 60 * 1000) {
                setExportandoFondo(null)
                return
            }
            try {
                const res = await fetch(route('notificaciones.index'))
                if (!res.ok) return
                const data = await res.json() as { notificaciones: { tipo: string; created_at: string }[] }
                const lista = data.notificaciones.some(n =>
                    (n.tipo === 'exportacion_proveedores' || n.tipo === 'exportacion_proveedores_error') &&
                    new Date(n.created_at).getTime() >= exportandoFondo.desde
                )
                if (lista) {
                    notify.ok('Tu exportación terminó de procesarse — revisa la campana de notificaciones para descargarla.')
                    setExportandoFondo(null)
                }
            } catch { /* red momentáneamente caída — se reintenta en el próximo tick */ }
        }, 15000)
        return () => clearInterval(intervalo)
    }, [exportandoFondo])

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

            <PageHeader
                title="Proveedores"
                breadcrumbs={[{ label: 'Compras' }, { label: 'Proveedores' }]}
                actions={
                    puede('crear') ? (
                        <button onClick={() => setModal({ open: true })}
                            className="btn-primary flex items-center gap-2 whitespace-nowrap shrink-0">
                            <Plus size={15} /> Nuevo
                        </button>
                    ) : undefined
                }
            />

            {exportandoFondo && (
                <div className="flex items-center gap-2 text-xs rounded-lg px-3 py-2 mx-6 mt-4"
                    style={{ background: 'color-mix(in srgb, var(--primary) 12%, var(--bg-main))', color: 'var(--text-main)' }}>
                    <Loader2 className="w-3.5 h-3.5 animate-spin shrink-0" style={{ color: 'var(--primary)' }} />
                    <span>
                        Tu {exportandoFondo.formato === 'excel' ? 'Excel' : 'PDF'} se está procesando en segundo
                        plano — te avisaremos por notificación cuando esté listo.
                    </span>
                </div>
            )}

            <div className="px-6 pt-6 mb-2">
                {/*
                    Ancho vía `style.width` inline a propósito, NO clases Tailwind: `.input-field`
                    (app.css) declara `width:100%` fuera de cualquier @layer, y las utilidades de
                    Tailwind v4 viven dentro de su @layer utilities interno — por reglas de CSS
                    Cascade Layers, lo no-layereado siempre gana sobre lo layereado sin importar
                    especificidad ni orden, así que un w-XX de Tailwind nunca puede ganarle a
                    `.input-field`. Mismo hallazgo documentado en Asientos/Facturas de Compra.
                */}
                <div className="overflow-x-auto">
                <div style={{ minWidth: '950px' }}>
                <FilterToolbar
                    search={{
                        value: busqueda,
                        onChange: setBusqueda,
                        onSearch: aplicarFiltros,
                        placeholder: 'Nombre, RUC, email...',
                    }}
                    searchWidth="w-[130px]"
                    onExport={() => iniciarExportacion('excel')}
                    exportDisabled={verificandoExport !== null}
                    exportTitle={verificandoExport === 'excel' ? 'Verificando tamaño…' : 'Exportar a Excel'}
                    extraActions={
                        <button
                            type="button"
                            onClick={() => iniciarExportacion('pdf')}
                            disabled={verificandoExport !== null}
                            title={verificandoExport === 'pdf' ? 'Verificando tamaño…' : 'PDF'}
                            className="flex items-center justify-center w-9 h-9 rounded-md border text-sm font-medium shrink-0 disabled:opacity-40 disabled:cursor-not-allowed"
                            style={{ background: '#EF4444', color: 'white', borderColor: '#EF4444' }}>
                            <FileText className="w-4 h-4" />
                        </button>
                    }
                >
                    <select value={tipo} onChange={e => setTipo(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '160px' }}>
                        <option value="">Todos los tipos</option>
                        <option value="nacional">Nacional</option>
                        <option value="internacional">Internacional</option>
                    </select>
                    <select value={estado} onChange={e => setEstado(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '150px' }}>
                        <option value="">Todos los estados</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                    <select value={credito} onChange={e => setCredito(e.target.value)}
                        className="input-field shrink-0 text-xs"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)', width: '160px' }}>
                        <option value="">Todo (crédito)</option>
                        <option value="con">Con crédito</option>
                        <option value="sin">Sin crédito</option>
                    </select>
                </FilterToolbar>
                </div>
                </div>
            </div>

            {/* Estado inicial: aún no se ha buscado (carga bajo demanda) */}
            {!haBuscado && (
                <div className="px-6 pb-8">
                    <div className="text-center py-16">
                        <Search className="w-12 h-12 mx-auto mb-4 opacity-30" style={{ color: 'var(--text-muted)' }} />
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Ajusta los filtros y presiona Buscar para consultar los proveedores.
                        </p>
                    </div>
                </div>
            )}

            {/* Tabla */}
            {haBuscado && (
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

                    {proveedores.length === 0 && (
                        <div className="py-20 text-center">
                            <ShoppingCart className="opacity-20 mx-auto mb-3 w-10 h-10" style={{ color: 'var(--text-muted)' }} />
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                No se encontraron proveedores con estos filtros
                            </p>
                        </div>
                    )}

                    {proveedores.map(p => (
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
                                {puede('editar') && (
                                    <button onClick={() => setModal({ open: true, proveedor: p })}
                                        title="Editar"
                                        className="p-1.5 rounded hover:bg-blue-500/20 text-blue-500 dark:text-blue-400 transition-colors">
                                        <Pencil className="w-3.5 h-3.5" />
                                    </button>
                                )}
                                {puede('editar') && (
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
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
            )}

            {modal.open && (
                <ProveedorModal
                    proveedor={modal.proveedor}
                    onClose={() => setModal({ open: false })}
                />
            )}

            {/* ── Modal PDF ── */}
            {modalPdf && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.85)' }}
                     onClick={cerrarModalPdf}>
                    <div className="w-full max-w-5xl rounded-2xl overflow-hidden shadow-2xl flex flex-col"
                         style={{ background: 'var(--bg-card)', height: '90vh' }}
                         onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between px-4 py-3 border-b shrink-0"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm flex items-center gap-2"
                                style={{ color: 'var(--text-main)' }}>
                                <FileText size={16} style={{ color: '#ef4444' }} />
                                Reporte de Proveedores
                            </h3>
                            <div className="flex items-center gap-2">
                                {urlPdf && (
                                    <a href={urlPdf} download={`proveedores-${new Date().toISOString().slice(0, 10)}.pdf`}
                                       className="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-all hover:opacity-90"
                                       style={{ background: '#ef4444' }}>
                                        <Download size={13} /> Descargar
                                    </a>
                                )}
                                <button onClick={cerrarModalPdf}
                                    className="px-3 py-1.5 rounded-lg text-xs font-semibold border hover:opacity-80"
                                    style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                    ✕ Cerrar
                                </button>
                            </div>
                        </div>
                        {cargandoPdf ? (
                            <div className="flex-1 flex items-center justify-center text-sm" style={{ color: 'var(--text-muted)' }}>
                                Generando PDF…
                            </div>
                        ) : (
                            <iframe src={urlPdf} className="flex-1 w-full border-0" title="Reporte PDF Proveedores" />
                        )}
                    </div>
                </div>
            )}

            <ToastContainer position="top-right" autoClose={3500} hideProgressBar={false}
                newestOnTop closeOnClick pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500',
                    boxShadow: '0 8px 32px rgba(0,0,0,.18)', padding: '14px 18px', minWidth: '280px' }} />
        </AppLayout>
    )
}
