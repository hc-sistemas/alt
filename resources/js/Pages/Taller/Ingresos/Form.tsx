import React, { useState } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import BuscadorClienteModal from '@/Components/shared/BuscadorClienteModal'
import { toastError } from '@/lib/toast'
import { Search, Save, X, AlertTriangle, RotateCcw } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, Cliente, TallerTipoEquipo, TallerEquipo } from '@/types'

interface Props extends PageProps {
    clientes: Cliente[]
    tiposEquipo: TallerTipoEquipo[]
}

type EstadoBusquedaEquipo = 'idle' | 'searching' | 'found' | 'new'

const equipoVacio = {
    tipo_id: '' as number | '',
    marca: '',
    modelo: '',
    color: '',
    medida: '',
    adicional: '',
    observaciones: '',
}

export default function IngresoForm() {
    const { clientes, tiposEquipo, errors } = usePage<Props>().props
    const { puede } = usePermiso('taller')

    // — Cliente
    const [identificacion, setIdentificacion] = useState('')
    const [clienteSeleccionado, setClienteSeleccionado] = useState<Cliente | null>(null)
    const [modalCliente, setModalCliente] = useState<Cliente[]>([])
    const [mensajeCliente, setMensajeCliente] = useState('')

    // — Equipo
    const [numeroSerie, setNumeroSerie] = useState('')
    const [estadoEquipo, setEstadoEquipo] = useState<EstadoBusquedaEquipo>('idle')
    const [equipoEncontrado, setEquipoEncontrado] = useState<TallerEquipo | null>(null)
    const [equipoForm, setEquipoForm] = useState({ ...equipoVacio })

    // — Datos del ingreso
    const [diagnosticoInicial, setDiagnosticoInicial] = useState('')
    const [observaciones, setObservaciones] = useState('')
    const [imagen, setImagen] = useState('')

    const [guardando, setGuardando] = useState(false)

    // ── Handlers: cliente ────────────────────────────────────────────────────

    const seleccionarCliente = (c: Cliente) => {
        setClienteSeleccionado(c)
        setIdentificacion(c.identificacion)
        setMensajeCliente('')
        setModalCliente([])
    }

    const handleBuscarCliente = () => {
        const q = identificacion.trim()
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
            setClienteSeleccionado(null)
            setMensajeCliente('Cliente no encontrado.')
        }
    }

    const limpiarCliente = () => {
        setClienteSeleccionado(null)
        setIdentificacion('')
        setMensajeCliente('')
    }

    // ── Handlers: equipo ─────────────────────────────────────────────────────

    const buscarEquipoPorSerie = async () => {
        const serie = numeroSerie.trim()
        if (!serie) return
        setEstadoEquipo('searching')
        try {
            const res = await fetch(route('taller.equipos.buscar', { serie }), {
                headers: { Accept: 'application/json' },
            })
            const data = await res.json() as { found: boolean; equipo: TallerEquipo | null }
            if (data.found && data.equipo) {
                setEquipoEncontrado(data.equipo)
                setEstadoEquipo('found')
            } else {
                setEquipoEncontrado(null)
                setEquipoForm({ ...equipoVacio })
                setEstadoEquipo('new')
            }
        } catch {
            toastError('Error al buscar el equipo')
            setEstadoEquipo('idle')
        }
    }

    const limpiarEquipo = () => {
        setNumeroSerie('')
        setEquipoEncontrado(null)
        setEquipoForm({ ...equipoVacio })
        setEstadoEquipo('idle')
    }

    // ── Submit ───────────────────────────────────────────────────────────────

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()

        const errs: string[] = []
        if (!clienteSeleccionado) errs.push('Debe seleccionar un cliente.')
        if (estadoEquipo === 'idle') {
            errs.push('Debe buscar o registrar el equipo.')
        }
        if (estadoEquipo === 'new') {
            if (!equipoForm.tipo_id) errs.push('El tipo de equipo es obligatorio.')
            if (!equipoForm.marca.trim()) errs.push('La marca del equipo es obligatoria.')
            if (!equipoForm.modelo.trim()) errs.push('El modelo del equipo es obligatorio.')
        }
        if (!diagnosticoInicial.trim()) errs.push('El diagnóstico inicial es obligatorio.')
        if (errs.length > 0) { errs.forEach(toastError); return }
        setGuardando(true)

        router.post(route('taller.ingresos.store'), {
            cliente_id: clienteSeleccionado!.id,
            equipo: {
                equipo_id: estadoEquipo === 'found' ? equipoEncontrado?.id ?? null : null,
                numero_serie: numeroSerie.trim() || null,
                tipo_id: equipoForm.tipo_id || null,
                marca: equipoForm.marca || null,
                modelo: equipoForm.modelo || null,
                color: equipoForm.color || null,
                medida: equipoForm.medida || null,
                adicional: equipoForm.adicional || null,
                observaciones: equipoForm.observaciones || null,
            },
            diagnostico_inicial: diagnosticoInicial || null,
            observaciones: observaciones || null,
            imagen: imagen || null,
        }, {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        })
    }

    const tipoLabel: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }

    return (
        <AppLayout>
            <Head title="Nuevo Ingreso" />
            <PageHeader
                title="Nuevo Ingreso al Taller"
                breadcrumbs={[
                    { label: 'Taller', href: route('taller.ingresos.index') },
                    { label: 'Ingresos', href: route('taller.ingresos.index') },
                    { label: 'Nuevo' },
                ]}
            />

            <form onSubmit={handleSubmit} className="p-6 space-y-6 max-w-4xl">

                {errors?.error && (
                    <div className="rounded-lg p-4 border" style={{ background: 'rgba(239,68,68,.1)', borderColor: 'rgba(239,68,68,.3)' }}>
                        <p className="text-sm text-red-400 flex items-center gap-2">
                            <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                            {errors.error}
                        </p>
                    </div>
                )}

                {/* ── SECCIÓN 1: Cliente ── */}
                <div className="rounded-xl p-5 border" style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-xs font-semibold uppercase tracking-wider mb-4" style={{ color: 'var(--text-muted)' }}>
                        Cliente
                    </p>

                    {clienteSeleccionado ? (
                        <div className="flex items-start justify-between gap-4 flex-wrap">
                            <div className="space-y-1 text-sm">
                                <p className="font-semibold" style={{ color: 'var(--text-main)' }}>{clienteSeleccionado.razon_social}</p>
                                <p style={{ color: 'var(--text-muted)' }}>
                                    {tipoLabel[clienteSeleccionado.tipo_identificacion] ?? 'ID'}: {clienteSeleccionado.identificacion}
                                </p>
                                {clienteSeleccionado.telefono && (
                                    <p style={{ color: 'var(--text-muted)' }}>Tel: {clienteSeleccionado.telefono}</p>
                                )}
                                {clienteSeleccionado.email && (
                                    <p style={{ color: 'var(--text-muted)' }}>Email: {clienteSeleccionado.email}</p>
                                )}
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={limpiarCliente}>
                                <RotateCcw className="w-3.5 h-3.5" />
                                Cambiar cliente
                            </Button>
                        </div>
                    ) : (
                        <div style={{ maxWidth: 420 }}>
                            <Label style={{ color: 'var(--text-main)' }}>Identificación</Label>
                            <div className="flex items-center gap-2 mt-1">
                                <Input
                                    value={identificacion}
                                    onChange={e => { setIdentificacion(e.target.value); setMensajeCliente('') }}
                                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); handleBuscarCliente() } }}
                                    placeholder="RUC, cédula o nombre del cliente..."
                                />
                                <Button type="button" variant="outline" onClick={handleBuscarCliente}>
                                    <Search className="w-4 h-4" />
                                    Buscar
                                </Button>
                            </div>
                            {mensajeCliente && (
                                <p className="mt-2 text-xs" style={{ color: 'var(--text-muted)' }}>{mensajeCliente}</p>
                            )}
                        </div>
                    )}
                </div>

                {/* ── SECCIÓN 2: Equipo ── */}
                <div className="rounded-xl p-5 border" style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="flex items-center justify-between mb-4">
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Equipo
                        </p>
                        {estadoEquipo === 'found' && (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                Equipo existente
                            </span>
                        )}
                    </div>

                    <div style={{ maxWidth: 420 }} className="mb-4">
                        <Label style={{ color: 'var(--text-main)' }}>Número de Serie</Label>
                        <div className="flex items-center gap-2 mt-1">
                            <Input
                                value={numeroSerie}
                                onChange={e => { setNumeroSerie(e.target.value); if (estadoEquipo !== 'idle') setEstadoEquipo('idle') }}
                                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); buscarEquipoPorSerie() } }}
                                placeholder="Número de serie del equipo..."
                            />
                            <Button type="button" variant="outline" loading={estadoEquipo === 'searching'} onClick={buscarEquipoPorSerie}>
                                <Search className="w-4 h-4" />
                                Buscar
                            </Button>
                            {estadoEquipo !== 'idle' && (
                                <Button type="button" variant="ghost" onClick={limpiarEquipo} title="Limpiar">
                                    <X className="w-4 h-4" />
                                </Button>
                            )}
                        </div>
                    </div>

                    {estadoEquipo === 'found' && equipoEncontrado && (
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Tipo</Label>
                                <Input className="mt-1" value={equipoEncontrado.tipo?.descripcion ?? '—'} readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Marca</Label>
                                <Input className="mt-1" value={equipoEncontrado.marca ?? '—'} readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Modelo</Label>
                                <Input className="mt-1" value={equipoEncontrado.modelo ?? '—'} readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Color</Label>
                                <Input className="mt-1" value={equipoEncontrado.color ?? '—'} readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Medida</Label>
                                <Input className="mt-1" value={equipoEncontrado.medida ?? '—'} readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Adicional</Label>
                                <Input className="mt-1" value={equipoEncontrado.adicional ?? '—'} readOnly
                                    style={{ color: 'var(--text-muted)', cursor: 'not-allowed' }} />
                            </div>
                        </div>
                    )}

                    {estadoEquipo === 'new' && (
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Tipo *</Label>
                                <select
                                    className="mt-1 w-full h-9 rounded-md border px-3 text-sm"
                                    style={{ background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={equipoForm.tipo_id}
                                    onChange={e => setEquipoForm(f => ({ ...f, tipo_id: e.target.value ? Number(e.target.value) : '' }))}
                                >
                                    <option value="">-- Seleccione --</option>
                                    {tiposEquipo.map(t => (
                                        <option key={t.id} value={t.id}>{t.descripcion}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Marca *</Label>
                                <Input className="mt-1" value={equipoForm.marca}
                                    onChange={e => setEquipoForm(f => ({ ...f, marca: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Modelo *</Label>
                                <Input className="mt-1" value={equipoForm.modelo}
                                    onChange={e => setEquipoForm(f => ({ ...f, modelo: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Color</Label>
                                <Input className="mt-1" value={equipoForm.color}
                                    onChange={e => setEquipoForm(f => ({ ...f, color: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Medida</Label>
                                <Input className="mt-1" value={equipoForm.medida}
                                    onChange={e => setEquipoForm(f => ({ ...f, medida: e.target.value }))} />
                            </div>
                            <div>
                                <Label style={{ color: 'var(--text-main)' }}>Adicional</Label>
                                <Input className="mt-1" value={equipoForm.adicional}
                                    onChange={e => setEquipoForm(f => ({ ...f, adicional: e.target.value }))} />
                            </div>
                            <div className="md:col-span-3">
                                <Label style={{ color: 'var(--text-main)' }}>Observaciones del equipo</Label>
                                <textarea
                                    rows={2}
                                    className="mt-1 w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none"
                                    style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                                    value={equipoForm.observaciones}
                                    onChange={e => setEquipoForm(f => ({ ...f, observaciones: e.target.value }))}
                                />
                            </div>
                        </div>
                    )}
                </div>

                {/* ── SECCIÓN 3: Datos del ingreso ── */}
                <div className="rounded-xl p-5 border space-y-4" style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                        Datos del Ingreso
                    </p>
                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Diagnóstico inicial *</Label>
                        <textarea
                            rows={3}
                            className="mt-1 w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none"
                            style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            placeholder="Descripción del problema reportado por el cliente..."
                            value={diagnosticoInicial}
                            onChange={e => setDiagnosticoInicial(e.target.value)}
                        />
                    </div>
                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Observaciones</Label>
                        <textarea
                            rows={2}
                            className="mt-1 w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none"
                            style={{ background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }}
                            value={observaciones}
                            onChange={e => setObservaciones(e.target.value)}
                        />
                    </div>
                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Imagen (URL)</Label>
                        <Input
                            className="mt-1"
                            value={imagen}
                            onChange={e => setImagen(e.target.value)}
                            placeholder="https://..."
                        />
                    </div>
                </div>

                {/* ── SECCIÓN 4: Acciones ── */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={route('taller.ingresos.index')}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    {puede('crear') && (
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            Guardar Ingreso
                        </Button>
                    )}
                </div>
            </form>

            <BuscadorClienteModal
                coincidencias={modalCliente}
                abierto={modalCliente.length > 0}
                onCerrar={() => setModalCliente([])}
                onSelect={seleccionarCliente}
            />
        </AppLayout>
    )
}
