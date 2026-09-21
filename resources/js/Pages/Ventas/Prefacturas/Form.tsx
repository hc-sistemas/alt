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
import BuscadorClienteModal from '@/Components/shared/BuscadorClienteModal'
import DescuentoEspecialModal from '@/Components/Ventas/DescuentoEspecialModal'
import { cn, formatMoneda } from '@/lib/utils'
import { toastError } from '@/lib/toast'
import { Search, Save, X, AlertTriangle } from 'lucide-react'
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
    descuento_max_producto: number
    descuento_valor: number
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

const redondear = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100

function calcularLinea(linea: DetalleLinea): DetalleLinea {
    const base = linea.cantidad * linea.precio_unitario
    const descuento_valor = redondear(base * (linea.descuento_pct / 100))
    const subtotal = redondear(base - descuento_valor)
    const valor_iva = redondear(subtotal * (linea.porcentaje_iva / 100))
    return { ...linea, descuento_valor, subtotal, valor_iva, total: subtotal + valor_iva }
}

function lineaVacia(): DetalleLinea {
    return {
        producto_id: null, codigo: '', descripcion: '',
        cantidad: 1, precio_unitario: 0, descuento_pct: 0,
        descuento_max_producto: 100, descuento_valor: 0,
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
        <tr>
            <td
                className="py-0 px-2 text-xs font-semibold w-28 select-none whitespace-nowrap"
                style={{ color: 'var(--text-muted)' }}
            >
                {label}:
            </td>
            <td className="py-px px-1">
                <Input
                    type={type}
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    onKeyDown={onKeyDown}
                    placeholder=""
                    className="h-6 px-2 py-0 text-xs"
                />
            </td>
        </tr>
    )
}

export default function Form() {
    const { clientes, productos, vendedores, siguiente_numero, limites_descuento, errors } = usePage<Props>().props
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

    // — Descuento especial global (misma mecánica que Factura)
    const [descuentoEspecialActivo, setDescuentoEspecialActivo] = useState(false)
    const [aprobacionGlobalId, setAprobacionGlobalId] = useState<number | null>(null)
    const [modalDescuentoGlobal, setModalDescuentoGlobal] = useState(false)

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
        let subtotal0 = 0, subtotal15 = 0, descTotal = 0
        for (const d of detalles) {
            if (d.porcentaje_iva === 0) subtotal0 += d.subtotal
            else subtotal15 += d.subtotal
            descTotal += d.descuento_valor
        }
        // El IVA se calcula sobre la base imponible total (igual que la factura)
        subtotal0 = redondear(subtotal0); subtotal15 = redondear(subtotal15); descTotal = redondear(descTotal)
        const iva = redondear(subtotal15 * 0.15)
        return { subtotal0, subtotal15, descTotal, iva, total: redondear(subtotal0 + subtotal15 + iva) }
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

    // ── Handlers: descuento especial ─────────────────────────────────────────

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

    // Sin descuento especial el tope es el del producto: la promo vigente
    // (si estamos entre sus fechas) o el descuento normal de la lista.
    const handleDescuentoChange = (idx: number, valor: number) => {
        const linea = detalles[idx]
        const pct = isNaN(valor) ? 0 : valor
        if (!descuentoEspecialActivo && pct > linea.descuento_max_producto && linea.descuento_max_producto < 100) {
            updateDetalle(idx, {
                descuento_pct: linea.descuento_max_producto,
                _desc_error: '',
            })
            toastError(`Descuento máximo para este producto: ${linea.descuento_max_producto}%`)
        } else {
            updateDetalle(idx, { descuento_pct: pct, _desc_error: '' })
        }
    }

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
                porcentaje_iva: p.porcentaje_iva > 0 ? 15 : 0, // IVA vigente 15% si el producto grava IVA
                descuento_max_producto: p.descuento_max,
                descuento_pct: 0,
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

    // Enter dentro de un campo nunca guarda la prefactura (solo el botón "Guardar Prefactura").
    // En la última fila con producto agrega la siguiente y pone el cursor en su buscador.
    const handleFormKeyDown = (e: React.KeyboardEvent<HTMLFormElement>) => {
        if (e.key !== 'Enter') return
        const t = e.target as HTMLElement
        if (t.tagName !== 'INPUT' && t.tagName !== 'SELECT') return
        e.preventDefault()
        if (t.hasAttribute('data-busqueda') || t.closest('[data-cliente]')) return

        const filaDet = t.closest<HTMLElement>('[data-fila-detalle]')
        if (filaDet) {
            const idx = Number(filaDet.dataset.filaDetalle)
            if (idx === detalles.length - 1 && detalles[idx].producto_id !== null) {
                addDetalle()
                setTimeout(() => document.querySelector<HTMLInputElement>(`[data-busqueda="${idx + 1}"]`)?.focus(), 0)
            }
        }
    }

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
        const conProducto = detalles.filter(d => d.producto_id !== null)
        if (conProducto.length === 0) errs.push('Agregue al menos un producto.')
        else if (conProducto.length < detalles.length) errs.push('Hay filas de producto vacías. Complételas o elimínelas.')
        if (errs.length > 0) { setErrores(errs); return }
        setErrores([])
        setGuardando(true)
        router.post(route('ventas.prefacturas.store'), {
            cliente_id: clienteSeleccionado!.id,
            vendedor_id: vendedorId,
            observaciones,
            aprobacion_especial_id: aprobacionGlobalId,
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
    const hintSlotCls = "h-3 mt-0 text-[10px] font-medium leading-3 whitespace-nowrap overflow-hidden"
    const tdInput = "w-full text-xs py-0.5 px-1.5 rounded border focus:outline-none"
    const tdInputStyle = { background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }

    return (
        <AppLayout>
            <Head title="Nueva Prefactura" />
            <PageHeader
                title="Nueva Prefactura"
                breadcrumbs={[
                    { label: 'Ventas', href: route('ventas.prefacturas.index') },
                    { label: 'Proformas', href: route('ventas.prefacturas.index') },
                    { label: 'Nueva' },
                ]}
            />

            <form
                onSubmit={handleSubmit}
                onKeyDown={handleFormKeyDown}
                className="p-4 space-y-4 max-w-7xl [&_input::-webkit-inner-spin-button]:appearance-none [&_input::-webkit-outer-spin-button]:appearance-none [&_input[type=number]]:[appearance:textfield]"
            >

                {/* Errores del servidor */}
                {errors?.error && (
                    <div
                        className="rounded-lg p-3 border"
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

                {/* Encabezado (derecha) + Cliente (izquierda) en la misma fila */}
                <div className="flex flex-col lg:flex-row-reverse lg:items-start gap-4">

                    {/* ── Encabezado compacto ── */}
                    <div className="flex flex-col flex-1 min-w-0 gap-4">
                        <div
                            className="flex flex-wrap items-center gap-6 px-4 py-2.5 rounded-xl border"
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                        >
                            <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Prefactura N°:{' '}
                                <span className="font-mono font-semibold" style={{ color: 'var(--text-main)' }}>{siguiente_numero}</span>
                            </span>
                            <span className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Fecha: <span className="font-medium" style={{ color: 'var(--text-main)' }}>{hoy.split('-').reverse().join('/')}</span>
                            </span>
                            <span className="flex items-center gap-2 text-sm" style={{ color: 'var(--text-muted)' }}>
                                Vendedor:
                                {vendedores.length <= 1 ? (
                                    <span className="font-medium" style={{ color: 'var(--text-main)' }}>{vendedorActual?.nombre ?? '—'}</span>
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
                    </div>

                    {/* ── Cliente ── */}
                    <div
                        className="rounded-xl p-4 border w-full lg:w-xl shrink-0"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                    >
                        <p className="text-xs font-bold uppercase tracking-wider mb-3 text-(--primary-hover) dark:text-(--primary)">
                            Cliente
                        </p>

                        <div data-cliente style={{ maxWidth: 480 }}>
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
                </div>

                {/* ── Detalle de productos ── */}
                <div
                    className="rounded-xl p-3 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <p className="text-xs font-bold uppercase tracking-wider mb-2 text-(--primary-hover) dark:text-(--primary)">
                        Detalle de Productos
                    </p>

                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    {[
                                        { label: 'N°', cls: 'w-8 text-center' },
                                        { label: 'Producto', cls: 'min-w-55' },
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
                                    <tr key={idx} data-fila-detalle={idx} style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="py-1 px-1.5 text-center align-top" style={{ color: 'var(--text-muted)' }}>
                                            {idx + 1}
                                        </td>
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
                                                            data-busqueda={idx}
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
                                        <td className="py-1 px-1 align-top">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                value={det.cantidad}
                                                className={cn(tdInput, 'text-right')}
                                                style={{ ...tdInputStyle, borderColor: excesosStock[idx] ? 'var(--color-danger)' : 'var(--border)' }}
                                                onKeyDown={e => { if (e.key === '.' || e.key === ',') e.preventDefault() }}
                                                onChange={e => {
                                                    const val = parseInt(e.target.value, 10)
                                                    updateDetalle(idx, { cantidad: isNaN(val) || val < 1 ? 1 : val })
                                                }}
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
                                        <td className="py-1 px-1 align-top">
                                            <input
                                                type="number" min="0" step="0.01"
                                                value={det.precio_unitario}
                                                className={cn(tdInput, 'text-right')}
                                                style={tdInputStyle}
                                                onChange={e => updateDetalle(idx, { precio_unitario: Number(e.target.value) })}
                                            />
                                            <div className={hintSlotCls} />
                                        </td>
                                        <td className="py-1 px-1 align-top relative">
                                            <input
                                                type="number" min="0" max="100" step="0.1"
                                                value={det.descuento_pct}
                                                className={cn(tdInput, 'text-right')}
                                                style={tdInputStyle}
                                                onChange={e => handleDescuentoChange(idx, Number(e.target.value))}
                                            />
                                            <div
                                                className={hintSlotCls}
                                                style={{ color: 'var(--color-warning)' }}
                                            >
                                                {det.producto_id !== null
                                                    ? (descuentoEspecialActivo ? 'Desc. especial activo' : `Max. ${det.descuento_max_producto}%`)
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="py-1 px-1.5 text-right align-top" style={{ color: 'var(--text-muted)' }}>
                                            {formatMoneda(det.descuento_valor)}
                                        </td>
                                        <td className="py-1 px-1.5 text-right font-semibold align-top" style={{ color: 'var(--text-main)' }}>
                                            {formatMoneda(det.subtotal)}
                                        </td>
                                        <td className="py-1 px-1 text-center align-top">
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

                {/* ── Observaciones + Totales (60 / 40) ── */}
                <div className="grid grid-cols-5 gap-4">
                    <div className="col-span-3">
                        <p className="text-xs font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--text-muted)' }}>
                            Observaciones
                        </p>
                        <textarea
                            rows={3}
                            className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-(--primary) transition-shadow"
                            style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            placeholder="Observaciones adicionales para la prefactura..."
                            value={observaciones}
                            onChange={e => setObservaciones(e.target.value)}
                        />
                    </div>

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

            <DescuentoEspecialModal
                abierto={modalDescuentoGlobal}
                onCerrar={() => setModalDescuentoGlobal(false)}
                onAutorizado={aprobacion_id => {
                    setDescuentoEspecialActivo(true)
                    setAprobacionGlobalId(aprobacion_id)
                    setModalDescuentoGlobal(false)
                }}
                productoNombre=""
                descuentoMaximo={limites_descuento?.descuento_maximo_pct ?? 0}
                descuentoSolicitado={0}
            />

            <BuscadorClienteModal
                coincidencias={modalCliente}
                abierto={modalCliente.length > 0}
                onCerrar={() => setModalCliente([])}
                onSelect={seleccionarCliente}
            />
        </AppLayout>
    )
}
