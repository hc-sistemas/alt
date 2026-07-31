import React from 'react'
import { Link } from '@inertiajs/react'
import { ChevronRight } from 'lucide-react'

interface Breadcrumb {
    label: string
    href?: string
}

interface Props {
    title: string
    description?: React.ReactNode
    breadcrumbs?: Breadcrumb[]
    actions?: React.ReactNode
    /** Contenido de ancho completo (ej. un banner de alerta) que se muestra
     * debajo del título/acciones pero todavía DENTRO del borde inferior del
     * header — para pantallas que necesitan un aviso pegado al título en
     * vez de separado por la línea divisoria. */
    banner?: React.ReactNode
}

export default function PageHeader({ title, description, breadcrumbs, actions, banner }: Props) {
    return (
        <div className="px-6 py-4 border-b" style={{ borderColor: 'var(--border)' }}>
            <div className="flex items-start justify-between">
                <div>
                    {breadcrumbs && breadcrumbs.length > 0 && (
                        <nav className="flex items-center gap-1 text-xs mb-1" style={{ color: 'var(--text-muted)' }}>
                            {breadcrumbs.map((crumb, i) => (
                                <React.Fragment key={i}>
                                    {i > 0 && <ChevronRight className="w-3 h-3" />}
                                    {crumb.href ? (
                                        <Link href={crumb.href} className="hover:text-amber-500 transition-colors">
                                            {crumb.label}
                                        </Link>
                                    ) : (
                                        <span style={{ color: 'var(--text-main)' }}>{crumb.label}</span>
                                    )}
                                </React.Fragment>
                            ))}
                        </nav>
                    )}
                    <h1 className="text-xl font-semibold" style={{ color: 'var(--text-main)' }}>{title}</h1>
                    {description && (
                        <p className="text-sm mt-0.5 flex items-center gap-2 flex-wrap" style={{ color: 'var(--text-muted)' }}>{description}</p>
                    )}
                </div>
                {actions && <div className="flex items-center gap-2">{actions}</div>}
            </div>
            {banner && <div className="mt-3">{banner}</div>}
        </div>
    )
}
