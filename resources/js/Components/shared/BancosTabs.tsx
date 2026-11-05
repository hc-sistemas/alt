import { Link, usePage } from '@inertiajs/react'
import { cn } from '@/lib/utils'
import type { PageProps } from '@/types'

// Una sola entrada de menú «Bancos» con cuatro pestañas (02-06 §2, CAM-095):
// Cuentas, Movimientos, Conciliación y Tarjetas. Cheques, Reportes y Configuración
// de cobros siguen dentro del módulo como accesos secundarios a la derecha.
// AppLayout la dibuja sola en las rutas /bancos/* que figuran aquí.

const PESTANAS = [
    { nombre: 'Cuentas',      href: '/bancos/catalogo' },
    { nombre: 'Movimientos',  href: '/bancos/movimientos' },
    { nombre: 'Conciliación', href: '/bancos/conciliaciones' },
    { nombre: 'Tarjetas',     href: '/bancos/tarjetas' },
]

const SECUNDARIAS = [
    { nombre: 'Cheques',                href: '/bancos/cheques' },
    { nombre: 'Reportes',               href: '/bancos/reportes' },
    { nombre: 'Configuración de cobros', href: '/bancos/configuracion-cobros' },
]

/** ¿La ruta pertenece al módulo Bancos con pestañas? (Cajas es del módulo Caja y queda fuera.) */
export function esRutaBancos(path: string): boolean {
    return [...PESTANAS, ...SECUNDARIAS].some(p => path === p.href || path.startsWith(p.href + '/'))
}

export default function BancosTabs() {
    const { url } = usePage<PageProps>()
    const path = url.split('?')[0]

    if (!esRutaBancos(path)) return null

    const activa = (href: string) => path === href || path.startsWith(href + '/')

    return (
        <nav className="flex flex-wrap items-center gap-1 px-4 md:px-6 pt-3 border-b"
             style={{ borderColor: 'var(--border)', background: 'var(--bg-main)' }}
             aria-label="Bancos">
            {PESTANAS.map(p => (
                <Link key={p.href} href={p.href}
                    className={cn(
                        'px-4 py-2 text-sm font-semibold rounded-t-lg border-b-2 transition-colors',
                        activa(p.href) ? 'border-[var(--primary)]' : 'border-transparent hover:opacity-80'
                    )}
                    style={{ color: activa(p.href) ? 'var(--primary)' : 'var(--text-muted)' }}>
                    {p.nombre}
                </Link>
            ))}

            <span className="mx-2 h-5 w-px" style={{ background: 'var(--border)' }} />

            {SECUNDARIAS.map(p => (
                <Link key={p.href} href={p.href}
                    className="px-3 py-2 text-xs font-medium rounded-t-lg border-b-2 transition-colors"
                    style={{
                        color: activa(p.href) ? 'var(--primary)' : 'var(--text-muted)',
                        borderColor: activa(p.href) ? 'var(--primary)' : 'transparent',
                    }}>
                    {p.nombre}
                </Link>
            ))}
        </nav>
    )
}
