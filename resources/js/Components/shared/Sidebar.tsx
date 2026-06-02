import { Link, usePage } from '@inertiajs/react'
import { useState } from 'react'
import {
    LayoutDashboard, FileText, ShoppingCart, Package, BookOpen,
    Landmark, Users, Wrench, BarChart2, Settings, ChevronDown,
    ChevronLeft, ChevronRight, X
} from 'lucide-react'
import { cn } from '@/lib/utils'
import type { PageProps } from '@/types'

interface NavItem {
    nombre: string
    clave: string
    icon: React.ElementType
    href?: string
    hijos?: { nombre: string; href: string }[]
}

const navItems: NavItem[] = [
    { nombre: 'Dashboard', clave: 'dashboard', icon: LayoutDashboard, href: '/dashboard' },
    {
        nombre: 'Ventas', clave: 'ventas', icon: FileText,
        hijos: [
            { nombre: 'Facturas', href: '/ventas/facturas' },
            { nombre: 'Proformas', href: '/ventas/proformas' },
            { nombre: 'CxC', href: '/ventas/cxc' },
            { nombre: 'Notas de Crédito', href: '/ventas/notas-credito' },
        ]
    },
    {
        nombre: 'Compras', clave: 'compras', icon: ShoppingCart,
        hijos: [
            { nombre: 'Facturas de Compra', href: '/compras/facturas' },
            { nombre: 'Proveedores',        href: '/compras/proveedores' },
            { nombre: 'Cuentas por Pagar',  href: '/compras/cuentas-pagar' },
            { nombre: 'Importaciones',      href: '/compras/importaciones' },
        ]
    },
    {
        nombre: 'Inventario', clave: 'inventario', icon: Package,
        hijos: [
            { nombre: 'Productos', href: '/inventario/productos' },
            { nombre: 'Kárdex', href: '/inventario/kardex' },
            { nombre: 'Bodegas', href: '/inventario/bodegas' },
        ]
    },
    {
        nombre: 'Contabilidad', clave: 'contabilidad', icon: BookOpen,
        hijos: [
            { nombre: 'Ejercicios', href: '/contabilidad/ejercicios' },
            { nombre: 'Asientos', href: '/contabilidad/asientos' },
            { nombre: 'Plan de Cuentas', href: '/contabilidad/plan-cuentas' },
        ]
    },
    {
        nombre: 'Bancos', clave: 'bancos', icon: Landmark,
        hijos: [
            { nombre: 'Movimientos', href: '/bancos/movimientos' },
            { nombre: 'Cajas', href: '/bancos/cajas' },
        ]
    },
    {
        nombre: 'RRHH', clave: 'rrhh', icon: Users,
        hijos: [
            { nombre: 'Colaboradores', href: '/rrhh/colaboradores' },
            { nombre: 'Nómina', href: '/rrhh/nomina' },
        ]
    },
    {
        nombre: 'Taller', clave: 'taller', icon: Wrench,
        hijos: [
            { nombre: 'Órdenes de Trabajo', href: '/taller/ordenes' },
            { nombre: 'Equipos', href: '/taller/equipos' },
        ]
    },
    { nombre: 'Reportes', clave: 'reportes', icon: BarChart2, href: '/reportes' },
    {
        nombre: 'Configuración', clave: 'configuracion', icon: Settings,
        hijos: [
            { nombre: 'Usuarios', href: '/configuracion/usuarios' },
            { nombre: 'Permisos', href: '/configuracion/permisos' },
            { nombre: 'Empresa', href: '/configuracion/empresa' },
        ]
    },
]

interface Props {
    collapsed: boolean
    onCollapse: (v: boolean) => void
    mobileOpen: boolean
    onMobileClose: () => void
}

export default function Sidebar({ collapsed, onCollapse, mobileOpen, onMobileClose }: Props) {
    const { url } = usePage<PageProps>()
    const [openSections, setOpenSections] = useState<string[]>(['configuracion'])

    const toggleSection = (clave: string) => {
        setOpenSections(prev =>
            prev.includes(clave) ? prev.filter(s => s !== clave) : [...prev, clave]
        )
    }

    const isActive = (href: string) => url.startsWith(href)

    const content = (
        <div className="flex flex-col h-full"
            style={{ background: 'var(--sidebar-bg)', color: 'var(--sidebar-text)' }}>

            {/* Logo */}
            <div className="flex items-center h-16 px-4 shrink-0 border-b border-slate-700/50">
                <div className="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                    style={{ background: 'rgba(245,158,11,0.2)', border: '1px solid rgba(245,158,11,0.3)' }}>
                    <span className="text-base font-bold" style={{ color: '#F59E0B' }}>A</span>
                </div>
                {!collapsed && (
                    <div className="ml-3 overflow-hidden">
                        <p className="text-sm font-bold text-white leading-none">Altamira</p>
                        <p className="text-xs leading-none mt-0.5" style={{ color: '#F59E0B' }}>ERP</p>
                    </div>
                )}
            </div>

            {/* Nav */}
            <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-1">
                {navItems.map(item => {
                    const Icon = item.icon
                    const isOpen = openSections.includes(item.clave)
                    const hasHijos = item.hijos && item.hijos.length > 0
                    const isItemActive = item.href
                        ? isActive(item.href)
                        : item.hijos?.some(h => isActive(h.href)) ?? false

                    return (
                        <div key={item.clave}>
                            {item.href && !hasHijos ? (
                                <Link
                                    href={item.href}
                                    className={cn(
                                        'flex items-center gap-3 px-2 py-2 rounded-lg text-sm nav-item-transition',
                                        isItemActive
                                            ? 'text-white font-medium'
                                            : 'hover:text-white'
                                    )}
                                    style={isItemActive ? {
                                        background: 'var(--sidebar-active-bg)',
                                        color: 'var(--sidebar-active)',
                                        borderLeft: `3px solid var(--sidebar-active)`,
                                    } : {}}
                                    title={collapsed ? item.nombre : undefined}
                                >
                                    <Icon className="w-5 h-5 shrink-0" />
                                    {!collapsed && <span>{item.nombre}</span>}
                                </Link>
                            ) : (
                                <>
                                    <button
                                        onClick={() => !collapsed && toggleSection(item.clave)}
                                        className={cn(
                                            'w-full flex items-center gap-3 px-2 py-2 rounded-lg text-sm nav-item-transition',
                                            isItemActive ? 'font-medium' : 'hover:text-white'
                                        )}
                                        style={isItemActive ? {
                                            color: 'var(--sidebar-active)',
                                        } : {}}
                                        title={collapsed ? item.nombre : undefined}
                                    >
                                        <Icon className="w-5 h-5 shrink-0" />
                                        {!collapsed && (
                                            <>
                                                <span className="flex-1 text-left">{item.nombre}</span>
                                                <ChevronDown className={cn(
                                                    'w-4 h-4 transition-transform',
                                                    isOpen && 'rotate-180'
                                                )} />
                                            </>
                                        )}
                                    </button>

                                    {!collapsed && isOpen && hasHijos && (
                                        <div className="mt-1 ml-4 space-y-1 border-l border-slate-700/50 pl-3">
                                            {item.hijos!.map(hijo => (
                                                <Link
                                                    key={hijo.href}
                                                    href={hijo.href}
                                                    className={cn(
                                                        'block px-2 py-1.5 rounded-md text-xs nav-item-transition',
                                                        isActive(hijo.href)
                                                            ? 'font-medium'
                                                            : 'hover:text-white'
                                                    )}
                                                    style={isActive(hijo.href) ? {
                                                        color: 'var(--sidebar-active)',
                                                    } : {}}
                                                >
                                                    {hijo.nombre}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}
                        </div>
                    )
                })}
            </nav>

            {/* Botón colapsar */}
            <div className="p-2 border-t border-slate-700/50">
                <button
                    onClick={() => onCollapse(!collapsed)}
                    className="w-full flex items-center justify-center p-2 rounded-lg hover:bg-slate-700/50 transition-colors"
                    title={collapsed ? 'Expandir' : 'Colapsar'}
                >
                    {collapsed
                        ? <ChevronRight className="w-4 h-4" />
                        : <ChevronLeft className="w-4 h-4" />
                    }
                </button>
            </div>
        </div>
    )

    return (
        <>
            {/* Desktop sidebar */}
            <aside
                className="hidden md:flex flex-col h-full sidebar-transition shrink-0"
                style={{ width: collapsed ? '56px' : '220px' }}
            >
                {content}
            </aside>

            {/* Mobile overlay */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 md:hidden">
                    <div className="absolute inset-0 bg-black/60" onClick={onMobileClose} />
                    <aside className="absolute left-0 top-0 bottom-0 w-64 flex flex-col">
                        <div className="absolute top-3 right-3 z-10">
                            <button onClick={onMobileClose}
                                className="p-1.5 rounded-lg bg-slate-700/50 text-slate-300 hover:text-white">
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                        {content}
                    </aside>
                </div>
            )}
        </>
    )
}
