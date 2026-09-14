import React, { useState, useMemo } from 'react'
import { createPortal } from 'react-dom'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import { isAxiosError } from 'axios'
import axios from '@/lib/axios'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import BuscadorClienteModal from '@/Components/shared/BuscadorClienteModal'
import DescuentoEspecialModal from '@/Components/Ventas/DescuentoEspecialModal'
import { cn, formatMoneda } from '@/lib/utils'
import { toastError } from '@/lib/toast'
import { Plus, Trash2, Search, Save, X, AlertTriangle } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, Empresa, Usuario, Cliente } from '@/types'

interface ProductoVenta {
    id: number
    codigo: string
    nombre: string
    pvp: number
    pvd: number
    costo: number
    descuento_max: number
    porcentaje_iva: number
    stock_disponible: number
}

interface DetalleLinea {
    producto_id: number | null
    codigo: string
    descripcion: string
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    descuento_valor: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    descuento_especial: boolean
    aprobacion_id: number | null
    descuento_max_producto: number
    // Solo informativo (Bodega Principal UIO) — Proforma es cotización, no
    // reserva ni descuenta stock. Sin lógica de bloqueo al guardar.
    stock_disponible: number | null
    _busqueda: string
    _error: string
    _desc_error: string
}

interface ModalDescuentoState {
    indice: number
    descuentoSolicitado: number
    maxPermitido: number
}

interface Props extends PageProps {
    clientes: Cliente[]
    productos: ProductoVenta[]
    vendedores: Pick<Usuario, 'id' | 'nombre' | 'email'>[]
    empresa_activa: Empresa
    siguiente_numero: string
    limites_descuento: { descuento_maximo_pct: number; puede_aprobar: boolean }
}

function calcularLinea(linea: DetalleLinea): DetalleLinea {
    const base = linea.cantidad * linea.precio_unitario
    const descuento_valor = base * (linea.descuento_pct / 100)
    const subtotal = base - descuento_valor
    const valor_iva = subtotal * (linea.porcentaje_iva / 100)
    return { ...linea, descuento_valor, subtotal, valor_iva, total: subtotal + valor_iva }
}

function lineaVacia(): DetalleLinea {
    return {
        producto_id: null, codigo: '', descripcion: '',
        cantidad: 1, precio_unitario: 0, descuento_pct: 0,
        descuento_valor: 0, subtotal: 0, porcentaje_iva: 15,
        valor_iva: 0, total: 0, descuento_especial: false,
        aprobacion_id: null, descuento_max_producto: 100,
        stock_disponible: null,
        _busqueda: '', _error: '', _desc_error: '',
    }
}

const hoy = new Date().toISOString().slice(0, 10)
const en7dias = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10)

function ClienteField({ label, value, onChange, type = 'text', onKeyDown }: {
    label: string
    value: string
    onChange: (v: string) => void
    type?: 'text' | 'email'
    onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void
}) {
    return (
        <tr style={{ borderBottom: '1px solid var(--border)' }}>
            <td
                className="py-1 px-2 text-xs font-semibold w-28 select-none whitespace-nowrap"
                style={{ color: 'var(--text-muted)' }}
            >
                {label}:
            </td>
            <td className="py-0.5 px-1">
                <Input
                    type={type}
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder=""
                />
            </td>
        </tr>
    )
}

export default function Form() {
    const { clientes, productos, vendedores, siguiente_numero, limites_descuento } = usePage<Props>().props
    const { puede } = usePermiso('ventas')

    // — Cliente
    const [clienteSeleccionado, setClienteSeleccionado] = useState<Cliente | null>(null)
    const [clienteEditado, setClienteEditado] = useState<Partial<Cliente>>({
        tipo_identificacion: '04',
        identificacion: '',
        razon_social: '',
        direccion: '',
        telefono: '',
        email: '',
        ciudad: '',
        pais: 'ECUADOR',
    })
    const [modalCliente, setModalCliente] = useState<Cliente[]>([])
    const [guardandoCliente, setGuardandoCliente] = useState(false)
    const [mensajeCliente, setMensajeCliente] = useState('')

    // — Vendedor
    const [vendedorId, setVendedorId] = useState<number>(vendedores[0]?.id ?? 0)

    // — Vencimiento
    const [fechaVencimiento, setFechaVencimiento] = useState(en7dias)

    // — Líneas
    const [detalles, setDetalles] = useState<DetalleLinea[]>([lineaVacia()])
    const [modalProducto, setModalProducto] = useState<{ idx: number; matches: ProductoVenta[] } | null>(null)

    // — Misc
    const [observaciones, setObservaciones] = useState('')
    const [modal, setModal] = useState<ModalDescuentoState | null>(null)
    const [guardando, setGuardando] = useState(false)
    const [errores, setErrores] = useState<string[]>([])

    // ── Computed ────────────────────────────────────────────────────────────────

    const handleBuscarCliente = () => {
        const q = (clienteEditado.identificacion ?? '').trim()
        if (!q) return
        const matches = clientes.filter(c =>
            c.identificacion.toLowerCase().includes(q.toLowerCase()) ||
            c.razon_social.toLowerCase().includes(q.toLowerCase())
        )
        if (matches.length === 1) {
            seleccionarCliente(matches[0])
        } else if (matches.length >= 2) {
            setModalCliente(matches)
        } else {
            setMensajeCliente('Cliente no encontrado — complete los datos para crear uno nuevo')
        }
    }

    const totales = useMemo(() => {
        let subtotal0 = 0, subtotal15 = 0, descTotal = 0, iva = 0
        for (const d of detalles) {
            if (d.porcentaje_iva === 0) subtotal0 += d.subtotal
            else subtotal15 += d.subtotal
            descTotal += d.descuento_valor
            iva += d.valor_iva
        }
        return { subtotal0, subtotal15, descTotal, iva, total: subtotal0 + subtotal15 + iva }
    }, [detalles])

    // ── Handlers: cliente ────────────────────────────────────────────────────

    const seleccionarCliente = (c: Cliente) => {
        setClienteSeleccionado(c)
        setClienteEditado({ ...c })
        setMensajeCliente('')
        setModalCliente([])
    }

    const handleGuardarCliente = async () => {
        const confirm = await Swal.fire({
            title: clienteSeleccionado?.id ? 'Actualizar cliente' : 'Crear cliente',
            text: `¿Guardar los datos de ${clienteEditado.razon_social ?? 'este cliente'}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#F59E0B',
        })
        if (!confirm.isConfirmed) return
        setGuardandoCliente(true)
        try {
            const { data } = await axios.post<{ cliente: Cliente }>(
                route('ventas.facturas.cliente-guardar'),
                clienteEditado,
            )
            setClienteSeleccionado(data.cliente)
            setClienteEditado({ ...data.cliente })
        } catch (error) {
            // El backend valida con $request->validate() — en 422 devuelve el
            // formato estándar de Laravel ({message, errors}), no {mensaje}
            // como aprobacion.validar. Se lee de ahí el motivo real.
            const respData = isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)
                ? error.response?.data
                : undefined
            const mensaje = (respData?.errors && Object.values(respData.errors).flat().join(' ')) || respData?.message
            toastError(mensaje || 'No se pudo guardar el cliente. Intente nuevamente.')
        } finally {
            setGuardandoCliente(false)
        }
    }

    // ── Handlers: detalles ───────────────────────────────────────────────────

    const updateDetalle = (idx: number, patch: Partial<DetalleLinea>) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({ ...next[idx], ...patch })
            return next
        })
    }

    const handleDescuentoChange = (idx: number, valor: number) => {
        const linea = detalles[idx]

        // Tope de producto/lista de precios — capa dura, sin excepción, ni
        // siquiera con aprobación especial se puede superar.
        if (valor > linea.descuento_max_producto && linea.descuento_max_producto < 100) {
            updateDetalle(idx, {
                descuento_pct: linea.descuento_max_producto,
                descuento_especial: false, aprobacion_id: null,
                _desc_error: `Descuento máximo para este producto: ${linea.descuento_max_producto}%`,
            })
            return
        }

        // Tope del perfil del vendedor — sí se puede superar con PIN de un
        // supervisor, mientras no exceda el tope de producto verificado arriba.
        const limitePerfil = limites_descuento.descuento_maximo_pct
        if (valor > limitePerfil) {
            if (linea.aprobacion_id) {
                updateDetalle(idx, { descuento_pct: valor, _desc_error: '' })
                return
            }
            setModal({ indice: idx, descuentoSolicitado: valor, maxPermitido: limitePerfil })
            return
        }

        updateDetalle(idx, { descuento_pct: valor, descuento_especial: false, aprobacion_id: null, _desc_error: '' })
    }

    const seleccionarProductoLocal = (idx: number, p: ProductoVenta) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({
                ...next[idx],
                producto_id: p.id,
                codigo: p.codigo,
                descripcion: p.nombre,
                precio_unitario: Math.round(p.pvp * 100) / 100,
                porcentaje_iva: p.porcentaje_iva,
                descuento_max_producto: p.descuento_max,
                descuento_pct: 0,
                stock_disponible: p.stock_disponible,
                _busqueda: '',
                _error: '',
                _desc_error: '',
            })
            return next
        })
        setModalProducto(null)
    }

    const handleBuscarProducto = (idx: number, q: string) => {
        if (!q.trim()) return
        const matches = productos.filter(p =>
            p.codigo.toLowerCase().includes(q.toLowerCase()) ||
            p.nombre.toLowerCase().includes(q.toLowerCase())
        )
        if (matches.length === 1) {
            seleccionarProductoLocal(idx, matches[0])
        } else if (matches.length >= 2) {
            setModalProducto({ idx, matches })
        } else {
            updateDetalle(idx, { _error: 'Producto no encontrado' })
        }
    }

    const limpiarProducto = (idx: number) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = lineaVacia()
            return next
        })
    }

    const addDetalle = () => setDetalles(prev => [...prev, lineaVacia()])
    const removeDetalle = (idx: number) => setDetalles(prev => prev.filter((_, i) => i !== idx))

    // ── Submit ───────────────────────────────────────────────────────────────

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        const errs: string[] = []
        if (!clienteSeleccionado) errs.push('Debe seleccionar un cliente.')
        if (!fechaVencimiento) errs.push('La fecha de vencimiento es obligatoria.')
        if (detalles.length === 0) errs.push('Agregue al menos un producto.')
        const pendientes = detalles.filter(d => d.descuento_especial && !d.aprobacion_id)
        if (pendientes.length > 0) errs.push('Hay descuentos especiales sin autorización.')
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])

        setGuardando(true)
        router.post(route('ventas.proformas.store'), {
            cliente_id: clienteSeleccionado!.id,
            vendedor_id: vendedorId,
            fecha_vencimiento: fechaVencimiento,
            observaciones,
            detalles: detalles.map(d => ({
                producto_id: d.producto_id,
                codigo: d.codigo,
                descripcion: d.descripcion,
                cantidad: d.cantidad,
                precio: d.precio_unitario,
                descuento_pct: d.descuento_pct,
                graba_iva: d.porcentaje_iva > 0,
                aprobacion_id: d.aprobacion_id,
            })),
        }, {
            onError: errors => {
                Object.values(errors).forEach(msg => { if (msg) toastError(msg) })
                setGuardando(false)
            },
            onFinish: () => setGuardando(false),
        })
    }

    const vendedorActual = vendedores.find(v => v.id === vendedorId)
    const tipoLabel: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }
    // Slot de altura fija debajo del input de cantidad (16px), mismo patrón
    // de Facturas — aquí es solo informativo, nunca cambia de color a rojo.
    const hintSlotCls = "h-4 mt-0.5 text-[11px] font-medium leading-4 whitespace-nowrap overflow-hidden"

    return (
        <AppLayout>
            <Head title="Nueva Proforma" />
            <PageHeader
                title="Nueva Proforma"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.proformas.index') },
                    { label: 'Proformas', href: route('ventas.proformas.index') },
                    { label: 'Nueva' },
                ]}
            />

            <form onSubmit={handleSubmit} className="p-6 space-y-6 max-w-7xl">

                {/* Errores */}
                {errores.length > 0 && (
                    <div
                        className="rounded-lg p-4 border"
                        style={{ background: 'rgba(239,68,68,.1)', borderColor: 'rgba(239,68,68,.3)' }}
                    >
                        <ul className="space-y-1">
                            {errores.map((e, i) => (
                                <li key={i} className="text-sm text-red-400 flex items-center gap-2">
                                    <AlertTriangle className="w-3.5 h-3.5 shrink-0" /> {e}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {/* ── SECCIÓN 1: Encabezado ── */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Datos del Documento
                    </p>
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Número</Label>
                            <Input
                                className="mt-1 font-mono"
                                value={siguiente_numero}
                                readOnly
                                style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }}
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Fecha emisión</Label>
                            <Input
                                className="mt-1"
                                value={hoy}
                                readOnly
                                style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }}
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Fecha vencimiento *</Label>
                            <Input
                                type="date"
                                className="mt-1"
                                value={fechaVencimiento}
                                min={hoy}
                                onChange={e => setFechaVencimiento(e.target.value)}
                                required
                            />
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Vendedor</Label>
                            {vendedores.length <= 1 ? (
                                <Input
                                    className="mt-1"
                                    value={vendedorActual?.nombre ?? ''}
                                    readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }}
                                />
                            ) : (
                                <select
                                    className="mt-1 w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={vendedorId}
                                    onChange={e => setVendedorId(Number(e.target.value))}
                                >
                                    {vendedores.map(v => (
                                        <option key={v.id} value={v.id}>{v.nombre}</option>
                                    ))}
                                </select>
                            )}
                        </div>
                    </div>
                </div>

                {/* ── SECCIÓN 2: Cliente ── */}
                <div
                    className="rounded-xl p-4 border max-w-xl"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-3" style={{ color: 'var(--text-muted)' }}>
                        Cliente
                    </p>

                    <div style={{ maxWidth: 480 }}>
                        <table className="w-full">
                            <tbody>
                                <ClienteField
                                    label={tipoLabel[clienteEditado.tipo_identificacion ?? '04'] ?? 'RUC/CC'}
                                    value={clienteEditado.identificacion ?? ''}
                                    onChange={v => {
                                        setClienteEditado(p => ({ ...p, identificacion: v }))
                                        setMensajeCliente('')
                                        if (clienteSeleccionado) setClienteSeleccionado(null)
                                    }}
                                    onKeyDown={e => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault()
                                            handleBuscarCliente()
                                        }
                                    }}
                                />
                                <ClienteField
                                    label="NOMBRE"
                                    value={clienteEditado.razon_social ?? ''}
                                    onChange={v => setClienteEditado(p => ({ ...p, razon_social: v }))}
                                />
                                <ClienteField
                                    label="DIRECCIÓN"
                                    value={clienteEditado.direccion ?? ''}
                                    onChange={v => setClienteEditado(p => ({ ...p, direccion: v }))}
                                />
                                <ClienteField
                                    label="TELÉFONO"
                                    value={clienteEditado.telefono ?? ''}
                                    onChange={v => setClienteEditado(p => ({ ...p, telefono: v }))}
                                />
                                <ClienteField
                                    label="EMAIL"
                                    value={clienteEditado.email ?? ''}
                                    onChange={v => setClienteEditado(p => ({ ...p, email: v }))}
                                    type="email"
                                />
                                <ClienteField
                                    label="CIUDAD"
                                    value={clienteEditado.ciudad ?? ''}
                                    onChange={v => setClienteEditado(p => ({ ...p, ciudad: v }))}
                                />
                                <ClienteField
                                    label="PAÍS"
                                    value={clienteEditado.pais ?? 'ECUADOR'}
                                    onChange={v => setClienteEditado(p => ({ ...p, pais: v }))}
                                />
                            </tbody>
                        </table>

                        {mensajeCliente && (
                            <p className="mt-2 text-xs" style={{ color: 'var(--text-muted)' }}>
                                {mensajeCliente}
                            </p>
                        )}

                        <div className="mt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                loading={guardandoCliente}
                                onClick={handleGuardarCliente}
                            >
                                <Save className="w-3.5 h-3.5" />
                                {clienteSeleccionado?.id ? 'Actualizar Cliente' : 'Guardar Cliente'}
                            </Button>
                        </div>
                    </div>
                </div>

                {/* ── SECCIÓN 3: Productos ── */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="flex items-center justify-between mb-4">
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Detalle de Productos
                        </p>
                        <Button type="button" size="sm" onClick={addDetalle}>
                            <Plus className="w-4 h-4" />
                            Agregar producto
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    {[
                                        { label: 'Producto', cls: 'min-w-55' },
                                        { label: 'Cant.', cls: 'w-20 text-right' },
                                        { label: 'Precio Unit.', cls: 'w-24 text-right' },
                                        { label: 'Desc%', cls: 'w-20 text-right' },
                                        { label: 'Subtotal', cls: 'w-24 text-right' },
                                        { label: 'IVA%', cls: 'w-14 text-center' },
                                        { label: 'Total', cls: 'w-24 text-right' },
                                        { label: '', cls: 'w-8' },
                                    ].map((col, i) => (
                                        <th
                                            key={i}
                                            className={cn('py-2 px-2 font-medium text-left', col.cls)}
                                            style={{ color: 'var(--text-muted)' }}
                                        >
                                            {col.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((det, idx) => (
                                        <tr key={idx} style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="py-1 px-1">
                                                {det.producto_id !== null ? (
                                                    <div
                                                        className="flex items-center gap-1 min-w-0 px-1.5 py-0.5 rounded"
                                                        style={{
                                                            border: '1px solid var(--primary)',
                                                            background: 'rgba(245,158,11,0.06)',
                                                        }}
                                                    >
                                                        <span
                                                            className="font-mono font-semibold text-xs shrink-0"
                                                            style={{ color: 'var(--primary)' }}
                                                        >
                                                            {det.codigo}
                                                        </span>
                                                        <span
                                                            className="text-xs truncate flex-1"
                                                            style={{ color: 'var(--text-main)' }}
                                                        >
                                                            {' — '}{det.descripcion}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            className="shrink-0 p-0.5 rounded hover:bg-red-500/10 transition-colors"
                                                            onClick={() => limpiarProducto(idx)}
                                                            title="Limpiar producto"
                                                        >
                                                            <X className="w-3 h-3 text-red-400" />
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div>
                                                        <div className="relative">
                                                            <Search
                                                                className="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 pointer-events-none"
                                                                style={{ color: 'var(--text-muted)' }}
                                                            />
                                                            <input
                                                                type="text"
                                                                className="w-full h-7 pl-6 pr-2 text-xs rounded border focus:outline-none"
                                                                style={{
                                                                    background: 'var(--bg-main)',
                                                                    borderColor: det._error ? '#ef4444' : 'var(--border)',
                                                                    color: 'var(--text-main)',
                                                                }}
                                                                placeholder="Código o nombre... Enter"
                                                                value={det._busqueda}
                                                                onChange={e => updateDetalle(idx, { _busqueda: e.target.value, _error: '' })}
                                                                onKeyDown={e => {
                                                                    if (e.key === 'Enter') {
                                                                        e.preventDefault()
                                                                        handleBuscarProducto(idx, det._busqueda)
                                                                    }
                                                                }}
                                                            />
                                                        </div>
                                                        {det._error && (
                                                            <p className="text-xs mt-0.5" style={{ color: '#ef4444' }}>
                                                                {det._error}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                                <div className={hintSlotCls} />
                                            </td>
                                            <td className="py-1.5 px-2">
                                                <Input
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    value={det.cantidad}
                                                    className="h-7 px-2 text-xs text-right"
                                                    onKeyDown={e => { if (e.key === '.' || e.key === ',') e.preventDefault() }}
                                                    onChange={e => {
                                                        const val = parseInt(e.target.value, 10)
                                                        updateDetalle(idx, { cantidad: isNaN(val) || val < 1 ? 1 : val })
                                                    }}
                                                />
                                                <div className={hintSlotCls} style={{ color: 'var(--color-warning)' }}>
                                                    {det.producto_id !== null && det.stock_disponible !== null
                                                        ? `Stock: ${det.stock_disponible}`
                                                        : ''}
                                                </div>
                                            </td>
                                            <td className="py-1.5 px-2">
                                                <Input type="number" min="0" step="0.01" value={det.precio_unitario} className="h-7 px-2 text-xs text-right" onChange={e => updateDetalle(idx, { precio_unitario: Number(e.target.value) })} />
                                                <div className={hintSlotCls} />
                                            </td>
                                            <td className="py-1.5 px-2 relative">
                                                <Input
                                                    type="number" min="0" max="100" step="0.1"
                                                    value={det.descuento_pct}
                                                    className={cn('h-7 px-2 text-xs text-right pr-6', det.descuento_especial && 'border-amber-500')}
                                                    onChange={e => handleDescuentoChange(idx, Number(e.target.value))}
                                                />
                                                {det.descuento_especial && (
                                                    <AlertTriangle className="absolute right-3 top-1/2 -translate-y-1/2 w-3 h-3 text-amber-500 pointer-events-none" />
                                                )}
                                                <div
                                                    className={hintSlotCls}
                                                    style={{ color: det._desc_error ? 'var(--color-danger)' : 'var(--color-warning)' }}
                                                >
                                                    {det._desc_error || (det.producto_id !== null
                                                        ? `Max. ${det.descuento_max_producto}%`
                                                        : '')}
                                                </div>
                                            </td>
                                            <td className="py-1.5 px-2 text-right font-medium" style={{ color: 'var(--text-main)' }}>{formatMoneda(det.subtotal)}</td>
                                            <td className="py-1.5 px-2 text-center" style={{ color: 'var(--text-muted)' }}>{det.porcentaje_iva}%</td>
                                            <td className="py-1.5 px-2 text-right font-semibold" style={{ color: 'var(--text-main)' }}>{formatMoneda(det.total)}</td>
                                            <td className="py-1.5 px-2">
                                                <button type="button" className="p-1 rounded hover:bg-red-500/10 transition-colors" onClick={() => removeDetalle(idx)}>
                                                    <Trash2 className="w-4 h-4 text-red-400" />
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={3} className="py-3 px-2">
                                        <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                            Subtotal 0%: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.subtotal0)}</strong>
                                            <span className="mx-3">·</span>
                                            Subtotal 15%: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.subtotal15)}</strong>
                                            <span className="mx-3">·</span>
                                            Descuento: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.descTotal)}</strong>
                                        </span>
                                    </td>
                                    <td className="py-3 px-2 text-right text-xs" style={{ color: 'var(--text-muted)' }}>
                                        IVA: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.iva)}</strong>
                                    </td>
                                    <td colSpan={3} className="py-3 px-2 text-right">
                                        <span className="text-base font-bold" style={{ color: 'var(--primary)' }}>{formatMoneda(totales.total)}</span>
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {/* ── SECCIÓN 4: Observaciones ── */}
                <div
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-3" style={{ color: 'var(--text-muted)' }}>Observaciones</p>
                    <textarea
                        rows={3}
                        className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-(--primary) transition-shadow"
                        style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        placeholder="Observaciones adicionales para la proforma..."
                        value={observaciones}
                        onChange={e => setObservaciones(e.target.value)}
                    />
                </div>

                {/* ── SECCIÓN 5: Acciones ── */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('ventas.proformas.index')}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    {puede('crear') && (
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Guardar Proforma
                        </Button>
                    )}
                </div>
            </form>

            {modalProducto !== null && createPortal(
                <>
                    <div
                        className="fixed inset-0"
                        style={{ background: 'rgba(0,0,0,0.5)', zIndex: 50 }}
                        onClick={() => setModalProducto(null)}
                    />
                    <div
                        className="fixed inset-0 flex items-center justify-center p-4"
                        style={{ zIndex: 51 }}
                        onKeyDown={e => { if (e.key === 'Escape') setModalProducto(null) }}
                    >
                        <div
                            className="w-full rounded-xl shadow-xl flex flex-col"
                            style={{ maxWidth: 600, background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                        >
                            <div
                                className="flex items-center justify-between px-4 py-3 shrink-0"
                                style={{ borderBottom: '1px solid var(--border)' }}
                            >
                                <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    Seleccionar Producto
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setModalProducto(null)}
                                    className="p-1 rounded-md transition-colors"
                                    style={{ color: 'var(--text-muted)' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(0,0,0,0.08)')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    <X className="w-4 h-4" />
                                </button>
                            </div>
                            <div style={{ maxHeight: 360, overflowY: 'auto' }}>
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0" style={{ background: 'var(--bg-card)' }}>
                                        <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                            {['Código', 'Nombre', 'PVP'].map(h => (
                                                <th
                                                    key={h}
                                                    className="text-left px-4 py-2 text-xs font-medium"
                                                    style={{ color: 'var(--text-muted)' }}
                                                >
                                                    {h}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {modalProducto.matches.map(p => (
                                            <tr
                                                key={p.id}
                                                onClick={() => seleccionarProductoLocal(modalProducto.idx, p)}
                                                className="cursor-pointer transition-colors"
                                                style={{ borderBottom: '1px solid var(--border)' }}
                                                onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.08)')}
                                                onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                            >
                                                <td
                                                    className="px-4 py-2.5 font-mono text-xs font-semibold whitespace-nowrap"
                                                    style={{ color: 'var(--primary)' }}
                                                >
                                                    {p.codigo}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                    {p.nombre}
                                                </td>
                                                <td className="px-4 py-2.5 text-xs text-right" style={{ color: 'var(--text-muted)' }}>
                                                    {formatMoneda(p.pvp)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div
                                className="px-4 py-2 text-xs shrink-0"
                                style={{ borderTop: '1px solid var(--border)', color: 'var(--text-muted)' }}
                            >
                                {modalProducto.matches.length} resultado{modalProducto.matches.length !== 1 ? 's' : ''}
                            </div>
                        </div>
                    </div>
                </>,
                document.body
            )}

            <BuscadorClienteModal
                coincidencias={modalCliente}
                abierto={modalCliente.length > 0}
                onCerrar={() => setModalCliente([])}
                onSelect={seleccionarCliente}
            />

            {/* Modal descuento especial */}
            <DescuentoEspecialModal
                abierto={modal !== null}
                onCerrar={() => {
                    if (modal) {
                        updateDetalle(modal.indice, { descuento_pct: modal.maxPermitido, descuento_especial: false, aprobacion_id: null })
                        setModal(null)
                    }
                }}
                onAutorizado={aprobacion_id => {
                    if (modal) {
                        updateDetalle(modal.indice, { descuento_pct: modal.descuentoSolicitado, descuento_especial: true, aprobacion_id })
                        setModal(null)
                    }
                }}
                productoNombre={modal ? (detalles[modal.indice]?.descripcion ?? '') : ''}
                descuentoMaximo={modal?.maxPermitido ?? 0}
                descuentoSolicitado={modal?.descuentoSolicitado ?? 0}
            />
        </AppLayout>
    )
}
