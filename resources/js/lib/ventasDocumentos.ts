import { router } from '@inertiajs/react'
import Swal from 'sweetalert2'
import { isAxiosError } from 'axios'
import axios from '@/lib/axios'
import { toastError } from '@/lib/toast'

/**
 * Anulación con PIN de aprobación especial (mismo flujo que Facturas), para
 * Proformas y Prefacturas. `url` es la ruta PATCH de anulación del documento.
 */
export async function anularConPin(opts: { url: string; etiqueta: string; numero: string; aviso?: string }): Promise<void> {
    const { value: formValues } = await Swal.fire({
        title: `Anular ${opts.etiqueta}`,
        html:
            `<p style="margin-bottom:12px;font-size:14px;text-align:left;">` +
            `¿Desea anular la ${opts.etiqueta} <b>${opts.numero}</b>? ` +
            `${opts.aviso ?? ''} Requiere una aprobación especial.</p>` +
            `<input id="swal-codigo" type="password" class="swal2-input" placeholder="Código de aprobación">` +
            `<input id="swal-motivo" type="text" class="swal2-input" placeholder="Motivo de la anulación">`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Validar y anular',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        focusConfirm: false,
        preConfirm: () => {
            const codigo = (document.getElementById('swal-codigo') as HTMLInputElement | null)?.value ?? ''
            const motivo = (document.getElementById('swal-motivo') as HTMLInputElement | null)?.value ?? ''
            if (!codigo.trim() || !motivo.trim()) {
                Swal.showValidationMessage('Ingrese el código y el motivo.')
                return false
            }
            return { codigo, motivo }
        },
    })
    if (!formValues) return

    try {
        const { data } = await axios.post<{ valido: boolean; aprobacion_id?: number; mensaje?: string }>(
            route('ventas.aprobacion.validar'),
            { tipo: 'anulacion_factura', codigo: formValues.codigo, motivo: formValues.motivo },
        )

        if (!data.valido || !data.aprobacion_id) {
            void Swal.fire('Código incorrecto', data.mensaje ?? 'La aprobación no es válida.', 'error')
            return
        }

        router.patch(opts.url, { aprobacion_especial_id: data.aprobacion_id }, {
            preserveScroll: true,
            onError: errors => Object.values(errors).forEach(msg => { if (msg) toastError(msg) }),
        })
    } catch (error) {
        const mensaje = isAxiosError<{ mensaje?: string }>(error) && error.response
            ? error.response.data?.mensaje
            : undefined
        void Swal.fire('Error', mensaje ?? 'Error de conexión. Intente nuevamente.', 'error')
    }
}

/** Eliminación total (solo SuperAdmin): exige escribir el número del documento. */
export async function eliminarDocumento(opts: { url: string; etiqueta: string; numero: string; detalle: string }): Promise<void> {
    const { value: confirmacion } = await Swal.fire({
        title: `Eliminar ${opts.etiqueta}`,
        html:
            `<p style="margin-bottom:12px;font-size:14px;text-align:left;">` +
            `Se eliminará la ${opts.etiqueta} <b>${opts.numero}</b>${opts.detalle}. <b>No se puede deshacer.</b></p>` +
            `<p style="margin-bottom:6px;font-size:13px;text-align:left;">Escriba el número para confirmar:</p>`,
        input: 'text',
        inputPlaceholder: opts.numero,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Eliminar definitivamente',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626',
        inputValidator: v => (v?.trim() === opts.numero ? undefined : 'El número no coincide.'),
    })
    if (!confirmacion) return

    router.delete(opts.url, {
        data: { confirmacion },
        preserveScroll: true,
        onError: errors => Object.values(errors).forEach(msg => { if (msg) toastError(msg) }),
    })
}
