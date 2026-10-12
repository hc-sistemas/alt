import { router, usePage } from '@inertiajs/react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { Search, CornerDownLeft } from 'lucide-react'
import { cn } from '@/lib/utils'
import { navItems, MODULOS_SIN_PERMISO } from '@/Components/shared/Sidebar'
import type { PageProps } from '@/types'

interface Destino {
    nombre: string
    seccion: string
    href: string
    busqueda: string
}

// Nombres alternativos para pantallas que la gente nombra distinto al menú.
const ALIAS: Record<string, string> = {
    '/ventas/cxc': 'cuentas por cobrar cobros',
    '/compras/cuentas-pagar': 'cxp pagos proveedores',
    '/inventario/kardex': 'kardex movimientos stock',
    '/inventario/kardex/saldos': 'existencias stock saldos',
    '/ventas/facturas': 'factura venta',
    '/taller/ordenes': 'ot reparaciones fix',
    '/inventario/equipos-taller': 'equipos finalizados reparados entrega retiro despacho taller',
    '/rrhh/asistencia': 'marcaje entrada salida almuerzo break',
}

const normalizar = (s: string) =>
    s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim()

interface Props {
    conColor: boolean
}

export default function BuscadorModulos({ conColor }: Props) {
    const { props } = usePage<PageProps>()
    const permisos = props.permisos
    const perfil = props.auth.user?.perfil ?? ''
    const ruc = props.empresa_activa?.ruc

    const [abierto, setAbierto] = useState(false)
    const [q, setQ] = useState('')
    const [idx, setIdx] = useState(0)
    const inputRef = useRef<HTMLInputElement>(null)

    // Mismas reglas de visibilidad que el Sidebar: no se ofrece lo que el usuario no puede abrir.
    const destinos = useMemo<Destino[]>(() => {
        const out: Destino[] = []
        const add = (nombre: string, seccion: string, href: string) =>
            out.push({ nombre, seccion, href, busqueda: normalizar(`${nombre} ${seccion} ${ALIAS[href] ?? ''}`) })

        for (const item of navItems) {
            if (permisos !== '*' && !MODULOS_SIN_PERMISO.has(item.clave) && permisos[item.clave]?.ver !== true) continue
            if (item.href) add(item.nombre, item.nombre, item.href)
            const hijos = [...(item.hijos ?? []), ...(item.subgrupos?.flatMap(sg => sg.hijos) ?? [])]
            for (const h of hijos) {
                if (h.soloRuc && ruc !== h.soloRuc) continue
                if (permisos !== '*') {
                    if (h.permiso && permisos[h.permiso]?.ver !== true) continue
                    if (h.perfiles && !h.perfiles.includes(perfil)) continue
                }
                add(h.nombre, item.nombre, h.href)
            }
        }
        return out
    }, [permisos, perfil, ruc])

    const resultados = useMemo(() => {
        const t = normalizar(q)
        if (!t) return destinos.slice(0, 8)
        const palabras = t.split(/\s+/)
        return destinos
            .filter(d => palabras.every(p => d.busqueda.includes(p)))
            .sort((a, b) => Number(normalizar(b.nombre).startsWith(t)) - Number(normalizar(a.nombre).startsWith(t)))
            .slice(0, 8)
    }, [q, destinos])

    useEffect(() => setIdx(0), [q])

    // Ctrl+K / Cmd+K abre el buscador desde cualquier pantalla.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault()
                setAbierto(true)
                setTimeout(() => inputRef.current?.focus(), 0)
            }
        }
        window.addEventListener('keydown', onKey)
        return () => window.removeEventListener('keydown', onKey)
    }, [])

    const ir = (d: Destino | undefined) => {
        if (!d) return
        setAbierto(false)
        setQ('')
        router.visit(d.href)
    }

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setIdx(i => Math.min(i + 1, resultados.length - 1)) }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setIdx(i => Math.max(i - 1, 0)) }
        else if (e.key === 'Enter') { e.preventDefault(); ir(resultados[idx]) }
        else if (e.key === 'Escape') { setAbierto(false); inputRef.current?.blur() }
    }

    return (
        <div className="relative hidden sm:block">
            <div className={cn('flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm w-56 lg:w-72', conColor && 'border-white/30')}
                style={conColor
                    ? { background: 'rgba(255,255,255,0.12)', color: '#FFFFFF' }
                    : { background: 'var(--bg-main)', borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                <Search className="w-4 h-4 shrink-0" style={{ opacity: 0.7 }} />
                <input ref={inputRef} value={q}
                    onChange={e => { setQ(e.target.value); setAbierto(true) }}
                    onFocus={() => setAbierto(true)}
                    onKeyDown={onKeyDown}
                    placeholder="Buscar módulo… (Ctrl+K)"
                    aria-label="Buscar módulo o pantalla"
                    className={cn('flex-1 min-w-0 bg-transparent outline-none placeholder:opacity-70', conColor && 'placeholder:text-white')} />
            </div>

            {abierto && (
                <>
                    <div className="fixed inset-0 z-10" onClick={() => setAbierto(false)} />
                    <div className="absolute left-0 right-0 top-full mt-1 rounded-lg shadow-lg border z-20 overflow-hidden min-w-[18rem]"
                        style={{ background: 'var(--bg-card)', borderColor: 'var(--border)' }}>
                        {resultados.length === 0 ? (
                            <p className="px-3 py-3 text-sm" style={{ color: 'var(--text-muted)' }}>Sin resultados</p>
                        ) : resultados.map((d, i) => (
                            <button key={d.href} onClick={() => ir(d)} onMouseEnter={() => setIdx(i)}
                                className="w-full flex items-center justify-between gap-3 px-3 py-2 text-sm text-left"
                                style={{ background: i === idx ? 'var(--bg-main)' : 'transparent', color: 'var(--text-main)' }}>
                                <span className="truncate">{d.nombre}</span>
                                <span className="flex items-center gap-1 text-xs shrink-0" style={{ color: 'var(--text-muted)' }}>
                                    {d.seccion}
                                    {i === idx && <CornerDownLeft className="w-3 h-3" />}
                                </span>
                            </button>
                        ))}
                    </div>
                </>
            )}
        </div>
    )
}
