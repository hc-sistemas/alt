import { usePage } from '@inertiajs/react'
import type { PageProps } from '@/types'

export function usePermiso(modulo: string) {
    const { auth } = usePage<PageProps>().props

    // En una implementación real, los permisos vendrían del servidor via shared data
    // Por ahora, super_admin tiene acceso total
    const perfil = auth.user?.perfil

    const puede = (accion: 'ver' | 'crear' | 'editar' | 'eliminar' | 'anular'): boolean => {
        if (perfil === 'super_admin' || perfil === 'admin') return true
        // TODO: implementar lógica completa con permisos del servidor
        return accion === 'ver'
    }

    return { puede }
}
