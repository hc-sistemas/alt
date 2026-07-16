import { useState } from 'react'
import { usePage, Head } from '@inertiajs/react'
import AppLayout from '@/Layouts/AppLayout'
import {
    Library, Landmark, ShoppingCart, BookOpen,
    Settings, Package, UserCircle, X, ExternalLink,
    FileText, AlertCircle, ClipboardList, Users, BarChart2, Wrench,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { PageProps } from '@/types'

// ─── Types ───────────────────────────────────────────────────────────────────

interface Manual {
    clave: string
    titulo: string
    descripcion: string
    url: string
    disponible: boolean
}

interface Props extends PageProps {
    manuales: Manual[]
}

// ─── Config visual por módulo ─────────────────────────────────────────────────

const CONFIG: Record<string, {
    icon: React.ElementType
    color: string
    bg: string
    badge: string
}> = {
    diagnostico:   { icon: ClipboardList, color: 'text-violet-500', bg: 'bg-violet-500/10', badge: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300' },
    bancos:        { icon: Landmark,     color: 'text-blue-500',   bg: 'bg-blue-500/10',   badge: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' },
    compras:       { icon: ShoppingCart, color: 'text-orange-500', bg: 'bg-orange-500/10', badge: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300' },
    contabilidad:  { icon: BookOpen,     color: 'text-green-500',  bg: 'bg-green-500/10',  badge: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' },
    configuracion: { icon: Settings,     color: 'text-slate-500',  bg: 'bg-slate-500/10',  badge: 'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-300' },
    inventario:    { icon: Package,      color: 'text-purple-500', bg: 'bg-purple-500/10', badge: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300' },
    taller:        { icon: Wrench,       color: 'text-zinc-500',   bg: 'bg-zinc-500/10',   badge: 'bg-zinc-100 text-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300' },
    personas:        { icon: UserCircle,   color: 'text-teal-500',   bg: 'bg-teal-500/10',   badge: 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300' },
    rrhh:            { icon: Users,        color: 'text-rose-500',   bg: 'bg-rose-500/10',   badge: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' },
    'reportes-sri':  { icon: BarChart2,    color: 'text-amber-500',  bg: 'bg-amber-500/10',  badge: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' },
}

const fallback = { icon: FileText, color: 'text-gray-500', bg: 'bg-gray-500/10', badge: 'bg-gray-100 text-gray-800' }

// ─── ManualCard ───────────────────────────────────────────────────────────────

function ManualCard({ manual, onAbrir }: { manual: Manual; onAbrir: () => void }) {
    const cfg = CONFIG[manual.clave] ?? fallback
    const Icon = cfg.icon

    return (
        <div className={cn(
            'rounded-2xl border flex flex-col gap-4 p-5 transition-all duration-200',
            manual.disponible
                ? 'hover:shadow-lg hover:-translate-y-0.5 cursor-pointer'
                : 'opacity-50 cursor-not-allowed',
        )}
            style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}
            onClick={manual.disponible ? onAbrir : undefined}
        >
            {/* Icono + badge */}
            <div className="flex items-start justify-between">
                <div className={cn('p-3 rounded-xl', cfg.bg)}>
                    <Icon className={cn('w-6 h-6', cfg.color)} />
                </div>
                <span className={cn('text-[10px] font-semibold px-2 py-0.5 rounded-full', cfg.badge)}>
                    {['bancos', 'compras', 'contabilidad', 'diagnostico', 'inventario', 'rrhh', 'reportes-sri', 'taller'].includes(manual.clave) ? 'Dinámico' : 'PDF'}
                </span>
            </div>

            {/* Texto */}
            <div className="flex-1">
                <h3 className="font-bold text-base mb-1" style={{ color: 'var(--text-main)' }}>
                    {['diagnostico', 'reportes-sri'].includes(manual.clave) ? manual.titulo : `Manual de ${manual.titulo}`}
                </h3>
                <p className="text-sm leading-relaxed" style={{ color: 'var(--text-muted)' }}>
                    {manual.descripcion}
                </p>
            </div>

            {/* Acción */}
            <div className="pt-1 border-t" style={{ borderColor: 'var(--border)' }}>
                {manual.disponible ? (
                    <button
                        onClick={e => { e.stopPropagation(); onAbrir() }}
                        className="w-full flex items-center justify-center gap-2 py-2 rounded-xl text-sm font-semibold transition-all hover:opacity-90"
                        style={{ background: 'var(--primary)', color: '#fff' }}
                    >
                        <FileText size={14} /> Ver manual
                    </button>
                ) : (
                    <div className="flex items-center justify-center gap-2 py-2 text-sm"
                        style={{ color: 'var(--text-muted)' }}>
                        <AlertCircle size={14} /> No disponible
                    </div>
                )}
            </div>
        </div>
    )
}

// ─── Modal visor PDF ──────────────────────────────────────────────────────────

function PdfModal({ manual, onClose }: { manual: Manual; onClose: () => void }) {
    const cfg = CONFIG[manual.clave] ?? fallback
    const Icon = cfg.icon

    return (
        <div
            className="fixed inset-0 flex items-center justify-center p-4"
            style={{ background: 'rgba(0,0,0,0.6)', zIndex: 80 }}
            onClick={onClose}
        >
            <div
                className="flex flex-col rounded-2xl shadow-2xl overflow-hidden"
                style={{
                    width: 'min(900px, 95vw)',
                    height: '92vh',
                    background: 'var(--bg-card)',
                }}
                onClick={e => e.stopPropagation()}
            >
                {/* Header modal */}
                <div className="flex items-center justify-between px-5 py-3 border-b shrink-0"
                    style={{ borderColor: 'var(--border)' }}>
                    <div className="flex items-center gap-3">
                        <div className={cn('p-1.5 rounded-lg', cfg.bg)}>
                            <Icon className={cn('w-4 h-4', cfg.color)} />
                        </div>
                        <span className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                            Manual de {manual.titulo}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <a
                            href={manual.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors hover:opacity-80"
                            style={{ color: 'var(--text-muted)', borderColor: 'var(--border)' }}
                            onClick={e => e.stopPropagation()}
                        >
                            <ExternalLink size={12} /> Abrir en nueva pestaña
                        </a>
                        <button
                            onClick={onClose}
                            className="p-1.5 rounded-lg transition-colors hover:bg-red-500/10 text-red-500"
                        >
                            <X size={16} />
                        </button>
                    </div>
                </div>

                {/* iframe */}
                <iframe
                    src={manual.url}
                    title={`Manual ${manual.titulo}`}
                    className="flex-1 w-full border-0"
                    style={{ minHeight: 0 }}
                />
            </div>
        </div>
    )
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function ManualesIndex() {
    const { manuales } = usePage<Props>().props
    const [activo, setActivo] = useState<Manual | null>(null)

    const disponibles = manuales.filter(m => m.disponible)
    const noDisponibles = manuales.filter(m => !m.disponible)

    return (
        <AppLayout title="Manuales de Uso" suppressFlash>
            <Head title="Manuales de Uso" />

            <div className="px-6 pt-6 pb-10">

                {/* Header */}
                <div className="flex items-center gap-3 mb-2">
                    <div className="p-2 rounded-xl"
                        style={{ background: 'color-mix(in srgb, var(--primary) 15%, transparent)' }}>
                        <Library size={24} style={{ color: 'var(--primary)' }} />
                    </div>
                    <div>
                        <h1 className="text-xl font-bold" style={{ color: 'var(--text-main)' }}>
                            Manuales de Uso
                        </h1>
                        <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                            Guías y documentación de cada módulo del sistema
                        </p>
                    </div>
                </div>

                <p className="text-sm mb-8 mt-1" style={{ color: 'var(--text-muted)' }}>
                    Haz clic en cualquier manual para verlo directamente en pantalla.
                    {disponibles.length > 0 && ` ${disponibles.length} manual${disponibles.length > 1 ? 'es' : ''} disponible${disponibles.length > 1 ? 's' : ''}.`}
                </p>

                {/* Grid */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    {manuales.map(m => (
                        <ManualCard
                            key={m.clave}
                            manual={m}
                            onAbrir={() => setActivo(m)}
                        />
                    ))}
                </div>

                {noDisponibles.length > 0 && (
                    <p className="text-xs mt-8 text-center" style={{ color: 'var(--text-muted)' }}>
                        {noDisponibles.length} manual{noDisponibles.length > 1 ? 'es' : ''} aún no disponible{noDisponibles.length > 1 ? 's' : ''} — se publicarán próximamente.
                    </p>
                )}
            </div>

            {/* Visor PDF */}
            {activo && (
                <PdfModal manual={activo} onClose={() => setActivo(null)} />
            )}
        </AppLayout>
    )
}
