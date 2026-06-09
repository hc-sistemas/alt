import { toast } from 'react-toastify'

export const formatMoney = (n: number | string): string =>
    new Intl.NumberFormat('es-EC', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(n))

export const formatFecha = (fecha: string | null | undefined): string => {
    if (!fecha) return '—'
    // Maneja 'YYYY-MM-DD' y 'YYYY-MM-DDTHH:mm:ss...' (ISO con o sin timezone)
    const limpia = fecha.split('T')[0]
    const partes = limpia.split('-')
    if (partes.length !== 3) return fecha
    const [anio, mes, dia] = partes
    return `${dia}/${mes}/${anio}`
}

export const MESES: Record<number, string> = {
    1: 'Enero',    2: 'Febrero', 3: 'Marzo',     4: 'Abril',
    5: 'Mayo',     6: 'Junio',   7: 'Julio',     8: 'Agosto',
    9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre',
}

const S = { borderRadius: '14px', fontWeight: '600', color: '#fff' } as const

export const notify = {
    success: (msg: string) => toast.success(msg, {
        icon: () => '✅',
        style: { ...S, background: 'linear-gradient(135deg,#10b981,#059669)' },
        progressStyle: { background: 'rgba(255,255,255,0.4)' },
    }),
    warning: (msg: string) => toast.warning(msg, {
        icon: () => '⚠️',
        autoClose: 5000,
        style: { ...S, background: 'linear-gradient(135deg,#f59e0b,#d97706)' },
        progressStyle: { background: 'rgba(255,255,255,0.4)' },
    }),
    error: (msg: string) => toast.error(msg, {
        icon: () => '❌',
        autoClose: 6000,
        style: { ...S, background: 'linear-gradient(135deg,#ef4444,#dc2626)' },
        progressStyle: { background: 'rgba(255,255,255,0.4)' },
    }),
    info: (msg: string) => toast.info(msg, {
        icon: () => 'ℹ️',
        style: { ...S, background: 'linear-gradient(135deg,#3b82f6,#2563eb)' },
        progressStyle: { background: 'rgba(255,255,255,0.4)' },
    }),
}

const SWAL_CSS = `
    .swal-popup-custom {
        border-radius: 20px !important;
        padding: 28px !important;
        box-shadow: 0 25px 60px rgba(0,0,0,0.2) !important;
        max-width: 460px !important;
    }
    .swal-title-custom {
        font-size: 1.1rem !important;
        font-weight: 700 !important;
        color: #1f2937 !important;
    }
    .swal-confirm-custom, .swal-cancel-custom {
        border-radius: 10px !important;
        padding: 10px 20px !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
    }
    .swal-confirm-custom:hover { transform: translateY(-1px) !important; }
`

export function injectSwalStyles() {
    if (document.getElementById('swal-cont-styles')) return
    const s = document.createElement('style')
    s.id = 'swal-cont-styles'
    s.textContent = SWAL_CSS
    document.head.appendChild(s)
}

export const swalBase = {
    customClass: {
        popup:         'swal-popup-custom',
        title:         'swal-title-custom',
        confirmButton: 'swal-confirm-custom',
        cancelButton:  'swal-cancel-custom',
    },
    didOpen: injectSwalStyles,
}
