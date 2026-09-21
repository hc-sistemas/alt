import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs))
}

export function formatMoneda(valor: number | string): string {
    const num = typeof valor === 'string' ? parseFloat(valor) : valor
    return new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(num || 0)
}

export function formatFecha(fecha: string | Date): string {
    const d = typeof fecha === 'string' ? new Date(fecha) : fecha
    return d.toLocaleDateString('es-EC', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    })
}

/** Fecha de hoy (hora local) en formato YYYY-MM-DD, lista para <input type="date"> */
export function fechaHoy(): string {
    return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}
