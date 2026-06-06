import { useState, useEffect } from 'react'
import { router, usePage, Link } from '@inertiajs/react'
import { ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    BookOpen, Plus, Search, Eye, XCircle, CheckCircle,
    AlertTriangle, User, X, FileText, Zap, Download, Printer,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { AsientoContable, AsientoStats, EjercicioContable, PlanCuenta, PageProps } from '@/types'
import { notify, formatMoney, formatFecha, swalBase, injectSwalStyles } from '@/utils/contabilidad'
import 'react-toastify/dist/ReactToastify.css'

interface Partida {
    cuenta_id: string
    descripcion: string
    debe: string
    haber: string
}

// El paginador de Laravel pone current_page, last_page, total, etc. en el nivel raíz (no bajo meta)
interface PaginatedAsiento {
    data: AsientoContable[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
}

interface Props extends PageProps {
    asientos: PaginatedAsiento
    ejercicios: EjercicioContable[]
    cuentas: PlanCuenta[]
    periodoActivo: EjercicioContable | null
    filtros: Record<string, string>
    stats: AsientoStats
}

const PARTIDA_VACIA: Partida = { cuenta_id: '', descripcion: '', debe: '', haber: '' }

const TIPO_BADGE = {
    activo: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
    anulado: 'bg-red-100   text-red-800   dark:bg-red-900/30   dark:text-red-300',
}

export default function AsientosIndex() {
    const { asientos, ejercicios, cuentas, periodoActivo, filtros, stats, flash, auth }
        = usePage<Props>().props
    const perfil = auth.user?.perfil ?? ''
    const puedeCrear = ['super_admin', 'admin', 'contador'].includes(perfil)
    const puedeAnular = ['super_admin', 'admin', 'contador'].includes(perfil)

    // Filtros
    const [buscar, setBuscar] = useState(filtros.buscar ?? '')
    const [tipo, setTipo] = useState(filtros.tipo ?? '')
    const [estado, setEstado] = useState(filtros.estado ?? '')
    const [ejercicioId, setEjercicioId] = useState(filtros.ejercicio_id ?? '')
    const [fechaDesde, setFechaDesde] = useState(filtros.fecha_desde ?? '')
    const [fechaHasta, setFechaHasta] = useState(filtros.fecha_hasta ?? '')

    // Modal nuevo asiento
    const [modalAbierto, setModalAbierto] = useState(false)
    const [processing, setProcessing] = useState(false)
    const [concepto, setConcepto] = useState('')
    const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0])
    const [partidas, setPartidas] = useState<Partida[]>([{ ...PARTIDA_VACIA }, { ...PARTIDA_VACIA }])
    const [buscarCuenta, setBuscarCuenta] = useState<Record<number, string>>({})
    const [dropdownOpen, setDropdownOpen] = useState<number | null>(null)

    useEffect(() => {
        if (flash?.success) notify.success(flash.success)
        if (flash?.error) notify.error(flash.error)
        if (flash?.warning) notify.warning(flash.warning)
    }, [flash?.success, flash?.error, flash?.warning])

    // Totales en tiempo real
    const totalDebe = partidas.reduce((s, p) => s + (parseFloat(p.debe) || 0), 0)
    const totalHaber = partidas.reduce((s, p) => s + (parseFloat(p.haber) || 0), 0)
    const diferencia = Math.abs(totalDebe - totalHaber)
    const cuadrado = diferencia < 0.001

    // CORRECCIÓN 2: mostrar más resultados
    const cuentasFiltradas = (idx: number) => {
        const q = (buscarCuenta[idx] ?? '').toLowerCase().trim()
        if (!q) return cuentas.slice(0, 30)
        return cuentas
            .filter(c => c.codigo.toLowerCase().includes(q) || c.nombre.toLowerCase().includes(q))
            .slice(0, 25)
    }

    const aplicarFiltros = () => {
        router.get(route('contabilidad.asientos.index'), {
            buscar, tipo, estado, ejercicio_id: ejercicioId,
            fecha_desde: fechaDesde, fecha_hasta: fechaHasta,
        }, { preserveState: true, replace: true })
    }

    const limpiarFiltros = () => {
        setBuscar(''); setTipo(''); setEstado('')
        setEjercicioId(''); setFechaDesde(''); setFechaHasta('')
        router.get(route('contabilidad.asientos.index'))
    }

    const actualizarPartida = (idx: number, campo: keyof Partida, valor: string) => {
        setPartidas(p => p.map((row, i) => i === idx ? { ...row, [campo]: valor } : row))
    }

    const seleccionarCuenta = (idx: number, cuenta: PlanCuenta) => {
        actualizarPartida(idx, 'cuenta_id', String(cuenta.id))
        setBuscarCuenta(prev => ({ ...prev, [idx]: `${cuenta.codigo} — ${cuenta.nombre}` }))
        setDropdownOpen(null)
    }

    const cerrarModal = () => {
        setModalAbierto(false)
        setConcepto('')
        setFecha(new Date().toISOString().split('T')[0])
        setPartidas([{ ...PARTIDA_VACIA }, { ...PARTIDA_VACIA }])
        setBuscarCuenta({})
        setDropdownOpen(null)
    }

    const guardarAsiento = () => {
        if (!concepto.trim()) { notify.error('El concepto es obligatorio'); return }
        if (!cuadrado) { notify.error(`No cuadra. Diferencia: ${formatMoney(diferencia)}`); return }
        const limpias = partidas.filter(p => p.cuenta_id)
        if (limpias.length < 2) { notify.error('Selecciona al menos 2 cuentas'); return }

        setProcessing(true)
        router.post(route('contabilidad.asientos.store'), {
            concepto,
            fecha,
            partidas: limpias.map(p => ({
                cuenta_id: parseInt(p.cuenta_id),
                descripcion: p.descripcion || null,
                debe: parseFloat(p.debe) || 0,
                haber: parseFloat(p.haber) || 0,
            })),
        }, {
            onSuccess: () => cerrarModal(),
            onError: (errors) => notify.error(Object.values(errors).flat().join(' | ')),
            onFinish: () => setProcessing(false),
        })
    }

    const confirmarAnulacion = async (asiento: AsientoContable) => {
        injectSwalStyles()
        const { value: motivo } = await Swal.fire({
            ...swalBase,
            title: `Anular ${asiento.numero}`,
            html: `
                <div style="text-align:left">
                    <div style="background:var(--bg-main,#f9fafb);border-left:4px solid #F59E0B;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-family:monospace;font-weight:700;color:#F59E0B;margin:0 0 4px 0">
                            ${asiento.numero}
                        </p>
                        <p style="color:#374151;margin:0;font-size:0.875rem">${asiento.concepto}</p>
                        <p style="color:#9ca3af;margin:4px 0 0 0;font-size:0.8rem">
                            DEBE: ${formatMoney(asiento.total_debe)} ·
                            HABER: ${formatMoney(asiento.total_haber)}
                        </p>
                    </div>
                    <div style="background:#fef2f2;border:1px solid #fecaca;
                                border-radius:8px;padding:10px;margin-bottom:14px">
                        <p style="font-weight:700;color:#dc2626;margin:0 0 4px 0;font-size:0.85rem">
                            Se generará un asiento de reversión automáticamente
                        </p>
                        <p style="color:#ef4444;font-size:0.8rem;margin:0">
                            El original queda como ANULADO y no puede borrarse.
                        </p>
                    </div>
                    <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                  display:block;margin-bottom:6px">
                        Motivo <span style="color:#ef4444">*</span>
                    </label>
                    <textarea id="motivo-anulacion"
                        style="width:100%;border:2px solid #e5e7eb;border-radius:8px;
                               padding:10px;font-size:0.875rem;resize:vertical;
                               min-height:80px;box-sizing:border-box;font-family:inherit"
                        placeholder="ej: Error en el monto, documento incorrecto...">
                    </textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: '❌ Anular asiento',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            focusCancel: true,
            preConfirm: () => {
                const m = (document.getElementById('motivo-anulacion') as HTMLTextAreaElement)?.value
                if (!m || m.length < 10) {
                    Swal.showValidationMessage('El motivo debe tener al menos 10 caracteres')
                    return false
                }
                return m
            },
        })

        if (motivo) {
            router.patch(route('contabilidad.asientos.anular', asiento.id), { motivo })
        }
    }

    return (
        <AppLayout title="Asientos Contables" suppressFlash>
            <div className={cn('space-y-5', 'p-6')}>

                <div className="mb-6">
                    {/* Fila 1 — Solo título e ícono */}
                    <div className="flex items-center gap-3 mb-4">
                        <div className="p-2 rounded-xl"
                             style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                            <BookOpen size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold"
                                style={{ color: 'var(--text-main)' }}>
                                Asientos Contables
                            </h1>
                            <p className="text-sm"
                               style={{ color: 'var(--text-muted)' }}>
                                Registro de movimientos contables (partida doble)
                            </p>
                        </div>
                    </div>

                    {/* Fila 2 — Botones debajo del título */}
                    <div className="flex items-center gap-2 flex-wrap">

                        {/* Nuevo asiento */}
                        {puedeCrear && (
                            <button
                                onClick={() => {
                                    if (!periodoActivo) {
                                        notify.error('No hay período activo.')
                                        return
                                    }
                                    setModalAbierto(true)
                                }}
                                className="flex items-center gap-2 px-4 py-2 rounded-xl font-semibold text-sm text-white transition-all hover:opacity-90 hover:-translate-y-0.5"
                                style={{ background: 'var(--primary)' }}
                            >
                                <Plus size={15} />
                                Nuevo Asiento Manual
                            </button>
                        )}

                        {/* Exportar Excel */}
                        <a
                            href={`${route('contabilidad.asientos.exportar-excel')}?ejercicio_id=${ejercicioId}&fecha_desde=${fechaDesde}&fecha_hasta=${fechaHasta}`}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-all hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        >
                            <Download size={15} />
                            Exportar .xlsx
                        </a>

                        {/* PDF reporte */}
                        <a
                            href={route('contabilidad.asientos.reporte-pdf')}
                            target="_blank"
                            className="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-all hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}
                        >
                            <Printer size={15} />
                            PDF
                        </a>
                    </div>
                </div>
                {/* Stats */}
                <div className={cn('gap-4', 'grid', 'grid-cols-2', 'md:grid-cols-4')}>
                    {([
                        { label: 'Total', value: stats.total, color: '#3b82f6', Icon: FileText },
                        { label: 'Activos', value: stats.activos, color: '#10b981', Icon: CheckCircle },
                        { label: 'Anulados', value: stats.anulados, color: '#ef4444', Icon: XCircle },
                        { label: 'Manuales', value: stats.manuales, color: '#F59E0B', Icon: User },
                    ] as const).map(({ label, value, color, Icon }) => (
                        <div key={label}
                            className={cn('p-4', 'border', 'rounded-xl')}
                            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <div className={cn('flex', 'justify-between', 'items-center', 'mb-2')}>
                                <p className={cn('font-medium', 'text-xs', 'uppercase', 'tracking-wider')}
                                    style={{ color: 'var(--text-muted)' }}>{label}</p>
                                <Icon size={15} style={{ color }} />
                            </div>
                            <p className={cn('font-bold', 'text-2xl')} style={{ color }}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* Banner período */}
                {periodoActivo ? (
                    <div className={cn('flex', 'items-center', 'gap-2', 'px-4', 'py-2', 'border', 'rounded-xl', 'text-sm')}
                        style={{
                            background: 'color-mix(in srgb, #10b981 8%, var(--bg-card))',
                            borderColor: '#10b981', color: '#059669'
                        }}>
                        <CheckCircle size={14} />
                        <span className="font-semibold">
                            Período activo: <strong>{periodoActivo.periodo_label}</strong>
                            {periodoActivo.fecha_apertura && (
                                <> · Abierto desde {formatFecha(periodoActivo.fecha_apertura)}</>
                            )}
                        </span>
                    </div>
                ) : (
                    <div className={cn('flex', 'items-center', 'gap-2', 'px-4', 'py-2', 'border', 'rounded-xl', 'text-sm')}
                        style={{
                            background: 'color-mix(in srgb, #ef4444 8%, var(--bg-card))',
                            borderColor: '#ef4444', color: '#dc2626'
                        }}>
                        <AlertTriangle size={14} />
                        <span className="font-semibold">Sin período activo — no se pueden crear asientos</span>
                    </div>
                )}

                {/* Filtros */}
                <div className={cn('space-y-3', 'p-4', 'border', 'rounded-xl')}
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className={cn('gap-3', 'grid', 'grid-cols-1', 'md:grid-cols-3', 'lg:grid-cols-6')}>
                        <div className={cn('relative', 'lg:col-span-2')}>
                            <Search size={13} className={cn('top-1/2', 'left-3', 'absolute', '-translate-y-1/2')}
                                style={{ color: 'var(--text-muted)' }} />
                            <input
                                type="text"
                                value={buscar}
                                onChange={e => setBuscar(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && aplicarFiltros()}
                                placeholder="Número, concepto, referencia…"
                                className={cn('dark:bg-gray-800', 'py-2', 'pr-3', 'pl-9', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'focus:ring-1', 'focus:ring-amber-500', 'w-full', 'dark:text-gray-100', 'text-sm')}
                                style={{
                                    borderColor: 'var(--border)', background: 'var(--bg-main)',
                                    color: 'var(--text-main)'
                                }}
                            />
                        </div>
                        <select value={tipo} onChange={e => setTipo(e.target.value)}
                            className={cn('dark:bg-gray-800', 'px-3', 'py-2', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'dark:text-gray-100', 'text-sm')}
                            style={{
                                borderColor: 'var(--border)', background: 'var(--bg-main)',
                                color: 'var(--text-main)'
                            }}>
                            <option value="">Todos los tipos</option>
                            <option value="manual">Manuales</option>
                            <option value="automatico">Automáticos</option>
                        </select>
                        <select value={estado} onChange={e => setEstado(e.target.value)}
                            className={cn('dark:bg-gray-800', 'px-3', 'py-2', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'dark:text-gray-100', 'text-sm')}
                            style={{
                                borderColor: 'var(--border)', background: 'var(--bg-main)',
                                color: 'var(--text-main)'
                            }}>
                            <option value="">Todos los estados</option>
                            <option value="activo">Activos</option>
                            <option value="anulado">Anulados</option>
                        </select>
                        <select value={ejercicioId} onChange={e => setEjercicioId(e.target.value)}
                            className={cn('dark:bg-gray-800', 'px-3', 'py-2', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'dark:text-gray-100', 'text-sm')}
                            style={{
                                borderColor: 'var(--border)', background: 'var(--bg-main)',
                                color: 'var(--text-main)'
                            }}>
                            <option value="">Todos los períodos</option>
                            {ejercicios.map(e => (
                                <option key={e.id} value={e.id}>{e.periodo_label}</option>
                            ))}
                        </select>
                        <div className={cn('flex', 'gap-2')}>
                            <Button onClick={aplicarFiltros} className="flex-1">Filtrar</Button>
                            <Button variant="outline" onClick={limpiarFiltros} className="px-3">
                                <X size={14} />
                            </Button>
                        </div>
                    </div>
                    <div className={cn('flex', 'flex-wrap', 'gap-3')}>
                        <div className={cn('flex', 'items-center', 'gap-2')}>
                            <span className="text-xs" style={{ color: 'var(--text-muted)' }}>Desde:</span>
                            <input type="date" value={fechaDesde}
                                onChange={e => setFechaDesde(e.target.value)}
                                className={cn('dark:bg-gray-800', 'px-2', 'py-1.5', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'dark:text-gray-100', 'text-sm')}
                                style={{
                                    borderColor: 'var(--border)', background: 'var(--bg-main)',
                                    color: 'var(--text-main)'
                                }} />
                        </div>
                        <div className={cn('flex', 'items-center', 'gap-2')}>
                            <span className="text-xs" style={{ color: 'var(--text-muted)' }}>Hasta:</span>
                            <input type="date" value={fechaHasta}
                                onChange={e => setFechaHasta(e.target.value)}
                                className={cn('dark:bg-gray-800', 'px-2', 'py-1.5', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'dark:text-gray-100', 'text-sm')}
                                style={{
                                    borderColor: 'var(--border)', background: 'var(--bg-main)',
                                    color: 'var(--text-main)'
                                }} />
                        </div>
                    </div>
                </div>

                {/* Tabla */}
                <div className={cn('border', 'rounded-xl', 'overflow-hidden')}
                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="overflow-x-auto">
                        <table className={cn('w-full', 'text-sm')}>
                            <thead>
                                <tr style={{
                                    borderBottom: '1px solid var(--border)',
                                    background: 'rgba(245,158,11,0.05)'
                                }}>
                                    {['Número', 'Fecha', 'Concepto', 'Tipo', 'Debe', 'Haber', 'Estado', ''].map(h => (
                                        <th key={h}
                                            className={cn('px-4', 'py-3', 'font-semibold', 'text-xs', 'text-left', 'uppercase', 'tracking-wider')}
                                            style={{ color: 'var(--text-muted)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {asientos.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className={cn('py-16', 'text-center')}
                                            style={{ color: 'var(--text-muted)' }}>
                                            <BookOpen size={40} className={cn('opacity-20', 'mx-auto', 'mb-3')} />
                                            <p className="font-medium">No hay asientos registrados</p>
                                            <p className={cn('mt-1', 'text-xs')}>Ajusta los filtros o crea el primer asiento.</p>
                                        </td>
                                    </tr>
                                ) : asientos.data.map(a => (
                                    <tr key={a.id}
                                        className="transition-colors"
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.04)')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}>
                                        <td className={cn('px-4', 'py-3')}>
                                            <span className={cn('font-mono', 'font-bold', 'text-xs')}
                                                style={{ color: 'var(--primary)' }}>
                                                {a.numero}
                                            </span>
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'whitespace-nowrap')}
                                            style={{ color: 'var(--text-muted)' }}>
                                            {formatFecha(a.fecha)}
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'max-w-55')}>
                                            <p className={cn('font-medium', 'text-xs', 'truncate')}
                                                style={{ color: 'var(--text-main)' }}
                                                title={a.concepto}>
                                                {a.concepto}
                                            </p>
                                            {a.documento_ref && (
                                                <p className={cn('mt-0.5', 'text-xs', 'truncate')}
                                                    style={{ color: 'var(--text-muted)' }}>
                                                    {a.documento_tipo} · {a.documento_ref}
                                                </p>
                                            )}
                                        </td>
                                        <td className={cn('px-4', 'py-3')}>
                                            {a.es_automatico ? (
                                                <span className={cn('inline-flex', 'items-center', 'gap-1', 'bg-blue-100', 'dark:bg-blue-900/30', 'px-2', 'py-0.5', 'rounded-full', 'font-semibold', 'text-blue-800', 'dark:text-blue-300', 'text-xs')}>
                                                    <Zap size={10} /> Auto
                                                </span>
                                            ) : (
                                                <span className={cn('inline-flex', 'items-center', 'gap-1', 'bg-amber-100', 'dark:bg-amber-900/30', 'px-2', 'py-0.5', 'rounded-full', 'font-semibold', 'text-amber-800', 'dark:text-amber-300', 'text-xs')}>
                                                    <User size={10} /> Manual
                                                </span>
                                            )}
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'font-medium', 'tabular-nums', 'text-right')}
                                            style={{ color: 'var(--text-main)' }}>
                                            {formatMoney(a.total_debe)}
                                        </td>
                                        <td className={cn('px-4', 'py-3', 'font-medium', 'tabular-nums', 'text-right')}
                                            style={{ color: 'var(--text-main)' }}>
                                            {formatMoney(a.total_haber)}
                                        </td>
                                        <td className={cn('px-4', 'py-3')}>
                                            <span className={cn(
                                                'inline-flex items-center gap-1 px-2 py-0.5',
                                                'rounded-full text-xs font-semibold',
                                                a.estado === 1
                                                    ? TIPO_BADGE.activo
                                                    : TIPO_BADGE.anulado,
                                            )}>
                                                {a.estado === 1
                                                    ? <><CheckCircle size={10} /> Activo</>
                                                    : <><XCircle size={10} /> Anulado</>
                                                }
                                            </span>
                                        </td>
                                        <td className={cn('px-4', 'py-3')}>
                                            <div className={cn('flex', 'items-center', 'gap-1')}>
                                                <Link
                                                    href={route('contabilidad.asientos.show', a.id)}
                                                    title="Ver detalle"
                                                    className={cn('hover:bg-blue-100', 'dark:hover:bg-blue-900/30', 'p-1.5', 'rounded-lg', 'transition-colors')}>
                                                    <Eye size={14} className="text-blue-500" />
                                                </Link>
                                                {puedeAnular && a.estado === 1 && (
                                                    <button
                                                        onClick={() => confirmarAnulacion(a)}
                                                        title="Anular asiento"
                                                        className={cn('hover:bg-red-100', 'dark:hover:bg-red-900/30', 'p-1.5', 'rounded-lg', 'transition-colors')}>
                                                        <XCircle size={14} className="text-red-500" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginación */}
                    {asientos.last_page > 1 && (
                        <div className={cn('flex', 'justify-between', 'items-center', 'px-4', 'py-3', 'border-t')}
                            style={{ borderColor: 'var(--border)' }}>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Mostrando {asientos.from}–{asientos.to} de {asientos.total}
                            </p>
                            <div className={cn('flex', 'gap-1')}>
                                {asientos.links.map((link, i) => (
                                    link.url ? (
                                        <Link key={i} href={link.url}
                                            className={cn(
                                                'px-3 py-1 rounded font-medium text-xs transition-colors',
                                                link.active
                                                    ? 'text-white'
                                                    : 'hover:opacity-80',
                                            )}
                                            style={link.active
                                                ? { background: 'var(--primary)', color: '#fff' }
                                                : {
                                                    background: 'var(--bg-main)', color: 'var(--text-muted)',
                                                    border: '1px solid var(--border)'
                                                }
                                            }
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span key={i}
                                            className={cn('opacity-40', 'px-3', 'py-1', 'rounded', 'text-xs')}
                                            style={{ color: 'var(--text-muted)' }}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    )
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* MODAL NUEVO ASIENTO MANUAL */}
            {modalAbierto && (
                <div className={cn('z-50', 'fixed', 'inset-0', 'flex', 'justify-center', 'items-center', 'p-4')}
                    style={{ background: 'rgba(0,0,0,0.6)' }}>
                    <div className={cn('flex', 'flex-col', 'shadow-2xl', 'rounded-2xl', 'w-full', 'max-w-3xl', 'max-h-[90vh]')}
                        style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}>

                        {/* Header modal */}
                        <div className={cn('flex', 'justify-between', 'items-center', 'px-6', 'py-4', 'border-b', 'shrink-0')}
                            style={{ borderColor: 'var(--border)' }}>
                            <div>
                                <h2 className={cn('flex', 'items-center', 'gap-2', 'font-bold', 'text-lg')}
                                    style={{ color: 'var(--text-main)' }}>
                                    <BookOpen size={20} style={{ color: 'var(--primary)' }} />
                                    Nuevo Asiento Manual
                                </h2>
                                {periodoActivo && (
                                    <p className={cn('mt-0.5', 'text-xs')} style={{ color: 'var(--text-muted)' }}>
                                        Período: <strong>{periodoActivo.periodo_label}</strong>
                                    </p>
                                )}
                            </div>
                            <button onClick={cerrarModal}
                                className={cn('p-1', 'text-gray-400', 'hover:text-gray-600', 'text-xl', 'leading-none')}>×</button>
                        </div>

                        <div className={cn('flex-1', 'space-y-4', 'px-6', 'py-4', 'overflow-y-auto')}>
                            {/* Concepto + Fecha */}
                            <div className={cn('gap-4', 'grid', 'grid-cols-1', 'md:grid-cols-3')}>
                                <div className="md:col-span-2">
                                    <Label>Concepto <span className="text-red-500">*</span></Label>
                                    <Input
                                        className="mt-1"
                                        value={concepto}
                                        onChange={e => setConcepto(e.target.value)}
                                        placeholder="Descripción del asiento contable"
                                        maxLength={500}
                                    />
                                </div>
                                <div>
                                    <Label>Fecha <span className="text-red-500">*</span></Label>
                                    <input
                                        type="date"
                                        value={fecha}
                                        onChange={e => setFecha(e.target.value)}
                                        className={cn('dark:bg-gray-800', 'mt-1', 'px-3', 'py-2', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'focus:ring-1', 'focus:ring-amber-500', 'w-full', 'dark:text-gray-100', 'text-sm')}
                                        style={{
                                            borderColor: 'var(--border)', background: 'var(--bg-card)',
                                            color: 'var(--text-main)'
                                        }}
                                    />
                                </div>
                            </div>

                            {/* Partidas */}
                            <div>
                                <div className={cn('flex', 'justify-between', 'items-center', 'mb-2')}>
                                    <Label>Partidas <span className="text-red-500">*</span></Label>
                                    <button
                                        onClick={() => setPartidas(p => [...p, { ...PARTIDA_VACIA }])}
                                        disabled={partidas.length >= 20}
                                        className={cn('flex', 'items-center', 'gap-1', 'disabled:opacity-40', 'px-2', 'py-1', 'rounded-lg', 'font-semibold', 'text-xs', 'transition-colors')}
                                        style={{ color: 'var(--primary)' }}>
                                        <Plus size={12} /> Agregar fila
                                    </button>
                                </div>

                                {/* Cabecera partidas */}
                                <div className={cn('gap-2', 'grid', 'grid-cols-12', 'mb-1', 'px-1')}>
                                    <span className={cn('col-span-4', 'font-semibold', 'text-xs', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Cuenta</span>
                                    <span className={cn('col-span-3', 'font-semibold', 'text-xs', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Descripción</span>
                                    <span className={cn('col-span-2', 'font-semibold', 'text-xs', 'text-right', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Debe</span>
                                    <span className={cn('col-span-2', 'font-semibold', 'text-xs', 'text-right', 'uppercase', 'tracking-wider')}
                                        style={{ color: 'var(--text-muted)' }}>Haber</span>
                                    <span className="col-span-1" />
                                </div>

                                <div className="space-y-2">
                                    {partidas.map((p, idx) => (
                                        <div key={idx} className={cn('items-center', 'gap-2', 'grid', 'grid-cols-12')}>
                                            {/* Selector de cuenta con búsqueda */}
                                            <div className={cn('relative', 'col-span-4')}>
                                                <input
                                                    type="text"
                                                    value={buscarCuenta[idx] ?? ''}
                                                    onChange={e => {
                                                        setBuscarCuenta(prev => ({ ...prev, [idx]: e.target.value }))
                                                        if (!e.target.value) actualizarPartida(idx, 'cuenta_id', '')
                                                        setDropdownOpen(idx)
                                                    }}
                                                    onFocus={() => setDropdownOpen(idx)}
                                                    placeholder="Buscar cuenta..."
                                                    className={cn('dark:bg-gray-800', 'px-2', 'py-1.5', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'focus:ring-1', 'focus:ring-amber-500', 'w-full', 'dark:text-gray-100', 'text-xs')}
                                                    style={{
                                                        borderColor: 'var(--border)',
                                                        background: 'var(--bg-main)',
                                                        color: 'var(--text-main)'
                                                    }}
                                                />
                                                {dropdownOpen === idx && (
                                                    <div className={cn('top-full', 'right-0', 'left-0', 'z-50', 'absolute', 'shadow-xl', 'mt-1', 'border', 'rounded-lg', 'overflow-hidden')}
                                                        style={{
                                                            background: 'var(--bg-card)',
                                                            borderColor: 'var(--border)',
                                                            maxHeight: '180px', overflowY: 'auto'
                                                        }}>
                                                        {cuentasFiltradas(idx).length === 0 ? (
                                                            <p className={cn('px-3', 'py-2', 'text-xs')}
                                                                style={{ color: 'var(--text-muted)' }}>
                                                                Sin resultados
                                                            </p>
                                                        ) : cuentasFiltradas(idx).map(c => (
                                                            <button key={c.id}
                                                                type="button"
                                                                onClick={() => seleccionarCuenta(idx, c)}
                                                                className={cn('hover:opacity-80', 'px-3', 'py-2', 'w-full', 'text-xs', 'text-left', 'transition-colors')}
                                                                style={{ borderBottom: '1px solid var(--border)' }}
                                                                onMouseEnter={e =>
                                                                (e.currentTarget.style.background =
                                                                    'rgba(245,158,11,0.08)')}
                                                                onMouseLeave={e =>
                                                                    (e.currentTarget.style.background = 'transparent')}>
                                                                <span className={cn('font-mono', 'font-bold')}
                                                                    style={{ color: 'var(--primary)' }}>
                                                                    {c.codigo}
                                                                </span>
                                                                <span className="ml-2"
                                                                    style={{ color: 'var(--text-main)' }}>
                                                                    {c.nombre}
                                                                </span>
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Descripción */}
                                            <input
                                                type="text"
                                                value={p.descripcion}
                                                onChange={e => actualizarPartida(idx, 'descripcion', e.target.value)}
                                                placeholder="Detalle..."
                                                className={cn('col-span-3', 'dark:bg-gray-800', 'px-2', 'py-1.5', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'focus:ring-1', 'focus:ring-amber-500', 'dark:text-gray-100', 'text-xs')}
                                                style={{
                                                    borderColor: 'var(--border)',
                                                    background: 'var(--bg-main)',
                                                    color: 'var(--text-main)'
                                                }}
                                            />

                                            {/* Debe */}
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={p.debe}
                                                onChange={e => {
                                                    actualizarPartida(idx, 'debe', e.target.value)
                                                    if (parseFloat(e.target.value) > 0)
                                                        actualizarPartida(idx, 'haber', '')
                                                }}
                                                placeholder="0.00"
                                                className={cn('col-span-2', 'dark:bg-gray-800', 'px-2', 'py-1.5', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'focus:ring-1', 'focus:ring-amber-500', 'tabular-nums', 'dark:text-gray-100', 'text-xs', 'text-right')}
                                                style={{
                                                    borderColor: 'var(--border)',
                                                    background: 'var(--bg-main)',
                                                    color: 'var(--text-main)'
                                                }}
                                            />

                                            {/* Haber */}
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={p.haber}
                                                onChange={e => {
                                                    actualizarPartida(idx, 'haber', e.target.value)
                                                    if (parseFloat(e.target.value) > 0)
                                                        actualizarPartida(idx, 'debe', '')
                                                }}
                                                placeholder="0.00"
                                                className={cn('col-span-2', 'dark:bg-gray-800', 'px-2', 'py-1.5', 'border', 'dark:border-gray-600', 'rounded-lg', 'focus:outline-none', 'focus:ring-1', 'focus:ring-amber-500', 'tabular-nums', 'dark:text-gray-100', 'text-xs', 'text-right')}
                                                style={{
                                                    borderColor: 'var(--border)',
                                                    background: 'var(--bg-main)',
                                                    color: 'var(--text-main)'
                                                }}
                                            />

                                            {/* Eliminar fila */}
                                            <button
                                                type="button"
                                                onClick={() => setPartidas(p => p.filter((_, i) => i !== idx))}
                                                disabled={partidas.length <= 2}
                                                className={cn('flex', 'justify-center', 'col-span-1', 'hover:bg-red-100', 'dark:hover:bg-red-900/30', 'disabled:opacity-20', 'p-1', 'rounded', 'transition-colors', 'disabled:cursor-not-allowed')}>
                                                <X size={13} className="text-red-500" />
                                            </button>
                                        </div>
                                    ))}
                                </div>

                                {/* Totales */}
                                <div className={cn('flex', 'justify-end', 'mt-3')}>
                                    <div className={cn('px-4', 'py-3', 'border', 'rounded-lg', 'min-w-70')}
                                        style={{
                                            background: cuadrado
                                                ? 'color-mix(in srgb, #10b981 8%, var(--bg-main))'
                                                : 'color-mix(in srgb, #ef4444 8%, var(--bg-main))',
                                            borderColor: cuadrado ? '#10b981' : '#ef4444',
                                        }}>
                                        <div className={cn('flex', 'justify-between', 'mb-1', 'text-sm')}>
                                            <span style={{ color: 'var(--text-muted)' }}>Total DEBE</span>
                                            <span className={cn('font-bold', 'tabular-nums')}
                                                style={{ color: 'var(--text-main)' }}>
                                                {formatMoney(totalDebe)}
                                            </span>
                                        </div>
                                        <div className={cn('flex', 'justify-between', 'mb-2', 'text-sm')}>
                                            <span style={{ color: 'var(--text-muted)' }}>Total HABER</span>
                                            <span className={cn('font-bold', 'tabular-nums')}
                                                style={{ color: 'var(--text-main)' }}>
                                                {formatMoney(totalHaber)}
                                            </span>
                                        </div>
                                        <div className={cn('flex', 'items-center', 'gap-2', 'pt-2', 'border-t')}
                                            style={{ borderColor: cuadrado ? '#10b981' : '#ef4444' }}>
                                            {cuadrado ? (
                                                <>
                                                    <CheckCircle size={14} className="text-green-500" />
                                                    <span className={cn('font-semibold', 'text-green-600', 'text-xs')}>
                                                        Asiento cuadrado ✓
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <AlertTriangle size={14} className="text-red-500" />
                                                    <span className={cn('font-semibold', 'text-red-600', 'text-xs')}>
                                                        Diferencia: {formatMoney(diferencia)}
                                                    </span>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Footer modal */}
                        <div className={cn('flex', 'gap-3', 'px-6', 'py-4', 'border-t', 'shrink-0')}
                            style={{ borderColor: 'var(--border)' }}>
                            <Button variant="outline" onClick={cerrarModal} className="flex-1">
                                Cancelar
                            </Button>
                            <Button
                                onClick={guardarAsiento}
                                disabled={!cuadrado || processing}
                                loading={processing}
                                className="flex-1"
                            >
                                Guardar Asiento
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            {/* Click-outside para cerrar dropdowns */}
            {dropdownOpen !== null && (
                <div className={cn('z-40', 'fixed', 'inset-0')} onClick={() => setDropdownOpen(null)} />
            )}

            <ToastContainer position="top-right" autoClose={3500}
                hideProgressBar={false} newestOnTop closeOnClick
                pauseOnHover draggable theme="colored" style={{ zIndex: 9999 }}
                toastStyle={{
                    borderRadius: '14px', fontSize: '14px',
                    fontWeight: '500', boxShadow: '0 8px 32px rgba(0,0,0,0.18)'
                }}
            />
        </AppLayout>
    )
}
