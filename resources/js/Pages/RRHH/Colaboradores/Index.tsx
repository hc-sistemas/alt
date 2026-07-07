import { useState, useMemo } from 'react'
import { router, usePage, useForm, Head } from '@inertiajs/react'
import { toast, ToastContainer } from 'react-toastify'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { cn } from '@/lib/utils'
import {
    Plus, Pencil, ToggleLeft, ToggleRight, Search, X,
    User, Briefcase, DollarSign, CreditCard, Phone, Clock,
} from 'lucide-react'
import type { Colaborador, PuestoTrabajo, Horario, PageProps, PaginatedData } from '@/types'
import 'react-toastify/dist/ReactToastify.css'

// ─── Types ────────────────────────────────────────────────────────────────────

interface UsuarioItem { id: number; nombre: string; email: string }

interface Props extends PageProps {
    colaboradores: PaginatedData<Colaborador>
    puestos: PuestoTrabajo[]
    horarios: Horario[]
    usuarios: UsuarioItem[]
    departamentos: string[]
    filtros: { buscar?: string; departamento?: string; estado?: string }
}

// ─── Notify ───────────────────────────────────────────────────────────────────

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const
const notify = {
    ok:    (m: string) => toast.success(m, { icon: () => '✅', style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' } }),
    edit:  (m: string) => toast.success(m, { icon: () => '✏️', style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' } }),
    error: (m: string) => toast.error(m,   { icon: () => '❌', autoClose: 6000, style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' } }),
}

// ─── Badges ──────────────────────────────────────────────────────────────────

function EstadoBadge({ estado }: { estado: boolean }) {
    return estado
        ? <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">Activo</span>
        : <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">Inactivo</span>
}

function ContratoBadge({ tipo }: { tipo: string | null }) {
    const map: Record<string, string> = {
        indefinido: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        plazo_fijo: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        honorarios: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    }
    if (!tipo) return <span className="text-xs" style={{ color: 'var(--text-muted)' }}>—</span>
    return (
        <span className={cn('px-2 py-0.5 rounded-full text-xs font-semibold', map[tipo] ?? '')}>
            {tipo.replace('_', ' ')}
        </span>
    )
}

// ─── Tab navigation ───────────────────────────────────────────────────────────

const TABS = [
    { key: 'identificacion', label: 'Identificación', icon: User },
    { key: 'contacto',       label: 'Contacto',       icon: Phone },
    { key: 'cargo',          label: 'Cargo',          icon: Briefcase },
    { key: 'remuneracion',   label: 'Remuneración',   icon: DollarSign },
    { key: 'bancarios',      label: 'Datos Bancarios',icon: CreditCard },
] as const

type TabKey = typeof TABS[number]['key']

// ─── Modal Crear/Editar ───────────────────────────────────────────────────────

interface ModalProps {
    colaborador?: Colaborador
    puestos: PuestoTrabajo[]
    horarios: Horario[]
    usuarios: UsuarioItem[]
    onClose: () => void
}

function ColaboradorModal({ colaborador, puestos, horarios, usuarios, onClose }: ModalProps) {
    const isEditar = !!colaborador
    const [tab, setTab] = useState<TabKey>('identificacion')

    // Alta rápida de horario "plantilla" (tabla horarios, reutilizable entre colaboradores)
    const [nuevoHorario, setNuevoHorario] = useState(false)
    const [horarioForm, setHorarioForm] = useState({
        descripcion: '', hora_entrada: '08:00', hora_salida: '17:00', tolerancia_minutos: '10',
    })
    const [creandoHorario, setCreandoHorario] = useState(false)

    const { data, setData, post, put, processing, errors } = useForm({
        cedula_ruc:          colaborador?.cedula_ruc          ?? '',
        apellidos:           colaborador?.apellidos           ?? '',
        nombres:             colaborador?.nombres             ?? '',
        email:               colaborador?.email               ?? '',
        telefono:            colaborador?.telefono            ?? '',
        celular:             colaborador?.celular             ?? '',
        direccion:           colaborador?.direccion           ?? '',
        fecha_nacimiento:    colaborador?.fecha_nacimiento    ?? '',
        sexo:                colaborador?.sexo                ?? '',
        estado_civil:        colaborador?.estado_civil        ?? '',
        fecha_ingreso:       colaborador?.fecha_ingreso       ?? '',
        fecha_salida:        colaborador?.fecha_salida        ?? '',
        tipo_contrato:       colaborador?.tipo_contrato       ?? '',
        cargo:               colaborador?.cargo               ?? '',
        departamento:        colaborador?.departamento        ?? '',
        comision_porcentaje: String(colaborador?.comision_porcentaje ?? 0),
        puesto_id:           String(colaborador?.puesto_id           ?? ''),
        horario_id:          String(colaborador?.horario_id          ?? ''),
        sueldo_base:         String(colaborador?.sueldo_base         ?? 0),
        decimo_tercero:      colaborador?.decimo_tercero      ?? 'acumula',
        decimo_cuarto:       colaborador?.decimo_cuarto       ?? 'acumula',
        fondos_reserva:      colaborador?.fondos_reserva      ?? 'acumula',
        banco:               colaborador?.banco               ?? '',
        tipo_cuenta:         colaborador?.tipo_cuenta         ?? '',
        numero_cuenta:       colaborador?.numero_cuenta       ?? '',
        usuario_id:          String(colaborador?.usuario_id   ?? ''),
    })

    function crearHorario() {
        if (!horarioForm.descripcion || !horarioForm.hora_entrada || !horarioForm.hora_salida) {
            notify.error('Completa descripción, hora de entrada y hora de salida del horario.')
            return
        }
        setCreandoHorario(true)
        router.post(route('rrhh.horarios.store'), horarioForm, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const lista = (page.props as unknown as { horarios: Horario[] }).horarios ?? []
                const creado = [...lista].sort((a, b) => b.id - a.id)[0]
                if (creado) setData('horario_id', String(creado.id))
                setNuevoHorario(false)
                setHorarioForm({ descripcion: '', hora_entrada: '08:00', hora_salida: '17:00', tolerancia_minutos: '10' })
                notify.ok('Horario creado y asignado.')
            },
            onError: () => notify.error('Revisa los datos del horario.'),
            onFinish: () => setCreandoHorario(false),
        })
    }

    function submit(e: React.FormEvent) {
        e.preventDefault()
        const opts = {
            onSuccess: () => {
                onClose()
                isEditar ? notify.edit('Colaborador actualizado.') : notify.ok('Colaborador creado.')
            },
            onError: () => notify.error('Revisa los campos con error.'),
        }
        if (isEditar) {
            put(route('rrhh.colaboradores.update', colaborador!.id), opts)
        } else {
            post(route('rrhh.colaboradores.store'), opts)
        }
    }

    const field = (
        label: string,
        key: keyof typeof data,
        props?: React.InputHTMLAttributes<HTMLInputElement>
    ) => (
        <div>
            <Label className="input-label">{label}</Label>
            <Input
                className="input-field"
                value={data[key] as string}
                onChange={e => setData(key, e.target.value)}
                {...props}
            />
            {errors[key] && <p className="mt-1 text-xs text-red-500">{errors[key]}</p>}
        </div>
    )

    const selectDecimo = (label: string, key: 'decimo_tercero' | 'decimo_cuarto' | 'fondos_reserva') => (
        <div>
            <Label className="input-label">{label}</Label>
            <select className="input-field" value={data[key]} onChange={e => setData(key, e.target.value as 'acumula' | 'mensualiza')}>
                <option value="acumula">Acumula</option>
                <option value="mensualiza">Mensualiza</option>
            </select>
        </div>
    )

    return (
        <div className="modal-overlay" onClick={onClose}>
            <div className="modal-card max-w-2xl" onClick={e => e.stopPropagation()}>
                {/* Header */}
                <div className="modal-header flex items-center justify-between px-6 py-4">
                    <h2 className="text-base font-semibold" style={{ color: 'var(--text-main)' }}>
                        {isEditar ? 'Editar Colaborador' : 'Nuevo Colaborador'}
                    </h2>
                    <button onClick={onClose} className="p-1.5 rounded-lg transition-colors hover:bg-black/10">
                        <X className="w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                    </button>
                </div>

                {/* Tabs */}
                <div className="flex border-b px-6 gap-1" style={{ borderColor: 'var(--border)' }}>
                    {TABS.map(t => {
                        const Icon = t.icon
                        const active = tab === t.key
                        return (
                            <button
                                key={t.key}
                                type="button"
                                onClick={() => setTab(t.key)}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-2.5 text-xs font-medium border-b-2 transition-colors whitespace-nowrap',
                                    active
                                        ? 'border-amber-500 text-amber-600'
                                        : 'border-transparent hover:border-gray-300'
                                )}
                                style={{ color: active ? undefined : 'var(--text-muted)' }}
                            >
                                <Icon className="w-3.5 h-3.5" />
                                {t.label}
                            </button>
                        )
                    })}
                </div>

                {/* Body */}
                <form onSubmit={submit}>
                    <div className="modal-body px-6 py-5 overflow-y-auto max-h-[55vh]">

                        {/* Tab: Identificación */}
                        {tab === 'identificacion' && (
                            <div className="grid grid-cols-2 gap-4">
                                {field('Cédula / RUC *', 'cedula_ruc', { maxLength: 13 })}
                                <div>
                                    <Label className="input-label">Sexo</Label>
                                    <select className="input-field" value={data.sexo} onChange={e => setData('sexo', e.target.value)}>
                                        <option value="">— Seleccionar —</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Femenino</option>
                                    </select>
                                </div>
                                {field('Apellidos *', 'apellidos', { className: 'input-field col-span-2' })}
                                {field('Nombres *', 'nombres')}
                                {field('Fecha de Nacimiento', 'fecha_nacimiento', { type: 'date' })}
                                <div>
                                    <Label className="input-label">Estado Civil</Label>
                                    <select className="input-field" value={data.estado_civil} onChange={e => setData('estado_civil', e.target.value)}>
                                        <option value="">— Seleccionar —</option>
                                        <option value="soltero">Soltero/a</option>
                                        <option value="casado">Casado/a</option>
                                        <option value="divorciado">Divorciado/a</option>
                                        <option value="viudo">Viudo/a</option>
                                        <option value="union_libre">Unión Libre</option>
                                    </select>
                                </div>
                            </div>
                        )}

                        {/* Tab: Contacto */}
                        {tab === 'contacto' && (
                            <div className="grid grid-cols-2 gap-4">
                                {field('Email', 'email', { type: 'email' })}
                                {field('Teléfono', 'telefono')}
                                {field('Celular', 'celular')}
                                <div className="col-span-2">
                                    {field('Dirección', 'direccion')}
                                </div>
                            </div>
                        )}

                        {/* Tab: Cargo */}
                        {tab === 'cargo' && (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="input-label">Puesto</Label>
                                    <select className="input-field" value={data.puesto_id} onChange={e => setData('puesto_id', e.target.value)}>
                                        <option value="">— Sin puesto —</option>
                                        {puestos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                    </select>
                                </div>
                                <div className="col-span-2">
                                    <div className="flex items-center justify-between">
                                        <Label className="input-label">Horario</Label>
                                        <button
                                            type="button"
                                            onClick={() => setNuevoHorario(v => !v)}
                                            className="text-xs font-medium flex items-center gap-1 text-amber-600 hover:text-amber-700"
                                        >
                                            <Clock className="w-3 h-3" /> {nuevoHorario ? 'Cancelar' : 'Nuevo horario'}
                                        </button>
                                    </div>
                                    <select className="input-field" value={data.horario_id} onChange={e => setData('horario_id', e.target.value)}>
                                        <option value="">— Sin horario —</option>
                                        {horarios.map(h => (
                                            <option key={h.id} value={h.id}>
                                                {h.descripcion} ({h.hora_entrada}–{h.hora_salida}, tolerancia {h.tolerancia_minutos}min)
                                            </option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs" style={{ color: 'var(--text-muted)' }}>
                                        Los atrasos (NOM-05) y horas extras (NOM-06) se calculan comparando el timbre
                                        real contra este horario oficial + su tolerancia.
                                    </p>
                                    {nuevoHorario && (
                                        <div className="mt-3 p-3 rounded-lg grid grid-cols-2 gap-3" style={{ background: 'var(--bg-main)' }}>
                                            <div className="col-span-2">
                                                <Label className="input-label">Descripción</Label>
                                                <Input
                                                    className="input-field"
                                                    placeholder="Ej. Horario Administrativo"
                                                    value={horarioForm.descripcion}
                                                    onChange={e => setHorarioForm(f => ({ ...f, descripcion: e.target.value }))}
                                                />
                                            </div>
                                            <div>
                                                <Label className="input-label">Hora Entrada</Label>
                                                <Input
                                                    className="input-field" type="time"
                                                    value={horarioForm.hora_entrada}
                                                    onChange={e => setHorarioForm(f => ({ ...f, hora_entrada: e.target.value }))}
                                                />
                                            </div>
                                            <div>
                                                <Label className="input-label">Hora Salida</Label>
                                                <Input
                                                    className="input-field" type="time"
                                                    value={horarioForm.hora_salida}
                                                    onChange={e => setHorarioForm(f => ({ ...f, hora_salida: e.target.value }))}
                                                />
                                            </div>
                                            <div>
                                                <Label className="input-label">Tolerancia (min)</Label>
                                                <Input
                                                    className="input-field" type="number" min={0} max={120}
                                                    value={horarioForm.tolerancia_minutos}
                                                    onChange={e => setHorarioForm(f => ({ ...f, tolerancia_minutos: e.target.value }))}
                                                />
                                            </div>
                                            <div className="col-span-2 flex justify-end">
                                                <button
                                                    type="button"
                                                    disabled={creandoHorario}
                                                    onClick={crearHorario}
                                                    className="btn-primary text-xs px-3 py-1.5"
                                                >
                                                    {creandoHorario ? 'Creando…' : 'Crear y asignar'}
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                </div>
                                {field('Cargo', 'cargo')}
                                {field('Departamento', 'departamento')}
                                <div>
                                    <Label className="input-label">Tipo de Contrato</Label>
                                    <select className="input-field" value={data.tipo_contrato} onChange={e => setData('tipo_contrato', e.target.value)}>
                                        <option value="">— Seleccionar —</option>
                                        <option value="indefinido">Indefinido</option>
                                        <option value="plazo_fijo">Plazo Fijo</option>
                                        <option value="honorarios">Honorarios Profesionales</option>
                                    </select>
                                    {errors.tipo_contrato && <p className="mt-1 text-xs text-red-500">{errors.tipo_contrato}</p>}
                                </div>
                                {field('Fecha de Ingreso *', 'fecha_ingreso', { type: 'date' })}
                                {field('Fecha de Salida', 'fecha_salida', { type: 'date' })}
                                <div>
                                    <Label className="input-label">Usuario del Sistema</Label>
                                    <select className="input-field" value={data.usuario_id} onChange={e => setData('usuario_id', e.target.value)}>
                                        <option value="">— Sin vincular —</option>
                                        {usuarios.map(u => <option key={u.id} value={u.id}>{u.nombre} ({u.email})</option>)}
                                    </select>
                                </div>
                            </div>
                        )}

                        {/* Tab: Remuneración */}
                        {tab === 'remuneracion' && (
                            <div className="grid grid-cols-2 gap-4">
                                {field('Sueldo Base *', 'sueldo_base', { type: 'number', min: '0', step: '0.01' })}
                                {field('Comisión %', 'comision_porcentaje', { type: 'number', min: '0', max: '100', step: '0.01' })}
                                {selectDecimo('Décimo Tercero', 'decimo_tercero')}
                                {selectDecimo('Décimo Cuarto', 'decimo_cuarto')}
                                {selectDecimo('Fondos de Reserva', 'fondos_reserva')}
                                <div className="col-span-2 rounded-lg p-3 text-xs" style={{ background: 'var(--bg-main)', color: 'var(--text-muted)' }}>
                                    <strong>Acumula</strong>: se provisiona mensualmente y se paga en la fecha legal.<br />
                                    <strong>Mensualiza</strong>: se paga mensualmente junto al sueldo (décimo cuarto y tercero solo aplica para quienes llevan más de 1 año).
                                </div>
                            </div>
                        )}

                        {/* Tab: Datos Bancarios */}
                        {tab === 'bancarios' && (
                            <div className="grid grid-cols-2 gap-4">
                                {field('Banco', 'banco')}
                                <div>
                                    <Label className="input-label">Tipo de Cuenta</Label>
                                    <select className="input-field" value={data.tipo_cuenta} onChange={e => setData('tipo_cuenta', e.target.value)}>
                                        <option value="">— Seleccionar —</option>
                                        <option value="ahorros">Ahorros</option>
                                        <option value="corriente">Corriente</option>
                                    </select>
                                </div>
                                {field('Número de Cuenta', 'numero_cuenta', { maxLength: 30 })}
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div className="modal-footer flex items-center justify-between px-6 py-4">
                        <div className="flex gap-1">
                            {TABS.map((t, i) => (
                                <button
                                    key={t.key}
                                    type="button"
                                    onClick={() => setTab(t.key)}
                                    className={cn('w-2 h-2 rounded-full transition-colors', tab === t.key ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600')}
                                />
                            ))}
                        </div>
                        <div className="flex gap-3">
                            <button type="button" onClick={onClose} className="btn-secondary">
                                Cancelar
                            </button>
                            <button type="submit" disabled={processing} className="btn-primary">
                                {processing ? 'Guardando…' : isEditar ? 'Actualizar' : 'Crear Colaborador'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    )
}

// ─── Página principal ─────────────────────────────────────────────────────────

export default function ColaboradoresIndex() {
    const { colaboradores, puestos, horarios, usuarios, departamentos, filtros } =
        usePage<Props>().props

    const [modal, setModal] = useState<{ type: 'nuevo' | 'editar'; colaborador?: Colaborador } | null>(null)
    const [buscar, setBuscar] = useState(filtros.buscar ?? '')
    const [departamento, setDepartamento] = useState(filtros.departamento ?? '')
    const [estado, setEstado] = useState(filtros.estado ?? '')

    function filtrar() {
        router.get(route('rrhh.colaboradores.index'), {
            buscar:      buscar || undefined,
            departamento: departamento || undefined,
            estado:       estado || undefined,
        }, { preserveState: true, replace: true })
    }

    function toggleEstado(c: Colaborador) {
        Swal.fire({
            title: `¿${c.estado ? 'Desactivar' : 'Activar'} colaborador?`,
            text: `${c.apellidos} ${c.nombres}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: c.estado ? 'Desactivar' : 'Activar',
            cancelButtonText: 'Cancelar',
        }).then(res => {
            if (res.isConfirmed) {
                router.patch(route('rrhh.colaboradores.toggle', c.id), {}, {
                    onSuccess: () => notify.ok(`Colaborador ${c.estado ? 'desactivado' : 'activado'}.`),
                })
            }
        })
    }

    const valorHora = (sueldo: number) => (sueldo / 240).toFixed(4)

    return (
        <AppLayout title="Colaboradores" suppressFlash>
            <Head title="Colaboradores — RRHH" />
            <ToastContainer position="top-right" />

            <PageHeader
                title="Colaboradores"
                description="Gestión de la ficha laboral del personal"
                breadcrumbs={[{ label: 'RRHH' }, { label: 'Colaboradores' }]}
            />

            <div className="p-6 space-y-4">
                {/* Toolbar */}
                <div className="flex items-center justify-between gap-3 mb-4 px-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <button onClick={() => setModal({ type: 'nuevo' })} className="btn-primary flex items-center gap-2">
                            <Plus className="w-4 h-4" /> Nuevo Colaborador
                        </button>

                        <div className="relative">
                            <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                            <Input
                                value={buscar}
                                onChange={e => setBuscar(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && filtrar()}
                                placeholder="Nombre, cédula, cargo…"
                                className="pl-9 w-56"
                            />
                        </div>

                        <select
                            value={departamento}
                            onChange={e => { setDepartamento(e.target.value); }}
                            className="input-field w-44"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        >
                            <option value="">Todos los depto.</option>
                            {departamentos.map(d => <option key={d} value={d}>{d}</option>)}
                        </select>

                        <select
                            value={estado}
                            onChange={e => setEstado(e.target.value)}
                            className="input-field w-36"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}
                        >
                            <option value="">Todos</option>
                            <option value="activo">Activos</option>
                            <option value="inactivo">Inactivos</option>
                        </select>

                        <button onClick={filtrar} className="btn-secondary whitespace-nowrap">Buscar</button>

                        {(buscar || departamento || estado) && (
                            <button
                                onClick={() => { setBuscar(''); setDepartamento(''); setEstado(''); router.get(route('rrhh.colaboradores.index')); }}
                                className="btn-secondary flex items-center gap-1 whitespace-nowrap">
                                <X className="w-3 h-3" /> Limpiar
                            </button>
                        )}
                    </div>
                </div>

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {['Colaborador', 'Cédula', 'Cargo / Depto.', 'Contrato', 'Sueldo Base', 'V/Hora', 'Estado', ''].map(h => (
                                    <th key={h} className="text-left px-3 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {colaboradores.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-16 text-sm" style={{ color: 'var(--text-muted)' }}>
                                        No hay colaboradores registrados.
                                    </td>
                                </tr>
                            ) : colaboradores.data.map(c => (
                                <tr key={c.id} className="border-t transition-colors hover:bg-black/5"
                                    style={{ borderColor: 'var(--border)' }}>
                                    <td className="px-3 py-2.5">
                                        <div className="font-medium" style={{ color: 'var(--text-main)' }}>
                                            {c.apellidos} {c.nombres}
                                        </div>
                                        {c.email && (
                                            <div className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.email}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5 font-mono" style={{ color: 'var(--text-muted)' }}>
                                        {c.cedula_ruc}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div style={{ color: 'var(--text-main)' }}>{c.cargo ?? '—'}</div>
                                        {c.departamento && (
                                            <div className="text-xs" style={{ color: 'var(--text-muted)' }}>{c.departamento}</div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <ContratoBadge tipo={c.tipo_contrato ?? null} />
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-mono font-medium" style={{ color: 'var(--text-main)' }}>
                                        ${Number(c.sueldo_base).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-2.5 text-right font-mono text-xs" style={{ color: 'var(--text-muted)' }}>
                                        ${valorHora(Number(c.sueldo_base))}
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <EstadoBadge estado={c.estado} />
                                    </td>
                                    <td className="px-3 py-2.5">
                                        <div className="flex items-center gap-1">
                                            <button
                                                onClick={() => setModal({ type: 'editar', colaborador: c })}
                                                className="p-1.5 rounded-lg transition-colors hover:bg-blue-100 dark:hover:bg-blue-900/30"
                                                title="Editar"
                                            >
                                                <Pencil className="w-3.5 h-3.5 text-blue-500" />
                                            </button>
                                            <button
                                                onClick={() => toggleEstado(c)}
                                                className="p-1.5 rounded-lg transition-colors hover:bg-gray-100 dark:hover:bg-gray-800"
                                                title={c.estado ? 'Desactivar' : 'Activar'}
                                            >
                                                {c.estado
                                                    ? <ToggleRight className="w-4 h-4 text-emerald-500" />
                                                    : <ToggleLeft  className="w-4 h-4 text-gray-400" />
                                                }
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {colaboradores.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            {colaboradores.from}–{colaboradores.to} de {colaboradores.total}
                        </p>
                        <div className="flex gap-1">
                            {colaboradores.links.map((link, i) => (
                                link.url ? (
                                    <a key={i} href={link.url}
                                        className={cn('px-3 py-1 rounded border text-xs transition-colors',
                                            link.active
                                                ? 'border-amber-500 bg-amber-500 text-black font-medium'
                                                : 'hover:bg-slate-100 dark:hover:bg-slate-800'
                                        )}
                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 rounded border text-xs opacity-40"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                )
                            ))}
                        </div>
                    </div>
                )}
            </div>

            {/* Modal */}
            {modal && (
                <ColaboradorModal
                    colaborador={modal.colaborador}
                    puestos={puestos}
                    horarios={horarios}
                    usuarios={usuarios}
                    onClose={() => setModal(null)}
                />
            )}
        </AppLayout>
    )
}
