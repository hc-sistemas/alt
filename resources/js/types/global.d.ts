/// <reference types="vite/client" />

import { AxiosInstance } from 'axios'
import { route as routeFn } from 'ziggy-js'

declare global {
    interface Window {
        axios: AxiosInstance
    }
    const route: typeof routeFn
}

declare module '*.css' {
    const content: Record<string, string>
    export default content
}
