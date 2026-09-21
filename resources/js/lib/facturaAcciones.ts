import Swal from 'sweetalert2'
import { isAxiosError } from 'axios'
import axios from '@/lib/axios'
import { toastError, toastExito, toastInfo } from '@/lib/toast'

/** Color por acción (texto + fondo al pasar el mouse). Clases completas para que Tailwind las detecte. */
export const ACCION_CLS = {
    // Azul institucional del SRI (más claro en el tema oscuro para que se lea)
    sri:    { texto: 'text-[#1E4FA3] dark:text-[#6B9BFF]', fondo: 'hover:bg-[#1E4FA3]/10 dark:hover:bg-[#6B9BFF]/10' },
    correo: { texto: 'text-violet-500', fondo: 'hover:bg-violet-500/10' },
    xml:    { texto: 'text-teal-500',   fondo: 'hover:bg-teal-500/10' },
    ride:   { texto: 'text-red-500',    fondo: 'hover:bg-red-500/10' },
} as const

/**
 * Qué acciones corresponden a una factura según su estado:
 *  - Pendiente / rechazada por el SRI: Enviar SRI, XML, RIDE (y Anular).
 *  - Autorizada:                        Correo, XML, RIDE (y Anular).
 *  - Anulada:                           solo XML y RIDE.
 * "Ver" y "Anular" (mismo día + permiso) se resuelven aparte en cada pantalla.
 */
export function accionesFactura(f: { estado: string; estado_sri: string }) {
    const anulada = f.estado === 'anulada' || f.estado_sri === 'anulada'
    const activa = !anulada && f.estado === 'activa'
    return {
        sri: activa && f.estado_sri !== 'autorizada',
        correo: activa && f.estado_sri === 'autorizada',
        xml: true,
        ride: true,
    }
}

/** Envío al SRI: el ciclo real (XML + firma + webservice) aún no está implementado en el servidor. */
export async function enviarFacturaSri(facturaId: number): Promise<void> {
    try {
        const { data } = await axios.post<{ message?: string }>(route('ventas.facturas.enviar-sri', facturaId))
        toastInfo(data.message ?? 'Funcionalidad SRI pendiente')
    } catch {
        toastError('No se pudo contactar al servidor.')
    }
}

/** Pide/confirma el correo y envía la factura en PDF al cliente. */
export async function enviarFacturaPorCorreo(
    facturaId: number,
    numero: string,
    emailInicial: string | null | undefined,
): Promise<boolean> {
    const { value: email } = await Swal.fire({
        title: 'Enviar por correo',
        text: `Factura ${numero}`,
        input: 'email',
        inputValue: emailInicial ?? '',
        inputPlaceholder: 'correo@cliente.com',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#F59E0B',
        inputValidator: v => (!v ? 'Ingrese un correo.' : undefined),
    })
    if (!email) return false

    try {
        const { data } = await axios.post<{ mensaje?: string }>(
            route('ventas.facturas.enviar-correo', facturaId),
            { email },
        )
        toastExito(data.mensaje ?? 'Factura enviada.')
        return true
    } catch (error) {
        const mensaje = isAxiosError<{ mensaje?: string; message?: string }>(error) && error.response
            ? (error.response.data?.mensaje ?? error.response.data?.message)
            : undefined
        toastError(mensaje ?? 'No se pudo enviar el correo.')
        return false
    }
}
