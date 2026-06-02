export interface Empresa {
    id: number
    razon_social: string
    nombre_comercial: string
    ruc: string
    logo?: string
    slogan?: string
    ambiente_sri: '1' | '2'
    codigo_establecimiento: string
    codigo_punto_emision: string
    obligado_contabilidad: boolean
    contribuyente_especial: boolean
    numero_resolucion_agente_retencion?: string
    estado: boolean
}

export interface CentroCosto {
    id: number
    empresa_id: number
    nombre: string
    codigo: string
    tipo: 'empresa' | 'sucursal' | 'centro_costo_interno'
    es_taller: boolean
    estado: boolean
}

export interface Perfil {
    id: number
    nombre: string
    descripcion?: string
    es_sistema: boolean
}

export interface Usuario {
    id: number
    empresa_id: number
    perfil_id: number
    centro_costo_id?: number
    nombre: string
    email: string
    username: string
    telefono?: string
    avatar?: string
    estado: boolean
    ultimo_acceso?: string
    perfil?: Perfil
    empresa?: Empresa
    empresas?: Empresa[]
}

export interface Modulo {
    id: number
    nombre: string
    clave: string
    icono?: string
    orden: number
    padre_id?: number
    hijos?: Modulo[]
}

export interface Permiso {
    id: number
    perfil_id: number
    modulo_id: number
    ver: boolean
    crear: boolean
    editar: boolean
    eliminar: boolean
    anular: boolean
    modulo?: Modulo
}

export interface LimiteDescuento {
    id: number
    perfil_id: number
    porcentaje_maximo: number
    puede_aprobar: boolean
    porcentaje_aprobacion_max: number
}

export interface Notificacion {
    id: number
    usuario_id: number
    titulo: string
    mensaje: string
    tipo: 'info' | 'success' | 'warning' | 'danger'
    icono?: string
    url?: string
    leida: boolean
    leida_at?: string
    created_at: string
}

export interface LogSesion {
    id: number
    usuario_id?: number
    email?: string
    tipo: 'login_ok' | 'login_fail' | 'logout' | 'forzado'
    ip_address?: string
    user_agent?: string
    created_at: string
}

export interface AuthUser {
    id: number
    nombre: string
    email: string
    perfil?: string
    empresa_id: number
    centro_costo_id?: number
    avatar?: string
}

export interface PageProps {
    auth: { user: AuthUser | null }
    empresa_activa?: Partial<Empresa> | null
    empresas_usuario: Partial<Empresa>[]
    flash: { success?: string; error?: string; warning?: string }
    ziggy?: Record<string, unknown>
}

export interface PlanCuenta {
    id: number
    empresa_id: number | null
    codigo: string
    nombre: string
    descripcion: string | null
    tipo: 'activo' | 'pasivo' | 'patrimonio' | 'ingreso' | 'gasto'
    padre_id: number | null
    nivel: number
    permite_asientos: boolean
    estado: boolean
    total_asientos: number
    hijos?: PlanCuenta[]
}

export interface PlanCuentaStats {
    total: number
    activas: number
    con_asientos: number
    sin_uso: number
}

export interface EjercicioContable {
    id: number
    anio: number
    mes: number
    nombre_mes: string
    periodo_label: string
    descripcion: string | null
    fecha_apertura: string | null
    fecha_cierre: string | null
    estado: 'abierto' | 'cerrado'
    cerrado_por: string | null
    total_asientos: number
}

export interface AsientoDetalle {
    id: number
    asiento_id: number
    cuenta_id: number
    cuenta?: PlanCuenta
    centro_costo_id: number | null
    descripcion: string | null
    debe: number
    haber: number
}

export interface AsientoContable {
    id: number
    empresa_id: number
    ejercicio_id: number | null
    ejercicio?: EjercicioContable
    numero: string
    fecha: string
    concepto: string
    documento_tipo: string | null
    documento_id: number | null
    documento_ref: string | null
    total_debe: number
    total_haber: number
    es_automatico: boolean
    estado: number
    creado_por: number | null
    creado_por_nombre?: string
    created_at: string
    detalles?: AsientoDetalle[]
}

export interface AsientoStats {
    total: number
    activos: number
    anulados: number
    manuales: number
}

export interface PaginatedData<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
    meta: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
}

// ── Compras ──────────────────────────────────────────────────────────────────

export interface Proveedor {
    id: number
    empresa_id: number
    tipo: 'nacional' | 'internacional'
    tipo_identificacion: string
    identificacion: string
    razon_social: string
    nombre_comercial: string | null
    email: string | null
    telefono: string | null
    direccion: string | null
    ciudad: string | null
    pais: string
    divisa: string
    tiene_credito: boolean
    dias_credito: number
    estado: boolean
    nombre_display?: string
    saldo_pendiente?: number
}

export interface CompraDetalle {
    id: number
    compra_id: number
    producto_id: number | null
    cuenta_id: number | null
    cuenta?: PlanCuenta
    descripcion: string
    cantidad: number
    precio_unitario: number
    descuento: number
    subtotal: number
    porcentaje_iva: number
    valor_iva: number
    total: number
    es_activo_fijo: boolean
}

export interface Compra {
    id: number
    empresa_id: number
    proveedor_id: number
    proveedor?: Proveedor
    centro_costo_id: number | null
    importacion_id: number | null
    tipo_documento: string
    num_documento: string
    num_autorizacion: string | null
    fecha_emision: string
    fecha_registro: string
    fecha_vencimiento: string | null
    dias_credito: number
    subtotal_0: number
    subtotal_iva: number
    total_iva: number
    total_ice: number
    total: number
    iva_asumido: boolean
    gasto_no_deducible: boolean
    sustento_tributario: number | null
    asiento_id: number | null
    tiene_pago: boolean
    concepto: string | null
    estado: 'activa' | 'anulada'
    created_at: string
    detalles?: CompraDetalle[]
}

export interface CuentaPagar {
    id: number
    empresa_id: number
    proveedor_id: number
    proveedor?: Proveedor
    compra_id: number | null
    compra?: Compra
    monto: number
    saldo: number
    fecha_emision: string
    fecha_vencimiento: string
    aprobada: boolean
    estado: 'pendiente' | 'parcial' | 'pagada'
    dias_vencimiento?: number
    urgencia?: 'vencida' | 'critica' | 'proxima' | 'normal'
    color_urgencia?: string
}

export interface Importacion {
    id: number
    empresa_id: number
    proveedor_id: number | null
    proveedor?: Proveedor
    nombre: string
    num_invoice: string | null
    agente_aduanero: string | null
    pais_embarque: string | null
    costo_fob: number
    divisa: string
    fecha_partida: string | null
    fecha_llegada: string | null
    fecha_liquidacion: string | null
    total_costos_extra: number
    costo_total: number
    metodo_prorrateo: 'cantidad' | 'precio' | 'peso'
    estado: 'en_transito' | 'en_aduana' | 'liquidada'
    estado_label?: string
    estado_color?: string
    observaciones: string | null
}

export interface AnticipoProveedor {
    id: number
    empresa_id: number
    proveedor_id: number
    proveedor?: Proveedor
    importacion_id: number | null
    fecha: string
    monto: number
    saldo: number
    num_transferencia: string | null
    asiento_id: number | null
    estado: 'pendiente' | 'cruzado'
}
