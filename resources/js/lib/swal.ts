import Swal from 'sweetalert2'

export function confirmarEliminar(nombre: string): Promise<boolean> {
    const isDark = document.documentElement.classList.contains('dark')

    return Swal.fire({
        title: '¿Eliminar registro?',
        text: `¿Confirmas eliminar "${nombre}"? Esta acción no se puede deshacer.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',

        confirmButtonColor: '#F59E0B',
        cancelButtonColor: '#64748B',
        iconColor: '#F59E0B',

        background: isDark ? '#1E293B' : '#FFFFFF',
        color: isDark ? '#F1F5F9' : '#0F172A',

        borderRadius: '12px',

        customClass: {
            popup: 'swal-altamira',
            confirmButton: 'swal-btn-confirm',
            cancelButton: 'swal-btn-cancel',
        },
    }).then(result => result.isConfirmed)
}
