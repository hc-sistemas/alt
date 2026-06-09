import React, { useState, useMemo, useEffect } from 'react'
import { createPortal } from 'react-dom'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import BuscadorClienteModal from '@/Components/shared/BuscadorClienteModal'
import DescuentoEspecialModal from '@/Components/Ventas/DescuentoEspecialModal'
import { cn, formatMoneda } from '@/lib/utils'
import { Plus, Save, X, AlertTriangle, Send, Search } from 'lucide-react'
import type { PageProps, Empresa, Usuario, Cliente, LimiteDescuento } from '@/types'

// ── Interfaces locales ────────────────────────────────────────────────────────

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
    serie: string
    cantidad: number
    precio_unitario: number
    descuento_pct: number
    descuento_valor: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    descuento_max_producto: number
    _busqueda: string
    _error: string
    _desc_error: string
}

interface FormaPagoLinea {
    forma_pago: string
    valor: number
    dias_credito: number
    fecha_vencimiento: string | null
    banco: string | null
    num_cheque: string | null
}

interface Props extends PageProps {
    clientes: Cliente[]
    productos: ProductoVenta[]
    vendedores: Pick<Usuario, 'id' | 'nombre' | 'email'>[]
    formas_pago: string[]
    empresa_activa: Empresa
    siguiente_numero: string
    limite_descuento: LimiteDescuento | null
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function calcularLinea(linea: DetalleLinea): DetalleLinea {
    const base = linea.cantidad * linea.precio_unitario
    const descuento_valor = base * (linea.descuento_pct / 100)
    const subtotal = base - descuento_valor
    const valor_iva = subtotal * (linea.porcentaje_iva / 100)
    return { ...linea, descuento_valor, subtotal, valor_iva, total: subtotal + valor_iva }
}

function lineaVacia(): DetalleLinea {
    return {
        producto_id: null, codigo: '', descripcion: '', serie: '',
        cantidad: 1, precio_unitario: 0, descuento_pct: 0,
        descuento_valor: 0, subtotal: 0, porcentaje_iva: 15,
        valor_iva: 0, total: 0, descuento_max_producto: 100,
        _busqueda: '', _error: '', _desc_error: '',
    }
}

function pagoVacio(formas: string[]): FormaPagoLinea {
    return {
        forma_pago: formas[0] ?? 'efectivo',
        valor: 0, dias_credito: 0,
        fecha_vencimiento: null, banco: null, num_cheque: null,
    }
}

const hoy = new Date().toLocaleDateString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric' })

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
}

// ── ClienteField ──────────────────────────────────────────────────────────────

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

// ── Componente principal ──────────────────────────────────────────────────────

export default function Form() {
    const {
        clientes,
        productos,
        vendedores,
        formas_pago,
        siguiente_numero,
        limite_descuento,
        auth,
    } = usePage<Props>().props

    const esVendedor = auth.user?.perfil === 'vendedor'

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
    const [guardandoCliente, setGuardandoCliente] = useState(false)
    const [modalCliente, setModalCliente] = useState<Cliente[]>([])
    const [mensajeCliente, setMensajeCliente] = useState('')

    // — Descuento especial global
    const [descuentoEspecialActivo, setDescuentoEspecialActivo] = useState(false)
    const [aprobacionGlobalId, setAprobacionGlobalId] = useState<number | null>(null)
    const [modalDescuentoGlobal, setModalDescuentoGlobal] = useState(false)

    // — Vendedor
    const [vendedorId, setVendedorId] = useState<number>(
        esVendedor ? (auth.user?.id ?? 0) : (vendedores[0]?.id ?? 0)
    )

    // — Líneas de detalle
    const [detalles, setDetalles] = useState<DetalleLinea[]>([lineaVacia()])

    // — Modal producto
    const [modalProducto, setModalProducto] = useState<{ idx: number; matches: ProductoVenta[] } | null>(null)

    // — Forma de pago (fila única)
    const [pago, setPago] = useState<FormaPagoLinea>(() => pagoVacio(formas_pago))

    // — Misc
    const [observaciones, setObservaciones] = useState('')
    const [guardando, setGuardando] = useState(false)
    const [errores, setErrores] = useState<string[]>([])

    useEffect(() => {
        const isOpen = modalCliente.length > 0 || modalProducto !== null || modalDescuentoGlobal
        document.body.style.overflow = isOpen ? 'hidden' : ''
        return () => { document.body.style.overflow = '' }
    }, [modalCliente.length, modalProducto, modalDescuentoGlobal])

    // ── Computed ──────────────────────────────────────────────────────────────

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

    const diferencia = Math.round((pago.valor - totales.total) * 100) / 100

    // ── Handlers: descuento especial global ──────────────────────────────────

    const handleCheckboxDescuento = async (checked: boolean) => {
        if (checked) {
            setModalDescuentoGlobal(true)
        } else if (descuentoEspecialActivo) {
            const result = await Swal.fire({
                title: '¿Desactivar descuento especial?',
                text: 'Los descuentos que superen el máximo serán revertidos.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#F59E0B',
            })
            if (result.isConfirmed) {
                setDescuentoEspecialActivo(false)
                setAprobacionGlobalId(null)
                setDetalles(prev => prev.map(d => {
                    if (d.descuento_pct > d.descuento_max_producto && d.descuento_max_producto < 100) {
                        return calcularLinea({ ...d, descuento_pct: d.descuento_max_producto, _desc_error: '' })
                    }
                    return d
                }))
            }
        }
    }

    // ── Handlers: cliente ─────────────────────────────────────────────────────

    const seleccionarCliente = (c: Cliente) => {
        setClienteSeleccionado(c)
        setClienteEditado({ ...c })
        setMensajeCliente('')
    }

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
            const res = await fetch(route('ventas.facturas.cliente-guardar'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(clienteEditado),
            })
            const data = await res.json() as { cliente: Cliente }
            setClienteSeleccionado(data.cliente)
            setClienteEditado({ ...data.cliente })
        } catch {
            // silencioso
        } finally {
            setGuardandoCliente(false)
        }
    }

    // ── Handlers: detalles ────────────────────────────────────────────────────

    const updateDetalle = (idx: number, patch: Partial<DetalleLinea>) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({ ...next[idx], ...patch })
            return next
        })
    }

    const handleDescuentoChange = (idx: number, valor: number) => {
        const linea = detalles[idx]
        if (!descuentoEspecialActivo && valor > linea.descuento_max_producto && linea.descuento_max_producto < 100) {
            updateDetalle(idx, {
                descuento_pct: linea.descuento_max_producto,
                _desc_error: `Descuento máximo para este producto: ${linea.descuento_max_producto}%`,
            })
        } else {
            updateDetalle(idx, { descuento_pct: valor, _desc_error: '' })
        }
    }

    const seleccionarProductoLocal = (idx: number, p: ProductoVenta) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({
                ...next[idx],
                producto_id: p.id,
                codigo: p.codigo,
                descripcion: p.nombre,
                precio_unitario: p.pvp,
                porcentaje_iva: p.porcentaje_iva,
                descuento_max_producto: p.descuento_max,
                descuento_pct: 0,
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

    // ── Submit ────────────────────────────────────────────────────────────────

    const handleSubmit = (e: { preventDefault(): void }) => {
        e.preventDefault()
        const errs: string[] = []
        if (!clienteSeleccionado) errs.push('Debe seleccionar un cliente.')
        if (detalles.length === 0) errs.push('Agregue al menos un producto.')
        if (Math.abs(diferencia) > 0.01) errs.push(`Las formas de pago no cuadran. Diferencia: ${formatMoneda(Math.abs(diferencia))}`)
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])

        setGuardando(true)
        router.post(route('ventas.facturas.store'), {
            cliente_id: clienteSeleccionado!.id,
            vendedor_id: vendedorId,
            observaciones,
            tiene_descuento_especial: descuentoEspecialActivo,
            aprobacion_especial: aprobacionGlobalId,
            detalles: detalles.map(d => ({
                producto_id: d.producto_id,
                codigo: d.codigo,
                descripcion: d.descripcion,
                serie: d.serie,
                cantidad: d.cantidad,
                precio: d.precio_unitario,
                descuento_pct: d.descuento_pct,
                graba_iva: d.porcentaje_iva > 0,
                aprobacion_id: null,
            })),
            formas_pago: [{
                forma: pago.forma_pago,
                monto: pago.valor,
                plazo: pago.dias_credito ? String(pago.dias_credito) : null,
                banco: pago.banco,
                num_cheque: pago.num_cheque,
            }],
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    const vendedorActual = vendedores.find(v => v.id === vendedorId)
    const tipoLabel: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }

    const tdInput = "w-full text-xs py-1 px-1.5 rounded border focus:outline-none"
    const tdInputStyle = { background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }

    void limite_descuento

    // ── Render ────────────────────────────────────────────────────────────────

    return (
        <AppLayout>
            <Head title="Nueva Factura" />
            <PageHeader
                title="Nueva Factura"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.facturas.index') },
                    { label: 'Facturas', href: route('ventas.facturas.index') },
                    { label: 'Nueva' },
                ]}
            />

            <form onSubmit={handleSubmit} className="p-4 space-y-4 max-w-7xl">

                {/* Errores globales */}
                {errores.length > 0 && (
                    <div
                        className="rounded-lg p-3 border"
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

                {/* ── 1. Encabezado compacto ── */}
                <div
                    className="flex flex-wrap items-center gap-6 px-4 py-2.5 rounded-xl border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                        Factura N°:{' '}
                        <span className="font-mono font-semibold" style={{ color: 'var(--text-main)' }}>
                            {siguiente_numero}
                        </span>
                    </span>
                    <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                        Fecha:{' '}
                        <span className="font-medium" style={{ color: 'var(--text-main)' }}>{hoy}</span>
                    </span>
                    <button
                        type="button"
                        onClick={() => void handleCheckboxDescuento(!descuentoEspecialActivo)}
                        className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-600 text-white text-sm font-medium focus:outline-none"
                    >
                        <span>Descuento Especial</span>
                        <div className="w-8 h-4 bg-red-800 rounded-full relative">
                            <div className={cn(
                                'absolute top-0.5 w-3 h-3 bg-white rounded-full transition-all',
                                descuentoEspecialActivo ? 'left-4' : 'left-0.5'
                            )} />
                        </div>
                    </button>
                    {descuentoEspecialActivo && (
                        <span
                            className="text-xs font-semibold px-2 py-0.5 rounded-full"
                            style={{ background: 'rgba(239,68,68,.15)', color: 'rgb(239,68,68)' }}
                        >
                            DESCUENTO ESPECIAL ACTIVO
                        </span>
                    )}
                </div>

                {/* ── 2. Cliente ── */}
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

                {/* ── 3. Tabla de productos ── */}
                <div
                    className="rounded-xl p-4 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="flex items-center justify-between mb-3">
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Detalle de Productos
                        </p>
                        <Button type="button" size="sm" onClick={addDetalle}>
                            <Plus className="w-3.5 h-3.5" />
                            Agregar producto
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    {[
                                        { label: 'N°', cls: 'w-8 text-center' },
                                        { label: 'Producto', cls: 'min-w-[220px]' },
                                        { label: 'Cant', cls: 'w-16 text-right' },
                                        { label: 'Precio', cls: 'w-24 text-right' },
                                        { label: 'Desc%', cls: 'w-20 text-right' },
                                        { label: 'Desc$', cls: 'w-20 text-right' },
                                        { label: 'V.Tot', cls: 'w-24 text-right' },
                                        { label: '', cls: 'w-8' },
                                    ].map((col, i) => (
                                        <th
                                            key={i}
                                            className={cn('py-1.5 px-1.5 font-medium text-left', col.cls)}
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

                                        {/* N° */}
                                        <td className="py-1 px-1.5 text-center" style={{ color: 'var(--text-muted)' }}>
                                            {idx + 1}
                                        </td>

                                        {/* Producto */}
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
                                        </td>

                                        {/* Cantidad — enteros */}
                                        <td className="py-1 px-1">
                                            <input
                                                type="number"
                                                min={1}
                                                step="1"
                                                className={cn(tdInput, 'text-right')}
                                                style={tdInputStyle}
                                                value={det.cantidad}
                                                onKeyDown={e => {
                                                    if (e.key === '.' || e.key === ',') e.preventDefault()
                                                }}
                                                onChange={e => {
                                                    const val = parseInt(e.target.value, 10)
                                                    updateDetalle(idx, { cantidad: isNaN(val) || val < 1 ? 1 : val })
                                                }}
                                            />
                                        </td>

                                        {/* Precio */}
                                        <td className="py-1 px-1">
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className={cn(tdInput, 'text-right')}
                                                style={tdInputStyle}
                                                value={det.precio_unitario}
                                                onChange={e => updateDetalle(idx, { precio_unitario: Number(e.target.value) })}
                                            />
                                        </td>

                                        {/* Desc% */}
                                        <td className="py-1 px-1">
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.1"
                                                className={cn(tdInput, 'text-right')}
                                                style={tdInputStyle}
                                                value={det.descuento_pct}
                                                onChange={e => handleDescuentoChange(idx, Number(e.target.value))}
                                            />
                                            {det._desc_error && (
                                                <p className="text-xs mt-0.5 whitespace-nowrap" style={{ color: '#ef4444' }}>
                                                    {det._desc_error}
                                                </p>
                                            )}
                                        </td>

                                        {/* Desc$ */}
                                        <td className="py-1 px-1.5 text-right" style={{ color: 'var(--text-muted)' }}>
                                            {formatMoneda(det.descuento_valor)}
                                        </td>

                                        {/* V.Tot */}
                                        <td className="py-1 px-1.5 text-right font-semibold" style={{ color: 'var(--text-main)' }}>
                                            {formatMoneda(det.total)}
                                        </td>

                                        {/* Eliminar */}
                                        <td className="py-1 px-1 text-center">
                                            <button
                                                type="button"
                                                className="p-0.5 rounded hover:bg-red-500/10 transition-colors"
                                                onClick={() => removeDetalle(idx)}
                                                title="Eliminar fila"
                                            >
                                                <X className="w-3.5 h-3.5 text-red-400" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ── 4. Vendedor + Formas de pago ── */}
                <div
                    className="rounded-xl p-4 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    {/* Vendedor */}
                    <div className="flex items-center gap-2 mb-3">
                        <span className="text-xs font-semibold uppercase" style={{ color: 'var(--text-muted)' }}>
                            Vendedor:
                        </span>
                        {esVendedor ? (
                            <span className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>
                                {auth.user?.nombre ?? vendedorActual?.nombre ?? '—'}
                            </span>
                        ) : (
                            <select
                                className="h-7 rounded-md border px-2 text-sm"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={vendedorId}
                                onChange={e => setVendedorId(Number(e.target.value))}
                            >
                                {vendedores.map(v => (
                                    <option key={v.id} value={v.id}>{v.nombre}</option>
                                ))}
                            </select>
                        )}
                        <span
                            className="ml-auto text-xs font-semibold uppercase tracking-wider"
                            style={{ color: 'var(--text-muted)' }}
                        >
                            Formas de Pago
                        </span>
                    </div>

                    {/* Fila única de pago */}
                    <div className="flex flex-wrap items-end gap-3">

                        {/* Forma */}
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>FORMA</p>
                            <select
                                className="h-8 rounded-md border px-2 text-sm capitalize"
                                style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={pago.forma_pago}
                                onChange={e => setPago(prev => ({
                                    ...prev,
                                    forma_pago: e.target.value,
                                    dias_credito: e.target.value === 'credito'
                                        ? (clienteSeleccionado?.dias_credito ?? 30)
                                        : 0,
                                }))}
                            >
                                {formas_pago.map(f => (
                                    <option key={f} value={f} className="capitalize">{f}</option>
                                ))}
                            </select>
                        </div>

                        {/* N_DOC */}
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>
                                {pago.forma_pago === 'cheque' ? 'N° CHEQUE' : 'N_DOC'}
                            </p>
                            <input
                                className="h-8 w-32 rounded-md border px-2 text-sm focus:outline-none"
                                style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                placeholder="Número..."
                                value={pago.num_cheque ?? ''}
                                onChange={e => setPago(prev => ({ ...prev, num_cheque: e.target.value || null }))}
                            />
                        </div>

                        {/* Banco */}
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>BANCO</p>
                            <input
                                className="h-8 w-36 rounded-md border px-2 text-sm focus:outline-none"
                                style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                placeholder="Banco..."
                                value={pago.banco ?? ''}
                                onChange={e => setPago(prev => ({ ...prev, banco: e.target.value || null }))}
                            />
                        </div>

                        {/* Cantidad */}
                        <div>
                            <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>CANTIDAD</p>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                className="h-8 w-32 rounded-md border px-2 text-sm text-right focus:outline-none"
                                style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                value={pago.valor}
                                onChange={e => setPago(prev => ({ ...prev, valor: Number(e.target.value) }))}
                            />
                        </div>

                        {/* Días (solo crédito) */}
                        {pago.forma_pago === 'credito' && (
                            <div>
                                <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>DÍAS</p>
                                <input
                                    type="number"
                                    min="1"
                                    className="h-8 w-20 rounded-md border px-2 text-sm text-right focus:outline-none"
                                    style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={pago.dias_credito || (clienteSeleccionado?.dias_credito ?? 30)}
                                    onChange={e => setPago(prev => ({ ...prev, dias_credito: Number(e.target.value) }))}
                                />
                            </div>
                        )}

                        {/* Diferencia */}
                        <div className="ml-auto flex items-end pb-0.5">
                            <span className={cn('text-sm', Math.abs(diferencia) <= 0.01 ? 'text-emerald-400' : 'text-red-400')}>
                                Diferencia:{' '}
                                <strong>{formatMoneda(Math.abs(diferencia))}</strong>
                                {Math.abs(diferencia) <= 0.01 && ' ✓'}
                            </span>
                        </div>
                    </div>
                </div>

                {/* ── 5. Observaciones + Totales (60 / 40) ── */}
                <div className="grid grid-cols-5 gap-4">

                    {/* Observaciones — 60% */}
                    <div className="col-span-3">
                        <p className="text-xs font-semibold uppercase tracking-wider mb-2" style={{ color: 'var(--text-muted)' }}>
                            Observaciones
                        </p>
                        <textarea
                            rows={5}
                            className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-shadow"
                            style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            placeholder="Observaciones adicionales para la factura..."
                            value={observaciones}
                            onChange={e => setObservaciones(e.target.value)}
                        />
                    </div>

                    {/* Totales — 40% */}
                    <div className="col-span-2 flex flex-col justify-end">
                        <table className="w-full">
                            <tbody>
                                {[
                                    { label: 'SUBTOTAL 15%:', value: totales.subtotal15 },
                                    { label: 'SUBTOTAL SIN IMP.:', value: totales.subtotal0 },
                                    { label: 'TOTAL DESCUENTO:', value: totales.descTotal },
                                    { label: 'TOTAL IVA:', value: totales.iva },
                                ].map(row => (
                                    <tr key={row.label}>
                                        <td className="py-0.5 pr-3 text-right text-xs font-medium" style={{ color: 'var(--text-muted)' }}>
                                            {row.label}
                                        </td>
                                        <td className="py-0.5 text-right text-xs font-semibold w-28" style={{ color: 'var(--text-main)' }}>
                                            {formatMoneda(row.value)}
                                        </td>
                                    </tr>
                                ))}
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td className="pt-2 pr-3 text-right text-sm font-bold" style={{ color: 'var(--text-muted)' }}>
                                        TOTAL VALOR:
                                    </td>
                                    <td
                                        className="pt-2 text-right text-base font-bold w-28"
                                        style={{ color: totales.total > 0 ? 'var(--primary)' : 'var(--text-main)' }}
                                    >
                                        {formatMoneda(totales.total)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ── Acciones ── */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('ventas.facturas.index')}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    <div className="flex gap-3">
                        <span title="Funcionalidad en desarrollo">
                            <Button type="button" variant="secondary" disabled>
                                <Send className="w-4 h-4" />
                                Enviar al SRI
                            </Button>
                        </span>
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Guardar Factura
                        </Button>
                    </div>
                </div>
            </form>

            {/* Modal descuento especial global — activado desde checkbox */}
            <DescuentoEspecialModal
                abierto={modalDescuentoGlobal}
                onCerrar={() => setModalDescuentoGlobal(false)}
                onAutorizado={aprobacion_id => {
                    setDescuentoEspecialActivo(true)
                    setAprobacionGlobalId(aprobacion_id)
                    setModalDescuentoGlobal(false)
                }}
                productoNombre=""
                descuentoMaximo={0}
                descuentoSolicitado={0}
            />

            {/* Modal selección cliente */}
            <BuscadorClienteModal
                coincidencias={modalCliente}
                abierto={modalCliente.length > 0}
                onCerrar={() => setModalCliente([])}
                onSelect={seleccionarCliente}
            />

            {/* Modal selección producto */}
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
        </AppLayout>
    )
}
