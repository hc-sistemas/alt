import React, { useState, useEffect } from 'react'
import { createPortal } from 'react-dom'
import { X, Search } from 'lucide-react'
import type { Cliente } from '@/types'

interface Props {
    coincidencias: Cliente[]
    abierto: boolean
    onCerrar: () => void
    onSelect: (cliente: Cliente) => void
}

export default function BuscadorClienteModal({ coincidencias, abierto, onCerrar, onSelect }: Props) {
    const [busqueda, setBusqueda] = useState('')

    useEffect(() => {
        if (abierto) setBusqueda('')
    }, [abierto])

    if (!abierto) return null

    const filtrados = busqueda.trim()
        ? coincidencias.filter(c =>
            c.identificacion.toLowerCase().includes(busqueda.toLowerCase()) ||
            c.razon_social.toLowerCase().includes(busqueda.toLowerCase())
        )
        : coincidencias

    const modal = (
        <>
            <div
                className="fixed inset-0"
                style={{ background: 'rgba(0,0,0,0.5)', zIndex: 50 }}
                onClick={onCerrar}
            />
            <div
                className="fixed inset-0 flex items-center justify-center p-4"
                style={{ zIndex: 51 }}
                onKeyDown={e => { if (e.key === 'Escape') onCerrar() }}
            >
                <div
                    className="w-full rounded-xl shadow-xl flex flex-col"
                    style={{ maxWidth: 580, background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                >
                    {/* Header */}
                    <div
                        className="flex items-center justify-between px-4 py-3 shrink-0"
                        style={{ borderBottom: '1px solid var(--border)' }}
                    >
                        <span className="text-sm font-semibold" style={{ color: 'var(--text-main)' }}>
                            Seleccionar Cliente
                        </span>
                        <button
                            type="button"
                            onClick={onCerrar}
                            className="p-1 rounded-md transition-colors"
                            style={{ color: 'var(--text-muted)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(0,0,0,0.08)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    {/* Búsqueda interna */}
                    <div className="px-4 py-2 shrink-0" style={{ borderBottom: '1px solid var(--border)' }}>
                        <div className="relative">
                            <Search
                                className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none"
                                style={{ color: 'var(--text-muted)' }}
                            />
                            <input
                                type="text"
                                autoFocus
                                className="w-full h-8 pl-9 pr-3 text-sm rounded-md border focus:outline-none"
                                style={{
                                    background: 'var(--bg-main)',
                                    borderColor: 'var(--border)',
                                    color: 'var(--text-main)',
                                }}
                                placeholder="Filtrar por RUC o nombre..."
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Lista */}
                    <div style={{ maxHeight: 360, overflowY: 'auto' }}>
                        <table className="w-full text-sm">
                            <thead className="sticky top-0" style={{ background: 'var(--bg-card)' }}>
                                <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                    {['Identificación', 'Razón Social'].map(h => (
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
                                {filtrados.map(c => (
                                    <tr
                                        key={c.id}
                                        onClick={() => { onSelect(c); onCerrar() }}
                                        className="cursor-pointer transition-colors"
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = 'rgba(245,158,11,0.08)')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                    >
                                        <td
                                            className="px-4 py-2.5 font-mono text-xs font-semibold whitespace-nowrap"
                                            style={{ color: 'var(--primary)' }}
                                        >
                                            {c.identificacion}
                                        </td>
                                        <td className="px-4 py-2.5 text-xs" style={{ color: 'var(--text-main)' }}>
                                            {c.razon_social}
                                        </td>
                                    </tr>
                                ))}
                                {filtrados.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={2}
                                            className="px-4 py-4 text-center text-xs"
                                            style={{ color: 'var(--text-muted)' }}
                                        >
                                            Sin resultados
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Footer */}
                    <div
                        className="flex items-center justify-between px-4 py-2 shrink-0"
                        style={{ borderTop: '1px solid var(--border)' }}
                    >
                        <span className="text-xs" style={{ color: 'var(--text-muted)' }}>
                            {filtrados.length} coincidencia{filtrados.length !== 1 ? 's' : ''}
                        </span>
                        <button
                            type="button"
                            onClick={onCerrar}
                            className="text-xs px-3 py-1.5 rounded-md border transition-colors"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-muted)', background: 'transparent' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'rgba(0,0,0,0.05)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </>
    )

    return createPortal(modal, document.body)
}
