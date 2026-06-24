import { usePage } from '@inertiajs/react'
import type { PageProps } from '@/types'

export function usePermiso(modulo: string) {
    const { permisos } = usePage<PageProps>().props

    const puede = (accion: 'ver' | 'crear' | 'editar' | 'eliminar' | 'anular'): boolean => {
        if (permisos === '*') return true
        const p = permisos[modulo]
        if (!p) return false
        return p[accion] === true
    }

    return { puede }
}
