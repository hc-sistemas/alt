import { useEffect, useRef, useState } from 'react'
import { Button } from '@/Components/ui/button'
import { Camera, ImagePlus, Trash2 } from 'lucide-react'
import { toastError } from '@/lib/toast'

interface Props {
    /** Vista previa actual: URL existente (imagen ya guardada) o data URL recién elegida. */
    valor: string | null
    /** Devuelve un data URL JPEG reducido, o null al quitar la imagen. */
    onChange: (dataUrl: string | null) => void
}

const MAX_LADO = 1280

/** Reduce la imagen a MAX_LADO px y la devuelve como JPEG (evita subir fotos de varios MB). */
function reducir(fuente: CanvasImageSource, ancho: number, alto: number): string {
    const escala = Math.min(1, MAX_LADO / Math.max(ancho, alto))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(ancho * escala)
    canvas.height = Math.round(alto * escala)
    canvas.getContext('2d')!.drawImage(fuente, 0, 0, canvas.width, canvas.height)
    return canvas.toDataURL('image/jpeg', 0.8)
}

export default function CapturaImagen({ valor, onChange }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null)
    const streamRef = useRef<MediaStream | null>(null)
    const [camaraActiva, setCamaraActiva] = useState(false)

    function detenerCamara() {
        streamRef.current?.getTracks().forEach(t => t.stop())
        streamRef.current = null
        setCamaraActiva(false)
    }

    useEffect(() => detenerCamara, [])

    async function iniciarCamara() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            streamRef.current = stream
            setCamaraActiva(true)
            // El <video> se monta al activar la cámara; se asigna en el siguiente tick.
            setTimeout(() => {
                if (videoRef.current) {
                    videoRef.current.srcObject = stream
                    void videoRef.current.play()
                }
            }, 0)
        } catch {
            toastError('No se pudo acceder a la cámara. Puede subir una imagen desde el equipo.')
        }
    }

    function tomarFoto() {
        const video = videoRef.current
        if (!video || !video.videoWidth) return
        onChange(reducir(video, video.videoWidth, video.videoHeight))
        detenerCamara()
    }

    function elegirArchivo(e: React.ChangeEvent<HTMLInputElement>) {
        const archivo = e.target.files?.[0]
        e.target.value = ''
        if (!archivo) return
        if (!archivo.type.startsWith('image/')) {
            toastError('El archivo debe ser una imagen.')
            return
        }
        const img = new Image()
        const url = URL.createObjectURL(archivo)
        img.onload = () => {
            onChange(reducir(img, img.naturalWidth, img.naturalHeight))
            URL.revokeObjectURL(url)
        }
        img.onerror = () => {
            URL.revokeObjectURL(url)
            toastError('No se pudo leer la imagen.')
        }
        img.src = url
    }

    return (
        <div className="space-y-3">
            {camaraActiva ? (
                <div className="space-y-2">
                    <video ref={videoRef} muted playsInline className="w-full max-w-sm rounded-md border" style={{ borderColor: 'var(--border)' }} />
                    <div className="flex gap-2">
                        <Button type="button" onClick={tomarFoto}>
                            <Camera className="w-4 h-4" />
                            Capturar
                        </Button>
                        <Button type="button" variant="ghost" onClick={detenerCamara}>Cancelar</Button>
                    </div>
                </div>
            ) : (
                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" onClick={iniciarCamara}>
                        <Camera className="w-4 h-4" />
                        Tomar foto
                    </Button>
                    <label className="inline-flex">
                        <input type="file" accept="image/*" className="hidden" onChange={elegirArchivo} />
                        <span className="inline-flex items-center gap-2 h-9 px-3 rounded-md border text-sm font-medium cursor-pointer"
                            style={{ borderColor: 'var(--border)', color: 'var(--text-main)' }}>
                            <ImagePlus className="w-4 h-4" />
                            Subir imagen
                        </span>
                    </label>
                    {valor && (
                        <Button type="button" variant="ghost" onClick={() => onChange(null)}>
                            <Trash2 className="w-4 h-4" />
                            Quitar
                        </Button>
                    )}
                </div>
            )}

            {valor && !camaraActiva && (
                <img src={valor} alt="Imagen del equipo" className="max-h-48 rounded-md border"
                    style={{ borderColor: 'var(--border)' }} />
            )}
        </div>
    )
}
