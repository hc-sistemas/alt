import type { AxiosInstance } from 'axios'

declare global {
    interface Window {
        axios: AxiosInstance
    }
}

/**
 * Reexporta la instancia global configurada en resources/js/bootstrap.js.
 *
 * Usar SIEMPRE este import — nunca `import axios from 'axios'` directo ni
 * `fetch()` con el token leído de <meta name="csrf-token">. Ese token queda
 * congelado en la carga inicial de la página y, tras cualquier regeneración
 * de sesión (login, logout), queda obsoleto: la petición falla con
 * "CSRF token mismatch" (419). window.axios ya adjunta X-XSRF-TOKEN
 * automáticamente leyendo la cookie XSRF-TOKEN, que Laravel reemite fresca
 * en cada respuesta — no hace falta gestionar el token a mano.
 *
 * Ver bootstrap.js para el detalle del fix original (commit 91d1b61).
 *
 * IMPORTANTE: no exportar `window.axios` directo (`export default
 * window.axios`). Este proyecto carga las páginas de Inertia con
 * `import.meta.glob(..., { eager: true })` (ver app.tsx), lo que agrupa
 * todas las páginas en el mismo grafo de módulos que se evalúa al cargar
 * el bundle — y el orden de evaluación de Rollup no garantiza que
 * bootstrap.js corra antes de que este archivo se evalúe. Si se captura
 * `window.axios` en ese momento, puede quedar `undefined` para siempre y
 * cualquier código que use este import revienta con
 * "Cannot read properties of undefined (reading 'post'/'patch'/...)".
 * El Proxy relee `window.axios` en cada llamada, ya en tiempo de uso real
 * (mucho después de que bootstrap.js corrió), evitando el problema.
 */
const axios = new Proxy({} as AxiosInstance, {
    get(_target, prop) {
        const value = Reflect.get(window.axios, prop)
        // Los métodos de axios (get/post/patch/...) dependen de su propio `this`
        // interno (defaults, interceptors) — hay que ligarlos a la instancia real,
        // no al Proxy, o revientan al ejecutarse.
        return typeof value === 'function' ? value.bind(window.axios) : value
    },
})

export default axios
