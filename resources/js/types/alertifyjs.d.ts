declare module 'alertifyjs' {
    interface AlertifyStatic {
        success(message: string): void
        error(message: string): void
        warning(message: string): void
        message(message: string): void
        set(setting: string, key: string, value: string): void
    }
    const alertify: AlertifyStatic
    export default alertify
}
