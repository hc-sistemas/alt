import { useEffect, useRef, useState } from 'react'
import { X, Printer, Download, Loader2 } from 'lucide-react'

interface PdfPreviewModalProps {
    abierto: boolean
    onCerrar: () => void
    url: string
    titulo: string
    nombreDescarga: string
}

export default function PdfPreviewModal({ abierto, onCerrar, url, titulo, nombreDescarga }: PdfPreviewModalProps) {
    const iframeRef = useRef<HTMLIFrameElement>(null)
    const [cargando, setCargando] = useState(true)

    useEffect(() => {
        if (abierto) setCargando(true)
    }, [abierto, url])

    useEffect(() => {
        if (!abierto) return
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') onCerrar()
        }
        document.addEventListener('keydown', onKey)
        return () => document.removeEventListener('keydown', onKey)
    }, [abierto, onCerrar])

    if (!abierto) return null

    function imprimir() {
        iframeRef.current?.contentWindow?.print()
    }

    function descargar() {
        const sep = url.includes('?') ? '&' : '?'
        window.open(url + sep + 'download=1&nombre=' + encodeURIComponent(nombreDescarga), '_blank')
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/70" onClick={onCerrar} />
            <div
                className="relative flex flex-col rounded-xl shadow-2xl overflow-hidden"
                style={{
                    width: '90vw',
                    height: '90vh',
                    background: 'var(--bg-card)',
                    border: '1px solid var(--border)',
                }}
            >
                {/* Header */}
                <div
                    className="flex items-center justify-between px-4 py-3 shrink-0"
                    style={{ borderBottom: '1px solid var(--border)' }}
                >
                    <span className="font-semibold text-sm" style={{ color: 'var(--text-main)' }}>
                        {titulo}
                    </span>
                    <div className="flex items-center gap-2">
                        {url && (
                            <>
                                <button
                                    onClick={imprimir}
                                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                                    style={{ background: 'var(--primary)', color: 'white', transition: 'background 0.2s' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = 'var(--primary-hover)')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'var(--primary)')}
                                >
                                    <Printer className="w-4 h-4" />
                                    Imprimir
                                </button>
                                <button
                                    onClick={descargar}
                                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                                    style={{ background: '#2563EB', color: 'white', transition: 'background 0.2s' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = '#1D4ED8')}
                                    onMouseLeave={e => (e.currentTarget.style.background = '#2563EB')}
                                >
                                    <Download className="w-4 h-4" />
                                    Descargar
                                </button>
                            </>
                        )}
                        <button
                            onClick={onCerrar}
                            className="p-1.5 rounded-lg"
                            style={{ color: 'var(--text-muted)', transition: 'background 0.2s' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-main)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                            title="Cerrar"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {/* Body */}
                <div className="flex-1 relative overflow-hidden">
                    {!url ? (
                        <div className="flex items-center justify-center h-full">
                            <p className="text-sm" style={{ color: 'var(--text-muted)' }}>
                                Reporte no disponible aún
                            </p>
                        </div>
                    ) : (
                        <>
                            {cargando && (
                                <div
                                    className="absolute inset-0 flex items-center justify-center z-10"
                                    style={{ background: 'var(--bg-card)' }}
                                >
                                    <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--primary)' }} />
                                </div>
                            )}
                            <iframe
                                ref={iframeRef}
                                src={url}
                                className="w-full h-full border-0"
                                onLoad={() => setCargando(false)}
                                title={titulo}
                            />
                        </>
                    )}
                </div>
            </div>
        </div>
    )
}
