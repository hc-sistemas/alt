import React, { useState, useMemo, useEffect } from 'react'
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
import { toastError, toastExito } from '@/lib/toast'
import { Save, X, Search } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, Empresa, Usuario, Cliente } from '@/types'

// ── Interfaces locales ────────────────────────────────────────────────────────

interface ProductoVenta {
    id: number
    codigo: string
    nombre: string
    pvp: number
    pvd: number
    // Sin costo real (CHECKLIST_ERRORES_COMPLICACIONES.md, A2): el servidor
    // solo manda si el precio de lista quedó por debajo del costo.
    bajo_costo: boolean
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
    bajo_costo: boolean
    // A8 (CHECKLIST_ERRORES_COMPLICACIONES.md): línea de regalo, se factura a $0.
    es_regalo: boolean
    descuento_pct: number
    descuento_valor: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    descuento_max_producto: number
    aprobacion_id_precio: number | null
    _busqueda: string
    _error: string
    _desc_error: string
    _precio_error: string
    _disponible: number | null
    _disponibleCargando: boolean
    _disponibleError: string
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
    limites_descuento: { descuento_maximo_pct: number; puede_aprobar: boolean }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

// Con tarjeta de crédito no hay descuento de ningún tipo.
const esTarjeta = (forma: string) => forma === 'tarjeta' || forma === 'datafast'
const MSG_SIN_DESCUENTO_TARJETA = 'Con tarjeta de crédito no hay descuento de ningún tipo.'

const redondear = (n: number) => Math.round((n + Number.EPSILON) * 100) / 100

function calcularLinea(linea: DetalleLinea): DetalleLinea {
    // A8: un regalo va siempre en $0, sin descuento ni IVA que calcular.
    if (linea.es_regalo) {
        return { ...linea, descuento_valor: 0, subtotal: 0, valor_iva: 0, total: 0 }
    }
    const base = linea.cantidad * linea.precio_unitario
    // Redondeo a 2 decimales por línea (igual que el servidor) para que los valores sean exactos
    const descuento_valor = redondear(base * (linea.descuento_pct / 100))
    const subtotal = redondear(base - descuento_valor)
    const valor_iva = redondear(subtotal * (linea.porcentaje_iva / 100))
    return { ...linea, descuento_valor, subtotal, valor_iva, total: subtotal + valor_iva }
}

function lineaVacia(): DetalleLinea {
    return {
        producto_id: null, codigo: '', descripcion: '', serie: '',
        cantidad: 1, precio_unitario: 0, bajo_costo: false, es_regalo: false, descuento_pct: 0,
        descuento_valor: 0, subtotal: 0, porcentaje_iva: 15,
        valor_iva: 0, total: 0, descuento_max_producto: 100,
        aprobacion_id_precio: null,
        _busqueda: '', _error: '', _desc_error: '', _precio_error: '',
        _disponible: null, _disponibleCargando: false, _disponibleError: '',
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

async function consultarSaldoDisponible(productoId: number): Promise<number> {
    const res = await fetch(
        route('ventas.facturas.saldo-disponible', { producto_id: productoId }),
        { headers: { Accept: 'application/json' } },
    )
    if (!res.ok) {
        const data = await res.json().catch(() => null) as { error?: string } | null
        throw new Error(data?.error || 'No se pudo consultar el stock.')
    }
    const data = await res.json() as { disponible: number }
    return data.disponible
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

// ── Componente principal ──────────────────────────────────────────────────────

export default function Form() {
    const {
        clientes,
        productos,
        vendedores,
        formas_pago,
        siguiente_numero,
        limites_descuento,
        auth,
    } = usePage<Props>().props
    const { puede } = usePermiso('ventas')

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
    // Crédito: autorización de contador/admin cuando el cliente no tiene cupo
    const [modalCredito, setModalCredito] = useState(false)
    const [aprobacionCreditoId, setAprobacionCreditoId] = useState<number | null>(null)

    // — Vendedor
    const [vendedorId, setVendedorId] = useState<number>(
        esVendedor ? (auth.user?.id ?? 0) : (vendedores[0]?.id ?? 0)
    )

    // — Líneas de detalle
    const [detalles, setDetalles] = useState<DetalleLinea[]>([lineaVacia()])

    // — Modal producto
    const [modalProducto, setModalProducto] = useState<{ idx: number; matches: ProductoVenta[] } | null>(null)

    // — Modal aprobación precio bajo costo (por línea)
    const [modalPrecioBajoCosto, setModalPrecioBajoCosto] = useState<{ idx: number } | null>(null)

    // — Formas de pago (múltiples líneas)
    const [pagos, setPagos] = useState<FormaPagoLinea[]>(() => [pagoVacio(formas_pago)])

    // — Misc
    const [observaciones, setObservaciones] = useState('')
    const [guardando, setGuardando] = useState(false)
    const [erroresPago, setErroresPago] = useState<Record<number, string>>({})

    useEffect(() => {
        const isOpen = modalCliente.length > 0 || modalProducto !== null || modalDescuentoGlobal || modalPrecioBajoCosto !== null
        document.body.style.overflow = isOpen ? 'hidden' : ''
        return () => { document.body.style.overflow = '' }
    }, [modalCliente.length, modalProducto, modalDescuentoGlobal, modalPrecioBajoCosto])

    // ── Computed ──────────────────────────────────────────────────────────────

    const totales = useMemo(() => {
        let subtotal0 = 0, subtotal15 = 0, descTotal = 0, ivaLineas = 0
        for (const d of detalles) {
            if (d.porcentaje_iva === 0) subtotal0 += d.subtotal
            else { subtotal15 += d.subtotal; ivaLineas += d.valor_iva }
            descTotal += d.descuento_valor
        }
        // El IVA total es la suma del IVA de cada línea (como el servidor y el XML del SRI)
        subtotal0 = redondear(subtotal0); subtotal15 = redondear(subtotal15); descTotal = redondear(descTotal)
        const iva = redondear(ivaLineas)
        return { subtotal0, subtotal15, descTotal, iva, total: redondear(subtotal0 + subtotal15 + iva) }
    }, [detalles])

    // Suma cantidades de un mismo producto repetido en varias líneas, para no
    // dejar pasar por partes lo que junto sí supera el disponible. Todas las
    // líneas descuentan de la misma bodega fija, así que basta agrupar por producto.
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

    const totalPagado = useMemo(() => pagos.reduce((acc, p) => acc + p.valor, 0), [pagos])
    const pagaConTarjeta = pagos.some(p => esTarjeta(p.forma_pago))
    const diferencia = Math.round((totalPagado - totales.total) * 100) / 100

    // ── Handlers: descuento especial global ──────────────────────────────────

    const handleCheckboxDescuento = async (checked: boolean) => {
        if (checked && pagaConTarjeta) {
            toastError(MSG_SIN_DESCUENTO_TARJETA)
            return
        }
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

    // ── Handlers: detalles ────────────────────────────────────────────────────

    const updateDetalle = (idx: number, patch: Partial<DetalleLinea>) => {
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({ ...next[idx], ...patch })
            return next
        })
    }

    // Consulta InventarioService::getSaldoDisponible para la línea idx. Si falla
    // por red/servidor, degrada a advertencia y no bloquea el formulario.
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
                            _disponibleError: err instanceof Error
                                ? err.message
                                : 'No se pudo consultar el stock. Verifique manualmente.',
                        }
                    }
                    return next
                })
            })
    }

    const handleDescuentoChange = (idx: number, valor: number) => {
        const linea = detalles[idx]
        if (pagaConTarjeta && valor > 0) {
            toastError(MSG_SIN_DESCUENTO_TARJETA)
            return
        }
        // El tope de producto solo aplica sin autorización especial — con
        // descuento especial activo (PIN de supervisor) el vendedor puede
        // superarlo, hasta el límite de perfil aprobado vía limites_descuento.
        if (!descuentoEspecialActivo && valor > linea.descuento_max_producto && linea.descuento_max_producto < 100) {
            updateDetalle(idx, {
                descuento_pct: linea.descuento_max_producto,
                _desc_error: '',
            })
            toastError(`Descuento máximo para este producto: ${linea.descuento_max_producto}%`)
        } else {
            updateDetalle(idx, { descuento_pct: valor, _desc_error: '' })
        }
    }

    // A8 (CHECKLIST_ERRORES_COMPLICACIONES.md): tope de 2 regalos por factura
    // — se avisa al marcar el tercero, pero se deja continuar (decisión del
    // cliente: no bloquear).
    const toggleRegalo = (idx: number, marcado: boolean) => {
        if (marcado) {
            const yaHayRegalos = detalles.filter((d, i) => i !== idx && d.es_regalo).length
            if (yaHayRegalos >= 2) {
                toastError(`Ya hay ${yaHayRegalos} regalos en esta factura.`)
            }
        }
        updateDetalle(idx, { es_regalo: marcado, descuento_pct: 0, _precio_error: '' })
    }

    const seleccionarProductoLocal = (idx: number, p: ProductoVenta) => {
        const precioLista = Math.round(p.pvp * 100) / 100
        setDetalles(prev => {
            const next = [...prev]
            next[idx] = calcularLinea({
                ...next[idx],
                producto_id: p.id,
                codigo: p.codigo,
                descripcion: p.nombre,
                precio_unitario: precioLista,
                bajo_costo: p.bajo_costo,
                es_regalo: false,
                porcentaje_iva: p.porcentaje_iva > 0 ? 15 : 0, // el servidor factura siempre al 15% si el producto grava IVA
                descuento_max_producto: p.descuento_max,
                descuento_pct: 0,
                aprobacion_id_precio: null,
                _busqueda: '',
                _error: '',
                _desc_error: '',
                _precio_error: '',
            })
            return next
        })
        setModalProducto(null)
        actualizarDisponible(idx, p.id)
        // El precio no lo escribe el vendedor: sale siempre del precio de lista
        // del producto. Si ese precio de lista quedó por debajo del costo (un
        // error de configuración en el maestro de productos), se sigue pidiendo
        // aprobación de un supervisor antes de dejar facturar la línea.
        if (p.bajo_costo) {
            setModalPrecioBajoCosto({ idx })
        }
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

    // ── Handlers: formas de pago ────────────────────────────────────────────

    const addPago = () => setPagos(prev => [...prev, pagoVacio(formas_pago)])
    const removePago = (idx: number) => setPagos(prev => prev.filter((_, i) => i !== idx))
    const updatePago = (idx: number, patch: Partial<FormaPagoLinea>) => {
        setPagos(prev => {
            const next = [...prev]
            next[idx] = { ...next[idx], ...patch }
            return next
        })
    }

    // Al elegir tarjeta todo descuento pasa a 0, sin preguntar.
    const cambiarFormaPago = (idx: number, nueva: string) => {
        if (nueva === 'credito' && !puedeUsarCredito(idx)) return

        if (esTarjeta(nueva)) {
            setDetalles(prev => prev.map(d => (
                d.descuento_pct > 0 ? calcularLinea({ ...d, descuento_pct: 0, _desc_error: '' }) : d
            )))
            setDescuentoEspecialActivo(false)
            setAprobacionGlobalId(null)
        }

        updatePago(idx, {
            forma_pago: nueva,
            dias_credito: nueva === 'credito' ? (clienteSeleccionado?.dias_credito ?? 30) : 0,
        })
    }

    function puedeUsarCredito(idx: number): boolean {
        return !pagos.some((p, i) => i !== idx && p.forma_pago === 'credito')
    }

    // Enter dentro de un campo nunca guarda la factura (solo el botón "Guardar Factura").
    // En su lugar agrega la siguiente fila de producto / forma de pago si estás en la última.
    const handleFormKeyDown = (e: React.KeyboardEvent<HTMLFormElement>) => {
        if (e.key !== 'Enter') return
        const t = e.target as HTMLElement
        if (t.tagName !== 'INPUT' && t.tagName !== 'SELECT') return
        e.preventDefault()
        if (t.hasAttribute('data-busqueda') || t.closest('[data-cliente]')) return

        const filaPago = t.closest<HTMLElement>('[data-fila-pago]')
        if (filaPago) {
            const idx = Number(filaPago.dataset.filaPago)
            if (idx === pagos.length - 1 && pagos[idx].valor > 0) addPago()
            return
        }
        const filaDet = t.closest<HTMLElement>('[data-fila-detalle]')
        if (filaDet) {
            const idx = Number(filaDet.dataset.filaDetalle)
            if (idx === detalles.length - 1 && detalles[idx].producto_id !== null) {
                addDetalle()
                setTimeout(() => document.querySelector<HTMLInputElement>(`[data-busqueda="${idx + 1}"]`)?.focus(), 0)
            }
        }
    }

    // ── Submit ────────────────────────────────────────────────────────────────

    const handleSubmit = (e: { preventDefault(): void }) => {
        e.preventDefault()

        const erroresGlobales: string[] = []
        if (!clienteSeleccionado) erroresGlobales.push('Debe seleccionar un cliente.')
        const conProducto = detalles.filter(d => d.producto_id !== null)
        if (conProducto.length === 0) erroresGlobales.push('Agregue al menos un producto.')
        else if (conProducto.length < detalles.length) erroresGlobales.push('Hay filas de producto vacías. Complételas o elimínelas.')
        // Los valores están redondeados a centavos: las formas de pago deben sumar exactamente el total.
        if (Math.abs(diferencia) >= 0.005) {
            erroresGlobales.push(`Las formas de pago no cuadran. Diferencia: ${formatMoneda(Math.abs(diferencia))}`)
        }
        if (pagaConTarjeta && (detalles.some(d => d.descuento_pct > 0) || descuentoEspecialActivo)) {
            erroresGlobales.push(MSG_SIN_DESCUENTO_TARJETA)
        }
        if (excesosStock.some(Boolean)) {
            erroresGlobales.push('Hay productos con cantidad mayor al stock disponible.')
        }

        const nuevosErroresPago: Record<number, string> = {}
        pagos.forEach((p, i) => {
            if (!p.forma_pago) nuevosErroresPago[i] = 'Seleccione una forma de pago.'
            else if (p.valor <= 0) nuevosErroresPago[i] = 'El valor debe ser mayor a 0.'
        })
        setErroresPago(nuevosErroresPago)

        if (erroresGlobales.length > 0) {
            erroresGlobales.forEach(msg => toastError(msg))
            return
        }

        if (Object.keys(nuevosErroresPago).length > 0) return

        setGuardando(true)
        router.post(route('ventas.facturas.store'), {
            cliente_id: clienteSeleccionado!.id,
            vendedor_id: vendedorId,
            observaciones,
            tiene_descuento_especial: descuentoEspecialActivo,
            aprobacion_especial_id: aprobacionGlobalId,
            aprobacion_credito_id: aprobacionCreditoId,
            detalles: detalles.map(d => ({
                producto_id: d.producto_id,
                codigo: d.codigo,
                descripcion: d.descripcion,
                serie: d.serie,
                cantidad: d.cantidad,
                precio: d.precio_unitario,
                descuento_pct: d.descuento_pct,
                graba_iva: d.porcentaje_iva > 0,
                aprobacion_id: d.aprobacion_id_precio,
                es_regalo: d.es_regalo,
            })),
            formas_pago: pagos.map(p => ({
                forma: p.forma_pago,
                monto: p.valor,
                plazo: p.dias_credito ? String(p.dias_credito) : null,
                banco: p.banco,
                num_cheque: p.num_cheque,
            })),
        }, {
            onError: errors => {
                if (errors.aprobacion_credito) {
                    setModalCredito(true)
                } else {
                    Object.values(errors).forEach(msg => { if (msg) toastError(msg) })
                }
                setGuardando(false)
            },
            onFinish: () => setGuardando(false),
        })
    }

    const vendedorActual = vendedores.find(v => v.id === vendedorId)
    const tipoLabel: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }

    const tdInput = "w-full text-xs py-0.5 px-1.5 rounded border focus:outline-none"
    const tdInputStyle = { background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }
    // Slot de altura fija debajo del input (16px) — siempre presente, con o sin
    // texto, para que Bodega/Cantidad/Precio/Desc% queden a la misma altura
    // entre sí y entre líneas de producto distintas.
    const hintSlotCls = "h-3 mt-0 text-[10px] font-medium leading-3 whitespace-nowrap overflow-hidden"

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

            <form onSubmit={handleSubmit} onKeyDown={handleFormKeyDown} className="p-4 space-y-4 max-w-7xl [&_input::-webkit-inner-spin-button]:appearance-none [&_input::-webkit-outer-spin-button]:appearance-none [&_input[type=number]]:[appearance:textfield]">

                {/* Encabezado (derecha) + Cliente (izquierda) en la misma fila */}
                <div className="flex flex-col lg:flex-row-reverse lg:items-start gap-4">

                {/* Columna derecha: encabezado + vendedor / formas de pago */}
                <div className="flex flex-col flex-1 min-w-0 gap-4">
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

                {/* ── 4. Vendedor + Formas de pago ── */}
                <div
                    className="rounded-xl p-3 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    {/* Vendedor */}
                    <div className="flex items-center gap-2 mb-2">
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

                    {/* Líneas de pago */}
                    <div className="space-y-1">
                        {pagos.map((p, idx) => (
                            <div key={idx} data-fila-pago={idx}>
                            <div className="flex flex-wrap items-end gap-3">

                                {/* Forma */}
                                <div>
                                    {idx === 0 && <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>FORMA</p>}
                                    <select
                                        className="h-7 rounded-md border px-2 text-sm capitalize"
                                        style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                        value={p.forma_pago}
                                        onChange={e => cambiarFormaPago(idx, e.target.value)}
                                    >
                                        {formas_pago.map(f => (
                                            <option
                                                key={f}
                                                value={f}
                                                className="capitalize"
                                                disabled={f === 'credito' && !puedeUsarCredito(idx)}
                                            >
                                                {f}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                {/* N_DOC */}
                                <div>
                                    {idx === 0 && (
                                        <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>
                                            {p.forma_pago === 'cheque' ? 'N° CHEQUE' : 'N_DOC'}
                                        </p>
                                    )}
                                    <input
                                        className="h-7 w-32 rounded-md border px-2 text-sm focus:outline-none"
                                        style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                        placeholder="Número..."
                                        value={p.num_cheque ?? ''}
                                        onChange={e => updatePago(idx, { num_cheque: e.target.value || null })}
                                    />
                                </div>

                                {/* Cantidad */}
                                <div>
                                    {idx === 0 && <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>CANTIDAD</p>}
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className="h-7 w-32 rounded-md border px-2 text-sm text-right focus:outline-none"
                                        style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                        value={p.valor}
                                        onChange={e => updatePago(idx, { valor: Number(e.target.value) })}
                                    />
                                </div>

                                {/* Días (solo crédito) */}
                                {p.forma_pago === 'credito' && (
                                    <div>
                                        {idx === 0 && <p className="text-xs mb-0.5" style={{ color: 'var(--text-muted)' }}>DÍAS</p>}
                                        <input
                                            type="number"
                                            min="1"
                                            className="h-7 w-20 rounded-md border px-2 text-sm text-right focus:outline-none"
                                            style={{ background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                            value={p.dias_credito || (clienteSeleccionado?.dias_credito ?? 30)}
                                            onChange={e => updatePago(idx, { dias_credito: Number(e.target.value) })}
                                        />
                                    </div>
                                )}

                                {/* Eliminar línea */}
                                {pagos.length > 1 && (
                                    <button
                                        type="button"
                                        className="p-1 rounded hover:bg-red-500/10 transition-colors mb-0.5"
                                        onClick={() => removePago(idx)}
                                        title="Eliminar línea de pago"
                                    >
                                        <X className="w-4 h-4 text-red-400" />
                                    </button>
                                )}
                            </div>
                            {erroresPago[idx] && (
                                <p className="text-xs mt-0.5" style={{ color: '#ef4444' }}>
                                    {erroresPago[idx]}
                                </p>
                            )}
                            </div>
                        ))}

                        {/* Diferencia */}
                        <div className="flex items-center justify-end pt-0">
                            <span className={cn('text-xs', Math.abs(diferencia) <= 0.01 ? 'text-emerald-400' : 'text-red-400')}>
                                Diferencia:{' '}
                                <strong>{formatMoneda(Math.abs(diferencia))}</strong>
                                {Math.abs(diferencia) <= 0.01 && ' ✓'}
                            </span>
                        </div>
                    </div>
                </div>

                </div>

                {/* ── 2. Cliente ── */}
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

                {/* ── 3. Tabla de productos ── */}
                <div
                    className="rounded-xl p-3 border"
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
                >
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-xs font-bold uppercase tracking-wider text-(--primary-hover) dark:text-(--primary)">
                            Detalle de Productos
                        </p>
                    </div>

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
                                        { label: 'Regalo', cls: 'w-14 text-center' },
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

                                        {/* N° */}
                                        <td className="py-1 px-1.5 text-center align-top" style={{ color: 'var(--text-muted)' }}>
                                            {idx + 1}
                                        </td>

                                        {/* Producto */}
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

                                        {/* Cantidad — enteros + texto de stock */}
                                        <td className="py-1 px-1 align-top">
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

                                        {/* Precio — precio de lista, no editable. Solo cambia el total, vía descuento. */}
                                        <td className="py-1 px-1 align-top">
                                            <input
                                                type="number"
                                                readOnly
                                                tabIndex={-1}
                                                className={cn(tdInput, 'text-right cursor-not-allowed')}
                                                style={{ ...tdInputStyle, background: 'var(--bg-card)', color: 'var(--text-muted)' }}
                                                value={det.precio_unitario}
                                                title="El precio no se puede editar. Para bajar el valor de la línea, use el descuento."
                                            />
                                            <div
                                                className={hintSlotCls}
                                                style={{ color: 'var(--color-danger)' }}
                                            >
                                                {det._precio_error}
                                            </div>
                                        </td>

                                        {/* Desc% + texto de máximo permitido */}
                                        <td className="py-1 px-1 align-top">
                                            <input
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.1"
                                                className={cn(tdInput, 'text-right')}
                                                style={tdInputStyle}
                                                value={det.descuento_pct}
                                                disabled={pagaConTarjeta || det.es_regalo}
                                                title={pagaConTarjeta ? MSG_SIN_DESCUENTO_TARJETA : det.es_regalo ? 'Los regalos van a $0, no llevan descuento.' : undefined}
                                                onChange={e => handleDescuentoChange(idx, Number(e.target.value))}
                                            />
                                            <div
                                                className={hintSlotCls}
                                                style={{ color: det._desc_error ? 'var(--color-danger)' : 'var(--color-warning)' }}
                                            >
                                                {det._desc_error || (det.producto_id !== null
                                                    ? (pagaConTarjeta ? 'Sin descuento con tarjeta' : det.es_regalo ? '' : descuentoEspecialActivo ? 'Descuento especial activo — sin tope de producto' : `Max. ${det.descuento_max_producto}%`)
                                                    : '')}
                                            </div>
                                        </td>

                                        {/* Regalo — A8 (CHECKLIST_ERRORES_COMPLICACIONES.md) */}
                                        <td className="py-1 px-1 align-top text-center">
                                            <input
                                                type="checkbox"
                                                checked={det.es_regalo}
                                                disabled={det.producto_id === null || pagaConTarjeta}
                                                title={pagaConTarjeta ? MSG_SIN_DESCUENTO_TARJETA : 'Marcar esta línea como regalo (se factura a $0)'}
                                                onChange={e => toggleRegalo(idx, e.target.checked)}
                                                className="w-4 h-4 cursor-pointer"
                                            />
                                        </td>

                                        {/* Desc$ */}
                                        <td className="py-1 px-1.5 text-right align-top" style={{ color: 'var(--text-muted)' }}>
                                            {formatMoneda(det.descuento_valor)}
                                        </td>

                                        {/* V.Tot */}
                                        <td className="py-1 px-1.5 text-right font-semibold align-top" style={{ color: 'var(--text-main)' }}>
                                            {formatMoneda(det.subtotal)}
                                        </td>

                                        {/* Eliminar */}
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

                {/* ── 5. Observaciones + Totales (60 / 40) ── */}
                <div className="grid grid-cols-5 gap-4">

                    {/* Observaciones — 60% */}
                    <div className="col-span-3">
                        <p className="text-xs font-semibold uppercase tracking-wider mb-1" style={{ color: 'var(--text-muted)' }}>
                            Observaciones
                        </p>
                        <textarea
                            rows={3}
                            className="w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none focus:ring-1 focus:ring-(--primary) transition-shadow"
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
                        {puede('crear') && (
                            <Button type="submit" loading={guardando}>
                                <Save className="w-4 h-4" />
                                Guardar Factura
                            </Button>
                        )}
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
                descuentoMaximo={limites_descuento.descuento_maximo_pct}
                descuentoSolicitado={0}
            />

            {/* Modal aprobación precio bajo costo — por línea. Sin montos en
                pantalla (CHECKLIST_ERRORES_COMPLICACIONES.md, A2): el costo
                real del producto no debe llegar al navegador del vendedor. */}
            <DescuentoEspecialModal
                abierto={modalPrecioBajoCosto !== null}
                onCerrar={() => {
                    if (modalPrecioBajoCosto) {
                        // Sin aprobación no se puede vender a este precio: se
                        // quita el producto de la línea en vez de adivinar un
                        // precio "seguro" (no se conoce el costo en el navegador).
                        limpiarProducto(modalPrecioBajoCosto.idx)
                        toastError('No se puede facturar este producto sin aprobación de un supervisor.')
                    }
                    setModalPrecioBajoCosto(null)
                }}
                onAutorizado={aprobacion_id => {
                    if (modalPrecioBajoCosto) {
                        updateDetalle(modalPrecioBajoCosto.idx, {
                            aprobacion_id_precio: aprobacion_id,
                            _precio_error: '',
                        })
                    }
                    setModalPrecioBajoCosto(null)
                }}
                tipo="precio_bajo_costo"
                titulo="Aprobación de precio bajo costo"
                mensaje={modalPrecioBajoCosto && (
                    <>
                        El precio de lista de{' '}
                        <strong style={{ color: 'var(--text-main)' }}>
                            {detalles[modalPrecioBajoCosto.idx]?.descripcion || 'este producto'}
                        </strong>{' '}
                        está por debajo de su costo. Se requiere un código de autorización de un supervisor para continuar.
                    </>
                )}
                productoNombre={modalPrecioBajoCosto ? (detalles[modalPrecioBajoCosto.idx]?.descripcion ?? '') : ''}
                descuentoMaximo={0}
                descuentoSolicitado={0}
            />

            {/* Modal autorización de crédito — contador o administrador */}
            <DescuentoEspecialModal
                abierto={modalCredito}
                onCerrar={() => setModalCredito(false)}
                onAutorizado={aprobacion_id => {
                    setAprobacionCreditoId(aprobacion_id)
                    setModalCredito(false)
                    toastExito('Crédito autorizado. Presione Guardar nuevamente.')
                }}
                tipo="credito_excedido"
                titulo="Autorización de crédito"
                mensaje="Este cliente no tiene crédito disponible. Un contador o administrador debe ingresar su código para autorizar la venta a crédito."
                productoNombre={clienteSeleccionado?.razon_social ?? ''}
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
