-- ============================================================
--  SISTEMA DE GESTIÓN EMPRESARIAL ALTAMIRA
--  Esquema PostgreSQL — Laravel 8.x / PHP 8.x
--  Generado: Mayo 2026
--
--  LEYENDA DE MIGRACIÓN:
--  [MIGRAR]   → datos se trasladan directamente desde tabla antigua
--  [MAPEAR]   → requiere transformación de columnas antes de migrar
--  [NUEVO]    → tabla nueva, sin equivalente en sistema anterior
--  [REPORTE]  → solo se sacan reportes del sistema antiguo, no se migra
-- ============================================================

-- ============================================================
--  0. EXTENSIONES
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- ============================================================
--  1. CONFIGURACIÓN Y SISTEMA
-- ============================================================

-- Empresas / RUC   [MIGRAR desde erp_empresa + erp_emisor]
CREATE TABLE empresas (
    id                          SERIAL PRIMARY KEY,
    ruc                         VARCHAR(13)  NOT NULL UNIQUE,
    razon_social                VARCHAR(200) NOT NULL,
    nombre_comercial            VARCHAR(200),
    direccion_matriz            VARCHAR(300),
    direccion_establecimiento    VARCHAR(300),
    cod_establecimiento         VARCHAR(3)   NOT NULL DEFAULT '001',
    cod_punto_emision           VARCHAR(3)   NOT NULL DEFAULT '001',
    obligado_contabilidad       BOOLEAN      DEFAULT TRUE,
    contribuyente_especial      VARCHAR(13),
    agente_retencion            VARCHAR(13),
    logo                        VARCHAR(500),
    ambiente_sri                SMALLINT     DEFAULT 1,  -- 1=pruebas, 2=producción
    firma_electronica_path      VARCHAR(500),
    firma_electronica_pass      VARCHAR(200),
    email_notificaciones        VARCHAR(200),
    estado                      BOOLEAN      DEFAULT TRUE,
    created_at                  TIMESTAMP    DEFAULT NOW(),
    updated_at                  TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_emisor → empresas (emi_identificacion=ruc, emi_nombre=razon_social, etc.)

-- Centros de Costo   [NUEVO]
CREATE TABLE centros_costo (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    codigo              VARCHAR(20)  NOT NULL,
    nombre              VARCHAR(100) NOT NULL,  -- 'Altamira Matriz', 'Altamira Import', 'Altamira Fix'
    tipo                VARCHAR(50),            -- 'matriz', 'importacion', 'taller'
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);

-- Perfiles / Roles de usuario   [NUEVO]
CREATE TABLE perfiles (
    id                  SERIAL PRIMARY KEY,
    nombre              VARCHAR(50)  NOT NULL UNIQUE,  -- 'super_admin','admin','contador','vendedor','bodeguero','tecnico'
    descripcion         VARCHAR(200),
    estado              BOOLEAN      DEFAULT TRUE
);

-- Usuarios   [MAPEAR desde erp_users]
CREATE TABLE usuarios (
    id                  SERIAL PRIMARY KEY,
    perfil_id           INT          NOT NULL REFERENCES perfiles(id),
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    colaborador_id      INT,                    -- FK a colaboradores (se agrega luego)
    username            VARCHAR(50)  NOT NULL UNIQUE,
    email               VARCHAR(200) NOT NULL UNIQUE,
    password            VARCHAR(255) NOT NULL,
    nombre              VARCHAR(200),
    telefono            VARCHAR(20),
    remember_token      VARCHAR(100),
    -- Código de aprobación especial (diferente al password de login)
    -- Lo usa Admin/SuperAdmin para autorizar acciones que superan límites
    -- Se almacena hasheado con bcrypt, nunca en texto plano
    codigo_aprobacion   VARCHAR(255),
    estado              BOOLEAN      DEFAULT TRUE,
    ultimo_acceso       TIMESTAMP,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_users (usu_login=username, usu_pass=password, name=nombre)

-- Permisos por módulo y perfil   [NUEVO - reemplaza erp_criterios_permiso + erp_sol_acceso]
CREATE TABLE modulos (
    id                  SERIAL PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL,
    codigo              VARCHAR(50)  NOT NULL UNIQUE,
    icono               VARCHAR(50),
    orden               SMALLINT     DEFAULT 0
);

-- Permisos estándar por módulo
CREATE TABLE permisos (
    id                  SERIAL PRIMARY KEY,
    perfil_id           INT         NOT NULL REFERENCES perfiles(id),
    modulo_id           INT         NOT NULL REFERENCES modulos(id),
    puede_ver           BOOLEAN     DEFAULT FALSE,
    puede_crear         BOOLEAN     DEFAULT FALSE,
    puede_editar        BOOLEAN     DEFAULT FALSE,
    puede_eliminar      BOOLEAN     DEFAULT FALSE,
    puede_anular        BOOLEAN     DEFAULT FALSE,
    UNIQUE(perfil_id, modulo_id)
);

-- ============================================================
--  SISTEMA DE PERMISOS ESPECIALES Y APROBACIONES POR CÓDIGO
-- ============================================================
-- Cuando una acción supera los límites configurados (ej: descuento mayor
-- al máximo del producto), el sistema bloquea y solicita que un usuario
-- con autorización ingrese su código de aprobación para continuar.
-- El código es diferente al password de login — es un PIN de 4-6 dígitos
-- que el Súper Admin o Admin configura en su perfil.
-- Todo queda registrado en audit_log para trazabilidad total.

-- Catálogo de tipos de aprobación especial   [NUEVO]
-- Define qué perfiles pueden aprobar cada tipo de acción
CREATE TABLE tipos_aprobacion (
    id                  SERIAL PRIMARY KEY,
    codigo              VARCHAR(50)  NOT NULL UNIQUE,
    descripcion         VARCHAR(200) NOT NULL,
    -- Perfiles que pueden aprobar (separados por coma: 'super_admin,admin')
    perfiles_autorizados VARCHAR(200) NOT NULL DEFAULT 'super_admin',
    -- Si requiere código o solo confirmación de identidad (login)
    requiere_codigo     BOOLEAN      DEFAULT TRUE,
    activo              BOOLEAN      DEFAULT TRUE
);
-- Datos iniciales que se cargarán en seeders:
-- ('descuento_extra_factura',    'Descuento mayor al máximo del producto en factura',     'super_admin,admin', true)
-- ('descuento_extra_proforma',   'Descuento mayor al máximo del producto en proforma',    'super_admin,admin', true)
-- ('precio_bajo_costo',          'Vender producto por debajo de su costo',                'super_admin',       true)
-- ('anular_factura',             'Anular factura electrónica autorizada por SRI',         'super_admin',       true)
-- ('nota_credito',               'Emitir nota de crédito / devolución',                   'super_admin,admin', true)
-- ('ajuste_inventario',          'Ajuste manual de stock en Kárdex',                      'super_admin,admin', true)
-- ('modificar_precio_pvp',       'Modificar precio PVP o PVD de un producto',             'super_admin',       true)
-- ('aprobar_pago_cxp',           'Aprobar transferencia o pago a proveedor',              'super_admin,admin', true)
-- ('aprobar_horas_extras',       'Aprobar horas extras o suplementarias de empleado',     'super_admin,admin', true)
-- ('aprobar_liquidacion',        'Aprobar finiquito de empleado',                         'super_admin',       true)
-- ('cerrar_periodo_contable',    'Cerrar ejercicio contable del mes',                     'super_admin,admin', true)
-- ('asiento_manual',             'Crear asiento contable manual',                         'super_admin,admin', true)
-- ('castigar_deuda',             'Castigar cuenta incobrable mayor a 360 días',           'super_admin',       true)
-- ('aprobar_ot_garantia',        'Marcar orden de trabajo como garantía (costo $0)',       'super_admin,admin', true)
-- ('egreso_caja_sin_factura',    'Registrar egreso de caja sin comprobante válido',       'super_admin,admin', true)
-- ('modificar_factura_cerrada',  'Editar documento en período contable cerrado',          'super_admin',       true)
-- ('ver_costos_utilidad',        'Ver costo y margen de utilidad en documentos',          'super_admin,admin,contador', false)
-- ('ver_sueldos_empleados',      'Ver sueldo y datos financieros de otros empleados',     'super_admin,contador', false)
-- ('aprobar_traslado_bodega',    'Aprobar traslado de mercadería entre bodegas',          'super_admin,admin,bodeguero', false)

-- Registro de aprobaciones especiales otorgadas   [NUEVO]
-- Cada vez que alguien ingresa su código para aprobar una acción,
-- queda registrado aquí con todos los detalles.
CREATE TABLE aprobaciones_especiales (
    id                  BIGSERIAL    PRIMARY KEY,
    tipo_aprobacion_id  INT          NOT NULL REFERENCES tipos_aprobacion(id),
    -- Quién solicitó la aprobación
    solicitante_id      INT          NOT NULL REFERENCES usuarios(id),
    -- Quién aprobó (ingresó el código)
    aprobador_id        INT          NOT NULL REFERENCES usuarios(id),
    -- Documento sobre el que se aplicó
    documento_tipo      VARCHAR(20),            -- 'factura','proforma','ot','nomina', etc.
    documento_id        INT,
    -- Detalle de lo que se aprobó
    campo_afectado      VARCHAR(100),           -- ej: 'descuento_pct', 'precio_unitario'
    valor_original      VARCHAR(200),           -- ej: '10.00' (descuento máximo del producto)
    valor_aprobado      VARCHAR(200),           -- ej: '25.00' (descuento que se autorizó)
    motivo              VARCHAR(500),           -- razón que dio el aprobador
    ip_aprobacion       VARCHAR(45),
    fecha               TIMESTAMP    NOT NULL DEFAULT NOW()
    -- SIN updated_at → registro inmutable
);

-- Configuración de límites de descuento por perfil   [NUEVO]
-- Define hasta qué % puede dar descuento cada perfil SIN necesitar aprobación.
-- Si supera este límite, el sistema solicita código de aprobación.
CREATE TABLE limites_descuento (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    perfil_id           INT          NOT NULL REFERENCES perfiles(id),
    descuento_maximo_pct NUMERIC(5,2) NOT NULL DEFAULT 0,
    -- Si puede aprobar descuentos de otros (vendedores)
    puede_aprobar       BOOLEAN      DEFAULT FALSE,
    -- Hasta qué % puede aprobar (solo si puede_aprobar = TRUE)
    descuento_aprobacion_max_pct NUMERIC(5,2) DEFAULT 0,
    UNIQUE(empresa_id, perfil_id)
);
-- Datos iniciales sugeridos (ajustables desde configuración):
-- super_admin: descuento_maximo=100%, puede_aprobar=TRUE, aprobacion_max=100%
-- admin:       descuento_maximo=30%,  puede_aprobar=TRUE, aprobacion_max=50%
-- contador:    descuento_maximo=0%,   puede_aprobar=FALSE
-- vendedor:    descuento_maximo=5%,   puede_aprobar=FALSE
-- bodeguero:   descuento_maximo=0%,   puede_aprobar=FALSE
-- tecnico:     descuento_maximo=0%,   puede_aprobar=FALSE

-- ============================================================
--  SISTEMA DE AUDITORÍA EN 3 NIVELES   [NUEVO - reemplaza erp_auditoria]
--
--  FILOSOFÍA:
--  En lugar de una tabla gigante que registra todo, se usan 3 tablas
--  especializadas con diferente propósito y política de retención.
--  Además, cada tabla principal tiene created_by/updated_by para
--  saber de inmediato quién tocó cada registro sin buscar en logs.
--
--  NIVEL 1 — log_sesiones       : seguridad y accesos (retener 1 año)
--  NIVEL 2 — log_documentos     : trazabilidad de negocio (retener permanente)
--  NIVEL 3 — log_cambios_criticos: cambios en campos sensibles (retener 3 años)
--
--  QUÉ NO SE REGISTRA (para no inflar):
--  - Consultas / visualización de pantallas
--  - Exportaciones de reportes
--  - Cambios en campos no críticos (ej: observaciones de un producto)
--  - Navegación entre módulos
-- ============================================================

-- NIVEL 1: Sesiones y seguridad
-- Responde: ¿quién entró al sistema, cuándo y desde dónde?
-- ¿Hubo intentos fallidos de acceso?
CREATE TABLE log_sesiones (
    id                  BIGSERIAL    PRIMARY KEY,
    usuario_id          INT          REFERENCES usuarios(id),
    username            VARCHAR(50)  NOT NULL,
    tipo                VARCHAR(20)  NOT NULL,  -- 'login_ok','login_fail','logout','session_expire','cambio_password','codigo_aprobacion_usado'
    ip                  VARCHAR(45),
    user_agent          VARCHAR(300),
    fecha               TIMESTAMP    NOT NULL DEFAULT NOW()
    -- SIN updated_at — registro inmutable
);
-- Política de retención: purgar registros con más de 12 meses
-- via: php artisan audit:purge-sesiones --meses=12  (job programado)

-- NIVEL 2: Documentos de negocio
-- Responde: ¿quién creó/modificó/anuló cada factura, cliente, compra, etc.?
-- Un registro por evento sobre un documento. No a nivel de campo.
CREATE TABLE log_documentos (
    id                  BIGSERIAL    PRIMARY KEY,
    usuario_id          INT          REFERENCES usuarios(id),
    username            VARCHAR(50)  NOT NULL,
    -- Tipo de evento
    accion              VARCHAR(30)  NOT NULL,
    -- 'crear','editar','anular','eliminar','aprobar','pagar',
    -- 'enviar_sri','autorizar','rechazar','cerrar','reabrir'
    -- Documento afectado
    modulo              VARCHAR(50)  NOT NULL,   -- 'facturas','clientes','compras','nomina', etc.
    tabla               VARCHAR(100) NOT NULL,   -- nombre exacto de la tabla
    registro_id         INT          NOT NULL,   -- ID del registro afectado
    descripcion         VARCHAR(500),            -- texto legible: "Factura 001-001-000123 al cliente Pérez Juan"
    -- Contexto
    ip                  VARCHAR(45),
    empresa_id          INT,
    centro_costo_id     INT,
    fecha               TIMESTAMP    NOT NULL DEFAULT NOW()
    -- SIN updated_at — registro inmutable
);
-- Política de retención: permanente (estos registros son pequeños y valiosos)
-- Índices para consultas frecuentes (ver abajo en sección de índices)

-- NIVEL 3: Cambios en campos críticos
-- Responde: ¿quién cambió el precio de este producto? ¿quién editó el sueldo?
-- Solo para campos sensibles definidos en la configuración.
-- Se activa vía Observer de Laravel en los modelos correspondientes.
CREATE TABLE log_cambios_criticos (
    id                  BIGSERIAL    PRIMARY KEY,
    usuario_id          INT          REFERENCES usuarios(id),
    username            VARCHAR(50)  NOT NULL,
    -- Qué se cambió
    tabla               VARCHAR(100) NOT NULL,
    registro_id         INT          NOT NULL,
    campo               VARCHAR(100) NOT NULL,
    valor_anterior      TEXT,
    valor_nuevo         TEXT,
    -- Contexto
    motivo              VARCHAR(300),            -- opcional, si el sistema lo solicita
    ip                  VARCHAR(45),
    fecha               TIMESTAMP    NOT NULL DEFAULT NOW()
    -- SIN updated_at — registro inmutable
);
-- Campos que activan este log (configurados en Observers de Laravel):
--   productos          → costo, pvp, pvd
--   colaboradores      → sueldo_base, tipo_contrato, fecha_salida
--   clientes           → cupo_maximo, dias_credito, tiene_credito
--   usuarios           → perfil_id, estado, codigo_aprobacion
--   plan_cuentas       → codigo, descripcion
--   limites_descuento  → descuento_maximo_pct, puede_aprobar
--   ejercicios_contables → estado (apertura/cierre de período)
--   facturas           → estado (solo si se anula)
-- Política de retención: purgar registros con más de 36 meses
-- via: php artisan audit:purge-cambios --meses=36  (job programado)
-- Referencia migración: erp_auditoria (adt_modulo=modulo, adt_accion=accion, adt_ip=ip)

-- Configuraciones generales del sistema   [MAPEAR desde erp_configuraciones]
CREATE TABLE configuraciones (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          REFERENCES empresas(id),
    clave               VARCHAR(100) NOT NULL,
    valor               TEXT,
    descripcion         VARCHAR(300),
    UNIQUE(empresa_id, clave)
);

-- Parámetros contables (cuentas por evento)   [MAPEAR desde erp_ctas_asientos]
CREATE TABLE parametros_contables (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    codigo              VARCHAR(50)  NOT NULL,  -- 'cta_clientes','cta_proveedores','cta_iva_ventas', etc.
    cuenta_id           INT,                    -- FK a plan_cuentas
    descripcion         VARCHAR(200),
    UNIQUE(empresa_id, codigo)
);

-- Secuenciales por tipo de documento y emisor   [MAPEAR desde erp_emisor + erp_secuencial]
CREATE TABLE secuenciales (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    tipo_documento      VARCHAR(10)  NOT NULL,  -- 'FAC','NC','ND','RET','GR','PRF','PRE'
    establecimiento     VARCHAR(3)   NOT NULL DEFAULT '001',
    punto_emision       VARCHAR(3)   NOT NULL DEFAULT '001',
    secuencial          INT          NOT NULL DEFAULT 1,
    UNIQUE(empresa_id, tipo_documento, establecimiento, punto_emision)
);

-- Impuestos   [MIGRAR desde erp_impuestos]
CREATE TABLE impuestos (
    id                  SERIAL PRIMARY KEY,
    codigo              VARCHAR(10)  NOT NULL,
    descripcion         VARCHAR(100) NOT NULL,
    porcentaje          NUMERIC(5,2) NOT NULL DEFAULT 0,
    tipo                VARCHAR(20),            -- 'IVA','ICE','IRBPNR','retencion_ir','retencion_iva'
    estado              BOOLEAN      DEFAULT TRUE
);

-- Presupuestos y metas mensuales (Dashboard)   [NUEVO]
CREATE TABLE presupuestos_metas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          NOT NULL REFERENCES centros_costo(id),
    anio                SMALLINT     NOT NULL,
    mes                 SMALLINT     NOT NULL CHECK (mes BETWEEN 1 AND 12),
    meta_dolares        NUMERIC(14,2) NOT NULL DEFAULT 0,
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(centro_costo_id, anio, mes)
);

-- Notificaciones / Alertas   [NUEVO]
CREATE TABLE notificaciones (
    id                  BIGSERIAL    PRIMARY KEY,
    usuario_id          INT          REFERENCES usuarios(id),
    tipo                VARCHAR(50)  NOT NULL,  -- 'stock_critico','factura_vencida','ot_lista', etc.
    titulo              VARCHAR(200) NOT NULL,
    mensaje             TEXT,
    referencia_tabla    VARCHAR(100),
    referencia_id       INT,
    leida               BOOLEAN      DEFAULT FALSE,
    enviado_email       BOOLEAN      DEFAULT FALSE,
    created_at          TIMESTAMP    DEFAULT NOW()
);


-- ============================================================
--  2. PERSONAS
-- ============================================================

-- Tipos de identificación (catálogo)
CREATE TABLE tipos_identificacion (
    id                  SERIAL PRIMARY KEY,
    codigo              VARCHAR(5)   NOT NULL UNIQUE,   -- '04'=RUC,'05'=cedula,'06'=pasaporte,'07'=consumidor_final
    descripcion         VARCHAR(50)  NOT NULL
);

-- Clientes   [MAPEAR desde erp_i_cliente]
CREATE TABLE clientes (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    tipo_identificacion VARCHAR(5)   NOT NULL DEFAULT '05',
    identificacion      VARCHAR(20)  NOT NULL,
    razon_social        VARCHAR(200) NOT NULL,
    nombre_comercial    VARCHAR(200),
    email               VARCHAR(200),
    telefono            VARCHAR(20),
    celular             VARCHAR(20),
    direccion           VARCHAR(300),
    ciudad              VARCHAR(100),
    provincia           VARCHAR(100),
    pais                VARCHAR(100) DEFAULT 'ECUADOR',
    -- Crédito
    tiene_credito       BOOLEAN      DEFAULT FALSE,
    dias_credito        SMALLINT     DEFAULT 0,
    cupo_maximo         NUMERIC(12,2) DEFAULT 0,
    -- Retención
    agente_retencion    BOOLEAN      DEFAULT FALSE,
    -- Estado
    es_cliente_nuevo    BOOLEAN      DEFAULT FALSE,
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(empresa_id, identificacion)
);
-- Referencia migración: erp_i_cliente → clientes
-- cli_ced_ruc=identificacion, cli_raz_social=razon_social, cli_credito=tiene_credito,
-- cli_cup_maximo=cupo_maximo, cli_retencion=agente_retencion

-- Proveedores   [NUEVO - antes mezclado en erp_i_cliente o erp_reg_documentos]
CREATE TABLE proveedores (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    tipo                VARCHAR(20)  NOT NULL DEFAULT 'nacional',  -- 'nacional','internacional'
    tipo_identificacion VARCHAR(5)   NOT NULL DEFAULT '04',
    identificacion      VARCHAR(20)  NOT NULL,
    razon_social        VARCHAR(200) NOT NULL,
    nombre_comercial    VARCHAR(200),
    email               VARCHAR(200),
    telefono            VARCHAR(20),
    direccion           VARCHAR(300),
    ciudad              VARCHAR(100),
    pais                VARCHAR(100) DEFAULT 'ECUADOR',
    divisa              VARCHAR(10)  DEFAULT 'USD',
    -- Crédito
    tiene_credito       BOOLEAN      DEFAULT FALSE,
    dias_credito        SMALLINT     DEFAULT 0,
    -- Estado
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(empresa_id, identificacion)
);

-- Transportistas   [MIGRAR desde erp_transportista]
CREATE TABLE transportistas (
    id                  SERIAL PRIMARY KEY,
    identificacion      VARCHAR(20),
    razon_social        VARCHAR(200) NOT NULL,
    placa               VARCHAR(20),
    email               VARCHAR(200),
    telefono            VARCHAR(20),
    direccion           VARCHAR(300),
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_transportista → transportistas (1:1 directo)


-- ============================================================
--  3. COLABORADORES / RRHH
-- ============================================================

-- Puestos de trabajo   [MAPEAR desde par_puestos_trabajo]
CREATE TABLE puestos_trabajo (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    nombre              VARCHAR(100) NOT NULL,
    cargo               VARCHAR(100),
    departamento        VARCHAR(100),
    estado              BOOLEAN      DEFAULT TRUE
);

-- Horarios de trabajo   [MIGRAR desde erp_horarios]
CREATE TABLE horarios (
    id                  SERIAL PRIMARY KEY,
    descripcion         VARCHAR(100) NOT NULL,
    hora_entrada        TIME         NOT NULL,
    hora_salida         TIME         NOT NULL,
    tolerancia_minutos  SMALLINT     DEFAULT 5,
    lunes               BOOLEAN      DEFAULT TRUE,
    martes              BOOLEAN      DEFAULT TRUE,
    miercoles           BOOLEAN      DEFAULT TRUE,
    jueves              BOOLEAN      DEFAULT TRUE,
    viernes             BOOLEAN      DEFAULT TRUE,
    sabado              BOOLEAN      DEFAULT FALSE,
    domingo             BOOLEAN      DEFAULT FALSE
);
-- Referencia migración: erp_horarios (hor_h_entrada=hora_entrada, hor_h_salida=hora_salida)

-- Colaboradores / Empleados   [MAPEAR desde par_empleados]
CREATE TABLE colaboradores (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    puesto_id           INT          REFERENCES puestos_trabajo(id),
    horario_id          INT          REFERENCES horarios(id),
    -- Identificación
    cedula_ruc          VARCHAR(13)  NOT NULL UNIQUE,
    apellidos           VARCHAR(100) NOT NULL,
    nombres             VARCHAR(100) NOT NULL,
    email               VARCHAR(200) UNIQUE,
    telefono            VARCHAR(20),
    celular             VARCHAR(20),
    direccion           VARCHAR(300),
    fecha_nacimiento    DATE,
    sexo                VARCHAR(1),
    estado_civil        VARCHAR(20),
    -- Datos laborales
    fecha_ingreso       DATE         NOT NULL,
    fecha_salida        DATE,
    tipo_contrato       VARCHAR(50),            -- 'indefinido','plazo_fijo','honorarios'
    cargo               VARCHAR(100),
    departamento        VARCHAR(100),
    comision_porcentaje NUMERIC(5,2) DEFAULT 0,
    -- Remuneración
    sueldo_base         NUMERIC(10,2) NOT NULL DEFAULT 0,
    decimo_tercero      VARCHAR(10)  DEFAULT 'acumula',   -- 'acumula','mensualiza'
    decimo_cuarto       VARCHAR(10)  DEFAULT 'acumula',
    fondos_reserva      VARCHAR(10)  DEFAULT 'acumula',
    -- Datos bancarios
    banco               VARCHAR(100),
    tipo_cuenta         VARCHAR(20),            -- 'ahorros','corriente'
    numero_cuenta       VARCHAR(30),
    -- Sistema
    usuario_id          INT          REFERENCES usuarios(id),
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: par_empleados → colaboradores
-- emp_documento=cedula_ruc, emp_apellido_paterno+materno=apellidos, emp_nombres=nombres
-- emp_cta_bancaria=numero_cuenta, emp_cta_banco=banco

-- Actualizar FK de usuarios
ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_colaborador
    FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id);

-- Rubros de nómina   [MIGRAR desde erp_rubros_nomina]
CREATE TABLE rubros_nomina (
    id                  SERIAL PRIMARY KEY,
    codigo              VARCHAR(20)  NOT NULL UNIQUE,
    descripcion         VARCHAR(200) NOT NULL,
    grupo               VARCHAR(50),            -- 'ingreso','descuento','provision'
    tipo_valor          VARCHAR(20),            -- 'fijo','porcentaje','formula'
    valor               NUMERIC(10,4) DEFAULT 0,
    operacion           VARCHAR(10)  DEFAULT '+',  -- '+' ingreso, '-' descuento
    cuenta_contable     VARCHAR(20),
    afecta_iess         BOOLEAN      DEFAULT FALSE,
    afecta_renta        BOOLEAN      DEFAULT FALSE,
    estado              BOOLEAN      DEFAULT TRUE
);
-- Referencia migración: erp_rubros_nomina → rubros_nomina (rub_codigo=codigo, rub_descripcion=descripcion)

-- Nóminas (cabecera del rol)   [MAPEAR desde erp_nomina]
CREATE TABLE nominas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    colaborador_id      INT          NOT NULL REFERENCES colaboradores(id),
    periodo             VARCHAR(7)   NOT NULL,  -- 'YYYY-MM'
    tipo                VARCHAR(20)  NOT NULL DEFAULT 'mensual',  -- 'mensual','quincenal'
    fecha_desde         DATE         NOT NULL,
    fecha_hasta         DATE         NOT NULL,
    sueldo_base         NUMERIC(10,2) NOT NULL DEFAULT 0,
    dias_trabajados     NUMERIC(5,2)  DEFAULT 30,
    total_ingresos      NUMERIC(10,2) DEFAULT 0,
    total_egresos       NUMERIC(10,2) DEFAULT 0,
    neto_pagar          NUMERIC(10,2) DEFAULT 0,
    -- Pago
    forma_pago          VARCHAR(20),            -- 'transferencia','cheque','efectivo'
    banco               VARCHAR(100),
    num_comprobante     VARCHAR(50),
    fecha_pago          DATE,
    -- Contabilidad
    asiento_id          INT,                    -- FK a asientos_contables
    -- Estado: 'borrador','procesado','pagado'
    estado              VARCHAR(20)  DEFAULT 'borrador',
    modificado_manual   BOOLEAN      DEFAULT FALSE,
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_nomina → nominas
-- nom_empleado=colaborador_id, nom_periodo=periodo, nom_sueldo_base=sueldo_base

-- Detalle de rubros por nómina   [MIGRAR desde erp_det_nomina]
CREATE TABLE nomina_detalles (
    id                  SERIAL PRIMARY KEY,
    nomina_id           INT          NOT NULL REFERENCES nominas(id) ON DELETE CASCADE,
    rubro_id            INT          NOT NULL REFERENCES rubros_nomina(id),
    cantidad            NUMERIC(10,4) DEFAULT 1,
    valor               NUMERIC(10,2) NOT NULL DEFAULT 0,
    tipo                SMALLINT     DEFAULT 1,  -- 1=ingreso, 2=descuento
    formula_aplicada    VARCHAR(200)
);
-- Referencia migración: erp_det_nomina → nomina_detalles (dnm_rubro=rubro_id, dnm_valor=valor)

-- Préstamos y anticipos a empleados   [NUEVO]
CREATE TABLE prestamos_empleados (
    id                  SERIAL PRIMARY KEY,
    colaborador_id      INT          NOT NULL REFERENCES colaboradores(id),
    tipo                VARCHAR(20)  NOT NULL DEFAULT 'anticipo',  -- 'anticipo','prestamo'
    monto_total         NUMERIC(10,2) NOT NULL,
    saldo               NUMERIC(10,2) NOT NULL,
    cuota               NUMERIC(10,2) DEFAULT 0,
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    descripcion         VARCHAR(300),
    estado              VARCHAR(20)  DEFAULT 'activo',  -- 'activo','pagado'
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Asistencia / Timbre digital   [NUEVO]
CREATE TABLE asistencias (
    id                  BIGSERIAL    PRIMARY KEY,
    colaborador_id      INT          NOT NULL REFERENCES colaboradores(id),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora_entrada        TIMESTAMP,              -- timestamp del SERVIDOR
    hora_salida         TIMESTAMP,
    minutos_atraso      INT          DEFAULT 0,
    horas_extra         NUMERIC(5,2) DEFAULT 0,
    tipo_extra          VARCHAR(20),            -- 'suplementaria','extraordinaria'
    ip_entrada          VARCHAR(45),
    ip_salida           VARCHAR(45),
    observacion         VARCHAR(300)
);

-- Horas extras pendientes de aprobación   [NUEVO]
CREATE TABLE horas_extras_aprobacion (
    id                  SERIAL PRIMARY KEY,
    colaborador_id      INT          NOT NULL REFERENCES colaboradores(id),
    asistencia_id       BIGINT       REFERENCES asistencias(id),
    fecha               DATE         NOT NULL,
    horas_solicitadas   NUMERIC(5,2) NOT NULL,
    horas_aprobadas     NUMERIC(5,2) DEFAULT 0,
    tipo                VARCHAR(20)  NOT NULL,  -- 'suplementaria','extraordinaria'
    valor_calculado     NUMERIC(10,2) DEFAULT 0,
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','aprobado','rechazado'
    aprobado_por        INT          REFERENCES usuarios(id),
    fecha_aprobacion    TIMESTAMP,
    observacion         VARCHAR(300),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Liquidaciones / Finiquitos   [NUEVO]
CREATE TABLE liquidaciones (
    id                  SERIAL PRIMARY KEY,
    colaborador_id      INT          NOT NULL REFERENCES colaboradores(id),
    fecha_salida        DATE         NOT NULL,
    motivo              VARCHAR(50),            -- 'renuncia','despido','fin_contrato'
    decimos_acumulados  NUMERIC(10,2) DEFAULT 0,
    vacaciones          NUMERIC(10,2) DEFAULT 0,
    fondos_reserva      NUMERIC(10,2) DEFAULT 0,
    anticipos_descontar NUMERIC(10,2) DEFAULT 0,
    total_liquidacion   NUMERIC(10,2) DEFAULT 0,
    estado              VARCHAR(20)  DEFAULT 'borrador',
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);


-- ============================================================
--  4. INVENTARIO
-- ============================================================

-- Marcas   [MIGRAR desde erp_marcas]
CREATE TABLE marcas (
    id                  SERIAL PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL UNIQUE,
    logo                VARCHAR(500),
    icono               VARCHAR(50)  DEFAULT 'fa-volume-high',
    estado              BOOLEAN      DEFAULT TRUE
);
-- Referencia migración: erp_marcas → marcas (mar_descripcion=nombre, mar_logo=logo)

-- Categorías de producto   [NUEVO]
CREATE TABLE categorias_producto (
    id                  SERIAL PRIMARY KEY,
    nombre              VARCHAR(100) NOT NULL,
    categoria_padre_id  INT          REFERENCES categorias_producto(id),
    estado              BOOLEAN      DEFAULT TRUE
);

-- Bodegas   [NUEVO - antes implícito en erp_i_mov_inv_pt.bod_id]
CREATE TABLE bodegas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    nombre              VARCHAR(100) NOT NULL,
    tipo                VARCHAR(30)  DEFAULT 'general',  -- 'general','importacion','taller','reserva','cuarentena'
    es_virtual          BOOLEAN      DEFAULT FALSE,
    estado              BOOLEAN      DEFAULT TRUE
);

-- ============================================================
--  NOTA SOBRE created_by / updated_by
-- ============================================================
--  Las tablas principales incluyen created_by y updated_by (FK a usuarios).
--  Esto permite saber de inmediato quién tocó cada registro sin
--  consultar los logs. Es la forma más eficiente para preguntas del día a día:
--  "¿quién creó esta factura?" → factura.created_by
--  "¿quién modificó este cliente?" → cliente.updated_by
--  Los logs de nivel 2 y 3 complementan con el historial completo.
-- ============================================================

-- *** TABLA CENTRAL DE PRODUCTOS - reemplaza erp_mp + erp_mp_set ***   [REPORTE del antiguo]
CREATE TABLE productos (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    marca_id            INT          REFERENCES marcas(id),
    categoria_id        INT          REFERENCES categorias_producto(id),
    -- Identificación
    codigo              VARCHAR(50)  NOT NULL,
    codigo_externo      VARCHAR(50),             -- código del proveedor/importación
    nombre              VARCHAR(300) NOT NULL,
    descripcion         TEXT,
    unidad              VARCHAR(20)  DEFAULT 'unidad',
    -- Clasificación
    tipo                VARCHAR(30)  NOT NULL DEFAULT 'producto',  -- 'producto','servicio','repuesto','insumo'
    requiere_serie      BOOLEAN      DEFAULT FALSE,  -- DJs, computadoras, accesorios
    -- Precios
    costo               NUMERIC(14,4) DEFAULT 0,
    pvp                 NUMERIC(14,4) DEFAULT 0,   -- precio venta público
    pvd                 NUMERIC(14,4) DEFAULT 0,   -- precio venta distribuidor
    descuento_maximo    NUMERIC(5,2)  DEFAULT 0,
    -- Impuestos
    porcentaje_iva      NUMERIC(5,2)  DEFAULT 15,
    tiene_ice           BOOLEAN       DEFAULT FALSE,
    porcentaje_ice      NUMERIC(5,2)  DEFAULT 0,
    -- Inventario
    stock_minimo        NUMERIC(10,2) DEFAULT 0,
    stock_maximo        NUMERIC(10,2) DEFAULT 0,
    -- Contabilidad
    cuenta_inventario   VARCHAR(20),  -- ej: '1.1.4.1'
    cuenta_costo_ventas VARCHAR(20),  -- ej: '5.1.1.1'
    cuenta_ventas       VARCHAR(20),  -- ej: '4.1.1.01'
    -- Referencia importación
    ref_importacion     VARCHAR(100),
    -- Estado
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(empresa_id, codigo)
);
-- NOTA: Los datos de erp_mp tienen columnas genéricas mp_a, mp_b, etc.
-- Se deben mapear manualmente (ej: mp_a=codigo, mp_b=nombre, mp_e=pvp, mp_f=pvd, mp_g=costo)
-- antes de insertar en esta tabla. Se recomienda sacar reporte del sistema antiguo.

-- Series / Números de serie por producto   [NUEVO - antes en erp_etiqueta + mov_serie]
CREATE TABLE producto_series (
    id                  BIGSERIAL    PRIMARY KEY,
    producto_id         INT          NOT NULL REFERENCES productos(id),
    bodega_id           INT          NOT NULL REFERENCES bodegas(id),
    numero_serie        VARCHAR(100) NOT NULL,
    estado              VARCHAR(20)  DEFAULT 'disponible',  -- 'disponible','vendido','reservado','garantia'
    factura_compra_id   INT,                    -- FK a compras (se agrega luego)
    factura_venta_id    INT,                    -- FK a facturas (se agrega luego)
    created_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(numero_serie)
);

-- Saldos de inventario por producto y bodega   [MAPEAR desde erp_i_movpt_total]
CREATE TABLE inventario_saldos (
    id                  SERIAL PRIMARY KEY,
    producto_id         INT          NOT NULL REFERENCES productos(id),
    bodega_id           INT          NOT NULL REFERENCES bodegas(id),
    cantidad            NUMERIC(12,4) NOT NULL DEFAULT 0,
    costo_promedio      NUMERIC(14,4) DEFAULT 0,
    updated_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(producto_id, bodega_id)
);

-- Movimientos de inventario (Kárdex)   [MAPEAR desde erp_i_mov_inv_pt]
CREATE TABLE inventario_movimientos (
    id                  BIGSERIAL    PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    producto_id         INT          NOT NULL REFERENCES productos(id),
    bodega_origen_id    INT          REFERENCES bodegas(id),
    bodega_destino_id   INT          REFERENCES bodegas(id),
    tipo_movimiento     VARCHAR(20)  NOT NULL,  -- 'entrada','salida','traslado','ajuste','reserva'
    -- Documento de origen
    documento_tipo      VARCHAR(20),            -- 'FAC','NC','COMPRA','IMP','OT','AJUSTE'
    documento_id        INT,
    documento_numero    VARCHAR(50),
    -- Cantidades y valores
    cantidad            NUMERIC(12,4) NOT NULL,
    costo_unitario      NUMERIC(14,4) DEFAULT 0,
    costo_total         NUMERIC(14,4) DEFAULT 0,
    -- Serie si aplica
    numero_serie        VARCHAR(100),
    -- Trazabilidad
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora                TIME         DEFAULT CURRENT_TIME,
    usuario_id          INT          REFERENCES usuarios(id),
    observacion         VARCHAR(300),
    created_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_i_mov_inv_pt → inventario_movimientos
-- pro_id=producto_id, bod_id=bodega_destino_id, mov_cantidad=cantidad, mov_val_unit=costo_unitario
-- mov_serie=numero_serie, mov_fecha_trans=fecha

-- Traslados entre bodegas (requieren aprobación)   [NUEVO]
CREATE TABLE traslados_bodega (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    bodega_origen_id    INT          NOT NULL REFERENCES bodegas(id),
    bodega_destino_id   INT          NOT NULL REFERENCES bodegas(id),
    numero              VARCHAR(20),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','aceptado','rechazado'
    enviado_por         INT          REFERENCES usuarios(id),
    recibido_por        INT          REFERENCES usuarios(id),
    fecha_recepcion     TIMESTAMP,
    observacion         VARCHAR(300),
    created_at          TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE traslado_detalles (
    id                  SERIAL PRIMARY KEY,
    traslado_id         INT          NOT NULL REFERENCES traslados_bodega(id) ON DELETE CASCADE,
    producto_id         INT          NOT NULL REFERENCES productos(id),
    numero_serie        VARCHAR(100),
    cantidad_enviada    NUMERIC(12,4) NOT NULL,
    cantidad_recibida   NUMERIC(12,4) DEFAULT 0
);

-- Listas de precios (PVP / PVD por centro de costo)   [NUEVO]
CREATE TABLE listas_precio (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    producto_id         INT          NOT NULL REFERENCES productos(id),
    tipo                VARCHAR(10)  NOT NULL DEFAULT 'PVP',  -- 'PVP','PVD'
    precio              NUMERIC(14,4) NOT NULL DEFAULT 0,
    descuento_max       NUMERIC(5,2) DEFAULT 0,
    vigencia_desde      DATE,
    vigencia_hasta      DATE,
    UNIQUE(empresa_id, producto_id, tipo)
);

-- Activos fijos   [NUEVO]
CREATE TABLE activos_fijos (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    cuenta_id           INT,                    -- FK a plan_cuentas
    nombre              VARCHAR(200) NOT NULL,
    descripcion         TEXT,
    codigo              VARCHAR(50)  UNIQUE,
    fecha_adquisicion   DATE         NOT NULL,
    costo_adquisicion   NUMERIC(14,2) NOT NULL DEFAULT 0,
    vida_util_anios     SMALLINT     DEFAULT 5,
    valor_residual      NUMERIC(14,2) DEFAULT 0,
    depreciacion_acumulada NUMERIC(14,2) DEFAULT 0,
    valor_en_libros     NUMERIC(14,2) DEFAULT 0,
    estado              VARCHAR(20)  DEFAULT 'activo',
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);


-- ============================================================
--  5. CONTABILIDAD
-- ============================================================

-- Plan de cuentas   [MAPEAR desde erp_plan_cuentas]
CREATE TABLE plan_cuentas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    codigo              VARCHAR(30)  NOT NULL,
    descripcion         VARCHAR(300) NOT NULL,
    nivel               SMALLINT     DEFAULT 1,
    cuenta_padre_id     INT          REFERENCES plan_cuentas(id),
    -- Clasificación
    clase               VARCHAR(1),             -- '1'=Activo,'2'=Pasivo,'3'=Patrimonio,'4'=Ingreso,'5'=Gasto
    grupo               VARCHAR(100),
    tipo                VARCHAR(20)  DEFAULT 'detalle',  -- 'grupo','detalle'
    naturaleza          VARCHAR(10)  DEFAULT 'deudora',  -- 'deudora','acreedora'
    -- Control
    permite_asientos    BOOLEAN      DEFAULT TRUE,
    es_cuenta_caja      BOOLEAN      DEFAULT FALSE,
    es_cuenta_banco     BOOLEAN      DEFAULT FALSE,
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(empresa_id, codigo)
);
-- Referencia migración: erp_plan_cuentas → plan_cuentas
-- pln_codigo=codigo, pln_descripcion=descripcion, pln_grupo=grupo

-- Ejercicios contables (apertura/cierre por mes)   [NUEVO - reemplaza erp_periodos]
CREATE TABLE ejercicios_contables (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    anio                SMALLINT     NOT NULL,
    mes                 SMALLINT     NOT NULL CHECK (mes BETWEEN 1 AND 12),
    descripcion         VARCHAR(100),
    fecha_apertura      DATE,
    fecha_cierre        DATE,
    cerrado_por         INT          REFERENCES usuarios(id),
    estado              VARCHAR(20)  DEFAULT 'abierto',  -- 'abierto','cerrado'
    created_at          TIMESTAMP    DEFAULT NOW(),
    UNIQUE(empresa_id, anio, mes)
);

-- Asientos contables (cabecera)   [MAPEAR desde erp_asientos_contables]
CREATE TABLE asientos_contables (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    ejercicio_id        INT          REFERENCES ejercicios_contables(id),
    numero              VARCHAR(20)  NOT NULL,
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    concepto            VARCHAR(500) NOT NULL,
    -- Documento de origen
    documento_tipo      VARCHAR(20),            -- 'FAC','NC','COMPRA','NOM','BANCO','MANUAL'
    documento_id        INT,
    documento_ref       VARCHAR(50),
    -- Totales (deben cuadrar)
    total_debe          NUMERIC(14,4) DEFAULT 0,
    total_haber         NUMERIC(14,4) DEFAULT 0,
    -- Control
    es_automatico       BOOLEAN      DEFAULT TRUE,  -- FALSE = manual del contador
    estado              SMALLINT     DEFAULT 1,     -- 1=activo, 0=anulado
    creado_por          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_asientos_contables → asientos_contables
-- con_asiento=numero, con_concepto=concepto, con_fecha_emision=fecha

-- Líneas del asiento (partida doble)   [NUEVO - el sistema antiguo tenía debe/haber en 1 fila]
CREATE TABLE asiento_detalles (
    id                  BIGSERIAL    PRIMARY KEY,
    asiento_id          INT          NOT NULL REFERENCES asientos_contables(id) ON DELETE CASCADE,
    cuenta_id           INT          NOT NULL REFERENCES plan_cuentas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    descripcion         VARCHAR(300),
    debe                NUMERIC(14,4) DEFAULT 0,
    haber               NUMERIC(14,4) DEFAULT 0
);


-- ============================================================
--  6. VENTAS Y FACTURACIÓN
-- ============================================================

-- Facturas electrónicas SRI   [MAPEAR desde erp_factura]
CREATE TABLE facturas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    cliente_id          INT          NOT NULL REFERENCES clientes(id),
    usuario_id          INT          REFERENCES usuarios(id),
    -- Numeración SRI
    establecimiento     VARCHAR(3)   NOT NULL DEFAULT '001',
    punto_emision       VARCHAR(3)   NOT NULL DEFAULT '001',
    secuencial          VARCHAR(9)   NOT NULL,
    numero_completo     VARCHAR(17),            -- '001-001-000000001'
    -- Fechas
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora_emision        TIME         DEFAULT CURRENT_TIME,
    -- SRI
    clave_acceso        VARCHAR(49),
    autorizacion        VARCHAR(49),
    fecha_hora_aut      VARCHAR(30),
    estado_sri          VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','autorizada','rechazada','anulada'
    observacion_sri     TEXT,
    xml_doc             TEXT,
    -- Cliente en el documento
    tipo_identificacion VARCHAR(5),
    identificacion      VARCHAR(20),
    razon_social        VARCHAR(200),
    email_cliente       VARCHAR(200),
    telefono_cliente    VARCHAR(20),
    direccion_cliente   VARCHAR(300),
    -- Totales
    subtotal_0          NUMERIC(14,4) DEFAULT 0,
    subtotal_15         NUMERIC(14,4) DEFAULT 0,
    subtotal_exento     NUMERIC(14,4) DEFAULT 0,
    descuento_total     NUMERIC(14,4) DEFAULT 0,
    total_ice           NUMERIC(14,4) DEFAULT 0,
    total_iva           NUMERIC(14,4) DEFAULT 0,
    total               NUMERIC(14,4) NOT NULL DEFAULT 0,
    -- Control
    asiento_id          INT          REFERENCES asientos_contables(id),
    guia_remision       VARCHAR(17),
    observaciones       TEXT,
    email_enviado       BOOLEAN      DEFAULT FALSE,
    tipo                SMALLINT     DEFAULT 1,  -- 1=normal, 2=desde_prefactura, 3=desde_OT
    estado              VARCHAR(20)  DEFAULT 'activa',  -- 'activa','anulada'
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_factura → facturas (1:1 con renombre de columnas)

-- Detalle de facturas   [MIGRAR desde erp_det_factura]
CREATE TABLE factura_detalles (
    id                  BIGSERIAL    PRIMARY KEY,
    factura_id          INT          NOT NULL REFERENCES facturas(id) ON DELETE CASCADE,
    producto_id         INT          REFERENCES productos(id),
    codigo_producto     VARCHAR(50),
    descripcion         VARCHAR(500) NOT NULL,
    unidad              VARCHAR(20),
    cantidad            NUMERIC(12,4) NOT NULL DEFAULT 1,
    precio_unitario     NUMERIC(14,4) NOT NULL DEFAULT 0,
    descuento_pct       NUMERIC(5,2)  DEFAULT 0,
    descuento_valor     NUMERIC(14,4) DEFAULT 0,
    subtotal            NUMERIC(14,4) NOT NULL DEFAULT 0,
    porcentaje_iva      NUMERIC(5,2)  DEFAULT 15,
    valor_iva           NUMERIC(14,4) DEFAULT 0,
    valor_ice           NUMERIC(14,4) DEFAULT 0,
    total               NUMERIC(14,4) NOT NULL DEFAULT 0,
    numero_serie        VARCHAR(100),
    costo_unitario      NUMERIC(14,4) DEFAULT 0  -- para cálculo de utilidad
);

-- Pagos de factura (formas de pago)   [MIGRAR desde erp_pagos_factura]
CREATE TABLE factura_pagos (
    id                  SERIAL PRIMARY KEY,
    factura_id          INT          NOT NULL REFERENCES facturas(id) ON DELETE CASCADE,
    forma_pago          VARCHAR(30)  NOT NULL,  -- 'efectivo','transferencia','tarjeta','cheque','credito'
    valor               NUMERIC(14,4) NOT NULL DEFAULT 0,
    dias_credito        SMALLINT     DEFAULT 0,
    fecha_vencimiento   DATE,
    banco               VARCHAR(100),
    num_cheque          VARCHAR(50),
    num_voucher         VARCHAR(50),
    estado              VARCHAR(20)  DEFAULT 'pendiente'  -- 'pendiente','pagado'
);

-- Proformas / Cotizaciones   [MAPEAR desde erp_reg_pedido_venta]
CREATE TABLE proformas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    cliente_id          INT          NOT NULL REFERENCES clientes(id),
    usuario_id          INT          REFERENCES usuarios(id),
    numero              VARCHAR(20),
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    fecha_vencimiento   DATE,
    subtotal            NUMERIC(14,4) DEFAULT 0,
    descuento_total     NUMERIC(14,4) DEFAULT 0,
    total_iva           NUMERIC(14,4) DEFAULT 0,
    total               NUMERIC(14,4) DEFAULT 0,
    observaciones       TEXT,
    -- Conversión a factura
    factura_id          INT          REFERENCES facturas(id),
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','facturada','vencida','anulada'
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE proforma_detalles (
    id                  SERIAL PRIMARY KEY,
    proforma_id         INT          NOT NULL REFERENCES proformas(id) ON DELETE CASCADE,
    producto_id         INT          REFERENCES productos(id),
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            NUMERIC(12,4) NOT NULL DEFAULT 1,
    precio_unitario     NUMERIC(14,4) NOT NULL DEFAULT 0,
    descuento_pct       NUMERIC(5,2)  DEFAULT 0,
    subtotal            NUMERIC(14,4) DEFAULT 0,
    porcentaje_iva      NUMERIC(5,2)  DEFAULT 15,
    total               NUMERIC(14,4) DEFAULT 0
);

-- Prefacturas / Reservas con abonos   [NUEVO]
CREATE TABLE prefacturas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    cliente_id          INT          NOT NULL REFERENCES clientes(id),
    usuario_id          INT          REFERENCES usuarios(id),
    numero              VARCHAR(20),
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    total               NUMERIC(14,4) NOT NULL DEFAULT 0,
    total_abonado       NUMERIC(14,4) DEFAULT 0,
    saldo_pendiente     NUMERIC(14,4) DEFAULT 0,
    asiento_id          INT          REFERENCES asientos_contables(id),
    factura_id          INT          REFERENCES facturas(id),
    observaciones       TEXT,
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','parcial','liquidada','anulada'
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE prefactura_detalles (
    id                  SERIAL PRIMARY KEY,
    prefactura_id       INT          NOT NULL REFERENCES prefacturas(id) ON DELETE CASCADE,
    producto_id         INT          REFERENCES productos(id),
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            NUMERIC(12,4) NOT NULL DEFAULT 1,
    precio_unitario     NUMERIC(14,4) NOT NULL DEFAULT 0,
    total               NUMERIC(14,4) DEFAULT 0
);

CREATE TABLE prefactura_abonos (
    id                  SERIAL PRIMARY KEY,
    prefactura_id       INT          NOT NULL REFERENCES prefacturas(id),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    valor               NUMERIC(14,4) NOT NULL,
    forma_pago          VARCHAR(30),
    banco               VARCHAR(100),
    num_comprobante     VARCHAR(50),
    asiento_id          INT          REFERENCES asientos_contables(id),
    usuario_id          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Notas de crédito   [MIGRAR desde erp_nota_credito + erp_det_nota_credito]
CREATE TABLE notas_credito (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    factura_id          INT          NOT NULL REFERENCES facturas(id),
    cliente_id          INT          NOT NULL REFERENCES clientes(id),
    usuario_id          INT          REFERENCES usuarios(id),
    establecimiento     VARCHAR(3)   NOT NULL DEFAULT '001',
    punto_emision       VARCHAR(3)   NOT NULL DEFAULT '001',
    secuencial          VARCHAR(9)   NOT NULL,
    numero_completo     VARCHAR(17),
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    motivo              VARCHAR(300) NOT NULL,
    tipo                VARCHAR(20)  DEFAULT 'total',    -- 'total','parcial'
    subtotal            NUMERIC(14,4) DEFAULT 0,
    total_iva           NUMERIC(14,4) DEFAULT 0,
    total               NUMERIC(14,4) NOT NULL DEFAULT 0,
    clave_acceso        VARCHAR(49),
    autorizacion        VARCHAR(49),
    estado_sri          VARCHAR(20)  DEFAULT 'pendiente',
    xml_doc             TEXT,
    asiento_id          INT          REFERENCES asientos_contables(id),
    -- Si ya estaba pagada genera saldo a favor
    genera_saldo_favor  BOOLEAN      DEFAULT FALSE,
    saldo_favor         NUMERIC(14,4) DEFAULT 0,
    estado              VARCHAR(20)  DEFAULT 'activa',
    created_at          TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE nota_credito_detalles (
    id                  SERIAL PRIMARY KEY,
    nota_credito_id     INT          NOT NULL REFERENCES notas_credito(id) ON DELETE CASCADE,
    producto_id         INT          REFERENCES productos(id),
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            NUMERIC(12,4) NOT NULL DEFAULT 1,
    precio_unitario     NUMERIC(14,4) NOT NULL DEFAULT 0,
    total               NUMERIC(14,4) DEFAULT 0,
    -- Destino del producto devuelto
    bodega_destino_id   INT          REFERENCES bodegas(id),  -- 'cuarentena/garantias'
    numero_serie        VARCHAR(100)
);

-- Retenciones   [MIGRAR desde erp_retencion + erp_det_retencion]
CREATE TABLE retenciones (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    factura_id          INT          REFERENCES facturas(id),
    compra_id           INT,                    -- FK a compras (se agrega luego)
    cliente_id          INT          REFERENCES clientes(id),
    usuario_id          INT          REFERENCES usuarios(id),
    establecimiento     VARCHAR(3)   NOT NULL DEFAULT '001',
    punto_emision       VARCHAR(3)   NOT NULL DEFAULT '001',
    secuencial          VARCHAR(9)   NOT NULL,
    numero_completo     VARCHAR(17),
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    identificacion      VARCHAR(20),
    razon_social        VARCHAR(200),
    num_comp_retenido   VARCHAR(17),
    total               NUMERIC(14,4) DEFAULT 0,
    clave_acceso        VARCHAR(49),
    autorizacion        VARCHAR(49),
    estado_sri          VARCHAR(20)  DEFAULT 'pendiente',
    xml_doc             TEXT,
    asiento_id          INT          REFERENCES asientos_contables(id),
    estado              VARCHAR(20)  DEFAULT 'activa',
    created_at          TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE retencion_detalles (
    id                  SERIAL PRIMARY KEY,
    retencion_id        INT          NOT NULL REFERENCES retenciones(id) ON DELETE CASCADE,
    impuesto_id         INT          REFERENCES impuestos(id),
    tipo                VARCHAR(10)  NOT NULL,  -- 'IR','IVA'
    codigo              VARCHAR(10),
    porcentaje          NUMERIC(5,2) NOT NULL DEFAULT 0,
    base_imponible      NUMERIC(14,4) NOT NULL DEFAULT 0,
    valor_retenido      NUMERIC(14,4) NOT NULL DEFAULT 0
);

-- Guías de remisión   [MIGRAR desde erp_guia_remision + erp_det_guia]
CREATE TABLE guias_remision (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    factura_id          INT          REFERENCES facturas(id),
    transportista_id    INT          REFERENCES transportistas(id),
    establecimiento     VARCHAR(3)   NOT NULL DEFAULT '001',
    punto_emision       VARCHAR(3)   NOT NULL DEFAULT '001',
    secuencial          VARCHAR(9)   NOT NULL,
    numero_completo     VARCHAR(17),
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    fecha_inicio_transporte DATE,
    fecha_fin_transporte    DATE,
    origen              VARCHAR(300),
    destino             VARCHAR(300),
    ruta                VARCHAR(300),
    motivo              VARCHAR(300),
    clave_acceso        VARCHAR(49),
    autorizacion        VARCHAR(49),
    estado_sri          VARCHAR(20)  DEFAULT 'pendiente',
    xml_doc             TEXT,
    estado              VARCHAR(20)  DEFAULT 'activa',
    created_at          TIMESTAMP    DEFAULT NOW()
);

CREATE TABLE guia_remision_detalles (
    id                  SERIAL PRIMARY KEY,
    guia_id             INT          NOT NULL REFERENCES guias_remision(id) ON DELETE CASCADE,
    producto_id         INT          REFERENCES productos(id),
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            NUMERIC(12,4) NOT NULL DEFAULT 1,
    numero_serie        VARCHAR(100)
);

-- Cuentas por cobrar   [MAPEAR desde erp_ctasxcobrar]
CREATE TABLE cuentas_cobrar (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    cliente_id          INT          NOT NULL REFERENCES clientes(id),
    factura_id          INT          REFERENCES facturas(id),
    prefactura_id       INT          REFERENCES prefacturas(id),
    monto               NUMERIC(14,4) NOT NULL,
    saldo               NUMERIC(14,4) NOT NULL,
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    fecha_vencimiento   DATE         NOT NULL,
    forma_cobro         VARCHAR(30),
    -- Estado
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','parcial','cobrada','vencida','castigada'
    asiento_cobro_id    INT          REFERENCES asientos_contables(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_ctasxcobrar → cuentas_cobrar

-- Cuentas por pagar   [MAPEAR desde erp_ctasxpagar]
CREATE TABLE cuentas_pagar (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    proveedor_id        INT          NOT NULL REFERENCES proveedores(id),
    compra_id           INT,                    -- FK a compras (se agrega luego)
    monto               NUMERIC(14,4) NOT NULL,
    saldo               NUMERIC(14,4) NOT NULL,
    fecha_emision       DATE         NOT NULL DEFAULT CURRENT_DATE,
    fecha_vencimiento   DATE         NOT NULL,
    -- Estado
    aprobada            BOOLEAN      DEFAULT FALSE,
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','parcial','pagada'
    asiento_pago_id     INT          REFERENCES asientos_contables(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_ctasxpagar → cuentas_pagar


-- ============================================================
--  7. BANCOS Y CAJAS
-- ============================================================

-- Catálogo de bancos y cajas   [MIGRAR desde erp_bancos_y_cajas]
CREATE TABLE bancos_cajas (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    cuenta_id           INT          REFERENCES plan_cuentas(id),
    tipo                VARCHAR(20)  NOT NULL,  -- 'banco','caja','caja_chica','tarjeta'
    nombre              VARCHAR(150) NOT NULL,
    num_cuenta          VARCHAR(30),
    tipo_cuenta         VARCHAR(20),            -- 'ahorros','corriente'
    saldo_inicial       NUMERIC(14,4) DEFAULT 0,
    saldo_actual        NUMERIC(14,4) DEFAULT 0,
    estado              BOOLEAN      DEFAULT TRUE,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_bancos_y_cajas → bancos_cajas (byc_tipo=tipo, byc_num_cuenta=num_cuenta)

-- Movimientos bancarios   [MAPEAR desde erp_ctasxcobrar + erp_ctasxpagar + erp_obligacion_pago]
CREATE TABLE movimientos_bancarios (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    banco_caja_id       INT          NOT NULL REFERENCES bancos_cajas(id),
    tipo                VARCHAR(20)  NOT NULL,  -- 'ingreso','egreso'
    sub_tipo            VARCHAR(30),            -- 'transferencia','cheque','efectivo','deposito'
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    monto               NUMERIC(14,4) NOT NULL,
    -- Beneficiario / pagador
    persona_tipo        VARCHAR(20),            -- 'cliente','proveedor','empleado','otro'
    persona_id          INT,
    beneficiario        VARCHAR(200),
    -- Referencia
    num_documento       VARCHAR(50),
    num_cheque          VARCHAR(50),
    fecha_cheque        DATE,
    descripcion         VARCHAR(500),
    -- Documento origen
    documento_tipo      VARCHAR(20),
    documento_id        INT,
    -- Contabilidad
    cuenta_contrapartida_id INT      REFERENCES plan_cuentas(id),
    asiento_id          INT          REFERENCES asientos_contables(id),
    -- Control
    conciliado          BOOLEAN      DEFAULT FALSE,
    es_postfechado      BOOLEAN      DEFAULT FALSE,
    anulado             BOOLEAN      DEFAULT FALSE,
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);

-- Cheques   [MIGRAR desde erp_cheques]
CREATE TABLE cheques (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    banco_caja_id       INT          REFERENCES bancos_cajas(id),
    movimiento_id       INT          REFERENCES movimientos_bancarios(id),
    numero              VARCHAR(20)  NOT NULL,
    banco               VARCHAR(100),
    cuenta              VARCHAR(30),
    monto               NUMERIC(14,4) NOT NULL,
    fecha_emision       DATE,
    fecha_cobro         DATE,
    beneficiario        VARCHAR(200),
    estado              VARCHAR(20)  DEFAULT 'emitido',  -- 'emitido','cobrado','protestado','anulado'
    observacion         VARCHAR(300),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Cierres de caja diarios   [MAPEAR desde erp_cierres]
CREATE TABLE cierres_caja (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    banco_caja_id       INT          NOT NULL REFERENCES bancos_cajas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    usuario_apertura_id INT          REFERENCES usuarios(id),
    usuario_cierre_id   INT          REFERENCES usuarios(id),
    monto_inicial       NUMERIC(14,4) DEFAULT 0,
    total_facturado     NUMERIC(14,4) DEFAULT 0,
    total_cobrado       NUMERIC(14,4) DEFAULT 0,
    total_efectivo      NUMERIC(14,4) DEFAULT 0,
    total_tarjeta       NUMERIC(14,4) DEFAULT 0,
    total_cheque        NUMERIC(14,4) DEFAULT 0,
    total_transferencia NUMERIC(14,4) DEFAULT 0,
    total_notas_credito NUMERIC(14,4) DEFAULT 0,
    diferencia          NUMERIC(14,4) DEFAULT 0,
    observaciones       TEXT,
    estado              VARCHAR(20)  DEFAULT 'abierto',  -- 'abierto','cerrado'
    hora_apertura       TIMESTAMP,
    hora_cierre         TIMESTAMP,
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Lotes Datafast (cierre datáfono)   [NUEVO]
CREATE TABLE datafast_lotes (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    banco_caja_id       INT          NOT NULL REFERENCES bancos_cajas(id),
    numero_lote         VARCHAR(50)  NOT NULL,
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    total_vouchers      NUMERIC(14,4) NOT NULL DEFAULT 0,
    asiento_id          INT          REFERENCES asientos_contables(id),  -- cuenta puente 1.1.1.5
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','liquidado'
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Liquidaciones Datafast (cruce con banco)   [NUEVO]
CREATE TABLE datafast_liquidaciones (
    id                  SERIAL PRIMARY KEY,
    lote_id             INT          NOT NULL REFERENCES datafast_lotes(id),
    fecha_deposito      DATE         NOT NULL,
    valor_bruto         NUMERIC(14,4) NOT NULL DEFAULT 0,
    comision_datafast   NUMERIC(14,4) DEFAULT 0,
    retencion_iva       NUMERIC(14,4) DEFAULT 0,
    retencion_ir        NUMERIC(14,4) DEFAULT 0,
    valor_neto          NUMERIC(14,4) NOT NULL DEFAULT 0,
    banco_destino_id    INT          REFERENCES bancos_cajas(id),
    asiento_id          INT          REFERENCES asientos_contables(id),
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Conciliaciones bancarias   [NUEVO]
CREATE TABLE conciliaciones_bancarias (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    banco_caja_id       INT          NOT NULL REFERENCES bancos_cajas(id),
    fecha_corte         DATE         NOT NULL,
    saldo_banco         NUMERIC(14,4) DEFAULT 0,
    saldo_sistema       NUMERIC(14,4) DEFAULT 0,
    diferencia          NUMERIC(14,4) DEFAULT 0,
    descripcion         VARCHAR(300),
    archivo_csv         VARCHAR(500),
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','conciliada'
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW()
);

-- Partidas no conciliadas   [NUEVO]
CREATE TABLE partidas_transito (
    id                  SERIAL PRIMARY KEY,
    conciliacion_id     INT          NOT NULL REFERENCES conciliaciones_bancarias(id),
    tipo                VARCHAR(20),            -- 'sistema','banco'
    fecha               DATE,
    descripcion         VARCHAR(300),
    monto               NUMERIC(14,4),
    movimiento_id       INT          REFERENCES movimientos_bancarios(id),
    conciliada          BOOLEAN      DEFAULT FALSE,
    asiento_generado_id INT          REFERENCES asientos_contables(id)
);


-- ============================================================
--  8. COMPRAS E IMPORTACIONES
-- ============================================================

-- Compras locales e internacionales   [MAPEAR desde erp_reg_documentos]
CREATE TABLE compras (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    centro_costo_id     INT          REFERENCES centros_costo(id),
    proveedor_id        INT          NOT NULL REFERENCES proveedores(id),
    importacion_id      INT,                    -- FK a importaciones (se agrega luego)
    bodega_id           INT          REFERENCES bodegas(id),
    -- Documento
    tipo_documento      VARCHAR(10)  NOT NULL DEFAULT 'FAC',  -- 'FAC','LIQ','TIK','CON'
    num_documento       VARCHAR(30)  NOT NULL,
    num_autorizacion    VARCHAR(49),
    fecha_emision       DATE         NOT NULL,
    fecha_registro      DATE         NOT NULL DEFAULT CURRENT_DATE,
    fecha_vencimiento   DATE,
    dias_credito        SMALLINT     DEFAULT 0,
    -- Totales
    subtotal_0          NUMERIC(14,4) DEFAULT 0,
    subtotal_iva        NUMERIC(14,4) DEFAULT 0,
    total_iva           NUMERIC(14,4) DEFAULT 0,
    total_ice           NUMERIC(14,4) DEFAULT 0,
    total               NUMERIC(14,4) NOT NULL DEFAULT 0,
    iva_asumido         BOOLEAN      DEFAULT FALSE,
    gasto_no_deducible  BOOLEAN      DEFAULT FALSE,
    -- SRI
    sustento_tributario SMALLINT,
    -- Contabilidad
    asiento_id          INT          REFERENCES asientos_contables(id),
    -- Control (candado: si tiene pago no se puede editar)
    tiene_pago          BOOLEAN      DEFAULT FALSE,
    concepto            VARCHAR(500),
    estado              VARCHAR(20)  DEFAULT 'activa',  -- 'activa','anulada'
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: erp_reg_documentos → compras
-- Agregar FK: ALTER TABLE cuentas_pagar ADD CONSTRAINT fk_cpagar_compra FOREIGN KEY (compra_id) REFERENCES compras(id);
-- Agregar FK: ALTER TABLE retenciones ADD CONSTRAINT fk_retencion_compra FOREIGN KEY (compra_id) REFERENCES compras(id);

ALTER TABLE cuentas_pagar ADD CONSTRAINT fk_cpagar_compra
    FOREIGN KEY (compra_id) REFERENCES compras(id);
ALTER TABLE retenciones ADD CONSTRAINT fk_retencion_compra
    FOREIGN KEY (compra_id) REFERENCES compras(id);

CREATE TABLE compra_detalles (
    id                  SERIAL PRIMARY KEY,
    compra_id           INT          NOT NULL REFERENCES compras(id) ON DELETE CASCADE,
    producto_id         INT          REFERENCES productos(id),
    cuenta_id           INT          REFERENCES plan_cuentas(id),
    descripcion         VARCHAR(500) NOT NULL,
    cantidad            NUMERIC(12,4) DEFAULT 0,
    precio_unitario     NUMERIC(14,4) DEFAULT 0,
    descuento           NUMERIC(14,4) DEFAULT 0,
    subtotal            NUMERIC(14,4) DEFAULT 0,
    porcentaje_iva      NUMERIC(5,2)  DEFAULT 15,
    valor_iva           NUMERIC(14,4) DEFAULT 0,
    total               NUMERIC(14,4) DEFAULT 0,
    es_activo_fijo      BOOLEAN      DEFAULT FALSE,
    activo_fijo_id      INT          REFERENCES activos_fijos(id)
);

-- Importaciones COMEX   [MAPEAR desde lógica del sistema antiguo]
CREATE TABLE importaciones (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    proveedor_id        INT          REFERENCES proveedores(id),
    nombre              VARCHAR(200) NOT NULL,
    num_invoice         VARCHAR(100),
    agente_aduanero     VARCHAR(200),
    pais_embarque       VARCHAR(100),
    costo_fob           NUMERIC(14,4) DEFAULT 0,
    divisa              VARCHAR(10)  DEFAULT 'USD',
    fecha_partida       DATE,
    fecha_llegada       DATE,
    fecha_liquidacion   DATE,
    -- Costos extra (se registran como compras individuales y se prorratean)
    total_costos_extra  NUMERIC(14,4) DEFAULT 0,
    costo_total         NUMERIC(14,4) DEFAULT 0,
    metodo_prorrateo    VARCHAR(20)  DEFAULT 'cantidad',  -- 'cantidad','precio','peso'
    -- Estado
    estado              VARCHAR(20)  DEFAULT 'en_transito',  -- 'en_transito','en_aduana','liquidada'
    observaciones       TEXT,
    created_by          INT          REFERENCES usuarios(id),
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);

ALTER TABLE compras ADD CONSTRAINT fk_compras_importacion
    FOREIGN KEY (importacion_id) REFERENCES importaciones(id);

-- Anticipos a proveedores extranjeros   [NUEVO]
CREATE TABLE anticipos_proveedores (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    proveedor_id        INT          NOT NULL REFERENCES proveedores(id),
    importacion_id      INT          REFERENCES importaciones(id),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    monto               NUMERIC(14,4) NOT NULL,
    saldo               NUMERIC(14,4) NOT NULL,
    banco_id            INT          REFERENCES bancos_cajas(id),
    num_transferencia   VARCHAR(50),
    asiento_id          INT          REFERENCES asientos_contables(id),
    estado              VARCHAR(20)  DEFAULT 'pendiente',  -- 'pendiente','cruzado'
    created_at          TIMESTAMP    DEFAULT NOW()
);


-- ============================================================
--  9. TALLER (ALTAMIRA FIX)
-- ============================================================

-- Tipos de equipo de taller   [MIGRAR desde eqp_tipo]
CREATE TABLE taller_tipos_equipo (
    id                  SERIAL PRIMARY KEY,
    descripcion         VARCHAR(100) NOT NULL,
    estado              BOOLEAN      DEFAULT TRUE
);
-- Referencia migración: eqp_tipo → taller_tipos_equipo (tip_id=id, tip_descripcion=descripcion)

-- Equipos registrados   [MIGRAR desde eqp_equipos]
CREATE TABLE taller_equipos (
    id                  SERIAL PRIMARY KEY,
    tipo_id             INT          REFERENCES taller_tipos_equipo(id),
    marca               VARCHAR(100),
    modelo              VARCHAR(100),
    numero_serie        VARCHAR(100),
    color               VARCHAR(50),
    medida              VARCHAR(50),
    adicional           VARCHAR(200),
    observaciones       TEXT,
    estado              SMALLINT     DEFAULT 1
);
-- Referencia migración: eqp_equipos → taller_equipos (1:1 directo)

-- Ingreso de equipos al taller   [MIGRAR desde eqp_registro_ingresos]
CREATE TABLE taller_ingresos (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    cliente_id          INT          NOT NULL REFERENCES clientes(id),
    equipo_id           INT          NOT NULL REFERENCES taller_equipos(id),
    usuario_id          INT          REFERENCES usuarios(id),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora                TIME         DEFAULT CURRENT_TIME,
    diagnostico_inicial TEXT,
    imagen              VARCHAR(500),
    video               VARCHAR(500),
    observaciones       TEXT,
    estado              SMALLINT     DEFAULT 0,  -- 0=ingresado,1=en_diagnostico,2=en_proceso,3=listo,4=entregado
    created_at          TIMESTAMP    DEFAULT NOW()
);
-- Referencia migración: eqp_registro_ingresos → taller_ingresos (rgi_cli_id=cliente_id, rgi_eqp_id=equipo_id)

-- Órdenes de trabajo   [MAPEAR desde eqp_orden_trabajo]
CREATE TABLE taller_ordenes_trabajo (
    id                  SERIAL PRIMARY KEY,
    empresa_id          INT          NOT NULL REFERENCES empresas(id),
    ingreso_id          INT          NOT NULL REFERENCES taller_ingresos(id),
    tecnico_id          INT          REFERENCES usuarios(id),
    numero              VARCHAR(20),
    fecha_inicio        DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora_inicio         TIME         DEFAULT CURRENT_TIME,
    fecha_fin_estimada  DATE,
    fecha_fin_real      DATE,
    tipo_orden          SMALLINT     DEFAULT 1,  -- 1=reparacion,2=garantia,3=revision
    descripcion_trabajo TEXT,
    costo_mano_obra     NUMERIC(12,2) DEFAULT 0,
    costo_repuestos     NUMERIC(12,2) DEFAULT 0,
    costo_total         NUMERIC(12,2) DEFAULT 0,
    -- Contabilidad (garantías → gasto 5.1.1.4)
    es_garantia         BOOLEAN      DEFAULT FALSE,
    factura_id          INT          REFERENCES facturas(id),
    asiento_id          INT          REFERENCES asientos_contables(id),
    -- Estado: 'pendiente','en_proceso','listo','entregado','facturado','garantia'
    estado              VARCHAR(20)  DEFAULT 'pendiente',
    observaciones       TEXT,
    created_at          TIMESTAMP    DEFAULT NOW(),
    updated_at          TIMESTAMP    DEFAULT NOW()
);

-- Repuestos usados en OT   [NUEVO]
CREATE TABLE taller_ot_repuestos (
    id                  SERIAL PRIMARY KEY,
    orden_id            INT          NOT NULL REFERENCES taller_ordenes_trabajo(id) ON DELETE CASCADE,
    producto_id         INT          NOT NULL REFERENCES productos(id),
    numero_serie        VARCHAR(100),
    cantidad            NUMERIC(10,4) NOT NULL DEFAULT 1,
    costo_unitario      NUMERIC(14,4) DEFAULT 0,
    precio_venta        NUMERIC(14,4) DEFAULT 0,
    estado              VARCHAR(20)  DEFAULT 'reservado'  -- 'reservado','usado','devuelto'
);

-- Diagnósticos técnicos   [MIGRAR desde eqp_diagnostico_tecnico]
CREATE TABLE taller_diagnosticos (
    id                  SERIAL PRIMARY KEY,
    orden_id            INT          NOT NULL REFERENCES taller_ordenes_trabajo(id),
    tecnico_id          INT          REFERENCES usuarios(id),
    fecha               DATE         NOT NULL DEFAULT CURRENT_DATE,
    hora                TIME         DEFAULT CURRENT_TIME,
    diagnostico         TEXT         NOT NULL,
    tiempo_estimado     SMALLINT,
    tipo_tiempo         VARCHAR(20),            -- 'horas','dias'
    -- Aprobación del cliente
    cliente_aprueba     BOOLEAN      DEFAULT NULL,
    fecha_aprobacion    TIMESTAMP,
    observacion_aprobacion TEXT,
    usuario_aprobacion_id  INT          REFERENCES usuarios(id),
    estado              VARCHAR(20)  DEFAULT 'pendiente'  -- 'pendiente','aprobado','rechazado'
);


-- ============================================================
--  10. ÍNDICES PRINCIPALES
-- ============================================================

-- Facturación (búsquedas frecuentes)
CREATE INDEX idx_facturas_empresa       ON facturas(empresa_id);
CREATE INDEX idx_facturas_cliente       ON facturas(cliente_id);
CREATE INDEX idx_facturas_fecha         ON facturas(fecha_emision);
CREATE INDEX idx_facturas_estado        ON facturas(estado);
CREATE INDEX idx_facturas_clave_acceso  ON facturas(clave_acceso);

-- Inventario
CREATE INDEX idx_inv_movimientos_producto ON inventario_movimientos(producto_id);
CREATE INDEX idx_inv_movimientos_fecha    ON inventario_movimientos(fecha);
CREATE INDEX idx_inv_saldos_producto      ON inventario_saldos(producto_id);
CREATE INDEX idx_productos_codigo         ON productos(empresa_id, codigo);
CREATE INDEX idx_producto_series_serie    ON producto_series(numero_serie);

-- Contabilidad
CREATE INDEX idx_asientos_empresa   ON asientos_contables(empresa_id);
CREATE INDEX idx_asientos_fecha     ON asientos_contables(fecha);
CREATE INDEX idx_asientos_ejercicio ON asientos_contables(ejercicio_id);

-- CxC / CxP
CREATE INDEX idx_cxc_cliente        ON cuentas_cobrar(cliente_id);
CREATE INDEX idx_cxc_vencimiento    ON cuentas_cobrar(fecha_vencimiento);
CREATE INDEX idx_cxp_proveedor      ON cuentas_pagar(proveedor_id);
CREATE INDEX idx_cxp_vencimiento    ON cuentas_pagar(fecha_vencimiento);

-- Auditoría — log_sesiones
CREATE INDEX idx_log_ses_usuario    ON log_sesiones(usuario_id);
CREATE INDEX idx_log_ses_fecha      ON log_sesiones(fecha);
CREATE INDEX idx_log_ses_tipo       ON log_sesiones(tipo);
CREATE INDEX idx_log_ses_ip         ON log_sesiones(ip);

-- Auditoría — log_documentos
CREATE INDEX idx_log_doc_usuario    ON log_documentos(usuario_id);
CREATE INDEX idx_log_doc_fecha      ON log_documentos(fecha);
CREATE INDEX idx_log_doc_modulo     ON log_documentos(modulo);
CREATE INDEX idx_log_doc_registro   ON log_documentos(tabla, registro_id);
CREATE INDEX idx_log_doc_accion     ON log_documentos(accion);

-- Auditoría — log_cambios_criticos
CREATE INDEX idx_log_cam_usuario    ON log_cambios_criticos(usuario_id);
CREATE INDEX idx_log_cam_fecha      ON log_cambios_criticos(fecha);
CREATE INDEX idx_log_cam_registro   ON log_cambios_criticos(tabla, registro_id);
CREATE INDEX idx_log_cam_campo      ON log_cambios_criticos(campo);

-- Aprobaciones especiales
CREATE INDEX idx_aprobaciones_tipo       ON aprobaciones_especiales(tipo_aprobacion_id);
CREATE INDEX idx_aprobaciones_solicitante ON aprobaciones_especiales(solicitante_id);
CREATE INDEX idx_aprobaciones_aprobador  ON aprobaciones_especiales(aprobador_id);
CREATE INDEX idx_aprobaciones_documento  ON aprobaciones_especiales(documento_tipo, documento_id);
CREATE INDEX idx_aprobaciones_fecha      ON aprobaciones_especiales(fecha);

-- Clientes / Proveedores
CREATE INDEX idx_clientes_identificacion    ON clientes(identificacion);
CREATE INDEX idx_proveedores_identificacion ON proveedores(identificacion);

-- Taller
CREATE INDEX idx_ot_estado          ON taller_ordenes_trabajo(estado);
CREATE INDEX idx_ot_tecnico         ON taller_ordenes_trabajo(tecnico_id);

-- Nómina
CREATE INDEX idx_nominas_colaborador ON nominas(colaborador_id);
CREATE INDEX idx_nominas_periodo     ON nominas(periodo);

-- Asistencia
CREATE INDEX idx_asistencias_colaborador ON asistencias(colaborador_id);
CREATE INDEX idx_asistencias_fecha       ON asistencias(fecha);

-- Notificaciones
CREATE INDEX idx_notif_usuario      ON notificaciones(usuario_id);
CREATE INDEX idx_notif_leida        ON notificaciones(leida);


-- ============================================================
--  RESUMEN DE MIGRACIÓN
-- ============================================================
/*
  TABLAS ANTIGUAS → TABLAS NUEVAS (mapeo principal)
  --------------------------------------------------
  erp_emisor            → empresas
  erp_empresa           → empresas (enc1..5 = datos del encabezado)
  erp_i_cliente         → clientes
  erp_transportista     → transportistas
  erp_marcas            → marcas
  erp_plan_cuentas      → plan_cuentas
  erp_asientos_contables→ asientos_contables + asiento_detalles
  erp_periodos          → ejercicios_contables
  erp_factura           → facturas
  erp_det_factura       → factura_detalles
  erp_pagos_factura     → factura_pagos
  erp_nota_credito      → notas_credito
  erp_det_nota_credito  → nota_credito_detalles
  erp_retencion         → retenciones
  erp_det_retencion     → retencion_detalles
  erp_guia_remision     → guias_remision
  erp_det_guia          → guia_remision_detalles
  erp_ctasxcobrar       → cuentas_cobrar
  erp_ctasxpagar        → cuentas_pagar
  erp_bancos_y_cajas    → bancos_cajas
  erp_cheques           → cheques
  erp_cierres           → cierres_caja
  erp_arqueo_caja       → cierres_caja (campos adicionales)
  erp_reg_documentos    → compras
  erp_reg_det_documentos→ compra_detalles
  erp_nomina            → nominas
  erp_det_nomina        → nomina_detalles
  erp_rubros_nomina     → rubros_nomina
  par_empleados         → colaboradores
  par_puestos_trabajo   → puestos_trabajo
  erp_horarios          → horarios
  erp_users             → usuarios
  erp_auditoria         → audit_log
  erp_impuestos         → impuestos
  erp_configuraciones   → configuraciones
  erp_transportista     → transportistas
  eqp_tipo              → taller_tipos_equipo
  eqp_equipos           → taller_equipos
  eqp_registro_ingresos → taller_ingresos
  eqp_orden_trabajo     → taller_ordenes_trabajo
  eqp_diagnostico_tecnico → taller_diagnosticos
  erp_i_mov_inv_pt      → inventario_movimientos
  erp_i_movpt_total     → inventario_saldos
  erp_etiqueta          → producto_series

  TABLAS ANTIGUAS → SOLO REPORTE (no migrar datos)
  --------------------------------------------------
  erp_mp / erp_mp_set   → productos (cargar manual o desde reporte Excel)
  erp_ctas_asientos     → parametros_contables (reconfigurar en nuevo sistema)
  erp_secuencial        → secuenciales (reconfigurar)

  TABLAS ANTIGUAS DESCARTADAS
  --------------------------------------------------
  clientes_online, pedidos_online, webcam, sugerencias, tickets,
  erp_sms, erp_sol_acceso, erp_multimedia, erp_subseccion,
  erp_division, erp_gerencia, par_dias_extraordinarios,
  par_reg_premisos_vacaciones, par_secciones, porcentages_retencion,
  contador_facturas, egreso_movimientos, egreso_tipos,
  erp_asg_option_list, erp_option_list, erp_criterios_permiso,
  erp_reg_pedido_venta, erp_det_ped_venta, erp_pagos_pedventa
*/
