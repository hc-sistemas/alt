import { Head, Link, router, usePage } from '@inertiajs/react'
import { Fragment, useEffect, useRef, useState } from 'react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Search, FileSpreadsheet, Upload } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { cn } from '@/lib/utils'
import { usePermiso } from '@/Hooks/usePermiso'
import type { PaginatedData, PageProps } from '@/types'

interface ListaPrecioRow {
    producto_id: number
    codigo: string
    nombre: string
    marca_nombre: string
    pvp_base: number
    pvd_base: number
    porcentaje_iva: number
    lista_pvp_precio: number | null
    lista_pvp_descuento_max: number | null
    lista_pvp_descuento_max_promo: number | null
    lista_pvd_precio: number | null
    lista_pvd_descuento_max: number | null
    vigencia_desde: string | null
    vigencia_hasta: string | null
    inventario?: Record<number, number>
}

interface Marca { id: number; nombre: string }
interface Categoria { id: number; nombre: string }
interface BodegaOpcion { id: number; nombre: string }

interface Props extends PageProps {
    listas: PaginatedData<ListaPrecioRow>
    filters: { search?: string; marca_id?: string; categoria_id?: string }
    marcas: Marca[]
    categorias: Categoria[]
    bodegas: BodegaOpcion[]
}

interface SinPaginarResponse {
    props: {
        filas: ListaPrecioRow[]
    }
}

interface EditState {
    producto_id: number
    pvp: string
    pvd: string
    descuento_pvp: string
    descuento_pvd: string
    // No se editan aquí (ver grupo de columnas Promo) — se preservan para
    // no enviar null y borrar sin querer la vigencia de la promo al guardar
    // solo precio/descuento.
    vigencia_desde: string
    vigencia_hasta: string
}

interface PromoEditState {
    producto_id: number
    descuento_max_promo: string
    vigencia_desde: string
    vigencia_hasta: string
}

function fmt(val: number | null | undefined): string {
    if (val == null) return ''
    return Number(val).toFixed(2)
}

// Precio base (sin IVA) -> precio final mostrado, sumando el % de IVA propio
// del producto (varía por producto: 0%, 5%, 15%, etc.).
function conIva(base: number, porcentajeIva: number): number {
    return base * (1 + (Number(porcentajeIva) || 0) / 100)
}

function fmtDate(val: string | null | undefined): string {
    if (!val) return ''
    return val.slice(0, 10)
}

// Hay valor útil en el campo: no es null/undefined, y si llega como string
// (los NUMERIC/DATE de PostgreSQL vienen como string sin castear en el
// query builder crudo de index()) tampoco está vacío tras recortar espacios.
function tieneValor(val: unknown): boolean {
    return val !== null && val !== undefined && String(val).trim() !== ''
}

// Determina si mostrar los valores de la promo en vez del botón "Agregar
// promo": basta con que haya datos guardados (%, Desde y Hasta), sin
// importar si la fecha está vigente o vencida hoy — eso solo afecta el
// descuento aplicado (ver DescuentoService), no la visibilidad del bloque.
function tienePromoGuardada(row: ListaPrecioRow): boolean {
    return tieneValor(row.lista_pvp_descuento_max_promo)
        && tieneValor(row.vigencia_desde)
        && tieneValor(row.vigencia_hasta)
}

export default function ListasPrecioIndex() {
    const { listas, filters, marcas, categorias, bodegas } = usePage<Props>().props
    const { puede } = usePermiso('inventario')
    // Código, Nombre, PVP+IVA, Desc. PVP%, PVD+IVA, Desc. PVD% (6) + bodegas + Promo (1)
    const totalColumnas = 7 + bodegas.length

    const [search, setSearch]   = useState(filters.search ?? '')
    const [marcaId, setMarcaId] = useState(filters.marca_id ?? '')
    const [catId, setCatId]     = useState(filters.categoria_id ?? '')
    const [editing, setEditing] = useState<EditState | null>(null)
    const [promoEditing, setPromoEditing] = useState<PromoEditState | null>(null)

    const debounceRef   = useRef<ReturnType<typeof setTimeout> | null>(null)
    const isFirstRender = useRef(true)
    const fileInputRef  = useRef<HTMLInputElement>(null)
    const blurTimerRef  = useRef<ReturnType<typeof setTimeout> | null>(null)

    useEffect(() => {
        if (isFirstRender.current) { isFirstRender.current = false; return }
        if (debounceRef.current) clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            router.get(route('inventario.listas.index'), {
                search: search || undefined,
                marca_id: marcaId || undefined,
                categoria_id: catId || undefined,
            }, { preserveState: true, replace: true })
        }, 300)
        return () => { if (debounceRef.current) clearTimeout(debounceRef.current) }
    }, [search, marcaId, catId])

    function startEdit(row: ListaPrecioRow) {
        setEditing({
            producto_id:   row.producto_id,
            pvp:           fmt(row.lista_pvp_precio ?? row.pvp_base),
            pvd:           fmt(row.lista_pvd_precio ?? row.pvd_base),
            descuento_pvp: fmt(row.lista_pvp_descuento_max ?? 0),
            descuento_pvd: fmt(row.lista_pvd_descuento_max ?? 0),
            vigencia_desde: fmtDate(row.vigencia_desde),
            vigencia_hasta: fmtDate(row.vigencia_hasta),
        })
    }

    function guardarFila(snap: EditState) {
        router.put(
            route('inventario.listas.update', snap.producto_id),
            {
                pvp:            parseFloat(snap.pvp) || 0,
                pvd:            parseFloat(snap.pvd) || 0,
                descuento_pvp:  parseFloat(snap.descuento_pvp) || 0,
                descuento_pvd:  parseFloat(snap.descuento_pvd) || 0,
                vigencia_desde: snap.vigencia_desde || null,
                vigencia_hasta: snap.vigencia_hasta || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toastExito('Precios actualizados')
                    setEditing(prev => prev?.producto_id === snap.producto_id ? null : prev)
                },
                onError: () => toastError('Error al guardar los precios'),
            }
        )
    }

    function startPromoEdit(row: ListaPrecioRow) {
        setPromoEditing({
            producto_id:          row.producto_id,
            descuento_max_promo:  fmt(row.lista_pvp_descuento_max_promo ?? 0),
            vigencia_desde:       fmtDate(row.vigencia_desde),
            vigencia_hasta:       fmtDate(row.vigencia_hasta),
        })
    }

    // Reusa el mismo endpoint de update() — envía también los campos base
    // (pvp/pvd/descuentos) sin cambios, porque el backend los exige juntos.
    function guardarPromo(row: ListaPrecioRow, snap: PromoEditState) {
        if (snap.descuento_max_promo.trim() !== '' && (!snap.vigencia_desde || !snap.vigencia_hasta)) {
            toastError('Debe indicar Desde y Hasta para configurar la promo.')
            return
        }

        router.put(
            route('inventario.listas.update', snap.producto_id),
            {
                pvp:                  row.lista_pvp_precio ?? row.pvp_base,
                pvd:                  row.lista_pvd_precio ?? row.pvd_base,
                descuento_pvp:        row.lista_pvp_descuento_max ?? 0,
                descuento_pvd:        row.lista_pvd_descuento_max ?? 0,
                descuento_max_promo:  snap.descuento_max_promo.trim() !== '' ? parseFloat(snap.descuento_max_promo) : null,
                vigencia_desde:       snap.vigencia_desde || null,
                vigencia_hasta:       snap.vigencia_hasta || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toastExito('Promoción actualizada')
                    setPromoEditing(null)
                },
                onError: () => toastError('Error al guardar la promoción'),
            }
        )
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Enter') {
            if (blurTimerRef.current) clearTimeout(blurTimerRef.current)
            if (editing) guardarFila(editing)
        }
        if (e.key === 'Escape') {
            if (blurTimerRef.current) clearTimeout(blurTimerRef.current)
            setEditing(null)
        }
    }

    async function descargarPlantilla() {
        try {
            const response = await fetch(route('inventario.listas.index') + '?sin_paginar=1', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            if (!response.ok) { toastError('Error al obtener los productos'); return }
            const json: SinPaginarResponse = await response.json()
            const filas = json.props.filas
            const XLSX = await import('xlsx')
            const rows = filas.map(r => ({
                codigo:         r.codigo,
                nombre:         r.nombre,
                pvp_lista:      r.lista_pvp_precio != null ? Number(r.lista_pvp_precio) : Number(r.pvp_base),
                pvd_lista:      r.lista_pvd_precio != null ? Number(r.lista_pvd_precio) : Number(r.pvd_base),
                descuento_pvp:  r.lista_pvp_descuento_max != null ? Number(r.lista_pvp_descuento_max) : 0,
                descuento_pvd:  r.lista_pvd_descuento_max != null ? Number(r.lista_pvd_descuento_max) : 0,
                descuento_promo: r.lista_pvp_descuento_max_promo != null ? Number(r.lista_pvp_descuento_max_promo) : '',
                vigencia_desde: fmtDate(r.vigencia_desde),
                vigencia_hasta: fmtDate(r.vigencia_hasta),
            }))
            const ws = XLSX.utils.json_to_sheet(rows)
            const wb = XLSX.utils.book_new()
            XLSX.utils.book_append_sheet(wb, ws, 'Precios')
            XLSX.writeFile(wb, 'plantilla_precios.xlsx')
        } catch {
            toastError('Error al generar la plantilla')
        }
    }

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0]
        if (!file) return

        const formData = new FormData()
        formData.append('archivo', file)

        router.post(route('inventario.listas.importar'), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as { importados?: number; errores?: string[] }
                const importados = flash.importados ?? 0
                const errores = flash.errores ?? []
                Swal.fire({
                    icon: errores.length > 0 && importados === 0 ? 'error' : 'success',
                    title: `Importados: ${importados}`,
                    html: errores.length > 0
                        ? `<div style="text-align:left;max-height:200px;overflow-y:auto">${errores.map(e => `<p>${e}</p>`).join('')}</div>`
                        : 'Todos los precios fueron actualizados.',
                })
            },
            onError: () => toastError('Error al importar el archivo'),
        })

        if (fileInputRef.current) fileInputRef.current.value = ''
    }

    const isRowEditing = (id: number) => editing?.producto_id === id

    const editVal = (field: keyof EditState) => editing ? editing[field] as string : ''
    const setEditVal = (field: keyof Omit<EditState, 'producto_id'>) =>
        (e: React.ChangeEvent<HTMLInputElement>) =>
            setEditing(prev => prev ? { ...prev, [field]: e.target.value } : null)

    const tdBase = 'px-2 py-2 whitespace-nowrap text-xs'
    const tdMuted = cn(tdBase, 'font-mono text-right')

    function editCell(
        field: keyof Omit<EditState, 'producto_id'>,
        props: React.InputHTMLAttributes<HTMLInputElement> = {}
    ) {
        return (
            <td className={tdBase} style={{ color: 'var(--text-main)' }}>
                <Input
                    value={editVal(field)}
                    onChange={setEditVal(field)}
                    onKeyDown={handleKeyDown}
                    className="h-7 text-xs w-24 font-mono"
                    {...props}
                />
            </td>
        )
    }

    // Columna única de promo, al final de la tabla. Sin promo guardada: enlace
    // "Agregar promo" (si hay permiso de editar). Con promo guardada: % +
    // vigencia compacta, clickeable para reabrir el panel de edición inline
    // (Desc. promo % / Desde / Hasta).
    function promoCell(row: ListaPrecioRow) {
        if (!tienePromoGuardada(row)) {
            if (!puede('editar')) {
                return <td className={tdBase} style={{ borderLeft: '1px solid var(--border)' }} />
            }
            return (
                <td className={tdBase} style={{ borderLeft: '1px solid var(--border)' }}>
                    <button
                        type="button"
                        onClick={() => startPromoEdit(row)}
                        className="text-xs font-medium hover:underline whitespace-nowrap"
                        style={{ color: 'var(--primary)' }}
                    >
                        Agregar promo
                    </button>
                </td>
            )
        }
        return (
            <td
                className={cn(tdBase, puede('editar') && 'cursor-pointer hover:underline')}
                style={{ color: 'var(--primary)', borderLeft: '1px solid var(--border)' }}
                onClick={() => puede('editar') && startPromoEdit(row)}
                title={puede('editar') ? 'Clic para editar la promo' : undefined}
            >
                {fmt(row.lista_pvp_descuento_max_promo)}% · {fmtDate(row.vigencia_desde)} a {fmtDate(row.vigencia_hasta)}
            </td>
        )
    }

    function readonlyCell(val: string, align: 'right' | 'left' = 'right') {
        return (
            <td className={cn(tdBase, align === 'right' ? 'text-right font-mono' : '')}
                style={{ color: 'var(--text-muted)' }}>
                {val || '—'}
            </td>
        )
    }

    return (
        <AppLayout title="Listas de Precio">
            <Head title="Listas de Precio" />
            <PageHeader
                title="Listas de Precio"
                description={
                    <>
                        Precios especiales PVP/PVD por producto. Clic en una celda para editar.
                        <span
                            className="px-2 py-0.5 rounded-full text-xs font-semibold whitespace-nowrap"
                            style={{
                                background: 'color-mix(in srgb, var(--primary) 15%, transparent)',
                                border: '1px solid var(--primary)',
                                color: 'var(--primary)',
                            }}
                        >
                            Los precios se ingresan SIN IVA — el sistema lo calcula
                        </span>
                    </>
                }
                breadcrumbs={[{ label: 'Inventario' }, { label: 'Listas de Precio' }]}
            />

            <div className="p-6">
                {/* Filtros + acciones */}
                <div className="flex items-center gap-3 mb-4 flex-wrap">
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Código o nombre..."
                            className="pl-9 w-52"
                        />
                    </div>

                    <select value={marcaId} onChange={e => setMarcaId(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todas las marcas</option>
                        {marcas.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                    </select>

                    <select value={catId} onChange={e => setCatId(e.target.value)}
                        className="h-9 rounded-md border bg-transparent px-3 py-1 text-sm"
                        style={{ borderColor: 'var(--border)', color: 'var(--text-main)', background: 'var(--bg-card)' }}>
                        <option value="">Todas las categorías</option>
                        {categorias.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                    </select>

                    <div className="flex items-center gap-2 ml-auto">
                        <button
                            onClick={descargarPlantilla}
                            className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                            style={{ background: '#16A34A', color: 'white', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                            onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                        >
                            <FileSpreadsheet className="w-4 h-4" />
                            Excel
                        </button>
                        {puede('crear') && (
                            <button
                                onClick={() => fileInputRef.current?.click()}
                                className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                                style={{ background: 'var(--primary)', color: 'black', transition: 'background 0.2s' }}
                                onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                                onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}
                            >
                                <Upload className="w-4 h-4" />
                                Importar
                            </button>
                        )}
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls"
                            className="hidden"
                            onChange={handleFileChange}
                        />
                    </div>
                </div>

                {/* Tabla */}
                <div className="rounded-xl border overflow-x-auto" style={{ borderColor: 'var(--border)' }}>
                    <table className="w-full text-xs">
                        <thead>
                            <tr style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                {[
                                    'Código', 'Nombre',
                                    'PVP+IVA', 'Desc. PVP%',
                                    'PVD+IVA', 'Desc. PVD%',
                                ].map(h => (
                                    <th key={h}
                                        className="text-left px-2 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                                {bodegas.map(b => (
                                    <th key={b.id}
                                        className="text-right px-2 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>
                                        Stock {b.nombre}
                                    </th>
                                ))}
                                <th className="text-left px-2 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap text-amber-400"
                                    style={{ borderLeft: '1px solid var(--border)' }}>
                                    Promo
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {listas.data.length === 0 ? (
                                <tr>
                                    <td colSpan={totalColumnas} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
                                        <p className="font-medium text-sm" style={{ color: 'var(--text-main)' }}>
                                            No hay productos
                                        </p>
                                        <p>Ajusta los filtros para encontrar productos</p>
                                    </td>
                                </tr>
                            ) : listas.data.map(row => {
                                const active = isRowEditing(row.producto_id)
                                const blurHandler = () => {
                                    if (!editing) return
                                    const snap = { ...editing }
                                    blurTimerRef.current = setTimeout(() => guardarFila(snap), 150)
                                }
                                const focusHandler = () => {
                                    if (blurTimerRef.current) clearTimeout(blurTimerRef.current)
                                }
                                const promoOpen = promoEditing?.producto_id === row.producto_id
                                return (
                                    <Fragment key={row.producto_id}>
                                    <tr
                                        className="border-t transition-colors"
                                        style={{
                                            borderColor: 'var(--border)',
                                            background: active ? 'var(--bg-card)' : undefined,
                                        }}
                                    >
                                        {/* Código */}
                                        <td className={cn(tdBase, 'font-mono font-medium')} style={{ color: 'var(--text-muted)' }}>
                                            {row.codigo}
                                        </td>
                                        {/* Nombre */}
                                        <td className={cn(tdBase, 'max-w-45 truncate')} style={{ color: 'var(--text-main)' }}>
                                            {row.nombre}
                                        </td>
                                        {active ? (
                                            <>
                                                {editCell('pvp',          { type: 'number', step: 0.01, min: 0,       onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('descuento_pvp', { type: 'number', step: 0.01, min: 0, max: 100, onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('pvd',          { type: 'number', step: 0.01, min: 0,       onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('descuento_pvd', { type: 'number', step: 0.01, min: 0, max: 100, onBlur: blurHandler, onFocus: focusHandler })}
                                            </>
                                        ) : (
                                            <>
                                                {/* PVP+IVA: precio base (o de lista) + % IVA del producto */}
                                                <td
                                                    className={cn(tdMuted, puede('editar') && 'cursor-pointer hover:underline')}
                                                    style={{ color: row.lista_pvp_precio != null ? 'var(--primary)' : 'var(--text-muted)' }}
                                                    onClick={() => puede('editar') && startEdit(row)}
                                                    title={puede('editar') ? 'Clic para editar' : undefined}
                                                >
                                                    {conIva(Number(row.lista_pvp_precio ?? row.pvp_base), row.porcentaje_iva).toFixed(2)}
                                                </td>
                                                <td
                                                    className={cn(tdMuted, puede('editar') && 'cursor-pointer hover:underline')}
                                                    style={{ color: 'var(--text-muted)' }}
                                                    onClick={() => puede('editar') && startEdit(row)}
                                                    title={puede('editar') ? 'Clic para editar' : undefined}
                                                >
                                                    {row.lista_pvp_descuento_max != null ? `${Number(row.lista_pvp_descuento_max).toFixed(2)}%` : '—'}
                                                </td>

                                                {/* PVD+IVA */}
                                                <td
                                                    className={cn(tdMuted, puede('editar') && 'cursor-pointer hover:underline')}
                                                    style={{ color: row.lista_pvd_precio != null ? 'var(--primary)' : 'var(--text-muted)' }}
                                                    onClick={() => puede('editar') && startEdit(row)}
                                                    title={puede('editar') ? 'Clic para editar' : undefined}
                                                >
                                                    {conIva(Number(row.lista_pvd_precio ?? row.pvd_base), row.porcentaje_iva).toFixed(2)}
                                                </td>
                                                <td
                                                    className={cn(tdMuted, puede('editar') && 'cursor-pointer hover:underline')}
                                                    style={{ color: 'var(--text-muted)' }}
                                                    onClick={() => puede('editar') && startEdit(row)}
                                                    title={puede('editar') ? 'Clic para editar' : undefined}
                                                >
                                                    {row.lista_pvd_descuento_max != null ? `${Number(row.lista_pvd_descuento_max).toFixed(2)}%` : '—'}
                                                </td>
                                            </>
                                        )}
                                        {bodegas.map(b => (
                                            <td key={b.id} className={tdMuted} style={{ color: 'var(--text-muted)' }}>
                                                {Number(row.inventario?.[b.id] ?? 0).toFixed(2)}
                                            </td>
                                        ))}
                                        {promoCell(row)}
                                    </tr>
                                    {promoOpen && (
                                        <tr style={{ borderColor: 'var(--border)' }}>
                                            <td colSpan={totalColumnas} className="px-4 py-3" style={{ background: 'var(--bg-card)', borderTop: '1px dashed var(--border)' }}>
                                                <div className="flex items-end gap-3 flex-wrap">
                                                    <div>
                                                        <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Desc. promo %</p>
                                                        <Input
                                                            type="number" step="0.01" min={0} max={100}
                                                            value={promoEditing?.descuento_max_promo ?? ''}
                                                            onChange={e => setPromoEditing(prev => prev ? { ...prev, descuento_max_promo: e.target.value } : prev)}
                                                            className="h-7 text-xs w-24 font-mono"
                                                        />
                                                    </div>
                                                    <div>
                                                        <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Desde</p>
                                                        <Input
                                                            type="date"
                                                            value={promoEditing?.vigencia_desde ?? ''}
                                                            onChange={e => setPromoEditing(prev => prev ? { ...prev, vigencia_desde: e.target.value } : prev)}
                                                            className="h-7 text-xs"
                                                        />
                                                    </div>
                                                    <div>
                                                        <p className="text-xs mb-1" style={{ color: 'var(--text-muted)' }}>Hasta</p>
                                                        <Input
                                                            type="date"
                                                            value={promoEditing?.vigencia_hasta ?? ''}
                                                            onChange={e => setPromoEditing(prev => prev ? { ...prev, vigencia_hasta: e.target.value } : prev)}
                                                            className="h-7 text-xs"
                                                        />
                                                    </div>
                                                    <div className="flex gap-2">
                                                        <button
                                                            type="button"
                                                            onClick={() => promoEditing && guardarPromo(row, promoEditing)}
                                                            className="px-3 py-1.5 rounded-md text-xs font-medium"
                                                            style={{ background: 'var(--primary)', color: 'black' }}
                                                        >
                                                            Guardar
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setPromoEditing(null)}
                                                            className="px-3 py-1.5 rounded-md text-xs font-medium"
                                                            style={{ background: 'transparent', border: '1px solid var(--border)', color: 'var(--text-main)' }}
                                                        >
                                                            Cancelar
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                    </Fragment>
                                )
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {listas.last_page > 1 && (
                    <div className="flex items-center justify-between mt-4 text-sm">
                        <p style={{ color: 'var(--text-muted)' }}>
                            Mostrando {listas.from}–{listas.to} de {listas.total}
                        </p>
                        <div className="flex gap-1">
                            {listas.links.map((link, i) => (
                                link.url ? (
                                    <Link key={i} href={link.url}
                                        className={`px-3 py-1 rounded border text-xs transition-colors ${link.active ? 'border-amber-500 bg-amber-500 text-black font-medium' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}
                                        style={!link.active ? { borderColor: 'var(--border)', color: 'var(--text-main)' } : {}}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-3 py-1 rounded border text-xs opacity-40"
                                        style={{ borderColor: 'var(--border)', color: 'var(--text-muted)' }}
                                        dangerouslySetInnerHTML={{ __html: link.label }} />
                                )
                            ))}
                        </div>
                    </div>
                )}

                <p className="mt-3 text-xs" style={{ color: 'var(--text-muted)' }}>
                    Clic en cualquier celda para editar. Al salir de la fila se guarda automáticamente. Enter guarda, Escape cancela.
                </p>
            </div>
        </AppLayout>
    )
}
