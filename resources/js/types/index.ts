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
    direccion_matriz?: string | null
    email_notificaciones?: string | null
    telefono?: string | null
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
    tipo: string
    titulo: string
    mensaje: string | null
    icono?: string | null
    url?: string | null
    leida: boolean
    leida_at?: string | null
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

export interface PermisoAcciones {
    ver: boolean
    crear: boolean
    editar: boolean
    eliminar: boolean
    anular: boolean
}

export interface AuthUser {
    id: number
    nombre: string
    email: string
    perfil?: string
    perfil_clave?: string
    empresa_id: number
    centro_costo_id?: number
    avatar?: string
}

export interface PageProps {
    auth: { user: AuthUser | null }
    permisos: '*' | Record<string, PermisoAcciones>
    empresa_activa?: Partial<Empresa> | null
    empresas_usuario: Partial<Empresa>[]
    flash: { success?: string; error?: string; warning?: string }
    ziggy?: Record<string, unknown>
    notificaciones_no_leidas: number
    [key: string]: unknown
}

// ── Inventario ────────────────────────────────────────────────────────────────

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
    cuenta_id: number | null
    nombre: string
    descripcion: string | null
    codigo: string | null
    categoria?: string
    fecha_adquisicion: string
    costo_adquisicion: number
    vida_util_anios: number
    valor_residual: number
    depreciacion_acumulada: number
    valor_en_libros: number
    estado: string
    created_at: string
    updated_at: string
    depreciaciones?: ActivoDepreciacion[]
}

export interface Cliente {
    id: number
    empresa_id: number
    tipo_identificacion: '04' | '05' | '06' | '07'
    identificacion: string
    razon_social: string
    nombre_comercial?: string
    email?: string
    telefono?: string
    celular?: string
    direccion?: string
    ciudad?: string
    provincia?: string
    pais: string
    tiene_credito: boolean
    dias_credito: number
    cupo_maximo: number
    agente_retencion: boolean
    es_cliente_nuevo: boolean
    estado: boolean
    deleted_at?: string | null
}

export interface Transportista {
    id: number
    identificacion?: string
    razon_social: string
    placa?: string
    email?: string
    telefono?: string
    direccion?: string
    estado: boolean
    created_at?: string
    updated_at?: string
}

export interface Marca {
    id: number
    nombre: string
    logo: string | null
    icono: string
    estado: boolean
}

export interface CategoriaProducto {
    id: number
    nombre: string
    categoria_padre_id: number | null
    estado: boolean
    padre?: CategoriaProducto
    hijos?: CategoriaProducto[]
}

export interface Bodega {
    id: number
    empresa_id: number
    centro_costo_id: number | null
    nombre: string
    tipo: 'general' | 'importacion' | 'taller' | 'reserva' | 'cuarentena'
    es_virtual: boolean
    estado: boolean
    centro_costo?: CentroCosto
}

export interface Producto {
    id: number
    empresa_id: number
    marca_id: number | null
    categoria_id: number | null
    codigo: string
    codigo_externo: string | null
    nombre: string
    descripcion: string | null
    unidad: string
    tipo: 'producto' | 'servicio' | 'repuesto' | 'insumo'
    requiere_serie: boolean
    costo: number
    pvp: number
    pvd: number
    descuento_maximo: number
    porcentaje_iva: number
    tiene_ice: boolean
    porcentaje_ice: number
    stock_minimo: number
    stock_maximo: number
    cuenta_inventario: string | null
    cuenta_costo_ventas: string | null
    cuenta_ventas: string | null
    ref_importacion: string | null
    estado: boolean
    created_at: string
    updated_at: string
    marca?: Marca
    categoria?: CategoriaProducto
}

export interface ProductoSerie {
    id: number
    producto_id: number
    bodega_id: number
    numero_serie: string
    estado: 'disponible' | 'vendido' | 'reservado' | 'garantia'
    factura_compra_id: number | null
    factura_venta_id: number | null
    created_at: string
    producto?: Producto
    bodega?: Bodega
}

export interface InventarioSaldo {
    id: number
    producto_id: number
    bodega_id: number
    stock_actual: number
    stock_reservado: number
    costo_promedio: number
    updated_at: string
    producto?: Producto
    bodega?: Bodega
}

export interface InventarioMovimiento {
    id: number
    empresa_id: number
    producto_id: number
    bodega_id: number
    tipo: 'entrada' | 'salida' | 'traslado_entrada' | 'traslado_salida' | 'ajuste_positivo' | 'ajuste_negativo' | 'reserva' | 'liberacion'
    doc_tipo: string | null
    doc_id: number | null
    cantidad: number
    costo_unitario: number
    costo_total: number
    stock_anterior: number
    stock_nuevo: number
    usuario_id: number | null
    notas: string | null
    created_at: string
    bodega?: Bodega
    producto?: Producto
    usuario?: { nombre: string }
}

export interface KardexMovimientoExtendido {
    id: number
    fecha: string
    hora: string | null
    tipo: string
    tipo_descriptivo: string
    documento_tipo: string | null
    documento_numero: string | null
    documento_id: number | null
    observacion: string | null
    es_ingreso: boolean | null
    cantidad: number
    costo_unitario: number
    costo_total: number
    saldo_posterior: number
}

export interface TrasladoBodega {
    id: number
    empresa_id: number
    bodega_origen_id: number
    bodega_destino_id: number
    numero: string | null
    fecha: string
    estado: 'pendiente' | 'aceptado' | 'rechazado'
    enviado_por: number | null
    recibido_por: number | null
    fecha_recepcion: string | null
    observacion: string | null
    created_at: string
    detalles?: TrasladoDetalle[]
    bodega_origen?: Bodega
    bodega_destino?: Bodega
}

export interface TrasladoDetalle {
    id: number
    traslado_id: number
    producto_id: number
    numero_serie: string | null
    cantidad_enviada: number
    cantidad_recibida: number
    producto?: Producto
}

export interface ListaPrecio {
    id: number
    empresa_id: number
    producto_id: number
    tipo: 'PVP' | 'PVD'
    precio: number
    descuento_max: number
    vigencia_desde: string | null
    vigencia_hasta: string | null
    producto?: Producto
}

// ── Contabilidad ──────────────────────────────────────────────────────────────

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


export interface PaginatedData<T> {
    data: T[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    prev_page_url: string | null
    next_page_url: string | null
    meta?: {
        current_page: number
        last_page: number
        per_page: number
        total: number
        from: number
        to: number
    }
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
    estado: 'pendiente' | 'activa' | 'anulada'
    has_etiquetas: boolean
    tiene_productos_codificados: number
    created_at: string
    detalles?: CompraDetalle[]
    metodo_envio: string | null
    divisa: string | null
    tipo_cambio: number | null
    num_orden_compra: string | null
    num_contrato: string | null
    vigencia_desde: string | null
    vigencia_hasta: string | null
}

export interface EtiquetaGrupoProducto {
    codigo: string
    nombre: string
    etiquetas: string[]
}

export interface EtiquetaDetalleData {
    id: number
    producto_id: number
    codigo: string
    nombre: string
    descripcion: string
    cantidad: number
    prefijo: string
    ultima_etiqueta_prefijo: number
    desde: number
    hasta: number
    num_etiquetas: number
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

// ── RRHH ────────────────────────────────────────────────────────────────────

export interface PuestoTrabajo {
    id: number
    empresa_id: number
    nombre: string
    cargo: string | null
    departamento: string | null
    estado: boolean
}

export interface Horario {
    id: number
    descripcion: string
    hora_entrada: string
    hora_salida: string
    tolerancia_minutos: number
    lunes: boolean
    martes: boolean
    miercoles: boolean
    jueves: boolean
    viernes: boolean
    sabado: boolean
    domingo: boolean
}

export interface Colaborador {
    id: number
    empresa_id: number
    puesto_id: number | null
    horario_id: number | null
    cedula_ruc: string
    apellidos: string
    nombres: string
    nombre_completo?: string
    email: string | null
    telefono: string | null
    celular: string | null
    direccion: string | null
    fecha_nacimiento: string | null
    sexo: string | null
    estado_civil: string | null
    fecha_ingreso: string
    fecha_salida: string | null
    tipo_contrato: 'indefinido' | 'plazo_fijo' | 'honorarios' | null
    cargo: string | null
    departamento: string | null
    comision_porcentaje: number
    sueldo_base: number
    decimo_tercero: 'acumula' | 'mensualiza'
    decimo_cuarto: 'acumula' | 'mensualiza'
    fondos_reserva: 'acumula' | 'mensualiza'
    banco: string | null
    tipo_cuenta: 'ahorros' | 'corriente' | null
    numero_cuenta: string | null
    usuario_id: number | null
    estado: boolean
    created_at: string
    updated_at: string
    puesto?: PuestoTrabajo
    horario?: Horario
}

export interface Asistencia {
    id: number
    colaborador_id: number
    fecha: string
    hora_entrada: string | null
    hora_salida: string | null
    minutos_atraso: number
    horas_extra: number
    tipo_extra: 'suplementaria' | 'extraordinaria' | null
    ip_entrada: string | null
    ip_salida: string | null
    observacion: string | null
    colaborador?: Colaborador
}

export interface HorasExtrasAprobacion {
    id: number
    colaborador_id: number
    asistencia_id: number | null
    fecha: string
    horas_solicitadas: number
    horas_aprobadas: number | null
    tipo: 'suplementaria' | 'extraordinaria'
    valor_calculado: number
    estado: 'pendiente' | 'aprobado' | 'rechazado'
    aprobado_por: number | null
    fecha_aprobacion: string | null
    observacion: string | null
    created_at: string
    colaborador?: Colaborador
    aprobado_por_usuario?: Usuario
}

// ── Recepciones de bodega ────────────────────────────────────────────────────

export interface RecepcionDetalle {
    id: number
    recepcion_id: number
    compra_detalle_id: number
    producto_id: number
    cantidad_esperada: number
    cantidad_recibida: number
    estado: 'pendiente' | 'parcial' | 'completado'
    producto?: Producto
    compraDetalle?: CompraDetalle
}

export interface RecepcionBodega {
    id: number
    empresa_id: number
    compra_id: number
    bodega_id: number
    estado: 'pendiente' | 'completada' | 'parcial'
    recibido_por: number | null
    fecha_recepcion: string | null
    observacion: string | null
    created_at: string
    updated_at: string
    compra?: Compra
    bodega?: Bodega
    recibidoPor?: { id: number; nombre: string }
    detalles?: RecepcionDetalle[]
}

// ── Nómina ───────────────────────────────────────────────────────────────────

export interface PrestamoEmpleado {
    id: number
    colaborador_id: number
    tipo: 'anticipo' | 'prestamo'
    monto_total: number
    saldo: number
    cuota: number
    fecha: string
    descripcion: string | null
    estado: 'activo' | 'pagado'
    created_by: number | null
    created_at: string
    colaborador?: Colaborador
}

export interface NominaDetalle {
    id: number
    nomina_id: number
    colaborador_id: number
    sueldo_base: number
    horas_extras_50: number
    horas_extras_100: number
    comisiones: number
    otros_ingresos: number
    total_ingresos: number
    aporte_personal_iess: number
    descuento_atrasos: number
    descuento_prestamos: number
    descuento_anticipos: number
    otros_egresos: number
    total_egresos: number
    neto_pagar: number
    tipo_pago: 'transferencia' | 'cheque' | 'efectivo' | null
    num_cuenta: string | null
    banco: string | null
    estado: 'borrador' | 'procesado' | 'pagado'
    modificado_manualmente: boolean
    created_at: string
    colaborador?: Colaborador
}

export interface Nomina {
    id: number
    empresa_id: number
    periodo_tipo: 'mensual' | 'quincenal'
    anio: number
    mes: number
    quincena: number | null
    fecha_emision: string
    estado: 'borrador' | 'procesado' | 'pagado'
    total_ingresos: number
    total_egresos: number
    total_neto: number
    asiento_id: number | null
    generado_por: number
    procesado_por: number | null
    pagado_por: number | null
    created_at: string
    periodo_label?: string
    detalles_count?: number
    detalles?: NominaDetalle[]
    generadoPor?: { id: number; nombre: string }
    procesadoPor?: { id: number; nombre: string }
    pagadoPor?: { id: number; nombre: string }
}

export interface Liquidacion {
    id: number
    colaborador_id: number
    fecha_salida: string
    motivo: 'renuncia' | 'despido' | 'fin_contrato'
    decimos_acumulados: number
    vacaciones: number
    fondos_reserva: number
    anticipos_descontar: number
    total_liquidacion: number
    estado: 'borrador' | 'aprobada'
    modificado_manualmente?: boolean
    created_by: number | null
    created_at: string
    updated_at: string
    colaborador?: Colaborador
    creadoPor?: { id: number; nombre: string }
}

export interface LiquidacionCalculo {
    colaborador: Pick<Colaborador, 'id' | 'apellidos' | 'nombres' | 'cargo' | 'sueldo_base' | 'fecha_ingreso'>
    meses_laborados: number
    dias_laborados: number
    decimos_acumulados: number
    vacaciones: number
    fondos_reserva: number
    anticipos_descontar: number
    total_liquidacion: number
}
