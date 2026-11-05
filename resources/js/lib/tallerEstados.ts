/**
 * Lista única de estados de una orden de trabajo (02-10 §2.11). Ingresos y Órdenes de Trabajo leen el MISMO
 * estado de la orden: así una orden nunca aparece distinta en una pantalla y en otra (TAL-05).
 * Lista confirmada por el usuario el 03/11/2026 (DEC-051).
 */
export type VarianteEstado = 'info' | 'warning' | 'default' | 'success' | 'secondary'

export const ESTADOS_ORDEN: Record<string, { label: string; variant: VarianteEstado }> = {
    pendiente:          { label: 'En cola',            variant: 'warning' },
    en_proceso:         { label: 'En proceso',         variant: 'info' },
    esperando_repuesto: { label: 'Esperando repuesto', variant: 'warning' },
    listo:              { label: 'Listo',              variant: 'success' },
    entregado:          { label: 'Entregado',          variant: 'secondary' },
    garantia:           { label: 'Garantía',           variant: 'secondary' },
    facturado:          { label: 'Facturado',          variant: 'success' },
}

export function estadoOrden(estado: string | null | undefined): { label: string; variant: VarianteEstado } {
    if (!estado) return { label: '—', variant: 'secondary' }
    return ESTADOS_ORDEN[estado] ?? { label: estado, variant: 'secondary' }
}
