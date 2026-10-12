// Estilo del header por empresa (diseño "Header por empresa"): degradado, línea
// inferior de 3px y punto de color de la empresa activa. El Topbar y el bloque
// del logo del Sidebar lo comparten para verse como una sola barra continua.
export interface EstiloHeaderEmpresa {
    fondo: string
    linea: string
    punto: string
    etiqueta: string
}

export const RUC_IMPORT = '1755265848001'
export const RUC_MATRIZ = '1711293454001'

export function estiloHeaderEmpresa(ruc?: string | null): EstiloHeaderEmpresa | null {
    if (ruc === RUC_IMPORT) {
        return {
            fondo: 'linear-gradient(180deg, #1F1F24 0%, #0D0D10 100%)',
            linea: '#FFD100',
            punto: '#FFD100',
            etiqueta: '#C9C9D1',
        }
    }
    if (ruc === RUC_MATRIZ) {
        return {
            fondo: 'linear-gradient(180deg, #14275A 0%, #0E1B3D 100%)',
            linea: '#4C8DFF',
            punto: '#4C8DFF',
            etiqueta: '#B9C8EA',
        }
    }
    return null
}
