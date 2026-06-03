import alertify from 'alertifyjs'
import 'alertifyjs/build/css/alertify.min.css'

alertify.set('notifier', 'position', 'bottom-left')
alertify.set('notifier', 'delay', '4')

export function toastExito(mensaje: string) {
    alertify.success(mensaje)
}

export function toastError(mensaje: string) {
    alertify.error(mensaje)
}

export function toastInfo(mensaje: string) {
    alertify.message(mensaje)
}
