import { Search, FileSpreadsheet } from 'lucide-react'
import { Input } from '@/Components/ui/input'

interface SearchConfig {
    value: string
    onChange: (value: string) => void
    onSearch: () => void
    placeholder?: string
}

interface Props {
    children?: React.ReactNode
    search?: SearchConfig
    onExport?: () => void
    /** Alternativa a onExport para exportaciones vía enlace directo (querystring), en vez de un handler JS. */
    exportHref?: string
    exportDisabled?: boolean
    /** Botones adicionales (ej. PDF) que se renderizan al final, después del de exportar. */
    extraActions?: React.ReactNode
}

// Fila de filtros estándar: los filtros propios de cada página van como
// children (selects "píldora" con la clase input-field), y este componente
// solo estandariza el contenedor + el grupo de búsqueda (input con lupa a
// la izquierda + botón cuadrado dorado) + el botón cuadrado verde de
// exportar, que se repiten idénticos en casi todos los módulos.
export default function FilterToolbar({ children, search, onExport, exportHref, exportDisabled, extraActions }: Props) {
    return (
        <div className="flex items-center gap-3 mb-4 flex-wrap">
            {children}

            {search && (
                <div className="flex shrink-0 ml-auto" role="group">
                    <div className="relative">
                        <Search className="absolute left-3 top-2.5 w-4 h-4" style={{ color: 'var(--text-muted)' }} />
                        <Input
                            value={search.value}
                            onChange={e => search.onChange(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && search.onSearch()}
                            placeholder={search.placeholder ?? 'Buscar...'}
                            className="pl-9 w-52 rounded-r-none border-r-0"
                        />
                    </div>
                    <button
                        type="button"
                        className="flex items-center justify-center w-9 h-9 rounded-r-md border text-sm font-medium shrink-0"
                        style={{ background: 'var(--primary)', color: 'black', borderColor: 'var(--primary)' }}
                        onClick={search.onSearch}
                        title="Buscar"
                    >
                        <Search className="w-4 h-4" />
                    </button>
                </div>
            )}

            {onExport && (
                <button
                    type="button"
                    className={`flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0 ${!search ? 'ml-auto' : ''}`}
                    style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A', transition: 'background 0.2s' }}
                    onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                    onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                    onClick={onExport}
                    disabled={exportDisabled}
                    title="Excel"
                >
                    <FileSpreadsheet className="w-4 h-4" />
                </button>
            )}

            {exportHref && (
                <a
                    href={exportHref}
                    className={`flex items-center justify-center w-9 h-9 rounded-md text-sm font-medium border shrink-0 ${!search ? 'ml-auto' : ''}`}
                    style={{ background: '#16A34A', color: 'white', borderColor: '#16A34A', transition: 'background 0.2s' }}
                    onMouseEnter={e => (e.currentTarget.style.background = '#15803D')}
                    onMouseLeave={e => (e.currentTarget.style.background = '#16A34A')}
                    title="Excel"
                >
                    <FileSpreadsheet className="w-4 h-4" />
                </a>
            )}

            {extraActions}
        </div>
    )
}
