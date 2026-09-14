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
}

interface DetalleLinea {
    producto_id: number | null
    codigo: string
    descripcion: string
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    _busqueda: string
    _error: string
    _desc_error: string
    _disponible: number | null
    _disponibleCargando: boolean
    _disponibleError: string
}

interface Props extends PageProps {
    clientes: Cliente[]
    productos: ProductoVenta[]
    vendedores: Pick<Usuario, 'id' | 'nombre' | 'email'>[]
    empresa_activa: Empresa
    siguiente_numero: string
    limites_descuento: { descuento_maximo_pct: number; puede_aprobar: boolean } | null
}

function calcularLinea(linea: DetalleLinea): DetalleLinea {
    const subtotal = linea.cantidad * linea.precio_unitario
    const valor_iva = subtotal * (linea.porcentaje_iva / 100)
    return { ...linea, subtotal, valor_iva, total: subtotal + valor_iva }
}

function lineaVacia(): DetalleLinea {
    return {
        producto_id: null, codigo: '', descripcion: '',
        cantidad: 1, precio_unitario: 0, descuento_pct: 0,
        subtotal: 0, porcentaje_iva: 15, valor_iva: 0, total: 0,
        _busqueda: '', _error: '', _desc_error: '',
        _disponible: null, _disponibleCargando: false, _disponibleError: '',
    }
}

async function consultarSaldoDisponible(productoId: number): Promise<number> {
    const res = await fetch(
        route('ventas.prefacturas.saldo-disponible', { producto_id: productoId }),
        { headers: { Accept: 'application/json' } },
    )
    if (!res.ok) {
        const data = await res.json().catch(() => null) as { error?: string } | null
        throw new Error(data?.error || 'No se pudo consultar el stock.')
    }
    const data = await res.json() as { disponible: number }
    return data.disponible
}

const hoy = new Date().toISOString().slice(0, 10)

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
    const { clientes, productos, vendedores, siguiente_numero, errors } = usePage<Props>().props
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

    // — Líneas
    const [detalles, setDetalles] = useState<DetalleLinea[]>([lineaVacia()])
    const [modalProducto, setModalProducto] = useState<{ idx: number; matches: ProductoVenta[] } | null>(null)

    // — Misc
    const [observaciones, setObservaciones] = useState('')
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
        let subtotal = 0, iva = 0
        for (const d of detalles) {
            subtotal += d.subtotal
            iva += d.valor_iva
        }
        return { subtotal, iva, total: subtotal + iva }
    }, [detalles])

    // Suma cantidades de un mismo producto repetido en varias líneas, para no
    // dejar pasar por partes lo que junto sí supera el disponible. Todas las
    // líneas descuentan de la misma Bodega Principal UIO fija.
    const excesosStock = useMemo(() => {
        const sumas = new Map<number, number>()
        for (const d of detalles) {
            if (d.producto_id === null) continue
            sumas.set(d.producto_id, (sumas.get(d.producto_id) ?? 0) + d.cantidad)
        }
        return detalles.map(d => {
            if (d.producto_id === null || d._disponible === null) return false
            return (sumas.get(d.producto_id) ?? 0) > d._disponible
        })
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

    // Consulta InventarioService::getSaldoDisponible (Bodega Principal UIO)
    // para la línea idx. Si falla por red/servidor, degrada a advertencia y
    // no bloquea el formulario.
    const actualizarDisponible = (idx: number, productoId: number | null) => {
        if (productoId === null) {
            setDetalles(prev => {
                const next = [...prev]
                if (next[idx]) next[idx] = { ...next[idx], _disponible: null, _disponibleError: '', _disponibleCargando: false }
                return next
            })
            return
        }
        setDetalles(prev => {
            const next = [...prev]
            if (next[idx]) next[idx] = { ...next[idx], _disponibleCargando: true, _disponibleError: '' }
            return next
        })
        consultarSaldoDisponible(productoId)
            .then(disponible => {
                setDetalles(prev => {
                    const next = [...prev]
                    const linea = next[idx]
                    if (linea && linea.producto_id === productoId) {
                        next[idx] = { ...linea, _disponible: disponible, _disponibleCargando: false, _disponibleError: '' }
                    }
                    return next
                })
            })
            .catch((err: unknown) => {
                setDetalles(prev => {
                    const next = [...prev]
                    const linea = next[idx]
                    if (linea && linea.producto_id === productoId) {
                        next[idx] = {
                            ...linea,
                            _disponible: null,
                            _disponibleCargando: false,
                            _disponibleError: err instanceof Error ? err.message : 'No se pudo consultar el stock. Verifique manualmente.',
                        }
                    }
                    return next
                })
            })
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
                _busqueda: '',
                _error: '',
                _desc_error: '',
            })
            return next
        })
        setModalProducto(null)
        actualizarDisponible(idx, p.id)
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

        // Validación de stock disponible en Bodega Principal UIO — feedback
        // vía toast, sin duplicar en el bloque rojo.
        if (excesosStock.some(Boolean)) {
            toastError('Hay productos con cantidad mayor al stock disponible en Bodega Principal UIO.')
            return
        }

        const errs: string[] = []
        if (!clienteSeleccionado) errs.push('Debe seleccionar un cliente.')
        if (detalles.length === 0) errs.push('Agregue al menos un producto.')
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])
        setGuardando(true)
        router.post(route('ventas.prefacturas.store'), {
            cliente_id: clienteSeleccionado!.id,
            vendedor_id: vendedorId,
            observaciones,
            detalles: detalles.map(d => ({
                producto_id:  d.producto_id,
                descripcion:  d.descripcion,
                cantidad:     d.cantidad,
                precio:       d.precio_unitario,
                descuento_pct: d.descuento_pct,
                graba_iva:    d.porcentaje_iva > 0,
            })),
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    const vendedorActual = vendedores.find(v => v.id === vendedorId)
    const tipoLabel: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }
    // Slot de altura fija debajo del input de cantidad (16px), siempre
    // presente con o sin texto, mismo patrón usado en Facturas.
    const hintSlotCls = "h-4 mt-0.5 text-[11px] font-medium leading-4 whitespace-nowrap overflow-hidden"

    return (
        <AppLayout>
            <Head title="Nueva Prefactura" />
            <PageHeader
                title="Nueva Prefactura"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.prefacturas.index') },
                    { label: 'Prefacturas', href: route('ventas.prefacturas.index') },
                    { label: 'Nueva' },
                ]}
            />

            <form onSubmit={handleSubmit} className="p-6 space-y-6 max-w-7xl">

                {/* Errores del servidor */}
                {errors?.error && (
                    <div
                        className="rounded-lg p-4 border"
                        style={{ background: 'rgba(239,68,68,.1)', borderColor: 'rgba(239,68,68,.3)' }}
                    >
                        <p className="text-sm text-red-400 flex items-center gap-2">
                            <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                            {errors.error}
                        </p>
                    </div>
                )}

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
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                            <Label style={{ color: 'var(--text-main)' }}>Fecha</Label>
                            <Input
                                className="mt-1"
                                value={hoy}
                                readOnly
                                style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }}
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
                                    {['Producto', 'Cant.', 'Precio Unit.', 'IVA%', 'Total', ''].map((col, i) => (
                                        <th
                                            key={i}
                                            className={cn('py-2 px-2 font-medium text-left', (i >= 1 && i <= 2) && 'text-right')}
                                            style={{ color: 'var(--text-muted)' }}
                                        >
                                            {col}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((det, idx) => (
                                    <tr key={idx} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="py-1 px-1 align-top">
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
                                        </td>
                                        <td className="py-1.5 px-2 align-top" style={{ minWidth: 72 }}>
                                            <Input
                                                type="number"
                                                min="1"
                                                step="1"
                                                value={det.cantidad}
                                                className="text-xs text-right"
                                                style={{ borderColor: excesosStock[idx] ? 'var(--color-danger)' : undefined }}
                                                onChange={e => updateDetalle(idx, { cantidad: parseInt(e.target.value) || 1 })}
                                                onKeyDown={e => { if (e.key === '.' || e.key === ',') e.preventDefault() }}
                                            />
                                            <div
                                                className={hintSlotCls}
                                                style={{
                                                    color: det._disponibleCargando
                                                        ? 'var(--text-muted)'
                                                        : excesosStock[idx]
                                                            ? 'var(--color-danger)'
                                                            : 'var(--color-warning)',
                                                }}
                                            >
                                                {det.producto_id !== null && (
                                                    det._disponibleCargando
                                                        ? 'Consultando...'
                                                        : det._disponibleError
                                                            ? det._disponibleError
                                                            : det._disponible !== null
                                                                ? `Stock: ${det._disponible}`
                                                                : ''
                                                )}
                                            </div>
                                        </td>
                                        <td className="py-1.5 px-2 align-top" style={{ minWidth: 96 }}>
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={det.precio_unitario}
                                                className="text-xs text-right"
                                                onChange={e => updateDetalle(idx, { precio_unitario: Number(e.target.value) })}
                                            />
                                        </td>
                                        <td className="py-1.5 px-2 text-center align-top" style={{ color: 'var(--text-muted)' }}>{det.porcentaje_iva}%</td>
                                        <td className="py-1.5 px-2 text-right font-semibold align-top" style={{ color: 'var(--text-main)' }}>{formatMoneda(det.total)}</td>
                                        <td className="py-1.5 px-2 align-top">
                                            <button type="button" className="p-1 rounded hover:bg-red-500/10 transition-colors" onClick={() => removeDetalle(idx)}>
                                                <Trash2 className="w-4 h-4 text-red-400" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={2} className="py-3 px-2">
                                        <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                            Subtotal: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.subtotal)}</strong>
                                            <span className="mx-3">·</span>
                                            IVA: <strong style={{ color: 'var(--text-main)' }}>{formatMoneda(totales.iva)}</strong>
                                        </span>
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
                        placeholder="Observaciones adicionales para la prefactura..."
                        value={observaciones}
                        onChange={e => setObservaciones(e.target.value)}
                    />
                </div>

                {/* ── SECCIÓN 5: Acciones ── */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('ventas.prefacturas.index')}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    {puede('crear') && (
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Guardar Prefactura
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
        </AppLayout>
    )
}
