import type { ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { cn } from '@/lib/utils'

interface Props {
    titulo: string
    children: ReactNode
    /** Clases de color: texto + fondo al pasar el mouse. */
    color: string
    onClick?: () => void
    /** Navegación Inertia. */
    href?: string
}

/** Botón de acción solo-icono (o letras) con tooltip, para las filas de los listados. */
export default function AccionIcono({ titulo, children, color, onClick, href }: Props) {
    const boton = (
        <button
            type="button"
            className={cn('flex items-center justify-center min-w-7 h-7 px-1.5 rounded transition-colors', color)}
            onClick={onClick}
            title={titulo}
            aria-label={titulo}
        >
            {children}
        </button>
    )

    return href ? <Link href={href}>{boton}</Link> : boton
}

/** Colores por acción, iguales en todos los listados de ventas. */
export const COLOR_ACCION = {
    ver:      'text-(--primary) hover:bg-amber-500/10',
    pdf:      'text-red-500 hover:bg-red-500/10',
    convertir: 'text-emerald-500 hover:bg-emerald-500/10',
    abonar:   'text-teal-500 hover:bg-teal-500/10',
    anular:   'text-red-400 hover:bg-red-500/10',
    eliminar: 'text-red-600 hover:bg-red-600/15',
} as const
