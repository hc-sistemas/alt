import { AxiosInstance } from 'axios'
import { route as routeFn } from 'ziggy-js'

declare global {
    interface Window {
        axios: AxiosInstance
    }
    const route: typeof routeFn
}
