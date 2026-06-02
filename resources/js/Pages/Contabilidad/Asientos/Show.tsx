import { router, usePage, Link } from '@inertiajs/react'
import { ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import {
    BookOpen, CheckCircle, XCircle, Zap, User,
    ChevronLeft, ArrowLeftRight, Printer, Download,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { PageProps } from '@/types'
import { notify, formatMoney, swalBase, injectSwalStyles } from '@/utils/contabilidad'
import 'react-toastify/dist/ReactToastify.css'

interface CuentaRef {
    id: number
    codigo: string
    nombre: string
}

interface CentroCostoRef {
    id: number
    nombre: string
}

interface DetalleRef {
    id: number
    cuenta_id: number
    cuenta?: CuentaRef
    centro_costo_id: number | null
    centro_costo?: CentroCostoRef | null
    descripcion: string | null
    debe: number
    haber: number
}

interface EjercicioRef {
    id: number
    periodo_label: string
    estado: 'abierto' | 'cerrado'
}

interface UsuarioRef {
    id: number
    nombre: string
    email: string
}

interface AsientoDetallado {
    id: number
    empresa_id: number
    ejercicio_id: number | null
    ejercicio?: EjercicioRef | null
    numero: string
    fecha: string
    concepto: string
    documento_tipo: string | null
    documento_id: number | null
    documento_ref: string | null
    total_debe: number
    total_haber: number
    es_automatico: boolean
    estado: number
    creado_por?: UsuarioRef | null
    created_at: string
    detalles?: DetalleRef[]
}

interface Props extends PageProps {
    asiento: AsientoDetallado
}

export default function AsientoShow() {
    const { asiento, flash, auth } = usePage<Props>().props
    const perfil = auth.user?.perfil ?? ''
    const puedeAnular = ['super_admin', 'admin', 'contador'].includes(perfil)
    const cuadrado = Math.abs(asiento.total_debe - asiento.total_haber) < 0.0001

    const confirmarAnulacion = async () => {
        injectSwalStyles()
        const { value: motivo } = await Swal.fire({
            ...swalBase,
            title: `Anular ${asiento.numero}`,
            html: `
                <div style="text-align:left">
                    <div style="background:#fef2f2;border:1px solid #fecaca;
                                border-radius:8px;padding:12px;margin-bottom:14px">
                        <p style="font-weight:700;color:#dc2626;margin:0 0 4px 0;font-size:0.85rem">
                            Se generará un asiento de reversión automáticamente
                        </p>
                        <p style="color:#ef4444;font-size:0.8rem;margin:0">
                            El asiento original quedará marcado como ANULADO.
                        </p>
                    </div>
                    <label style="font-weight:600;color:#374151;font-size:0.875rem;
                                  display:block;margin-bottom:6px">
                        Motivo de anulación <span style="color:#ef4444">*</span>
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
            router.patch(
                route('contabilidad.asientos.anular', asiento.id),
                { motivo },
                {
                    onSuccess: () => notify.success(`Asiento ${asiento.numero} anulado. Reversión generada.`),
                    onError: (errors) => notify.error(Object.values(errors).flat().join(' ')),
                }
            )
        }
    }

    return (
        <AppLayout title={`Asiento ${asiento.numero}`} suppressFlash>
            <PageHeader
                title={`Asiento ${asiento.numero}`}
                description={asiento.concepto}
                breadcrumbs={[
                    { label: 'Contabilidad' },
                    { label: 'Asientos', href: route('contabilidad.asientos.index') },
                    { label: asiento.numero },
                ]}
                actions={
                    <div className="flex items-center gap-2">
                        <Link href={route('contabilidad.asientos.index')}>
                            <Button variant="outline">
                                <ChevronLeft className="w-4 h-4" />
                                Volver
                            </Button>
                        </Link>
                        <a href={route('contabilidad.asientos.pdf', asiento.id)}
                           target="_blank"
                           rel="noopener noreferrer"
                           className="inline-flex items-center gap-2 px-4 py-2 rounded-md
                                      text-sm font-medium border transition-colors
                                      hover:opacity-80 hover:-translate-y-0.5"
                           style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                            <Printer size={15} />
                            Imprimir PDF
                        </a>
                        <a href={`${route('contabilidad.asientos.exportar-excel')}?ejercicio_id=${asiento.ejercicio_id ?? ''}`}
                           className="inline-flex items-center gap-2 px-4 py-2 rounded-md
                                      text-sm font-medium border transition-colors
                                      hover:opacity-80 hover:-translate-y-0.5"
                           style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                            <Download size={15} />
                            Exportar .xlsx
                        </a>
                        {puedeAnular && asiento.estado === 1 && (
                            <Button
                                onClick={confirmarAnulacion}
                                className="bg-red-500 hover:bg-red-600 border-red-500">
                                <XCircle className="w-4 h-4" />
                                Anular asiento
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="p-6 space-y-5 max-w-4xl">
                {/* Cabecera del asiento */}
                <div className="rounded-xl border p-5"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="flex flex-wrap items-start justify-between gap-4 mb-4">
                        <div>
                            <span className="font-mono font-bold text-2xl"
                                  style={{ color: 'var(--primary)' }}>
                                {asiento.numero}
                            </span>
                            <div className="flex items-center gap-2 mt-1">
                                {/* Estado */}
                                {asiento.estado === 1 ? (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                     text-xs font-semibold bg-green-100 text-green-800
                                                     dark:bg-green-900/30 dark:text-green-300">
                                        <CheckCircle size={11} /> Activo
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                     text-xs font-semibold bg-red-100 text-red-800
                                                     dark:bg-red-900/30 dark:text-red-300">
                                        <XCircle size={11} /> Anulado
                                    </span>
                                )}
                                {/* Tipo */}
                                {asiento.es_automatico ? (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                     text-xs font-semibold bg-blue-100 text-blue-800
                                                     dark:bg-blue-900/30 dark:text-blue-300">
                                        <Zap size={11} /> Automático
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                                                     text-xs font-semibold bg-amber-100 text-amber-800
                                                     dark:bg-amber-900/30 dark:text-amber-300">
                                        <User size={11} /> Manual
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Totales highlight */}
                        <div className={cn(
                            'rounded-xl px-5 py-3 border',
                            cuadrado
                                ? 'border-green-300 dark:border-green-700'
                                : 'border-red-300 dark:border-red-700',
                        )}
                             style={{
                                 background: cuadrado
                                     ? 'color-mix(in srgb, #10b981 8%, var(--bg-main))'
                                     : 'color-mix(in srgb, #ef4444 8%, var(--bg-main))',
                             }}>
                            <div className="flex gap-8 text-sm">
                                <div className="text-center">
                                    <p style={{ color: 'var(--text-muted)' }} className="text-xs mb-0.5">DEBE</p>
                                    <p className="font-bold tabular-nums text-lg"
                                       style={{ color: 'var(--text-main)' }}>
                                        {formatMoney(asiento.total_debe)}
                                    </p>
                                </div>
                                <div className="flex items-center">
                                    <ArrowLeftRight size={16} style={{ color: 'var(--text-muted)' }} />
                                </div>
                                <div className="text-center">
                                    <p style={{ color: 'var(--text-muted)' }} className="text-xs mb-0.5">HABER</p>
                                    <p className="font-bold tabular-nums text-lg"
                                       style={{ color: 'var(--text-main)' }}>
                                        {formatMoney(asiento.total_haber)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center justify-center gap-1 mt-2">
                                {cuadrado ? (
                                    <>
                                        <CheckCircle size={12} className="text-green-500" />
                                        <span className="text-xs text-green-600 font-semibold">Cuadrado</span>
                                    </>
                                ) : (
                                    <>
                                        <XCircle size={12} className="text-red-500" />
                                        <span className="text-xs text-red-600 font-semibold">
                                            Diferencia: {formatMoney(Math.abs(asiento.total_debe - asiento.total_haber))}
                                        </span>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Metadatos */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t"
                         style={{ borderColor: 'var(--border)' }}>
                        <div>
                            <p className="text-xs uppercase tracking-wider mb-1"
                               style={{ color: 'var(--text-muted)' }}>Fecha</p>
                            <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                {asiento.fecha}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wider mb-1"
                               style={{ color: 'var(--text-muted)' }}>Período</p>
                            <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                {asiento.ejercicio?.periodo_label ?? '—'}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wider mb-1"
                               style={{ color: 'var(--text-muted)' }}>Creado por</p>
                            <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                {asiento.creado_por?.nombre ?? 'Sistema'}
                            </p>
                        </div>
                        {asiento.documento_ref && (
                            <div>
                                <p className="text-xs uppercase tracking-wider mb-1"
                                   style={{ color: 'var(--text-muted)' }}>Documento</p>
                                <p className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                                    {asiento.documento_tipo} · {asiento.documento_ref}
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Partidas */}
                <div className="rounded-xl border overflow-hidden"
                     style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3 border-b flex items-center gap-2"
                         style={{ borderColor: 'var(--border)',
                                  background: 'rgba(245,158,11,0.05)' }}>
                        <BookOpen size={16} style={{ color: 'var(--primary)' }} />
                        <h2 className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                            Partidas del asiento
                        </h2>
                        <span className="ml-auto text-xs px-2 py-0.5 rounded-full"
                              style={{ background: 'var(--bg-main)', color: 'var(--text-muted)',
                                       border: '1px solid var(--border)' }}>
                            {asiento.detalles?.length ?? 0} líneas
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    <th className="text-left px-4 py-2 text-xs font-semibold uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>Cuenta</th>
                                    <th className="text-left px-4 py-2 text-xs font-semibold uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>Descripción</th>
                                    <th className="text-right px-4 py-2 text-xs font-semibold uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>Debe</th>
                                    <th className="text-right px-4 py-2 text-xs font-semibold uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>Haber</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(asiento.detalles ?? []).map((d, i) => (
                                    <tr key={d.id}
                                        style={{ borderBottom: '1px solid var(--border)' }}>
                                        <td className="px-4 py-2.5">
                                            <span className="font-mono font-bold text-xs"
                                                  style={{ color: 'var(--primary)' }}>
                                                {d.cuenta?.codigo}
                                            </span>
                                            <span className="ml-2 text-xs"
                                                  style={{ color: 'var(--text-main)' }}>
                                                {d.cuenta?.nombre}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-xs"
                                            style={{ color: 'var(--text-muted)' }}>
                                            {d.descripcion ?? '—'}
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums font-medium text-xs">
                                            {d.debe > 0
                                                ? <span className="text-blue-600 dark:text-blue-400">
                                                    {formatMoney(d.debe)}
                                                  </span>
                                                : <span style={{ color: 'var(--text-muted)' }}>—</span>
                                            }
                                        </td>
                                        <td className="px-4 py-2.5 text-right tabular-nums font-medium text-xs">
                                            {d.haber > 0
                                                ? <span className="text-green-600 dark:text-green-400">
                                                    {formatMoney(d.haber)}
                                                  </span>
                                                : <span style={{ color: 'var(--text-muted)' }}>—</span>
                                            }
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            {/* Fila de totales */}
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)',
                                             background: 'rgba(245,158,11,0.05)' }}>
                                    <td colSpan={2}
                                        className="px-4 py-2.5 font-bold text-xs uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>
                                        TOTALES
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums font-bold text-sm text-blue-600 dark:text-blue-400">
                                        {formatMoney(asiento.total_debe)}
                                    </td>
                                    <td className="px-4 py-2.5 text-right tabular-nums font-bold text-sm text-green-600 dark:text-green-400">
                                        {formatMoney(asiento.total_haber)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <ToastContainer position="top-right" autoClose={3500}
                hideProgressBar={false} newestOnTop closeOnClick
                pauseOnHover draggable theme="colored" style={{ zIndex: 9999 }}
                toastStyle={{ borderRadius: '14px', fontSize: '14px',
                              fontWeight: '500', boxShadow: '0 8px 32px rgba(0,0,0,0.18)' }}
            />
        </AppLayout>
    )
}
