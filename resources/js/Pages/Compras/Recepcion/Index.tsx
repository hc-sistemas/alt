import { useEffect, useRef, useState } from 'react'
import { router, usePage, Head, Link } from '@inertiajs/react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { cn } from '@/lib/utils'
import { Package, Scan, CheckCircle2, AlertTriangle, ArrowLeft, Barcode } from 'lucide-react'
import type { Compra, PageProps } from '@/types'
import { usePermiso } from '@/Hooks/usePermiso'

// ─── Types ────────────────────────────────────────────────────────────────────

interface ItemEsperado {
    detalle_id: number
    producto_id: number
    codigo: string
    nombre: string
    unidad: string
    cantidad: number
}

interface ItemEscaneado {
    codigoCompleto: string   // ej: IML.000037-000009
    codigoProducto: string   // ej: IML.000037
    correlativo: number      // 9
    ts: number
}

interface Props extends PageProps {
    compra: Compra & { proveedor?: { razon_social: string } }
    itemsEsperados: ItemEsperado[]
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function parsearCodigo(raw: string): { codigoProducto: string; correlativo: number } | null {
    // Formato esperado: "ABC.000037-000009" o "ABC123-000009"
    // El correlativo siempre son los últimos dígitos después del último '-'
    const m = raw.trim().match(/^(.+)-(\d+)$/)
    if (!m) return null
    return { codigoProducto: m[1], correlativo: parseInt(m[2], 10) }
}

function progresoPorProducto(
    esperados: ItemEsperado[],
    escaneados: ItemEscaneado[],
): { item: ItemEsperado; recibidos: number; completo: boolean }[] {
    return esperados.map(item => {
        const recibidos = escaneados.filter(e => e.codigoProducto === item.codigo).length
        return { item, recibidos, completo: recibidos >= item.cantidad }
    })
}

// ─── Página ──────────────────────────────────────────────────────────────────

export default function RecepcionIndex() {
    const { compra, itemsEsperados } = usePage<Props>().props
    const { puede } = usePermiso('compras')

    const inputRef                  = useRef<HTMLInputElement>(null)
    const [scan, setScan]           = useState('')
    const [escaneados, setEscaneados] = useState<ItemEscaneado[]>([])
    const [ultimoScan, setUltimoScan] = useState<{ codigo: string; ok: boolean; msg: string } | null>(null)
    const [completando, setCompletando] = useState(false)

    // Foco automático en el input de scan
    useEffect(() => {
        inputRef.current?.focus()
    }, [])

    const progreso     = progresoPorProducto(itemsEsperados, escaneados)
    const totalEsperado  = progreso.reduce((s, p) => s + p.item.cantidad, 0)
    const totalRecibido  = progreso.reduce((s, p) => s + Math.min(p.recibidos, p.item.cantidad), 0)
    const todoCompleto   = progreso.every(p => p.completo)

    function procesarScan(raw: string) {
        const valor = raw.trim()
        if (!valor) return

        setScan('')
        setTimeout(() => inputRef.current?.focus(), 50)

        const parsed = parsearCodigo(valor)

        // Código no tiene el formato esperado — buscar si es un código de producto directo
        const productoDirecto = !parsed
            ? itemsEsperados.find(i => i.codigo === valor)
            : null

        if (!parsed && !productoDirecto) {
            setUltimoScan({ codigo: valor, ok: false, msg: 'Código no reconocido' })
            return
        }

        const codigoProducto = parsed?.codigoProducto ?? valor
        const correlativo    = parsed?.correlativo ?? 0

        const itemEsperado = itemsEsperados.find(i => i.codigo === codigoProducto)
        if (!itemEsperado) {
            setUltimoScan({ codigo: valor, ok: false, msg: `Producto "${codigoProducto}" no está en esta factura` })
            return
        }

        // Verificar duplicado (mismo código de barras completo)
        if (valor.includes('-') && escaneados.some(e => e.codigoCompleto === valor)) {
            setUltimoScan({ codigo: valor, ok: false, msg: 'Esta unidad ya fue escaneada' })
            return
        }

        const yaRecibidos = escaneados.filter(e => e.codigoProducto === codigoProducto).length
        if (yaRecibidos >= itemEsperado.cantidad) {
            setUltimoScan({ codigo: valor, ok: false, msg: `Cantidad completa para ${codigoProducto}` })
            return
        }

        setEscaneados(prev => [...prev, {
            codigoCompleto:  valor,
            codigoProducto,
            correlativo,
            ts: Date.now(),
        }])

        const nuevosRecibidos = yaRecibidos + 1
        setUltimoScan({
            codigo: valor,
            ok: true,
            msg: `${codigoProducto} — ${nuevosRecibidos}/${itemEsperado.cantidad} unidades`,
        })
    }

    function completarRecepcion(forzar = false) {
        const incompletos = progreso.filter(p => !p.completo)

        if (!forzar && incompletos.length > 0) {
            const lista = incompletos
                .map(p => `${p.item.codigo}: ${p.recibidos}/${p.item.cantidad}`)
                .join('<br/>')

            Swal.fire({
                title: '¿Confirmar recepción incompleta?',
                html: `<p style="color:#6b7280;font-size:13px;margin-bottom:10px">
                           Solo se escanearon <strong>${totalRecibido}</strong> de <strong>${totalEsperado}</strong> unidades:
                       </p>
                       <div style="text-align:left;font-size:12px;color:#374151;padding:8px 12px;
                                   background:#fef3c7;border-radius:8px;border-left:3px solid #f59e0b">
                           ${lista}
                       </div>
                       <p style="color:#6b7280;font-size:12px;margin-top:10px">
                           ¿Confirmar de todas formas?
                       </p>`,
                confirmButtonText:  'Confirmar igual',
                cancelButtonText:   'Cancelar',
                confirmButtonColor: '#f59e0b',
                cancelButtonColor:  '#6b7280',
                showCancelButton: true,
                reverseButtons: true,
                customClass: { popup: 'rounded-2xl' },
            }).then(r => { if (r.isConfirmed) completarRecepcion(true) })
            return
        }

        setCompletando(true)
        router.post(route('compras.facturas.activar', compra.id), {}, {
            onSuccess: () => {
                // Redirigir al listado con éxito
                router.visit(route('compras.facturas.index'), {
                    replace: true,
                })
            },
            onError: (e) => {
                Swal.fire({
                    title: 'Error',
                    text: Object.values(e)[0] ?? 'Error al confirmar recepción',
                    icon: 'error',
                })
                setCompletando(false)
            },
        })
    }

    const pctTotal = totalEsperado > 0 ? Math.round((totalRecibido / totalEsperado) * 100) : 0

    return (
        <AppLayout title="Recepción de Bodega">
            <Head title="Recepción de Bodega" />

            <PageHeader
                title="Recepción de Bodega"
                description={`Factura ${compra.num_documento} — ${compra.proveedor?.razon_social ?? ''}`}
                breadcrumbs={[
                    { label: 'Compras', href: route('compras.facturas.index') },
                    { label: 'Recepción de Bodega' },
                ]}
            />

            <div className="p-6 max-w-4xl space-y-6">

                {/* ── Scanner de códigos ── */}
                <div className="rounded-2xl border p-6" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center gap-3 mb-4">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center"
                            style={{ background: 'rgba(245,158,11,0.1)' }}>
                            <Scan className="w-5 h-5" style={{ color: 'var(--primary)' }} />
                        </div>
                        <div>
                            <p className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                                Escanear etiquetas
                            </p>
                            <p className="text-xs" style={{ color: 'var(--text-muted)' }}>
                                Apunta la pistola al código de barras de cada unidad
                            </p>
                        </div>
                    </div>

                    {puede('editar') && (
                        <input
                            ref={inputRef}
                            type="text"
                            value={scan}
                            onChange={e => setScan(e.target.value)}
                            onKeyDown={e => {
                                if (e.key === 'Enter') { procesarScan(scan) }
                            }}
                            onBlur={() => setTimeout(() => inputRef.current?.focus(), 100)}
                            placeholder="Escanear código de barras… (Enter para confirmar)"
                            className="input-field w-full text-base font-mono"
                            style={{ fontSize: '15px', letterSpacing: '0.05em' }}
                            autoComplete="off"
                        />
                    )}

                    {/* Feedback del último scan */}
                    {ultimoScan && (
                        <div className={cn(
                            'mt-3 flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-medium',
                            ultimoScan.ok
                                ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800'
                                : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800',
                        )}>
                            {ultimoScan.ok
                                ? <CheckCircle2 className="w-4 h-4 shrink-0" />
                                : <AlertTriangle className="w-4 h-4 shrink-0" />
                            }
                            <span className="font-mono text-xs mr-2 opacity-70">{ultimoScan.codigo}</span>
                            <span>{ultimoScan.msg}</span>
                        </div>
                    )}
                </div>

                {/* ── Progreso global ── */}
                <div className="rounded-2xl border p-4" style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                    <div className="flex items-center justify-between mb-2">
                        <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Progreso de recepción
                        </span>
                        <span className="text-sm font-bold" style={{ color: todoCompleto ? '#10b981' : 'var(--primary)' }}>
                            {totalRecibido} / {totalEsperado} unidades ({pctTotal}%)
                        </span>
                    </div>
                    <div className="w-full h-2 rounded-full overflow-hidden" style={{ background: 'var(--bg-main)' }}>
                        <div
                            className="h-2 rounded-full transition-all duration-300"
                            style={{
                                width: `${pctTotal}%`,
                                background: todoCompleto ? '#10b981' : 'var(--primary)',
                            }}
                        />
                    </div>
                </div>

                {/* ── Tabla de productos ── */}
                <div className="rounded-xl border overflow-hidden" style={{ borderColor: 'var(--border)' }}>
                    <div className="px-4 py-3 border-b flex items-center gap-2"
                        style={{ borderColor: 'var(--border)', background: 'var(--bg-card)' }}>
                        <Package className="w-4 h-4" style={{ color: 'var(--primary)' }} />
                        <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Productos en la factura
                        </span>
                    </div>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'rgba(245,158,11,0.05)', borderBottom: '1px solid var(--border)' }}>
                                {['Código', 'Producto', 'Esperado', 'Recibidos', 'Estado'].map(h => (
                                    <th key={h} className="text-left px-3 py-2 font-semibold uppercase tracking-wider"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {progreso.map(({ item, recibidos, completo }) => (
                                <tr key={item.detalle_id} className="border-t" style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-3 py-2 font-mono font-bold" style={{ color: 'var(--primary)' }}>
                                        {item.codigo}
                                    </td>
                                    <td className="px-3 py-2" style={{ color: 'var(--text-main)' }}>
                                        <p>{item.nombre}</p>
                                        {item.unidad && (
                                            <p className="text-[10px]" style={{ color: 'var(--text-muted)' }}>{item.unidad}</p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-center" style={{ color: 'var(--text-muted)' }}>
                                        {item.cantidad}
                                    </td>
                                    <td className="px-3 py-2 text-center">
                                        <span className={cn(
                                            'font-bold',
                                            completo ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400',
                                        )}>
                                            {recibidos}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2">
                                        {completo ? (
                                            <span className="flex items-center gap-1 text-green-600 dark:text-green-400 font-medium">
                                                <CheckCircle2 className="w-3.5 h-3.5" /> Completo
                                            </span>
                                        ) : (
                                            <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                <Barcode className="w-3.5 h-3.5" />
                                                Pendiente ({item.cantidad - recibidos} restantes)
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* ── Acciones ── */}
                <div className="flex items-center justify-between gap-3 pb-6">
                    <div className="flex items-center gap-3">
                        <Link href={route('compras.facturas.index')} className="btn-secondary flex items-center gap-2">
                            <ArrowLeft className="w-4 h-4" /> Volver
                        </Link>
                    </div>

                    {puede('editar') && (
                        <button
                            onClick={() => completarRecepcion(false)}
                            disabled={completando || escaneados.length === 0}
                            className={cn(
                                'flex items-center gap-2 px-6 py-2.5 rounded-xl font-semibold text-sm text-black transition-all',
                                todoCompleto
                                    ? 'bg-green-600 hover:bg-green-700'
                                    : 'bg-amber-500 hover:bg-amber-600',
                                (completando || escaneados.length === 0) && 'opacity-50 cursor-not-allowed',
                            )}>
                            <CheckCircle2 className="w-4 h-4" />
                            {completando
                                ? 'Procesando…'
                                : todoCompleto
                                    ? `Completar Recepción (${totalRecibido}/${totalEsperado})`
                                    : `Completar Recepción (${totalRecibido}/${totalEsperado} — incompleto)`
                            }
                        </button>
                    )}
                </div>
            </div>
        </AppLayout>
    )
}
