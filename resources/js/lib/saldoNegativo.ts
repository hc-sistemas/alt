import Swal from 'sweetalert2'

/** Mensaje de advertencia si el servidor detuvo un egreso por dejar un banco o caja en negativo (SaldoBancario). */
export function advertenciaSaldo(page: { props: unknown }): string | null {
    const flash = (page.props as { flash?: { saldo_negativo?: string | null } }).flash
    return flash?.saldo_negativo ?? null
}

/** Pide al usuario confirmar un egreso que deja el saldo en negativo (02-06 §2.8, DEC-041 pendiente). */
export async function confirmarSaldoNegativo(mensaje: string): Promise<boolean> {
    const res = await Swal.fire({
        title: 'El saldo quedaría en negativo',
        text: `${mensaje} ¿Desea continuar de todos modos?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar',
    })
    return res.isConfirmed
}
