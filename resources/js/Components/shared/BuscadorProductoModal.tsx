import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Search, X } from 'lucide-react'

export interface Resultado {
    id: number
    codigo: string
    nombre: string
    marca: string | null
    requiere_serie: boolean
}

interface Props {
    onSelect: (producto: Resultado) => void
    placeholder?: string
    disabled?: boolean
}

export default function BuscadorProductoModal({ onSelect, placeholder, disabled }: Props) {
    const [queryInput, setQueryInput]   = useState('')
    const [resultados, setResultados]   = useState<Resultado[]>([])
    const [buscando, setBuscando]       = useState(false)
    const [error, setError]             = useState('')
    const [abierto, setAbierto]         = useState(false)
    const [filtroModal, setFiltroModal] = useState('')

    const inputExternoRef = useRef<HTMLInputElement>(null)
    const inputModalRef   = useRef<HTMLInputElement>(null)

    // Filtrado local dentro del modal
    const resultadosFiltrados = filtroModal.trim()
        ? resultados.filter(r =>
            r.codigo.toLowerCase().includes(filtroModal.toLowerCase()) ||
            r.nombre.toLowerCase().includes(filtroModal.toLowerCase()) ||
            (r.marca ?? '').toLowerCase().includes(filtroModal.toLowerCase())
          )
        : resultados

    async function buscar() {
        const q = queryInput.trim()
        if (!q) return
        setError('')
        setBuscando(true)
        try {
            const res = await fetch(
                route('inventario.productos.buscar') + `?q=${encodeURIComponent(q)}`,
                { headers: { Accept: 'application/json' } }
            )
            const json = await res.json()
            const items: Resultado[] = json.resultados ?? []

            if (items.length === 0) {
                setError('Sin coincidencias')
            } else if (items.length === 1) {
                onSelect(items[0])
                setQueryInput('')
            } else {
                setResultados(items)
                setFiltroModal('')
                setAbierto(true)
            }
        } catch {
            setError('Error al buscar')
        } finally {
            setBuscando(false)
        }
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter') {
            e.preventDefault()
            buscar()
        }
    }

    function seleccionar(r: Resultado) {
        onSelect(r)
        cerrarModal()
        setQueryInput('')
    }

    function cerrarModal() {
        setAbierto(false)
        setResultados([])
        setFiltroModal('')
    }

    function handleOverlayKey(e: React.KeyboardEvent) {
        if (e.key === 'Escape') cerrarModal()
    }

    // Autofocus al abrir modal
    useEffect(() => {
        if (abierto) {
            const t = setTimeout(() => inputModalRef.current?.focus(), 50)
            return () => clearTimeout(t)
        }
    }, [abierto])

    // Bloquear scroll del body mientras el modal está abierto
    useEffect(() => {
        if (abierto) {
            document.body.style.overflow = 'hidden'
            return () => { document.body.style.overflow = '' }
        }
    }, [abierto])

    const modal = abierto ? (
        <>
            {/* Overlay */}
            <div
                className="fixed inset-0"
                style={{ background: 'rgba(0,0,0,0.5)', zIndex: 50 }}
                onClick={cerrarModal}
            />

            {/* Ventana modal */}
            <div
                className="fixed inset-0 flex items-center justify-center p-4"
                style={{ zIndex: 51 }}
                onKeyDown={handleOverlayKey}
            >
                <div
                    className="w-full rounded-xl shadow-xl flex flex-col"
                    style={{
                        maxWidth: 600,
                        background: 'var(--bg-card)',
                        border: '1px solid var(--border)',
                    }}
                >
                    {/* Header */}
                    <div
                        className="flex items-center justify-between px-4 py-3 shrink-0"
                        style={{ borderBottom: '1px solid var(--border)' }}
                    >
                        <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Seleccionar Producto
                        </span>
                        <button
                            type="button"
                            onClick={cerrarModal}
                            className="p-1 rounded-md transition-colors"
                            style={{ color: 'var(--text-muted)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(0,0,0,0.08)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    {/* Input de refinamiento */}
                    <div className="px-4 py-3 shrink-0" style={{ borderBottom: '1px solid var(--border)' }}>
                        <div className="relative">
                            <Search
                                className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none"
                                style={{ color: 'var(--text-muted)' }}
                            />
                            <input
                                ref={inputModalRef}
                                value={filtroModal}
                                onChange={e => setFiltroModal(e.target.value)}
                                placeholder="Filtrar resultados..."
                                className="w-full h-9 pl-9 pr-3 rounded-md border text-sm outline-none"
                                style={{
                                    background: 'var(--bg-main)',
                                    borderColor: 'var(--border)',
                                    color: 'var(--text-main)',
                                }}
                            />
                        </div>
                    </div>

                    {/* Tabla de resultados */}
                    <div style={{ maxHeight: 350, overflowY: 'auto' }}>
                        {resultadosFiltrados.length > 0 ? (
                            <table className="w-full text-sm">
                                <thead className="sticky top-0" style={{ background: 'var(--bg-card)' }}>
                                    <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                        {['Código', 'Nombre', 'Marca'].map(h => (
                                            <th
                                                key={h}
                                                className="text-left px-4 py-2 text-xs font-medium"
                                                style={{ color: 'var(--text-muted)' }}
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {resultadosFiltrados.map(r => (
                                        <tr
                                            key={r.id}
                                            onClick={() => seleccionar(r)}
                                            className="cursor-pointer transition-colors"
                                            style={{ borderBottom: '1px solid var(--border)' }}
                                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.08)')}
                                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                        >
                                            <td className="px-4 py-2.5 font-mono text-xs font-semibold whitespace-nowrap" style={{ color: 'var(--primary)' }}>
                                                {r.codigo}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                                {r.nombre}
                                            </td>
                                            <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-muted)' }}>
                                                {r.marca ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <div className="px-4 py-8 text-center text-xs" style={{ color: 'var(--text-muted)' }}>
                                Sin coincidencias
                            </div>
                        )}
                    </div>

                    {/* Footer */}
                    <div
                        className="px-4 py-2 text-xs shrink-0"
                        style={{ borderTop: '1px solid var(--border)', color: 'var(--text-muted)' }}
                    >
                        {resultadosFiltrados.length} resultado{resultadosFiltrados.length !== 1 ? 's' : ''} encontrado{resultadosFiltrados.length !== 1 ? 's' : ''}
                    </div>
                </div>
            </div>
        </>
    ) : null

    return (
        <>
            <div className="flex gap-2">
                {/* Input principal */}
                <div className="relative flex-1">
                    <Search
                        className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none"
                        style={{ color: 'var(--text-muted)' }}
                    />
                    <input
                        ref={inputExternoRef}
                        value={queryInput}
                        onChange={e => { setQueryInput(e.target.value); setError('') }}
                        onKeyDown={handleKeyDown}
                        placeholder={placeholder ?? 'Buscar por código, nombre o marca...'}
                        disabled={disabled || buscando}
                        className="w-full h-9 pl-9 pr-3 rounded-md border text-sm outline-none"
                        style={{
                            background: 'var(--bg-card)',
                            borderColor: error ? '#ef4444' : 'var(--border)',
                            color: 'var(--text-main)',
                            opacity: disabled ? 0.5 : 1,
                            cursor: disabled ? 'not-allowed' : 'text',
                        }}
                    />
                </div>

                {/* Botón buscar */}
                <button
                    type="button"
                    onClick={buscar}
                    disabled={disabled || buscando || !queryInput.trim()}
                    className="h-9 px-3 rounded-md border text-sm font-medium transition-colors shrink-0"
                    style={{
                        background: 'var(--primary)',
                        borderColor: 'var(--primary)',
                        color: '#000',
                        opacity: disabled || buscando || !queryInput.trim() ? 0.5 : 1,
                        cursor: disabled || buscando || !queryInput.trim() ? 'not-allowed' : 'pointer',
                    }}
                >
                    {buscando ? '...' : 'Buscar'}
                </button>
            </div>

            {/* Error inline */}
            {error && (
                <p className="text-xs mt-1" style={{ color: '#ef4444' }}>
                    {error}
                </p>
            )}

            {createPortal(modal, document.body)}
        </>
    )
}
