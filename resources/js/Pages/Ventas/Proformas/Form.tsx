import React, { useState, useMemo } from 'react'
import { Head, usePage, router, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import DescuentoEspecialModal from '@/Components/Ventas/DescuentoEspecialModal'
import { cn, formatMoneda } from '@/lib/utils'
import { Plus, Trash2, Search, Save, X, AlertTriangle } from 'lucide-react'
import type { PageProps, Empresa, Usuario, Cliente, LimiteDescuento } from '@/types'

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
    descuento_valor: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    descuento_especial: boolean
    aprobacion_id: number | null
    descuento_max_producto: number
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
    limite_descuento: LimiteDescuento | null
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
    }
}

function getCsrf(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
}

const hoy = new Date().toISOString().slice(0, 10)
const en7dias = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10)

export default function Form() {
    const { clientes, productos, vendedores, siguiente_numero } = usePage<Props>().props

    // — Cliente
    const [busquedaCliente, setBusquedaCliente] = useState('')
    const [clienteSeleccionado, setClienteSeleccionado] = useState<Cliente | null>(null)
    const [clienteEditado, setClienteEditado] = useState<Partial<Cliente>>({})
    const [mostrarDropdown, setMostrarDropdown] = useState(false)
    const [esNuevoCliente, setEsNuevoCliente] = useState(false)
    const [guardandoCliente, setGuardandoCliente] = useState(false)

    // — Vendedor
    const [vendedorId, setVendedorId] = useState<number>(vendedores[0]?.id ?? 0)

    // — Vencimiento
    const [fechaVencimiento, setFechaVencimiento] = useState(en7dias)

    // — Líneas
    const [detalles, setDetalles] = useState<DetalleLinea[]>([lineaVacia()])
    const [productoDropIdx, setProductoDropIdx] = useState<number | null>(null)

    // — Misc
    const [observaciones, setObservaciones] = useState('')
    const [modal, setModal] = useState<ModalDescuentoState | null>(null)
    const [guardando, setGuardando] = useState(false)
    const [errores, setErrores] = useState<string[]>([])

    // ── Computed ────────────────────────────────────────────────────────────────

    const clientesFiltrados = useMemo(() => {
        const q = busquedaCliente.trim().toLowerCase()
        if (!q) return []
        return clientes.filter(
            c => c.identificacion.toLowerCase().includes(q) || c.razon_social.toLowerCase().includes(q)
        ).slice(0, 8)
    }, [clientes, busquedaCliente])

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
        setBusquedaCliente(c.razon_social)
        setMostrarDropdown(false)
        setEsNuevoCliente(false)
    }

    const iniciarNuevoCliente = () => {
        setClienteSeleccionado(null)
        setClienteEditado({
            razon_social: busquedaCliente,
            identificacion: '',
            tipo_identificacion: '04',
            tiene_credito: false,
            dias_credito: 0,
            cupo_maximo: 0,
        })
        setEsNuevoCliente(true)
        setMostrarDropdown(false)
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
                    Accept: 'application/json',
                },
                body: JSON.stringify(clienteEditado),
            })
            const data = await res.json() as { cliente: Cliente }
            setClienteSeleccionado(data.cliente)
            setClienteEditado({ ...data.cliente })
            setBusquedaCliente(data.cliente.razon_social)
            setEsNuevoCliente(false)
        } catch {
            // error silencioso
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
        if (valor > linea.descuento_max_producto && linea.descuento_max_producto < 100) {
            if (linea.aprobacion_id) {
                updateDetalle(idx, { descuento_pct: valor })
                return
            }
            setModal({ indice: idx, descuentoSolicitado: valor, maxPermitido: linea.descuento_max_producto })
        } else {
            updateDetalle(idx, { descuento_pct: valor, descuento_especial: false, aprobacion_id: null })
        }
    }

    const seleccionarProducto = (idx: number, p: ProductoVenta) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({
                ...next[idx],
                producto_id: p.id,
                codigo: p.codigo,
                descripcion: p.nombre,
                precio_unitario: p.pvp,
                porcentaje_iva: p.porcentaje_iva ?? 15,
                descuento_max_producto: p.descuento_max ?? 0,
                descuento_pct: 0,
                descuento_especial: false,
                aprobacion_id: null,
            })
            return next
        })
        setProductoDropIdx(null)
    }

    const addDetalle = () => setDetalles(prev => [...prev, lineaVacia()])
    const removeDetalle = (idx: number) => setDetalles(prev => prev.filter((_, i) => i !== idx))

    const productosFiltrados = (q: string) => {
        if (!q.trim()) return []
        const lq = q.toLowerCase()
        return productos.filter(
            p => p.codigo.toLowerCase().includes(lq) || p.nombre.toLowerCase().includes(lq)
        ).slice(0, 8)
    }

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

        const primeraAprobacion = detalles.find(d => d.aprobacion_id)?.aprobacion_id ?? null
        setGuardando(true)
        router.post(route('ventas.proformas.store'), {
            cliente_id: clienteSeleccionado!.id,
            vendedor_id: vendedorId,
            fecha_vencimiento: fechaVencimiento,
            observaciones,
            aprobacion_especial: primeraAprobacion,
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
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    const vendedorActual = vendedores.find(v => v.id === vendedorId)

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
                    className="rounded-xl p-5 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Cliente
                    </p>

                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            className="pl-9"
                            placeholder="Buscar por RUC, cédula o razón social..."
                            value={busquedaCliente}
                            onChange={e => {
                                setBusquedaCliente(e.target.value)
                                setMostrarDropdown(true)
                                if (!e.target.value) { setClienteSeleccionado(null); setEsNuevoCliente(false) }
                            }}
                            onFocus={() => setMostrarDropdown(true)}
                            onBlur={() => setTimeout(() => setMostrarDropdown(false), 200)}
                        />
                        {mostrarDropdown && busquedaCliente.trim().length >= 2 && (
                            <div
                                className="absolute z-30 left-0 right-0 mt-1 rounded-xl border shadow-2xl overflow-hidden"
                                style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                            >
                                {clientesFiltrados.map(c => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        className="w-full text-left px-4 py-3 hover:bg-amber-500/10 border-b transition-colors"
                                        style={{ borderColor: 'var(--border)' }}
                                        onMouseDown={() => seleccionarCliente(c)}
                                    >
                                        <p className="text-sm font-medium" style={{ color: 'var(--text-main)' }}>{c.razon_social}</p>
                                        <p className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.identificacion}</p>
                                    </button>
                                ))}
                                {clientesFiltrados.length === 0 && (
                                    <button
                                        type="button"
                                        className="w-full text-left px-4 py-3 text-sm transition-colors hover:bg-amber-500/10"
                                        style={{ color: 'var(--primary)' }}
                                        onMouseDown={iniciarNuevoCliente}
                                    >
                                        <Plus className="w-4 h-4 inline mr-1" />
                                        Crear nuevo cliente "{busquedaCliente}"
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {(clienteSeleccionado || esNuevoCliente) && (
                        <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Razón Social *</Label>
                                <Input className="mt-1" value={clienteEditado.razon_social ?? ''} onChange={e => setClienteEditado(p => ({ ...p, razon_social: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Identificación *</Label>
                                <Input className="mt-1" value={clienteEditado.identificacion ?? ''} onChange={e => setClienteEditado(p => ({ ...p, identificacion: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Tipo identificación</Label>
                                <select
                                    className="mt-1 w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={clienteEditado.tipo_identificacion ?? '04'}
                                    onChange={e => setClienteEditado(p => ({ ...p, tipo_identificacion: e.target.value as Cliente['tipo_identificacion'] }))}
                                >
                                    <option value="04">RUC</option>
                                    <option value="05">Cédula</option>
                                    <option value="06">Pasaporte</option>
                                    <option value="07">Consumidor Final</option>
                                </select>
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Teléfono</Label>
                                <Input className="mt-1" value={clienteEditado.telefono ?? ''} onChange={e => setClienteEditado(p => ({ ...p, telefono: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Email</Label>
                                <Input className="mt-1" type="email" value={clienteEditado.email ?? ''} onChange={e => setClienteEditado(p => ({ ...p, email: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Dirección</Label>
                                <Input className="mt-1" value={clienteEditado.direccion ?? ''} onChange={e => setClienteEditado(p => ({ ...p, direccion: e.target.value }))} />
                            </div>
                            <div className="md:col-span-2 flex justify-end">
                                <Button type="button" variant="outline" size="sm" loading={guardandoCliente} onClick={() => void handleGuardarCliente()}>
                                    <Save className="w-4 h-4" />
                                    {clienteSeleccionado?.id ? 'Actualizar Cliente' : 'Guardar Cliente'}
                                </Button>
                            </div>
                        </div>
                    )}
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
                                    {['Código', 'Descripción', 'Cant.', 'Precio Unit.', 'Desc%', 'Subtotal', 'IVA%', 'Total', ''].map((col, i) => (
                                        <th
                                            key={i}
                                            className={cn('py-2 px-2 font-medium text-left', (i >= 2 && i <= 5) && 'text-right')}
                                            style={{ color: 'var(--text-muted)' }}
                                        >
                                            {col}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {detalles.map((det, idx) => {
                                    const filtrados = productosFiltrados(det.codigo)
                                    return (
                                        <tr key={idx} style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="py-1.5 px-2 relative" style={{ minWidth: 130 }}>
                                                <Input
                                                    value={det.codigo}
                                                    placeholder="Buscar..."
                                                    className="text-xs"
                                                    onChange={e => {
                                                        updateDetalle(idx, { codigo: e.target.value, producto_id: null })
                                                        setProductoDropIdx(idx)
                                                    }}
                                                    onFocus={() => setProductoDropIdx(idx)}
                                                    onBlur={() => setTimeout(() => setProductoDropIdx(null), 200)}
                                                />
                                                {productoDropIdx === idx && filtrados.length > 0 && (
                                                    <div
                                                        className="absolute left-0 top-full z-30 w-72 mt-0.5 rounded-lg border shadow-xl overflow-hidden"
                                                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                                                    >
                                                        {filtrados.map(p => (
                                                            <button
                                                                key={p.id}
                                                                type="button"
                                                                className="w-full text-left px-3 py-2 hover:bg-amber-500/10 border-b transition-colors"
                                                                style={{ borderColor: 'var(--border)' }}
                                                                onMouseDown={() => seleccionarProducto(idx, p)}
                                                            >
                                                                <p className="font-medium" style={{ color: 'var(--text-main)' }}>{p.codigo} — {p.nombre}</p>
                                                                <p style={{ color: 'var(--text-muted)' }}>{formatMoneda(p.pvp)} · IVA {p.porcentaje_iva ?? 15}%</p>
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-1.5 px-2" style={{ minWidth: 160 }}>
                                                <Input value={det.descripcion} placeholder="Descripción..." className="text-xs" onChange={e => updateDetalle(idx, { descripcion: e.target.value })} />
                                            </td>
                                            <td className="py-1.5 px-2" style={{ minWidth: 72 }}>
                                                <Input type="number" min="0.01" step="0.01" value={det.cantidad} className="text-xs text-right" onChange={e => updateDetalle(idx, { cantidad: Number(e.target.value) })} />
                                            </td>
                                            <td className="py-1.5 px-2" style={{ minWidth: 96 }}>
                                                <Input type="number" min="0" step="0.01" value={det.precio_unitario} className="text-xs text-right" onChange={e => updateDetalle(idx, { precio_unitario: Number(e.target.value) })} />
                                            </td>
                                            <td className="py-1.5 px-2 relative" style={{ minWidth: 72 }}>
                                                <Input
                                                    type="number" min="0" max="100" step="0.1"
                                                    value={det.descuento_pct}
                                                    className={cn('text-xs text-right pr-6', det.descuento_especial && 'border-amber-500')}
                                                    onChange={e => handleDescuentoChange(idx, Number(e.target.value))}
                                                />
                                                {det.descuento_especial && (
                                                    <AlertTriangle className="absolute right-3 top-1/2 -translate-y-1/2 w-3 h-3 text-amber-500 pointer-events-none" />
                                                )}
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
                                    )
                                })}
                            </tbody>
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)' }}>
                                    <td colSpan={4} className="py-3 px-2">
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
                        className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-[var(--primary)] transition-shadow"
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
                    <Button type="submit" loading={guardando}>
                        <Save className="w-4 h-4" />
                        Guardar Proforma
                    </Button>
                </div>
            </form>

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
