import { Head, Link, router, usePage } from '@inertiajs/react'
import { useEffect, useRef, useState } from 'react'
import Swal from 'sweetalert2'
import AppLayout from '@/Layouts/AppLayout'
import PageHeader from '@/Components/shared/PageHeader'
import { Input } from '@/Components/ui/input'
import { Search, FileSpreadsheet, Upload } from 'lucide-react'
import { toastExito, toastError } from '@/lib/toast'
import { cn } from '@/lib/utils'
import type { PaginatedData, PageProps } from '@/types'

interface ListaPrecioRow {
    producto_id: number
    codigo: string
    nombre: string
    marca_nombre: string
    pvp_base: number
    pvd_base: number
    lista_pvp_precio: number | null
    lista_pvp_descuento_max: number | null
    lista_pvd_precio: number | null
    lista_pvd_descuento_max: number | null
    vigencia_desde: string | null
    vigencia_hasta: string | null
}

interface Marca { id: number; nombre: string }
interface Categoria { id: number; nombre: string }

interface Props extends PageProps {
    listas: PaginatedData<ListaPrecioRow>
    filters: { search?: string; marca_id?: string; categoria_id?: string }
    marcas: Marca[]
    categorias: Categoria[]
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
    vigencia_desde: string
    vigencia_hasta: string
}

function fmt(val: number | null | undefined): string {
    if (val == null) return ''
    return Number(val).toFixed(2)
}

function fmtDate(val: string | null | undefined): string {
    if (!val) return ''
    return val.slice(0, 10)
}

export default function ListasPrecioIndex() {
    const { listas, filters, marcas, categorias } = usePage<Props>().props

    const [search, setSearch]   = useState(filters.search ?? '')
    const [marcaId, setMarcaId] = useState(filters.marca_id ?? '')
    const [catId, setCatId]     = useState(filters.categoria_id ?? '')
    const [editing, setEditing] = useState<EditState | null>(null)

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
                description="Precios especiales PVP/PVD por producto. Clic en una celda para editar."
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
                                    'Código', 'Nombre', 'Marca',
                                    'PVP Base', 'PVP Lista', 'Desc. PVP%',
                                    'PVD Base', 'PVD Lista', 'Desc. PVD%',
                                    'Vigencia Desde', 'Vigencia Hasta',
                                ].map(h => (
                                    <th key={h}
                                        className="text-left px-2 py-3 font-medium text-xs uppercase tracking-wider whitespace-nowrap"
                                        style={{ color: 'var(--text-muted)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {listas.data.length === 0 ? (
                                <tr>
                                    <td colSpan={11} className="text-center py-16" style={{ color: 'var(--text-muted)' }}>
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
                                return (
                                    <tr
                                        key={row.producto_id}
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
                                        {/* Marca */}
                                        <td className={tdBase} style={{ color: 'var(--text-muted)' }}>
                                            {row.marca_nombre}
                                        </td>

                                        {/* PVP Base — solo lectura */}
                                        <td className={tdMuted} style={{ color: 'var(--text-muted)' }}>
                                            {Number(row.pvp_base).toFixed(2)}
                                        </td>

                                        {active ? (
                                            <>
                                                {editCell('pvp',          { type: 'number', step: 0.01, min: 0,       onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('descuento_pvp', { type: 'number', step: 0.01, min: 0, max: 100, onBlur: blurHandler, onFocus: focusHandler })}
                                                {/* PVD Base — solo lectura intercalada */}
                                                <td className={tdMuted} style={{ color: 'var(--text-muted)' }}>
                                                    {Number(row.pvd_base).toFixed(2)}
                                                </td>
                                                {editCell('pvd',          { type: 'number', step: 0.01, min: 0,       onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('descuento_pvd', { type: 'number', step: 0.01, min: 0, max: 100, onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('vigencia_desde', { type: 'date', onBlur: blurHandler, onFocus: focusHandler })}
                                                {editCell('vigencia_hasta', { type: 'date', onBlur: blurHandler, onFocus: focusHandler })}
                                            </>
                                        ) : (
                                            <>
                                                <td
                                                    className={cn(tdMuted, 'cursor-pointer hover:underline')}
                                                    style={{ color: row.lista_pvp_precio != null ? 'var(--primary)' : 'var(--text-muted)' }}
                                                    onClick={() => startEdit(row)}
                                                    title="Clic para editar"
                                                >
                                                    {row.lista_pvp_precio != null ? Number(row.lista_pvp_precio).toFixed(2) : '—'}
                                                </td>
                                                <td
                                                    className={cn(tdMuted, 'cursor-pointer hover:underline')}
                                                    style={{ color: 'var(--text-muted)' }}
                                                    onClick={() => startEdit(row)}
                                                    title="Clic para editar"
                                                >
                                                    {row.lista_pvp_descuento_max != null ? Number(row.lista_pvp_descuento_max).toFixed(2) : '—'}
                                                </td>

                                                {/* PVD Base */}
                                                <td className={tdMuted} style={{ color: 'var(--text-muted)' }}>
                                                    {Number(row.pvd_base).toFixed(2)}
                                                </td>

                                                <td
                                                    className={cn(tdMuted, 'cursor-pointer hover:underline')}
                                                    style={{ color: row.lista_pvd_precio != null ? 'var(--primary)' : 'var(--text-muted)' }}
                                                    onClick={() => startEdit(row)}
                                                    title="Clic para editar"
                                                >
                                                    {row.lista_pvd_precio != null ? Number(row.lista_pvd_precio).toFixed(2) : '—'}
                                                </td>
                                                <td
                                                    className={cn(tdMuted, 'cursor-pointer hover:underline')}
                                                    style={{ color: 'var(--text-muted)' }}
                                                    onClick={() => startEdit(row)}
                                                    title="Clic para editar"
                                                >
                                                    {row.lista_pvd_descuento_max != null ? Number(row.lista_pvd_descuento_max).toFixed(2) : '—'}
                                                </td>

                                                <td
                                                    className={cn(tdBase, 'cursor-pointer hover:underline')}
                                                    style={{ color: 'var(--text-muted)' }}
                                                    onClick={() => startEdit(row)}
                                                >
                                                    {fmtDate(row.vigencia_desde) || '—'}
                                                </td>
                                                <td
                                                    className={cn(tdBase, 'cursor-pointer hover:underline')}
                                                    style={{ color: 'var(--text-muted)' }}
                                                    onClick={() => startEdit(row)}
                                                >
                                                    {fmtDate(row.vigencia_hasta) || '—'}
                                                </td>
                                            </>
                                        )}
                                    </tr>
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
