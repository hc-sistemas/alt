import { useState, useMemo } from 'react'
import { router, usePage } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import { Settings, Search, CheckCircle, AlertCircle, Zap, Save } from 'lucide-react'
import { notify, swalBase } from '@/utils/contabilidad'
import type { PlanCuenta, PageProps } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

interface Parametro {
    codigo: string
    descripcion: string
    grupo: string
    cuenta_id: number | null
    cuenta: string | null
    configurado: boolean
}

interface Props extends PageProps {
    grupos: Record<string, Parametro[]>
    cuentas: PlanCuenta[]
    stats: { total: number; configurados: number; pendientes: number }
}

const COLORES_GRUPO: Record<string, string> = {
    Ventas:     '#10b981',
    Compras:    '#3b82f6',
    Inventario: '#8b5cf6',
    Bancos:     '#F59E0B',
    'Nómina':   '#ec4899',
    SRI:        '#ef4444',
}

export default function ParametrosIndex({ grupos, cuentas, stats }: Props) {
    usePage<Props>()

    const [valores, setValores] = useState<Record<string, number | null>>(() => {
        const init: Record<string, number | null> = {}
        Object.values(grupos).flat().forEach(p => {
            init[p.codigo] = p.cuenta_id
        })
        return init
    })

    const [modalAbierto, setModalAbierto] = useState(false)
    const [codigoActivo, setCodigoActivo] = useState<string | null>(null)
    const [busqueda, setBusqueda]         = useState('')

    const cuentasFiltradas = useMemo(() => {
        const q = busqueda.toLowerCase().trim()
        if (!q) return cuentas.slice(0, 30)
        return cuentas.filter(c =>
            c.codigo.toLowerCase().includes(q) ||
            c.nombre.toLowerCase().includes(q)
        ).slice(0, 25)
    }, [busqueda, cuentas])

    const abrirModal = (codigo: string) => {
        setCodigoActivo(codigo)
        setBusqueda('')
        setModalAbierto(true)
    }

    const seleccionarCuenta = (cuenta: PlanCuenta | null) => {
        if (codigoActivo) {
            setValores(v => ({ ...v, [codigoActivo]: cuenta?.id ?? null }))
        }
        setModalAbierto(false)
        setCodigoActivo(null)
    }

    const getNombreCuenta = (codigo: string): string => {
        const id = valores[codigo]
        if (!id) return '— Sin configurar —'
        const c = cuentas.find(c => c.id === id)
        return c ? `${c.codigo} — ${c.nombre}` : '— Sin configurar —'
    }

    const guardar = () => {
        const parametros = Object.entries(valores).map(([cod, cta_id]) => ({
            codigo:    cod,
            cuenta_id: cta_id,
        }))
        router.post(route('contabilidad.parametros.update'), { parametros }, {
            onSuccess: () => notify.success('Parámetros contables guardados correctamente'),
            onError:   () => notify.error('Error al guardar los parámetros'),
        })
    }

    const autoconfigurar = async () => {
        const result = await Swal.fire({
            ...swalBase,
            title: 'Autoconfigurar parámetros',
            html: `
                <div style="text-align:left">
                    <p style="color:#374151;margin-bottom:12px">
                        El sistema asignará automáticamente las cuentas contables
                        del Plan de Cuentas de Altamira a cada parámetro.
                    </p>
                    <div style="background:#fef3c7;border-radius:8px;padding:10px">
                        <p style="color:#92400e;font-size:0.85rem;margin:0">
                            ⚠️ Esto sobreescribirá los parámetros ya configurados.
                        </p>
                    </div>
                </div>
            `,
            icon: 'question',
            showCancelButton:    true,
            confirmButtonColor:  '#F59E0B',
            cancelButtonColor:   '#6b7280',
            confirmButtonText:   '⚡ Sí, autoconfigurar',
            cancelButtonText:    'Cancelar',
            reverseButtons:      true,
        })

        if (result.isConfirmed) {
            router.post(route('contabilidad.parametros.auto'), {}, {
                onSuccess: () => {
                    notify.success('Parámetros configurados automáticamente')
                    router.reload()
                },
                onError: () => notify.error('Error al autoconfigurar'),
            })
        }
    }

    // Calcular stats actuales (desde estado local)
    const totalConfigurados = Object.values(valores).filter(Boolean).length
    const totalPendientes   = Object.values(valores).filter(v => !v).length

    return (
        <AppLayout>
            <div className="p-4 md:p-6 space-y-6"
                 style={{ background: 'var(--bg-main)', minHeight: '100vh' }}>

                {/* HEADER */}
                <div className="mb-6">
                    <div className="flex items-center gap-3 mb-4">
                        <div className="p-2 rounded-xl"
                             style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                            <Settings size={24} style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                                Parámetros Contables
                            </h1>
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Mapeo de eventos operativos a cuentas del Plan de Cuentas
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 flex-wrap mb-6">
                        <button onClick={guardar}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl
                                       font-semibold text-sm text-white whitespace-nowrap transition-all
                                       hover:opacity-90 hover:-translate-y-0.5"
                            style={{ background: 'var(--primary)' }}>
                            <Save size={15} />
                            Guardar cambios
                        </button>
                        <button onClick={autoconfigurar}
                            className="flex items-center gap-2 px-4 py-2 rounded-xl
                                       font-semibold text-sm border whitespace-nowrap transition-all hover:opacity-80"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                            <Zap size={15} style={{ color: 'var(--primary)' }} />
                            Autoconfigurar
                        </button>
                    </div>
                </div>

                {/* STATS */}
                <div className="grid grid-cols-3 gap-4">
                    {[
                        { label: 'Total',        value: stats.total,        color: '#3b82f6' },
                        { label: 'Configurados', value: totalConfigurados,  color: '#10b981' },
                        { label: 'Pendientes',   value: totalPendientes,    color: '#ef4444' },
                    ].map(({ label, value, color }) => (
                        <div key={label} className="rounded-2xl p-4 border"
                             style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                            <p className="text-xs font-medium uppercase tracking-wider mb-1"
                               style={{ color: 'var(--text-muted)' }}>{label}</p>
                            <p className="text-2xl font-bold" style={{ color }}>{value}</p>
                        </div>
                    ))}
                </div>

                {/* Banner pendientes */}
                {totalPendientes > 0 && (
                    <div className="flex items-center gap-3 p-4 rounded-xl border"
                         style={{
                             background:  'color-mix(in srgb, #ef4444 8%, var(--bg-card))',
                             borderColor: '#ef4444',
                         }}>
                        <AlertCircle size={18} className="text-red-500 shrink-0" />
                        <div>
                            <p className="font-semibold text-red-700 dark:text-red-400 text-sm">
                                {totalPendientes} parámetro(s) sin configurar
                            </p>
                            <p className="text-xs text-red-600 dark:text-red-500 mt-0.5">
                                Los asientos automáticos fallarán hasta que estén configurados.
                                Usa "Autoconfigurar" para asignarlos automáticamente.
                            </p>
                        </div>
                    </div>
                )}

                {/* GRUPOS */}
                {Object.entries(grupos).map(([nombreGrupo, params]) => (
                    <div key={nombreGrupo} className="rounded-2xl overflow-hidden border"
                         style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>

                        <div className="px-4 py-3 flex items-center gap-2"
                             style={{
                                 borderBottom: '1px solid var(--border)',
                                 borderLeft:   `4px solid ${COLORES_GRUPO[nombreGrupo] ?? '#6b7280'}`,
                             }}>
                            <span className="font-bold text-sm"
                                  style={{ color: COLORES_GRUPO[nombreGrupo] ?? 'var(--text-main)' }}>
                                {nombreGrupo}
                            </span>
                            <span className="text-xs px-2 py-0.5 rounded-full ml-auto"
                                  style={{ background: 'var(--bg-main)', color: 'var(--text-muted)' }}>
                                {params.filter(p => valores[p.codigo]).length}/{params.length}
                            </span>
                        </div>

                        {params.map((param, idx) => {
                            const tieneValor  = !!valores[param.codigo]
                            const nombreCuenta = getNombreCuenta(param.codigo)

                            return (
                                <div key={param.codigo}
                                     className="flex flex-col md:flex-row md:items-center gap-3 px-4 py-3"
                                     style={{
                                         borderBottom: idx < params.length - 1
                                             ? '1px solid var(--border)' : 'none',
                                     }}>

                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            {tieneValor
                                                ? <CheckCircle size={14} className="text-green-500 shrink-0" />
                                                : <AlertCircle size={14} className="text-red-400 shrink-0" />}
                                            <p className="text-sm font-medium truncate"
                                               style={{ color: 'var(--text-main)' }}>
                                                {param.descripcion}
                                            </p>
                                        </div>
                                        <p className="text-xs mt-0.5 ml-5 font-mono"
                                           style={{ color: 'var(--text-muted)' }}>
                                            {param.codigo}
                                        </p>
                                    </div>

                                    <div className="md:w-80">
                                        <button type="button"
                                            onClick={() => abrirModal(param.codigo)}
                                            className="w-full flex items-center justify-between
                                                       rounded-lg px-3 py-2 text-sm border
                                                       transition-colors hover:opacity-80 text-left"
                                            style={{
                                                borderColor: tieneValor ? '#10b981' : '#ef4444',
                                                background:  'var(--bg-main)',
                                                color:       tieneValor ? 'var(--text-main)' : '#ef4444',
                                            }}>
                                            <span className="truncate text-xs">{nombreCuenta}</span>
                                            <Search size={12}
                                                    style={{ color: 'var(--text-muted)', flexShrink: 0 }} />
                                        </button>
                                    </div>
                                </div>
                            )
                        })}
                    </div>
                ))}
            </div>

            {/* MODAL BÚSQUEDA */}
            {modalAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style={{ background: 'rgba(0,0,0,0.6)' }}
                     onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden"
                         style={{ background: 'var(--bg-card)' }}
                         onClick={e => e.stopPropagation()}>

                        <div className="flex items-center justify-between px-4 py-3 border-b"
                             style={{ borderColor: 'var(--border)' }}>
                            <h3 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                Seleccionar cuenta contable
                            </h3>
                            <button onClick={() => setModalAbierto(false)}
                                    className="text-gray-400 hover:text-gray-600 text-xl leading-none">
                                ×
                            </button>
                        </div>

                        <div className="px-4 py-3 border-b" style={{ borderColor: 'var(--border)' }}>
                            <div className="relative">
                                <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2"
                                        style={{ color: 'var(--text-muted)' }} />
                                <input autoFocus type="text" value={busqueda}
                                    onChange={e => setBusqueda(e.target.value)}
                                    placeholder="Buscar por código o nombre..."
                                    className="w-full pl-9 pr-3 py-2 text-sm rounded-lg border
                                               focus:outline-none focus:ring-2"
                                    style={{
                                        borderColor: 'var(--border)',
                                        background:  'var(--bg-main)',
                                        color:       'var(--text-main)',
                                    }}
                                />
                            </div>
                        </div>

                        <div className="overflow-y-auto" style={{ maxHeight: '380px' }}>
                            <button onClick={() => seleccionarCuenta(null)}
                                className="w-full text-left px-4 py-2.5 text-sm border-b
                                           transition-colors hover:opacity-80"
                                style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                                — Sin configurar —
                            </button>

                            {cuentasFiltradas.map(c => (
                                <button key={c.id} onClick={() => seleccionarCuenta(c)}
                                    className="w-full text-left px-4 py-2.5 text-sm border-b
                                               transition-colors hover:opacity-80 flex items-center gap-3"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <span className="font-mono font-bold text-xs shrink-0"
                                          style={{ color: 'var(--primary)' }}>
                                        {c.codigo}
                                    </span>
                                    <span className="truncate" style={{ color: 'var(--text-main)' }}>
                                        {c.nombre}
                                    </span>
                                </button>
                            ))}

                            {cuentasFiltradas.length === 0 && (
                                <div className="px-4 py-8 text-center text-sm"
                                     style={{ color: 'var(--text-muted)' }}>
                                    Sin resultados para "{busqueda}"
                                </div>
                            )}
                        </div>

                        <div className="px-4 py-2 border-t text-xs"
                             style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}>
                            {cuentasFiltradas.length} resultado(s)
                        </div>
                    </div>
                </div>
            )}

            <ToastContainer position="top-right" autoClose={3500}
                hideProgressBar={false} newestOnTop closeOnClick
                pauseOnHover draggable theme="colored"
                style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px', fontWeight: '500' }}
            />
        </AppLayout>
    )
}
