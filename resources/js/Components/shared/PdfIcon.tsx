interface Props {
    className?: string
}

/** Icono de archivo PDF (documento rojo con esquina doblada y la etiqueta PDF), al estilo Adobe. */
export default function PdfIcon({ className }: Props) {
    return (
        <svg viewBox="0 0 24 24" className={className} aria-hidden="true">
            <path
                d="M5 1.5h9.5L20 7v14.5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-19a1 1 0 0 1 1-1z"
                fill="currentColor"
            />
            <path d="M14.5 1.5V6a1 1 0 0 0 1 1H20z" fill="#000" opacity="0.25" />
            <text
                x="12"
                y="17.6"
                textAnchor="middle"
                fontSize="7"
                fontWeight="800"
                fontFamily="Arial, Helvetica, sans-serif"
                fill="#fff"
            >
                PDF
            </text>
        </svg>
    )
}
