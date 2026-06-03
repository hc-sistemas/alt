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
    empresa?: Empresa
    nombre: string
    codigo: string
    tipo: 'empresa' | 'sucursal' | 'centro_costo_interno'
    es_taller: boolean
    estado: boolean
    created_at: string
    updated_at: string
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
    flash: { success?: string; error?: string }
    ziggy?: Record<string, unknown>
}

export interface ActivoDepreciacion {
    id: number
    activo_id: number
    periodo_año: number
    periodo_mes: number
    monto: number
    depreciacion_acumulada_al_periodo: number
    valor_libro_al_periodo: number
    created_at: string
}

export interface ActivoFijo {
    id: number
    empresa_id: number
    empresa?: Empresa
    codigo: string
    nombre: string
    descripcion: string | null
    categoria: string
    ubicacion: string | null
    fecha_adquisicion: string
    valor_adquisicion: number
    valor_residual: number
    vida_util_años: number
    metodo_depreciacion: string
    depreciacion_acumulada: number
    valor_libro: number
    estado: 'activo' | 'dado_de_baja' | 'vendido'
    cuenta_activo_id: number | null
    cuenta_depreciacion_id: number | null
    notas: string | null
    depreciaciones?: ActivoDepreciacion[]
    created_at: string
    updated_at: string
    deleted_at: string | null
}

export interface Cliente {
    id: number
    empresa_id: number
    ruc_cedula: string
    nombre: string
    direccion?: string
    telefono?: string
    email?: string
    ciudad?: string
    pais: string
    tiene_credito: boolean
    dias_credito?: number
    cupo_credito?: number
    es_agente_retencion: boolean
    estado: boolean
    observaciones?: string
    created_at?: string
    updated_at?: string
}

export interface Proveedor {
    id: number
    empresa_id: number
    tipo: 'nacional' | 'internacional'
    ruc_cedula?: string
    nombre: string
    direccion?: string
    telefono?: string
    email?: string
    ciudad?: string
    pais: string
    divisa?: string
    tiene_credito: boolean
    dias_credito?: number
    estado: boolean
    observaciones?: string
    created_at?: string
    updated_at?: string
}

export interface Transportista {
    id: number
    empresa_id: number
    razon_social: string
    ruc: string
    placa?: string
    contacto?: string
    telefono?: string
    estado: boolean
    created_at?: string
    updated_at?: string
}

export interface Marca {
    id: number
    empresa_id: number | null
    empresa?: Empresa
    nombre: string
    descripcion: string | null
    activo: boolean
    created_at: string
    updated_at: string
}

export interface CategoriaProducto {
    id: number
    empresa_id: number | null
    parent_id: number | null
    padre?: CategoriaProducto
    hijos?: CategoriaProducto[]
    nombre: string
    descripcion: string | null
    activo: boolean
    created_at: string
    updated_at: string
}

export interface Bodega {
    id: number
    empresa_id: number
    empresa?: Empresa
    centro_costo_id: number | null
    centro_costo?: CentroCosto
    nombre: string
    tipo: 'general' | 'importacion' | 'taller' | 'reserva' | 'cuarentena'
    descripcion: string | null
    activo: boolean
    created_at: string
    updated_at: string
}

export interface TrasladoItem {
    id: number
    traslado_id: number
    producto_id: number
    producto?: Producto
    cantidad_enviada: number
    cantidad_recibida: number | null
    notas: string | null
    created_at: string
    updated_at: string
}

export interface Traslado {
    id: number
    empresa_id: number
    bodega_origen_id: number
    bodega_origen?: Bodega
    bodega_destino_id: number
    bodega_destino?: Bodega
    estado: 'pendiente' | 'confirmado' | 'anulado'
    usuario_origen_id: number
    usuario_origen?: Usuario
    usuario_destino_id: number | null
    usuario_destino?: Usuario
    fecha_traslado: string
    fecha_confirmacion: string | null
    notas_origen: string | null
    notas_destino: string | null
    items?: TrasladoItem[]
    created_at: string
    updated_at: string
}

export interface InventarioSaldo {
    id: number
    producto_id: number
    producto?: Producto
    bodega_id: number
    bodega?: Bodega
    stock_actual: number
    stock_reservado: number
    costo_promedio: number
    updated_at: string
    stock_minimo?: number
}

export interface InventarioMovimiento {
    id: number
    producto_id: number
    producto?: Producto
    bodega_id: number
    bodega?: Bodega
    tipo: 'entrada' | 'salida' | 'traslado_entrada' | 'traslado_salida' |
          'ajuste_positivo' | 'ajuste_negativo' | 'reserva' | 'liberacion'
    doc_tipo: string | null
    doc_id: number | null
    cantidad: number
    costo_unitario: number | null
    costo_total: number | null
    stock_anterior: number
    stock_nuevo: number
    usuario_id: number
    usuario?: Usuario
    empresa_id: number
    notas: string | null
    created_at: string
}

export interface Producto {
    id: number
    empresa_id: number
    empresa?: Empresa
    marca_id: number | null
    marca?: Marca
    categoria_id: number | null
    categoria?: CategoriaProducto
    bodega_default_id: number | null
    bodega_default?: Bodega
    codigo: string
    nombre: string
    descripcion: string | null
    tipo: 'producto' | 'servicio' | 'combo'
    unidad: string
    requiere_serie: boolean
    pvp: number
    pvd: number
    costo: number
    descuento_maximo: number
    iva_porcentaje: number
    ice_porcentaje: number
    stock_minimo: number
    stock_maximo: number | null
    cuenta_inventario_id: number | null
    cuenta_costo_id: number | null
    cuenta_ventas_id: number | null
    estado: boolean
    observaciones: string | null
    created_at: string
    updated_at: string
    deleted_at: string | null
}

export interface ProductoSerie {
    id: number
    producto_id: number
    producto?: Producto
    bodega_id: number
    bodega?: Bodega
    numero_serie: string
    estado: 'disponible' | 'vendido' | 'reservado' | 'defectuoso'
    doc_entrada_tipo: string | null
    doc_entrada_id: number | null
    doc_salida_tipo: string | null
    doc_salida_id: number | null
    created_at: string
    updated_at: string
}

export interface PaginatedData<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
}
