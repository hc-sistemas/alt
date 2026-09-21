import React, { useState } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import BuscadorClienteModal from '@/Components/shared/BuscadorClienteModal'
import CapturaImagen from '@/Components/taller/CapturaImagen'
import axios from '@/lib/axios'
import { toastError } from '@/lib/toast'
import { Search, Save, X, AlertTriangle, RotateCcw, Plus, Trash2 } from 'lucide-react'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PageProps, Cliente, TallerTipoEquipo, TallerEquipo, TallerIngreso } from '@/types'

interface Tecnico {
    id: number
    nombre: string
}

interface Props extends PageProps {
    clientes: Cliente[]
    tiposEquipo: TallerTipoEquipo[]
    tecnicos: Tecnico[]
    ingreso: TallerIngreso | null
}

type EstadoBusquedaEquipo = 'idle' | 'searching' | 'found' | 'new'

interface ComponenteForm {
    nombre: string
    funciona: boolean
    accion: 0 | 1
    descripcion: string
    costo: string
}

const equipoVacio = {
    tipo_id: '' as number | '',
    marca: '',
    modelo: '',
    color: '',
    medida: '',
    adicional: '',
    observaciones: '',
}

const componenteVacio: ComponenteForm = { nombre: '', funciona: true, accion: 0, descripcion: '', costo: '' }

const selectCls = 'mt-1 w-full h-9 rounded-md border px-3 text-sm'
const selectStyle = { background: 'var(--bg-card)', borderColor: 'var(--border)', color: 'var(--text-main)' }
const textareaCls = 'mt-1 w-full rounded-md border px-3 py-2 text-sm resize-none focus:outline-none'
const textareaStyle = { background: 'transparent', borderColor: 'var(--border)', color: 'var(--text-main)' }

function equipoAForm(e: TallerEquipo) {
    return {
        tipo_id: (e.tipo_id ?? '') as number | '',
        marca: e.marca ?? '',
        modelo: e.modelo ?? '',
        color: e.color ?? '',
        medida: e.medida ?? '',
        adicional: e.adicional ?? '',
        observaciones: e.observaciones ?? '',
    }
}

export default function IngresoForm() {
    const { clientes, tiposEquipo: tiposIniciales, tecnicos, ingreso, errors } = usePage<Props>().props
    const { puede } = usePermiso('taller')
    const editando = ingreso !== null
    const ot = ingreso?.ordenes_trabajo?.[ingreso.ordenes_trabajo.length - 1]

    // — Cliente
    const [identificacion, setIdentificacion] = useState(ingreso?.cliente?.identificacion ?? '')
    const [clienteSeleccionado, setClienteSeleccionado] = useState<Cliente | null>(ingreso?.cliente ?? null)
    const [modalCliente, setModalCliente] = useState<Cliente[]>([])
    const [mensajeCliente, setMensajeCliente] = useState('')

    // — Equipo
    const [tiposEquipo, setTiposEquipo] = useState<TallerTipoEquipo[]>(tiposIniciales)
    const [numeroSerie, setNumeroSerie] = useState(ingreso?.equipo?.numero_serie ?? '')
    const [estadoEquipo, setEstadoEquipo] = useState<EstadoBusquedaEquipo>(ingreso?.equipo ? 'found' : 'idle')
    const [equipoEncontrado, setEquipoEncontrado] = useState<TallerEquipo | null>(ingreso?.equipo ?? null)
    const [equipoForm, setEquipoForm] = useState(ingreso?.equipo ? equipoAForm(ingreso.equipo) : { ...equipoVacio })
    const [nuevoTipo, setNuevoTipo] = useState<string | null>(null)

    // — Datos del ingreso
    const [diagnosticoInicial, setDiagnosticoInicial] = useState(ingreso?.diagnostico_inicial ?? '')
    const [observaciones, setObservaciones] = useState(ingreso?.observaciones ?? '')
    const [tecnicoId, setTecnicoId] = useState(ot?.tecnico_id ? String(ot.tecnico_id) : '')
    const [descripcionTrabajo, setDescripcionTrabajo] = useState(ot?.descripcion_trabajo ?? '')

    // — Imagen: `imagenData` es la foto nueva (data URL); la existente se sirve por ruta autenticada.
    const imagenExistente = ingreso?.imagen
        ? (/^https?:\/\//i.test(ingreso.imagen) ? ingreso.imagen : route('taller.ingresos.imagen', ingreso.id))
        : null
    const [imagenData, setImagenData] = useState<string | null>(null)
    const [quitarImagen, setQuitarImagen] = useState(false)

    // — Revisión de componentes
    const [componentes, setComponentes] = useState<ComponenteForm[]>(
        (ingreso?.componentes ?? []).map(c => ({
            nombre: c.nombre,
            funciona: c.funciona,
            accion: (c.accion === 1 ? 1 : 0) as 0 | 1,
            descripcion: c.descripcion ?? '',
            costo: c.costo ? String(c.costo) : '',
        }))
    )

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
            const { data } = await axios.get<{ found: boolean; equipo: TallerEquipo | null }>(
                route('taller.equipos.buscar', { serie })
            )
            if (data.found && data.equipo) {
                setEquipoEncontrado(data.equipo)
                setEquipoForm(equipoAForm(data.equipo))
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

    const crearTipo = async () => {
        const descripcion = (nuevoTipo ?? '').trim()
        if (!descripcion) return
        try {
            const { data } = await axios.post<{ id: number; descripcion: string }>(
                route('taller.tipos-equipo.store'), { descripcion }
            )
            setTiposEquipo(t => [...t, { id: data.id, descripcion: data.descripcion, estado: true }]
                .sort((a, b) => a.descripcion.localeCompare(b.descripcion)))
            setEquipoForm(f => ({ ...f, tipo_id: data.id }))
            setNuevoTipo(null)
        } catch (err) {
            const e = err as { response?: { data?: { errors?: { descripcion?: string[] } } } }
            toastError(e.response?.data?.errors?.descripcion?.[0] ?? 'No se pudo crear el tipo de equipo.')
        }
    }

    // ── Handlers: componentes ────────────────────────────────────────────────

    const actualizarComponente = (i: number, cambios: Partial<ComponenteForm>) =>
        setComponentes(cs => cs.map((c, idx) => idx === i ? { ...c, ...cambios } : c))

    // ── Submit ───────────────────────────────────────────────────────────────

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()

        const errs: string[] = []
        if (!clienteSeleccionado) errs.push('Debe seleccionar un cliente.')
        if (estadoEquipo === 'idle' || estadoEquipo === 'searching') {
            errs.push('Debe buscar o registrar el equipo.')
        } else {
            if (!equipoForm.tipo_id) errs.push('El tipo de equipo es obligatorio.')
            if (!equipoForm.marca.trim()) errs.push('La marca del equipo es obligatoria.')
            if (!equipoForm.modelo.trim()) errs.push('El modelo del equipo es obligatorio.')
        }
        if (!diagnosticoInicial.trim()) errs.push('El diagnóstico inicial es obligatorio.')
        if (componentes.some(c => !c.nombre.trim())) errs.push('Todos los componentes revisados necesitan un nombre.')
        if (errs.length > 0) { errs.forEach(toastError); return }
        setGuardando(true)

        const payload = {
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
            tecnico_id: tecnicoId || null,
            descripcion_trabajo: descripcionTrabajo || null,
            imagen_data: imagenData,
            quitar_imagen: quitarImagen && !imagenData,
            componentes: componentes.map(c => ({
                nombre: c.nombre.trim(),
                funciona: c.funciona,
                accion: c.accion,
                descripcion: c.descripcion || null,
                costo: c.costo ? Number(c.costo) : 0,
            })),
        }

        const opciones = {
            onError: () => setGuardando(false),
            onFinish: () => setGuardando(false),
        }

        if (editando) {
            router.put(route('taller.ingresos.update', ingreso!.id), payload, opciones)
        } else {
            router.post(route('taller.ingresos.store'), payload, opciones)
        }
    }

    const tipoLabel: Record<string, string> = { '04': 'RUC', '05': 'CÉDULA', '06': 'PASAPORTE', '07': 'CONSUMIDOR' }
    const titulo = editando ? `Editar Ingreso #${ingreso!.id}` : 'Nuevo Ingreso al Taller'
    const puedeGuardar = editando ? puede('editar') : puede('crear')
    const vistaImagen = imagenData ?? (quitarImagen ? null : imagenExistente)

    const camposEquipo = (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <Label style={{ color: 'var(--text-main)' }}>Tipo *</Label>
                <select
                    className={selectCls}
                    style={selectStyle}
                    value={equipoForm.tipo_id}
                    onChange={e => setEquipoForm(f => ({ ...f, tipo_id: e.target.value ? Number(e.target.value) : '' }))}
                >
                    <option value="">-- Seleccione --</option>
                    {tiposEquipo.map(t => (
                        <option key={t.id} value={t.id}>{t.descripcion}</option>
                    ))}
                </select>
                {nuevoTipo === null ? (
                    puede('crear') && (
                        <button type="button" className="mt-1 text-xs underline" style={{ color: 'var(--text-muted)' }}
                            onClick={() => setNuevoTipo('')}>
                            + Nuevo tipo
                        </button>
                    )
                ) : (
                    <div className="mt-1 flex items-center gap-1">
                        <Input value={nuevoTipo} autoFocus placeholder="Descripción del tipo..."
                            onChange={e => setNuevoTipo(e.target.value)}
                            onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); void crearTipo() } }} />
                        <Button type="button" size="sm" onClick={() => void crearTipo()}>Crear</Button>
                        <Button type="button" size="sm" variant="ghost" onClick={() => setNuevoTipo(null)}>
                            <X className="w-4 h-4" />
                        </Button>
                    </div>
                )}
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
                    className={textareaCls}
                    style={textareaStyle}
                    value={equipoForm.observaciones}
                    onChange={e => setEquipoForm(f => ({ ...f, observaciones: e.target.value }))}
                />
            </div>
        </div>
    )

    return (
        <AppLayout>
            <Head title={titulo} />
            <PageHeader
                title={titulo}
                breadcrumbs={[
                    { label: 'Taller', href: route('taller.ingresos.index') },
                    { label: 'Ingresos', href: route('taller.ingresos.index') },
                    { label: editando ? `#${ingreso!.id}` : 'Nuevo' },
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
                                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); void buscarEquipoPorSerie() } }}
                                placeholder="Número de serie del equipo..."
                            />
                            <Button type="button" variant="outline" loading={estadoEquipo === 'searching'} onClick={() => void buscarEquipoPorSerie()}>
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

                    {(estadoEquipo === 'found' || estadoEquipo === 'new') && camposEquipo}
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
                            className={textareaCls}
                            style={textareaStyle}
                            placeholder="Descripción del problema reportado por el cliente..."
                            value={diagnosticoInicial}
                            onChange={e => setDiagnosticoInicial(e.target.value)}
                        />
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Técnico</Label>
                            <select className={selectCls} style={selectStyle}
                                value={tecnicoId} onChange={e => setTecnicoId(e.target.value)}>
                                <option value="">Sin asignar</option>
                                {tecnicos.map(t => (
                                    <option key={t.id} value={t.id}>{t.nombre}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <Label style={{ color: 'var(--text-main)' }}>Trabajo a realizar</Label>
                            <textarea
                                rows={2}
                                className={textareaCls}
                                style={textareaStyle}
                                value={descripcionTrabajo}
                                onChange={e => setDescripcionTrabajo(e.target.value)}
                            />
                        </div>
                    </div>
                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Observaciones</Label>
                        <textarea
                            rows={2}
                            className={textareaCls}
                            style={textareaStyle}
                            value={observaciones}
                            onChange={e => setObservaciones(e.target.value)}
                        />
                    </div>
                    <div>
                        <Label style={{ color: 'var(--text-main)' }}>Foto del equipo</Label>
                        <div className="mt-2">
                            <CapturaImagen
                                valor={vistaImagen}
                                onChange={data => {
                                    setImagenData(data)
                                    setQuitarImagen(data === null)
                                }}
                            />
                        </div>
                    </div>
                </div>

                {/* ── SECCIÓN 4: Revisión de componentes ── */}
                <div className="rounded-xl p-5 border space-y-4" style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                    <div className="flex items-center justify-between">
                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-muted)' }}>
                            Revisión de componentes
                        </p>
                        <Button type="button" variant="outline" size="sm"
                            onClick={() => setComponentes(cs => [...cs, { ...componenteVacio }])}>
                            <Plus className="w-4 h-4" />
                            Agregar
                        </Button>
                    </div>

                    {componentes.length === 0 ? (
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Sin componentes revisados. Agregue los que se inspeccionaron al recibir el equipo (opcional).
                        </p>
                    ) : componentes.map((c, i) => (
                        <div key={i} className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end border-t pt-3"
                            style={{ borderColor: 'var(--border)' }}>
                            <div className="md:col-span-3">
                                <Label style={{ color: 'var(--text-main)' }}>Componente *</Label>
                                <Input className="mt-1" value={c.nombre}
                                    onChange={e => actualizarComponente(i, { nombre: e.target.value })} />
                            </div>
                            <div className="md:col-span-2">
                                <Label style={{ color: 'var(--text-main)' }}>Funciona</Label>
                                <select className={selectCls} style={selectStyle}
                                    value={c.funciona ? '1' : '0'}
                                    onChange={e => actualizarComponente(i, { funciona: e.target.value === '1' })}>
                                    <option value="1">Sí</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <Label style={{ color: 'var(--text-main)' }}>Acción</Label>
                                <select className={selectCls} style={selectStyle}
                                    value={c.accion}
                                    onChange={e => actualizarComponente(i, { accion: e.target.value === '1' ? 1 : 0 })}>
                                    <option value="0">Reparación</option>
                                    <option value="1">Reemplazo</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <Label style={{ color: 'var(--text-main)' }}>Costo</Label>
                                <Input className="mt-1" type="number" min="0" step="0.01" value={c.costo}
                                    onChange={e => actualizarComponente(i, { costo: e.target.value })} />
                            </div>
                            <div className="md:col-span-2">
                                <Label style={{ color: 'var(--text-main)' }}>Detalle</Label>
                                <Input className="mt-1" value={c.descripcion}
                                    onChange={e => actualizarComponente(i, { descripcion: e.target.value })} />
                            </div>
                            <div className="md:col-span-1 flex justify-end">
                                <Button type="button" variant="ghost" size="icon" title="Quitar"
                                    onClick={() => setComponentes(cs => cs.filter((_, idx) => idx !== i))}>
                                    <Trash2 className="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── Acciones ── */}
                <div className="flex items-center justify-between pb-2">
                    <Link href={editando ? route('taller.ingresos.show', ingreso!.id) : route('taller.ingresos.index')}>
                        <Button type="button" variant="ghost">
                            <X className="w-4 h-4" />
                            Cancelar
                        </Button>
                    </Link>
                    {puedeGuardar && (
                        <Button type="submit" loading={guardando}>
                            <Save className="w-4 h-4" />
                            {editando ? 'Guardar Cambios' : 'Guardar Ingreso'}
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
