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
    flash: { success?: string; error?: string; warning?: string }
    ziggy?: Record<string, unknown>
}

// ── Contabilidad ─────────────────────────────────────────────────────────────

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

// ── Inventario / Activos Fijos ────────────────────────────────────────────────

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

// ── Personas ──────────────────────────────────────────────────────────────────

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

// ── Inventario ────────────────────────────────────────────────────────────────

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

// ── Compras ───────────────────────────────────────────────────────────────────

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

// ── Bancos ────────────────────────────────────────────────────────────────────

export interface BancoCaja {
    id: number
    empresa_id: number
    cuenta_id: number | null
    cuenta?: PlanCuenta
    tipo: 'banco' | 'caja' | 'caja_chica' | 'tarjeta'
    tipo_label?: string
    tipo_color?: string
    nombre: string
    num_cuenta: string | null
    tipo_cuenta: string | null
    saldo_inicial: number
    saldo_actual: number
    estado: boolean
}

export interface MovimientoBancario {
    id: number
    empresa_id: number
    banco_caja_id: number
    banco_caja?: BancoCaja
    tipo: 'ingreso' | 'egreso'
    sub_tipo: string | null
    sub_tipo_label?: string
    fecha: string
    monto: number
    persona_tipo: string | null
    persona_id: number | null
    beneficiario: string | null
    num_documento: string | null
    num_cheque: string | null
    descripcion: string | null
    documento_tipo: string | null
    documento_id: number | null
    cuenta_contrapartida_id: number | null
    asiento_id: number | null
    conciliado: boolean
    es_postfechado: boolean
    anulado: boolean
    created_at: string
}

export interface CierreCaja {
    id: number
    empresa_id: number
    banco_caja_id: number
    banco_caja?: BancoCaja
    centro_costo_id: number | null
    fecha: string
    usuario_apertura_id: number | null
    usuario_cierre_id: number | null
    monto_inicial: number
    total_facturado: number
    total_cobrado: number
    total_efectivo: number
    total_tarjeta: number
    total_cheque: number
    total_transferencia: number
    diferencia: number
    observaciones: string | null
    estado: 'abierto' | 'cerrado'
    hora_apertura: string | null
    hora_cierre: string | null
}

export interface DatafastLote {
    id: number
    empresa_id: number
    banco_caja_id: number
    banco_caja?: BancoCaja
    numero_lote: string
    fecha: string
    total_vouchers: number
    asiento_id: number | null
    estado: 'pendiente' | 'liquidado'
    liquidacion?: DatafastLiquidacion
}

export interface DatafastLiquidacion {
    id: number
    lote_id: number
    fecha_deposito: string
    valor_bruto: number
    comision_datafast: number
    retencion_iva: number
    retencion_ir: number
    valor_neto: number
    banco_destino_id: number | null
    asiento_id: number | null
}

export interface ConciliacionBancaria {
    id: number
    empresa_id: number
    banco_caja_id: number
    banco_caja?: BancoCaja
    fecha_corte: string
    saldo_banco: number
    saldo_sistema: number
    diferencia: number
    descripcion: string | null
    estado: 'pendiente' | 'conciliada'
    created_at: string
    partidas?: PartidaTransito[]
}

export interface PartidaTransito {
    id: number
    conciliacion_id: number
    tipo: 'sistema' | 'banco'
    fecha: string | null
    descripcion: string | null
    monto: number | null
    movimiento_id: number | null
    conciliada: boolean
}
