import Swal from 'sweetalert2'
import { router } from '@inertiajs/react'
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

/** Firma y envía la factura al SRI (recepción + autorización) y refresca la pantalla con el resultado. */
export async function enviarFacturaSri(facturaId: number): Promise<void> {
    interface RespuestaSri { message?: string; estado?: string; errores?: string[] }
    toastInfo('Consultando y enviando al SRI…')
    try {
        const { data } = await axios.post<RespuestaSri>(
            route('ventas.facturas.enviar-sri', facturaId),
            {},
            { timeout: 120000 },
        )
        toastExito(data.message ?? 'Factura autorizada.')
    } catch (error) {
        const resp = isAxiosError<RespuestaSri>(error) ? error.response : undefined
        if (!resp) {
            toastError('No se pudo contactar al servidor. Revise su conexión e intente de nuevo.')
        } else {
            await mostrarErroresSri(resp.data?.message ?? 'No se pudo completar el envío al SRI.', resp.data?.errores ?? [], resp.status >= 500)
        }
    }
    router.reload()
}

/** Muestra el motivo y la lista de puntos a corregir (validación previa, firma, conexión o rechazo del SRI). */
async function mostrarErroresSri(titulo: string, errores: string[], inesperado: boolean): Promise<void> {
    const esc = (t: string) => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    const lista = errores.length
        ? `<ul style="text-align:left;margin-top:8px;padding-left:18px;list-style:disc;font-size:13px;max-height:320px;overflow:auto">${errores.map(e => `<li style="margin-bottom:4px">${esc(e)}</li>`).join('')}</ul>`
        : ''
    await Swal.fire({
        icon: inesperado ? 'error' : 'warning',
        title: 'Factura no enviada',
        html: `<div style="font-size:14px">${esc(titulo)}</div>${lista}`,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#F59E0B',
        width: 640,
    })
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
