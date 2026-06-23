--
-- PostgreSQL database dump
--

\restrict dbOXqTXgY9FqjlpGVP0SkDMgTNearrInoPCDJ3tZ97GNt1gfM4T7NMWnMYWuQ5L

-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activos_depreciaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activos_depreciaciones (
    id bigint NOT NULL,
    activo_id bigint NOT NULL,
    "periodo_año" integer NOT NULL,
    periodo_mes integer NOT NULL,
    monto numeric(14,2) NOT NULL,
    depreciacion_acumulada_al_periodo numeric(14,2) NOT NULL,
    valor_libro_al_periodo numeric(14,2) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: activos_depreciaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activos_depreciaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activos_depreciaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activos_depreciaciones_id_seq OWNED BY public.activos_depreciaciones.id;


--
-- Name: activos_fijos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activos_fijos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    codigo character varying(50) NOT NULL,
    nombre character varying(255) NOT NULL,
    descripcion text,
    categoria character varying(100) NOT NULL,
    ubicacion character varying(255),
    fecha_adquisicion date NOT NULL,
    costo_adquisicion numeric(14,2) NOT NULL,
    valor_residual numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    vida_util_anios integer NOT NULL,
    metodo_depreciacion character varying(30) DEFAULT 'lineal'::character varying NOT NULL,
    depreciacion_acumulada numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    valor_en_libros numeric(14,2) NOT NULL,
    estado character varying(20) DEFAULT 'activo'::character varying NOT NULL,
    cuenta_id bigint,
    cuenta_depreciacion_id bigint,
    notas text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT activos_fijos_estado_check CHECK (((estado)::text = ANY ((ARRAY['activo'::character varying, 'dado_de_baja'::character varying, 'vendido'::character varying])::text[])))
);


--
-- Name: activos_fijos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activos_fijos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activos_fijos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activos_fijos_id_seq OWNED BY public.activos_fijos.id;


--
-- Name: anticipos_proveedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.anticipos_proveedores (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    proveedor_id bigint NOT NULL,
    importacion_id bigint,
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    monto numeric(14,4) NOT NULL,
    saldo numeric(14,4) NOT NULL,
    banco_id bigint,
    num_transferencia character varying(50),
    asiento_id bigint,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: anticipos_proveedores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.anticipos_proveedores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: anticipos_proveedores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.anticipos_proveedores_id_seq OWNED BY public.anticipos_proveedores.id;


--
-- Name: aprobaciones_especiales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aprobaciones_especiales (
    id bigint NOT NULL,
    tipo_aprobacion_id bigint NOT NULL,
    aprobado_por bigint NOT NULL,
    solicitado_por bigint NOT NULL,
    empresa_id bigint NOT NULL,
    tabla_referencia character varying(255),
    registro_id bigint,
    descripcion text,
    valor_aprobado numeric(10,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: aprobaciones_especiales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.aprobaciones_especiales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: aprobaciones_especiales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.aprobaciones_especiales_id_seq OWNED BY public.aprobaciones_especiales.id;


--
-- Name: asiento_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asiento_detalles (
    id bigint NOT NULL,
    asiento_id bigint NOT NULL,
    cuenta_id bigint NOT NULL,
    centro_costo_id bigint,
    descripcion character varying(300),
    debe numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    haber numeric(14,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: asiento_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asiento_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asiento_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asiento_detalles_id_seq OWNED BY public.asiento_detalles.id;


--
-- Name: asientos_contables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asientos_contables (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    ejercicio_id bigint,
    numero character varying(20) NOT NULL,
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    concepto character varying(500) NOT NULL,
    documento_tipo character varying(20),
    documento_id integer,
    documento_ref character varying(50),
    total_debe numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_haber numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    es_automatico boolean DEFAULT true NOT NULL,
    estado smallint DEFAULT '1'::smallint NOT NULL,
    creado_por bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: asientos_contables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asientos_contables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asientos_contables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asientos_contables_id_seq OWNED BY public.asientos_contables.id;


--
-- Name: asistencias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.asistencias (
    id bigint NOT NULL,
    colaborador_id bigint NOT NULL,
    fecha date DEFAULT '2026-06-16'::date NOT NULL,
    hora_entrada timestamp(0) without time zone,
    hora_salida timestamp(0) without time zone,
    minutos_atraso integer DEFAULT 0 NOT NULL,
    horas_extra numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tipo_extra character varying(20),
    ip_entrada character varying(45),
    ip_salida character varying(45),
    observacion character varying(300)
);


--
-- Name: asistencias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.asistencias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: asistencias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.asistencias_id_seq OWNED BY public.asistencias.id;


--
-- Name: bancos_cajas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bancos_cajas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    cuenta_id bigint,
    tipo character varying(20) NOT NULL,
    nombre character varying(150) NOT NULL,
    num_cuenta character varying(30),
    tipo_cuenta character varying(20),
    saldo_inicial numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    saldo_actual numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: bancos_cajas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bancos_cajas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bancos_cajas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bancos_cajas_id_seq OWNED BY public.bancos_cajas.id;


--
-- Name: bodegas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bodegas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    centro_costo_id bigint,
    nombre character varying(150) NOT NULL,
    tipo character varying(50) DEFAULT 'general'::character varying NOT NULL,
    descripcion text,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    es_virtual boolean DEFAULT false NOT NULL
);


--
-- Name: bodegas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bodegas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bodegas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bodegas_id_seq OWNED BY public.bodegas.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: categorias_producto; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias_producto (
    id bigint NOT NULL,
    empresa_id bigint,
    categoria_padre_id bigint,
    nombre character varying(150) NOT NULL,
    descripcion text,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: categorias_producto_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_producto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categorias_producto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_producto_id_seq OWNED BY public.categorias_producto.id;


--
-- Name: centros_costo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.centros_costo (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    codigo character varying(20) NOT NULL,
    tipo character varying(255) DEFAULT 'empresa'::character varying NOT NULL,
    es_taller boolean DEFAULT false NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT centros_costo_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['empresa'::character varying, 'sucursal'::character varying, 'centro_costo_interno'::character varying])::text[])))
);


--
-- Name: centros_costo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.centros_costo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: centros_costo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.centros_costo_id_seq OWNED BY public.centros_costo.id;


--
-- Name: cheques; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cheques (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    banco_caja_id bigint,
    movimiento_id bigint,
    numero character varying(20) NOT NULL,
    banco character varying(100),
    cuenta character varying(30),
    monto numeric(14,4) NOT NULL,
    fecha_emision date,
    fecha_cobro date,
    beneficiario character varying(200),
    estado character varying(20) DEFAULT 'emitido'::character varying NOT NULL,
    observacion character varying(300),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: cheques_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cheques_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cheques_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cheques_id_seq OWNED BY public.cheques.id;


--
-- Name: cierres_caja; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cierres_caja (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    banco_caja_id bigint NOT NULL,
    centro_costo_id bigint,
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    usuario_apertura_id bigint,
    usuario_cierre_id bigint,
    monto_inicial numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_facturado numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_cobrado numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_efectivo numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_tarjeta numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_cheque numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_transferencia numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_notas_credito numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    diferencia numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    observaciones text,
    estado character varying(20) DEFAULT 'abierto'::character varying NOT NULL,
    hora_apertura timestamp(0) without time zone,
    hora_cierre timestamp(0) without time zone,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: cierres_caja_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cierres_caja_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cierres_caja_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cierres_caja_id_seq OWNED BY public.cierres_caja.id;


--
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.clientes (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    tipo_identificacion character varying(5) DEFAULT '05'::character varying NOT NULL,
    identificacion character varying(20) NOT NULL,
    razon_social character varying(200) NOT NULL,
    nombre_comercial character varying(200),
    email character varying(200),
    telefono character varying(20),
    celular character varying(20),
    direccion character varying(300),
    ciudad character varying(100),
    provincia character varying(100),
    pais character varying(100) DEFAULT 'ECUADOR'::character varying NOT NULL,
    tiene_credito boolean DEFAULT false NOT NULL,
    dias_credito smallint DEFAULT '0'::smallint NOT NULL,
    cupo_maximo numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    agente_retencion boolean DEFAULT false NOT NULL,
    es_cliente_nuevo boolean DEFAULT false NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: clientes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: clientes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_seq OWNED BY public.clientes.id;


--
-- Name: colaboradores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.colaboradores (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    puesto_id bigint,
    horario_id bigint,
    cedula_ruc character varying(13) NOT NULL,
    apellidos character varying(100) NOT NULL,
    nombres character varying(100) NOT NULL,
    email character varying(200),
    telefono character varying(20),
    celular character varying(20),
    direccion character varying(300),
    fecha_nacimiento date,
    sexo character(1),
    estado_civil character varying(20),
    fecha_ingreso date NOT NULL,
    fecha_salida date,
    tipo_contrato character varying(50),
    cargo character varying(100),
    departamento character varying(100),
    comision_porcentaje numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    sueldo_base numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    decimo_tercero character varying(10) DEFAULT 'acumula'::character varying NOT NULL,
    decimo_cuarto character varying(10) DEFAULT 'acumula'::character varying NOT NULL,
    fondos_reserva character varying(10) DEFAULT 'acumula'::character varying NOT NULL,
    banco character varying(100),
    tipo_cuenta character varying(20),
    numero_cuenta character varying(30),
    usuario_id bigint,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: colaboradores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.colaboradores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: colaboradores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.colaboradores_id_seq OWNED BY public.colaboradores.id;


--
-- Name: compra_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.compra_detalles (
    id bigint NOT NULL,
    compra_id bigint NOT NULL,
    producto_id bigint,
    cuenta_id bigint,
    descripcion character varying(500) NOT NULL,
    cantidad numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    precio_unitario numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    subtotal numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    porcentaje_iva numeric(5,2) DEFAULT '15'::numeric NOT NULL,
    valor_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    es_activo_fijo boolean DEFAULT false NOT NULL,
    activo_fijo_id bigint
);


--
-- Name: compra_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compra_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: compra_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compra_detalles_id_seq OWNED BY public.compra_detalles.id;


--
-- Name: compras; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.compras (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    centro_costo_id bigint,
    proveedor_id bigint NOT NULL,
    importacion_id bigint,
    bodega_id bigint,
    tipo_documento character varying(10) DEFAULT 'FAC'::character varying NOT NULL,
    num_documento character varying(30) NOT NULL,
    num_autorizacion character varying(49),
    fecha_emision date NOT NULL,
    fecha_registro date DEFAULT CURRENT_DATE NOT NULL,
    fecha_vencimiento date,
    dias_credito smallint DEFAULT '0'::smallint NOT NULL,
    subtotal_0 numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    subtotal_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_ice numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    iva_asumido boolean DEFAULT false NOT NULL,
    gasto_no_deducible boolean DEFAULT false NOT NULL,
    sustento_tributario smallint,
    asiento_id bigint,
    tiene_pago boolean DEFAULT false NOT NULL,
    concepto character varying(500),
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: compras_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compras_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: compras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compras_id_seq OWNED BY public.compras.id;


--
-- Name: conciliaciones_bancarias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.conciliaciones_bancarias (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    banco_caja_id bigint NOT NULL,
    fecha_corte date NOT NULL,
    saldo_banco numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    saldo_sistema numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    diferencia numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descripcion character varying(300),
    archivo_csv character varying(500),
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: conciliaciones_bancarias_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.conciliaciones_bancarias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: conciliaciones_bancarias_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.conciliaciones_bancarias_id_seq OWNED BY public.conciliaciones_bancarias.id;


--
-- Name: configuraciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuraciones (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    clave character varying(100) NOT NULL,
    valor text,
    tipo character varying(20) DEFAULT 'string'::character varying NOT NULL,
    descripcion character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN configuraciones.tipo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.configuraciones.tipo IS 'string, integer, boolean, json';


--
-- Name: configuraciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuraciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: configuraciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuraciones_id_seq OWNED BY public.configuraciones.id;


--
-- Name: cuentas_cobrar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_cobrar (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    cliente_id integer NOT NULL,
    factura_id integer,
    prefactura_id integer,
    monto numeric(14,4) NOT NULL,
    saldo numeric(14,4) NOT NULL,
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    fecha_vencimiento date NOT NULL,
    forma_cobro character varying(30),
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    asiento_cobro_id integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cuentas_cobrar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_cobrar_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_cobrar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_cobrar_id_seq OWNED BY public.cuentas_cobrar.id;


--
-- Name: cuentas_pagar; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cuentas_pagar (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    proveedor_id bigint NOT NULL,
    compra_id bigint,
    monto numeric(14,4) NOT NULL,
    saldo numeric(14,4) NOT NULL,
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    fecha_vencimiento date NOT NULL,
    aprobada boolean DEFAULT false NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    asiento_pago_id bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: cuentas_pagar_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cuentas_pagar_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cuentas_pagar_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cuentas_pagar_id_seq OWNED BY public.cuentas_pagar.id;


--
-- Name: datafast_liquidaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.datafast_liquidaciones (
    id bigint NOT NULL,
    lote_id bigint NOT NULL,
    fecha_deposito date NOT NULL,
    valor_bruto numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    comision_datafast numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    retencion_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    retencion_ir numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    valor_neto numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    banco_destino_id bigint,
    asiento_id bigint,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: datafast_liquidaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.datafast_liquidaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: datafast_liquidaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.datafast_liquidaciones_id_seq OWNED BY public.datafast_liquidaciones.id;


--
-- Name: datafast_lotes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.datafast_lotes (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    banco_caja_id bigint NOT NULL,
    numero_lote character varying(50) NOT NULL,
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    total_vouchers numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    asiento_id bigint,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: datafast_lotes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.datafast_lotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: datafast_lotes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.datafast_lotes_id_seq OWNED BY public.datafast_lotes.id;


--
-- Name: ejercicios_contables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ejercicios_contables (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    anio smallint NOT NULL,
    mes smallint NOT NULL,
    descripcion character varying(100),
    fecha_apertura date,
    fecha_cierre date,
    cerrado_por bigint,
    estado character varying(20) DEFAULT 'abierto'::character varying NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_mes_valido CHECK (((mes >= 1) AND (mes <= 12)))
);


--
-- Name: ejercicios_contables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ejercicios_contables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ejercicios_contables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ejercicios_contables_id_seq OWNED BY public.ejercicios_contables.id;


--
-- Name: empresa_usuario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresa_usuario (
    empresa_id bigint NOT NULL,
    usuario_id bigint NOT NULL
);


--
-- Name: empresas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.empresas (
    id bigint NOT NULL,
    razon_social character varying(255) NOT NULL,
    nombre_comercial character varying(255) NOT NULL,
    ruc character varying(13) NOT NULL,
    direccion_matriz character varying(255),
    direccion_establecimiento character varying(255),
    email_notificaciones character varying(255),
    telefono character varying(20),
    logo character varying(255),
    slogan character varying(255),
    ambiente_sri character varying(255) DEFAULT '1'::character varying NOT NULL,
    codigo_establecimiento character varying(3) DEFAULT '001'::character varying NOT NULL,
    codigo_punto_emision character varying(3) DEFAULT '001'::character varying NOT NULL,
    obligado_contabilidad boolean DEFAULT false NOT NULL,
    contribuyente_especial boolean DEFAULT false NOT NULL,
    numero_resolucion_agente_retencion character varying(255),
    firma_electronica bytea,
    clave_firma character varying(255),
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT empresas_ambiente_sri_check CHECK (((ambiente_sri)::text = ANY ((ARRAY['1'::character varying, '2'::character varying])::text[])))
);


--
-- Name: COLUMN empresas.ambiente_sri; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.empresas.ambiente_sri IS '1=Pruebas, 2=Produccion';


--
-- Name: empresas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: empresas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresas_id_seq OWNED BY public.empresas.id;


--
-- Name: erp_plan_cuentas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.erp_plan_cuentas (
    pln_id integer NOT NULL,
    pln_codigo character varying,
    pln_descripcion character varying,
    pln_obs character varying,
    pln_estado integer DEFAULT 0,
    pln_grupo character varying
);


--
-- Name: etiquetas_productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.etiquetas_productos (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    compra_id integer NOT NULL,
    compra_detalle_id integer,
    producto_id integer NOT NULL,
    codigo_producto character varying(50) NOT NULL,
    correlativo_desde integer NOT NULL,
    correlativo_hasta integer NOT NULL,
    cantidad integer NOT NULL,
    generado_por integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: etiquetas_productos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.etiquetas_productos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: etiquetas_productos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.etiquetas_productos_id_seq OWNED BY public.etiquetas_productos.id;


--
-- Name: factura_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.factura_detalles (
    id bigint NOT NULL,
    factura_id integer NOT NULL,
    producto_id integer,
    codigo_producto character varying(50),
    descripcion character varying(500) NOT NULL,
    unidad character varying(20),
    cantidad numeric(12,4) DEFAULT '1'::numeric NOT NULL,
    precio_unitario numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    descuento_valor numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    subtotal numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    porcentaje_iva numeric(5,2) DEFAULT '15'::numeric NOT NULL,
    valor_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    valor_ice numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    numero_serie character varying(100),
    costo_unitario numeric(14,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: factura_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.factura_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: factura_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.factura_detalles_id_seq OWNED BY public.factura_detalles.id;


--
-- Name: factura_pagos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.factura_pagos (
    id bigint NOT NULL,
    factura_id integer NOT NULL,
    forma_pago character varying(30) NOT NULL,
    valor numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    dias_credito smallint DEFAULT '0'::smallint NOT NULL,
    fecha_vencimiento date,
    banco character varying(100),
    num_cheque character varying(50),
    num_voucher character varying(50),
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL
);


--
-- Name: factura_pagos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.factura_pagos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: factura_pagos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.factura_pagos_id_seq OWNED BY public.factura_pagos.id;


--
-- Name: facturas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.facturas (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    centro_costo_id integer,
    cliente_id integer NOT NULL,
    usuario_id integer,
    establecimiento character varying(3) DEFAULT '001'::character varying NOT NULL,
    punto_emision character varying(3) DEFAULT '001'::character varying NOT NULL,
    secuencial character varying(9) NOT NULL,
    numero_completo character varying(17),
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    hora_emision time(0) without time zone,
    clave_acceso character varying(49),
    autorizacion character varying(49),
    fecha_hora_aut character varying(30),
    estado_sri character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    observacion_sri text,
    xml_doc text,
    tipo_identificacion character varying(5),
    identificacion character varying(20),
    razon_social character varying(200),
    email_cliente character varying(200),
    telefono_cliente character varying(20),
    direccion_cliente character varying(300),
    subtotal_0 numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    subtotal_15 numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    subtotal_exento numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento_total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_ice numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    asiento_id integer,
    guia_remision character varying(17),
    observaciones text,
    email_enviado boolean DEFAULT false NOT NULL,
    tipo smallint DEFAULT '1'::smallint NOT NULL,
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    tiene_descuento_especial boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: facturas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.facturas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: facturas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.facturas_id_seq OWNED BY public.facturas.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: guia_remision_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.guia_remision_detalles (
    id bigint NOT NULL,
    guia_id integer NOT NULL,
    producto_id integer,
    descripcion character varying(500) NOT NULL,
    cantidad numeric(12,4) DEFAULT '1'::numeric NOT NULL,
    numero_serie character varying(100)
);


--
-- Name: guia_remision_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.guia_remision_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: guia_remision_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.guia_remision_detalles_id_seq OWNED BY public.guia_remision_detalles.id;


--
-- Name: guias_remision; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.guias_remision (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    factura_id integer,
    transportista_id integer,
    establecimiento character varying(3) DEFAULT '001'::character varying NOT NULL,
    punto_emision character varying(3) DEFAULT '001'::character varying NOT NULL,
    secuencial character varying(9) NOT NULL,
    numero_completo character varying(17),
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    fecha_inicio_transporte date,
    fecha_fin_transporte date,
    origen character varying(300),
    destino character varying(300),
    ruta character varying(300),
    motivo character varying(300),
    clave_acceso character varying(49),
    autorizacion character varying(49),
    estado_sri character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    xml_doc text,
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: guias_remision_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.guias_remision_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: guias_remision_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.guias_remision_id_seq OWNED BY public.guias_remision.id;


--
-- Name: horarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.horarios (
    id bigint NOT NULL,
    descripcion character varying(100) NOT NULL,
    hora_entrada time(0) without time zone NOT NULL,
    hora_salida time(0) without time zone NOT NULL,
    tolerancia_minutos smallint DEFAULT '5'::smallint NOT NULL,
    lunes boolean DEFAULT true NOT NULL,
    martes boolean DEFAULT true NOT NULL,
    miercoles boolean DEFAULT true NOT NULL,
    jueves boolean DEFAULT true NOT NULL,
    viernes boolean DEFAULT true NOT NULL,
    sabado boolean DEFAULT false NOT NULL,
    domingo boolean DEFAULT false NOT NULL
);


--
-- Name: horarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.horarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: horarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.horarios_id_seq OWNED BY public.horarios.id;


--
-- Name: horas_extras_aprobacion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.horas_extras_aprobacion (
    id bigint NOT NULL,
    colaborador_id bigint NOT NULL,
    asistencia_id bigint,
    fecha date NOT NULL,
    horas_solicitadas numeric(5,2) NOT NULL,
    horas_aprobadas numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tipo character varying(20) NOT NULL,
    valor_calculado numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    aprobado_por bigint,
    fecha_aprobacion timestamp(0) without time zone,
    observacion character varying(300),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: horas_extras_aprobacion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.horas_extras_aprobacion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: horas_extras_aprobacion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.horas_extras_aprobacion_id_seq OWNED BY public.horas_extras_aprobacion.id;


--
-- Name: importaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.importaciones (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    proveedor_id bigint,
    nombre character varying(200) NOT NULL,
    num_invoice character varying(100),
    agente_aduanero character varying(200),
    pais_embarque character varying(100),
    costo_fob numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    divisa character varying(10) DEFAULT 'USD'::character varying NOT NULL,
    fecha_partida date,
    fecha_llegada date,
    fecha_liquidacion date,
    total_costos_extra numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    costo_total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    metodo_prorrateo character varying(20) DEFAULT 'cantidad'::character varying NOT NULL,
    estado character varying(20) DEFAULT 'en_transito'::character varying NOT NULL,
    observaciones text,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: importaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.importaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: importaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.importaciones_id_seq OWNED BY public.importaciones.id;


--
-- Name: inventario_movimientos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventario_movimientos (
    id bigint NOT NULL,
    producto_id bigint NOT NULL,
    bodega_id bigint NOT NULL,
    tipo character varying(30) NOT NULL,
    doc_tipo character varying(50),
    doc_id bigint,
    cantidad numeric(12,4) NOT NULL,
    costo_unitario numeric(14,4),
    costo_total numeric(14,4),
    stock_anterior numeric(12,4) NOT NULL,
    stock_nuevo numeric(12,4) NOT NULL,
    usuario_id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    notas text,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    liberado_at timestamp(0) without time zone
);


--
-- Name: inventario_movimientos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventario_movimientos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventario_movimientos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventario_movimientos_id_seq OWNED BY public.inventario_movimientos.id;


--
-- Name: inventario_saldos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.inventario_saldos (
    id bigint NOT NULL,
    producto_id bigint NOT NULL,
    bodega_id bigint NOT NULL,
    stock_actual numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    stock_reservado numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    costo_promedio numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP,
    cantidad_reservada numeric(12,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: inventario_saldos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.inventario_saldos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: inventario_saldos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.inventario_saldos_id_seq OWNED BY public.inventario_saldos.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: limites_descuento; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.limites_descuento (
    id bigint NOT NULL,
    perfil_id bigint NOT NULL,
    porcentaje_maximo numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    puede_aprobar boolean DEFAULT false NOT NULL,
    porcentaje_aprobacion_max numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN limites_descuento.porcentaje_maximo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.limites_descuento.porcentaje_maximo IS '% máximo sin necesitar aprobación';


--
-- Name: COLUMN limites_descuento.porcentaje_aprobacion_max; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.limites_descuento.porcentaje_aprobacion_max IS '% máximo que puede aprobar para otros';


--
-- Name: limites_descuento_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.limites_descuento_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: limites_descuento_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.limites_descuento_id_seq OWNED BY public.limites_descuento.id;


--
-- Name: listas_precio; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.listas_precio (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    tipo character varying(10) DEFAULT 'PVP'::character varying NOT NULL,
    precio numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento_max numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    vigencia_desde date,
    vigencia_hasta date
);


--
-- Name: listas_precio_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.listas_precio_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: listas_precio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.listas_precio_id_seq OWNED BY public.listas_precio.id;


--
-- Name: log_cambios_criticos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_cambios_criticos (
    id bigint NOT NULL,
    usuario_id bigint,
    empresa_id bigint,
    tabla character varying(255) NOT NULL,
    registro_id bigint NOT NULL,
    campo character varying(255) NOT NULL,
    valor_anterior text,
    valor_nuevo text,
    ip_address character varying(45),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: log_cambios_criticos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.log_cambios_criticos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: log_cambios_criticos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.log_cambios_criticos_id_seq OWNED BY public.log_cambios_criticos.id;


--
-- Name: log_documentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_documentos (
    id bigint NOT NULL,
    usuario_id bigint,
    empresa_id bigint,
    accion character varying(30) NOT NULL,
    modulo character varying(50) NOT NULL,
    tabla character varying(255) NOT NULL,
    registro_id bigint,
    descripcion text,
    ip_address character varying(45),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    username character varying(50),
    fecha timestamp(0) without time zone
);


--
-- Name: COLUMN log_documentos.accion; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.log_documentos.accion IS 'crear, editar, anular, eliminar, imprimir';


--
-- Name: log_documentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.log_documentos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: log_documentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.log_documentos_id_seq OWNED BY public.log_documentos.id;


--
-- Name: log_sesiones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.log_sesiones (
    id bigint NOT NULL,
    usuario_id bigint,
    email character varying(255),
    tipo character varying(255) DEFAULT 'login_ok'::character varying NOT NULL,
    ip_address character varying(45),
    user_agent text,
    empresa_id bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT log_sesiones_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['login_ok'::character varying, 'login_fail'::character varying, 'logout'::character varying, 'forzado'::character varying])::text[])))
);


--
-- Name: log_sesiones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.log_sesiones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: log_sesiones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.log_sesiones_id_seq OWNED BY public.log_sesiones.id;


--
-- Name: marcas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.marcas (
    id bigint NOT NULL,
    empresa_id bigint,
    nombre character varying(150) NOT NULL,
    descripcion text,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: marcas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.marcas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: marcas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.marcas_id_seq OWNED BY public.marcas.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: modulos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.modulos (
    id bigint NOT NULL,
    nombre character varying(50) NOT NULL,
    clave character varying(30) NOT NULL,
    icono character varying(50),
    orden integer DEFAULT 0 NOT NULL,
    padre_id bigint,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN modulos.clave; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.modulos.clave IS 'slug: ventas, compras, inventario...';


--
-- Name: COLUMN modulos.icono; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.modulos.icono IS 'Nombre del ícono Lucide';


--
-- Name: modulos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.modulos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: modulos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.modulos_id_seq OWNED BY public.modulos.id;


--
-- Name: movimientos_bancarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.movimientos_bancarios (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    banco_caja_id bigint NOT NULL,
    tipo character varying(20) NOT NULL,
    sub_tipo character varying(30),
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    monto numeric(14,4) NOT NULL,
    persona_tipo character varying(20),
    persona_id integer,
    beneficiario character varying(200),
    num_documento character varying(50),
    num_cheque character varying(50),
    fecha_cheque date,
    descripcion character varying(500),
    documento_tipo character varying(20),
    documento_id integer,
    cuenta_contrapartida_id bigint,
    asiento_id bigint,
    conciliado boolean DEFAULT false NOT NULL,
    es_postfechado boolean DEFAULT false NOT NULL,
    anulado boolean DEFAULT false NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: movimientos_bancarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.movimientos_bancarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: movimientos_bancarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.movimientos_bancarios_id_seq OWNED BY public.movimientos_bancarios.id;


--
-- Name: nomina_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nomina_detalles (
    id bigint NOT NULL,
    nomina_id bigint NOT NULL,
    colaborador_id bigint NOT NULL,
    sueldo_base numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    horas_extras_50 numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    horas_extras_100 numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    comisiones numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    otros_ingresos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_ingresos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    aporte_personal_iess numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    descuento_atrasos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    descuento_prestamos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    descuento_anticipos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    otros_egresos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_egresos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    neto_pagar numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    tipo_pago character varying(20),
    num_cuenta character varying(50),
    banco character varying(100),
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    modificado_manualmente boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: nomina_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nomina_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nomina_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nomina_detalles_id_seq OWNED BY public.nomina_detalles.id;


--
-- Name: nominas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nominas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    periodo_tipo character varying(20) NOT NULL,
    anio smallint NOT NULL,
    mes smallint NOT NULL,
    quincena smallint,
    fecha_emision date NOT NULL,
    estado character varying(20) DEFAULT 'borrador'::character varying NOT NULL,
    total_ingresos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_egresos numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_neto numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    asiento_id bigint,
    generado_por bigint NOT NULL,
    procesado_por bigint,
    pagado_por bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: nominas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nominas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nominas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nominas_id_seq OWNED BY public.nominas.id;


--
-- Name: nota_credito_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nota_credito_detalles (
    id bigint NOT NULL,
    nota_credito_id integer NOT NULL,
    producto_id integer,
    descripcion character varying(500) NOT NULL,
    cantidad numeric(12,4) DEFAULT '1'::numeric NOT NULL,
    precio_unitario numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    bodega_destino_id integer,
    numero_serie character varying(100)
);


--
-- Name: nota_credito_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nota_credito_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nota_credito_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nota_credito_detalles_id_seq OWNED BY public.nota_credito_detalles.id;


--
-- Name: notas_credito; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notas_credito (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    factura_id integer NOT NULL,
    cliente_id integer NOT NULL,
    usuario_id integer,
    establecimiento character varying(3) DEFAULT '001'::character varying NOT NULL,
    punto_emision character varying(3) DEFAULT '001'::character varying NOT NULL,
    secuencial character varying(9) NOT NULL,
    numero_completo character varying(17),
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    motivo character varying(300) NOT NULL,
    tipo character varying(20) DEFAULT 'total'::character varying NOT NULL,
    subtotal numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    clave_acceso character varying(49),
    autorizacion character varying(49),
    estado_sri character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    xml_doc text,
    asiento_id integer,
    genera_saldo_favor boolean DEFAULT false NOT NULL,
    saldo_favor numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: notas_credito_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notas_credito_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notas_credito_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notas_credito_id_seq OWNED BY public.notas_credito.id;


--
-- Name: notificaciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notificaciones (
    id bigint NOT NULL,
    usuario_id bigint NOT NULL,
    titulo character varying(255) NOT NULL,
    mensaje text NOT NULL,
    tipo character varying(20) DEFAULT 'info'::character varying NOT NULL,
    icono character varying(50),
    url character varying(255),
    leida boolean DEFAULT false NOT NULL,
    leida_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: COLUMN notificaciones.tipo; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.notificaciones.tipo IS 'info, success, warning, danger';


--
-- Name: COLUMN notificaciones.url; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.notificaciones.url IS 'URL de acción al hacer clic';


--
-- Name: notificaciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notificaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notificaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notificaciones_id_seq OWNED BY public.notificaciones.id;


--
-- Name: parametros_contables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.parametros_contables (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    codigo character varying(60) NOT NULL,
    cuenta_id bigint NOT NULL,
    descripcion character varying(200)
);


--
-- Name: parametros_contables_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.parametros_contables_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: parametros_contables_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.parametros_contables_id_seq OWNED BY public.parametros_contables.id;


--
-- Name: partidas_transito; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.partidas_transito (
    id bigint NOT NULL,
    conciliacion_id bigint NOT NULL,
    tipo character varying(20),
    fecha date,
    descripcion character varying(300),
    monto numeric(14,4),
    movimiento_id bigint,
    conciliada boolean DEFAULT false NOT NULL,
    asiento_generado_id bigint
);


--
-- Name: partidas_transito_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.partidas_transito_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: partidas_transito_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.partidas_transito_id_seq OWNED BY public.partidas_transito.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: perfiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.perfiles (
    id bigint NOT NULL,
    nombre character varying(50) NOT NULL,
    descripcion character varying(255),
    es_sistema boolean DEFAULT false NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN perfiles.es_sistema; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.perfiles.es_sistema IS 'No se puede eliminar';


--
-- Name: perfiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.perfiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: perfiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.perfiles_id_seq OWNED BY public.perfiles.id;


--
-- Name: permisos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permisos (
    id bigint NOT NULL,
    perfil_id bigint NOT NULL,
    modulo_id bigint NOT NULL,
    ver boolean DEFAULT false NOT NULL,
    crear boolean DEFAULT false NOT NULL,
    editar boolean DEFAULT false NOT NULL,
    eliminar boolean DEFAULT false NOT NULL,
    anular boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permisos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permisos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permisos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permisos_id_seq OWNED BY public.permisos.id;


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: plan_cuentas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plan_cuentas (
    id bigint NOT NULL,
    empresa_id bigint,
    codigo character varying(30) NOT NULL,
    nombre character varying(150) NOT NULL,
    descripcion character varying(300),
    tipo character varying(255) NOT NULL,
    padre_id bigint,
    nivel smallint DEFAULT '1'::smallint NOT NULL,
    permite_asientos boolean DEFAULT false NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    total_asientos integer DEFAULT 0 NOT NULL,
    CONSTRAINT plan_cuentas_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['activo'::character varying, 'pasivo'::character varying, 'patrimonio'::character varying, 'ingreso'::character varying, 'gasto'::character varying])::text[])))
);


--
-- Name: plan_cuentas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.plan_cuentas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plan_cuentas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.plan_cuentas_id_seq OWNED BY public.plan_cuentas.id;


--
-- Name: prefactura_abonos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prefactura_abonos (
    id bigint NOT NULL,
    prefactura_id integer NOT NULL,
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    valor numeric(14,4) NOT NULL,
    forma_pago character varying(30),
    banco character varying(100),
    num_comprobante character varying(50),
    asiento_id integer,
    usuario_id integer,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: prefactura_abonos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.prefactura_abonos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prefactura_abonos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.prefactura_abonos_id_seq OWNED BY public.prefactura_abonos.id;


--
-- Name: prefactura_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prefactura_detalles (
    id bigint NOT NULL,
    prefactura_id integer NOT NULL,
    producto_id integer,
    descripcion character varying(500) NOT NULL,
    cantidad numeric(12,4) DEFAULT '1'::numeric NOT NULL,
    precio_unitario numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: prefactura_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.prefactura_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prefactura_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.prefactura_detalles_id_seq OWNED BY public.prefactura_detalles.id;


--
-- Name: prefacturas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prefacturas (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    centro_costo_id integer,
    cliente_id integer NOT NULL,
    usuario_id integer,
    numero character varying(20),
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_abonado numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    saldo_pendiente numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    asiento_id integer,
    factura_id integer,
    observaciones text,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: prefacturas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.prefacturas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prefacturas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.prefacturas_id_seq OWNED BY public.prefacturas.id;


--
-- Name: prestamos_empleados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.prestamos_empleados (
    id bigint NOT NULL,
    colaborador_id bigint NOT NULL,
    tipo character varying(20) DEFAULT 'anticipo'::character varying NOT NULL,
    monto_total numeric(10,2) NOT NULL,
    saldo numeric(10,2) NOT NULL,
    cuota numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    fecha date DEFAULT '2026-06-22'::date NOT NULL,
    descripcion character varying(300),
    estado character varying(20) DEFAULT 'activo'::character varying NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: prestamos_empleados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.prestamos_empleados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: prestamos_empleados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.prestamos_empleados_id_seq OWNED BY public.prestamos_empleados.id;


--
-- Name: presupuestos_metas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.presupuestos_metas (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    centro_costo_id bigint,
    mes smallint NOT NULL,
    anio smallint NOT NULL,
    meta_ventas numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    meta_cobros numeric(14,2) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN presupuestos_metas.mes; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.presupuestos_metas.mes IS '1-12';


--
-- Name: presupuestos_metas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.presupuestos_metas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: presupuestos_metas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.presupuestos_metas_id_seq OWNED BY public.presupuestos_metas.id;


--
-- Name: producto_series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.producto_series (
    id bigint NOT NULL,
    producto_id bigint NOT NULL,
    bodega_id bigint NOT NULL,
    numero_serie character varying(100) NOT NULL,
    estado character varying(20) DEFAULT 'disponible'::character varying NOT NULL,
    doc_entrada_tipo character varying(50),
    doc_entrada_id bigint,
    doc_salida_tipo character varying(50),
    doc_salida_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: producto_series_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.producto_series_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: producto_series_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.producto_series_id_seq OWNED BY public.producto_series.id;


--
-- Name: productos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.productos (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    marca_id bigint,
    categoria_id bigint,
    bodega_default_id bigint,
    codigo character varying(50) NOT NULL,
    nombre character varying(255) NOT NULL,
    descripcion text,
    tipo character varying(30) DEFAULT 'producto'::character varying NOT NULL,
    unidad character varying(20) DEFAULT 'unidad'::character varying NOT NULL,
    requiere_serie boolean DEFAULT false NOT NULL,
    pvp numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    pvd numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    costo numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento_maximo numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    porcentaje_iva numeric(5,2) DEFAULT '15'::numeric NOT NULL,
    porcentaje_ice numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    stock_minimo numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    stock_maximo numeric(12,4),
    cuenta_inventario_id bigint,
    cuenta_costo_id bigint,
    cuenta_ventas_id bigint,
    estado boolean DEFAULT true NOT NULL,
    observaciones text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    tiene_ice boolean DEFAULT false NOT NULL,
    codigo_externo character varying(50),
    ref_importacion character varying(100),
    cuenta_inventario character varying(20),
    cuenta_costo_ventas character varying(20),
    cuenta_ventas character varying(20)
);


--
-- Name: productos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.productos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: productos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.productos_id_seq OWNED BY public.productos.id;


--
-- Name: proforma_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proforma_detalles (
    id bigint NOT NULL,
    proforma_id integer NOT NULL,
    producto_id integer,
    descripcion character varying(500) NOT NULL,
    cantidad numeric(12,4) DEFAULT '1'::numeric NOT NULL,
    precio_unitario numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento_pct numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    subtotal numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    porcentaje_iva numeric(5,2) DEFAULT '15'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: proforma_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proforma_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proforma_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proforma_detalles_id_seq OWNED BY public.proforma_detalles.id;


--
-- Name: proformas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proformas (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    centro_costo_id integer,
    cliente_id integer NOT NULL,
    usuario_id integer,
    numero character varying(20),
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    fecha_vencimiento date,
    subtotal numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    descuento_total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total_iva numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    observaciones text,
    factura_id integer,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: proformas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proformas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proformas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proformas_id_seq OWNED BY public.proformas.id;


--
-- Name: proveedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.proveedores (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    tipo character varying(20) DEFAULT 'nacional'::character varying NOT NULL,
    tipo_identificacion character varying(5) DEFAULT '04'::character varying NOT NULL,
    identificacion character varying(20) NOT NULL,
    razon_social character varying(200) NOT NULL,
    nombre_comercial character varying(200),
    email character varying(200),
    telefono character varying(20),
    direccion character varying(300),
    ciudad character varying(100),
    pais character varying(100) DEFAULT 'ECUADOR'::character varying NOT NULL,
    divisa character varying(10) DEFAULT 'USD'::character varying NOT NULL,
    tiene_credito boolean DEFAULT false NOT NULL,
    dias_credito smallint DEFAULT '0'::smallint NOT NULL,
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: proveedores_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.proveedores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: proveedores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.proveedores_id_seq OWNED BY public.proveedores.id;


--
-- Name: puestos_trabajo; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.puestos_trabajo (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    cargo character varying(100),
    departamento character varying(100),
    estado boolean DEFAULT true NOT NULL
);


--
-- Name: puestos_trabajo_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.puestos_trabajo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: puestos_trabajo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.puestos_trabajo_id_seq OWNED BY public.puestos_trabajo.id;


--
-- Name: recepcion_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recepcion_detalles (
    id bigint NOT NULL,
    recepcion_id bigint NOT NULL,
    compra_detalle_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    cantidad_esperada numeric(12,4) NOT NULL,
    cantidad_recibida numeric(12,4) DEFAULT '0'::numeric NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL
);


--
-- Name: recepcion_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recepcion_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recepcion_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recepcion_detalles_id_seq OWNED BY public.recepcion_detalles.id;


--
-- Name: recepcion_escaneos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recepcion_escaneos (
    id bigint NOT NULL,
    recepcion_id bigint NOT NULL,
    recepcion_detalle_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    codigo_escaneado character varying(100) NOT NULL,
    correlativo integer NOT NULL,
    usuario_id bigint,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: recepcion_escaneos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recepcion_escaneos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recepcion_escaneos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recepcion_escaneos_id_seq OWNED BY public.recepcion_escaneos.id;


--
-- Name: recepciones_bodega; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recepciones_bodega (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    compra_id bigint NOT NULL,
    bodega_id bigint NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    recibido_por bigint,
    fecha_recepcion date,
    observacion text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: recepciones_bodega_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recepciones_bodega_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recepciones_bodega_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recepciones_bodega_id_seq OWNED BY public.recepciones_bodega.id;


--
-- Name: retencion_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.retencion_detalles (
    id bigint NOT NULL,
    retencion_id integer NOT NULL,
    impuesto_id integer,
    tipo character varying(10) NOT NULL,
    codigo character varying(10),
    porcentaje numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    base_imponible numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    valor_retenido numeric(14,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: retencion_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.retencion_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: retencion_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.retencion_detalles_id_seq OWNED BY public.retencion_detalles.id;


--
-- Name: retenciones; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.retenciones (
    id bigint NOT NULL,
    empresa_id integer NOT NULL,
    factura_id integer,
    compra_id integer,
    cliente_id integer,
    usuario_id integer,
    establecimiento character varying(3) DEFAULT '001'::character varying NOT NULL,
    punto_emision character varying(3) DEFAULT '001'::character varying NOT NULL,
    secuencial character varying(9) NOT NULL,
    numero_completo character varying(17),
    fecha_emision date DEFAULT CURRENT_DATE NOT NULL,
    identificacion character varying(20),
    razon_social character varying(200),
    num_comp_retenido character varying(17),
    total numeric(14,4) DEFAULT '0'::numeric NOT NULL,
    clave_acceso character varying(49),
    autorizacion character varying(49),
    estado_sri character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    xml_doc text,
    asiento_id integer,
    estado character varying(20) DEFAULT 'activa'::character varying NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: retenciones_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.retenciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: retenciones_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.retenciones_id_seq OWNED BY public.retenciones.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: rubros_nomina; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rubros_nomina (
    id bigint NOT NULL,
    codigo character varying(20) NOT NULL,
    descripcion character varying(200) NOT NULL,
    grupo character varying(50),
    tipo_valor character varying(20),
    valor numeric(10,4) DEFAULT '0'::numeric NOT NULL,
    operacion character varying(10) DEFAULT '+'::character varying NOT NULL,
    cuenta_contable character varying(20),
    afecta_iess boolean DEFAULT false NOT NULL,
    afecta_renta boolean DEFAULT false NOT NULL,
    estado boolean DEFAULT true NOT NULL
);


--
-- Name: rubros_nomina_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rubros_nomina_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rubros_nomina_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rubros_nomina_id_seq OWNED BY public.rubros_nomina.id;


--
-- Name: secuenciales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.secuenciales (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    tipo_documento character varying(30) NOT NULL,
    establecimiento character varying(3) DEFAULT '001'::character varying NOT NULL,
    punto_emision character varying(3) DEFAULT '001'::character varying NOT NULL,
    siguiente bigint DEFAULT '1'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    inicializado_desde_migracion boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN secuenciales.tipo_documento; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.secuenciales.tipo_documento IS 'factura, nota_credito, proforma, retencion, etc.';


--
-- Name: secuenciales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.secuenciales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: secuenciales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.secuenciales_id_seq OWNED BY public.secuenciales.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: tipos_aprobacion; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tipos_aprobacion (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    clave character varying(50) NOT NULL,
    descripcion character varying(255),
    activo boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tipos_aprobacion_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tipos_aprobacion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tipos_aprobacion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tipos_aprobacion_id_seq OWNED BY public.tipos_aprobacion.id;


--
-- Name: transportistas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transportistas (
    id bigint NOT NULL,
    identificacion character varying(20),
    razon_social character varying(200) NOT NULL,
    placa character varying(20),
    email character varying(200),
    telefono character varying(20),
    direccion character varying(300),
    estado boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: transportistas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transportistas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transportistas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transportistas_id_seq OWNED BY public.transportistas.id;


--
-- Name: traslado_detalles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.traslado_detalles (
    id bigint NOT NULL,
    traslado_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    numero_serie character varying(100),
    cantidad_enviada numeric(12,4) NOT NULL,
    cantidad_recibida numeric(12,4) DEFAULT '0'::numeric NOT NULL
);


--
-- Name: traslado_detalles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.traslado_detalles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: traslado_detalles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.traslado_detalles_id_seq OWNED BY public.traslado_detalles.id;


--
-- Name: traslado_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.traslado_items (
    id bigint NOT NULL,
    traslado_id bigint NOT NULL,
    producto_id bigint NOT NULL,
    cantidad_enviada numeric(12,4) NOT NULL,
    cantidad_recibida numeric(12,4),
    notas text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: traslado_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.traslado_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: traslado_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.traslado_items_id_seq OWNED BY public.traslado_items.id;


--
-- Name: traslados; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.traslados (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    bodega_origen_id bigint NOT NULL,
    bodega_destino_id bigint NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    usuario_origen_id bigint NOT NULL,
    usuario_destino_id bigint,
    fecha_traslado timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    fecha_confirmacion timestamp(0) without time zone,
    notas_origen text,
    notas_destino text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: traslados_bodega; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.traslados_bodega (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    bodega_origen_id bigint NOT NULL,
    bodega_destino_id bigint NOT NULL,
    numero character varying(20),
    fecha date DEFAULT CURRENT_DATE NOT NULL,
    estado character varying(20) DEFAULT 'pendiente'::character varying NOT NULL,
    enviado_por bigint,
    recibido_por bigint,
    fecha_recepcion timestamp(0) without time zone,
    observacion character varying(300),
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: traslados_bodega_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.traslados_bodega_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: traslados_bodega_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.traslados_bodega_id_seq OWNED BY public.traslados_bodega.id;


--
-- Name: traslados_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.traslados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: traslados_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.traslados_id_seq OWNED BY public.traslados.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id bigint NOT NULL,
    empresa_id bigint NOT NULL,
    perfil_id bigint NOT NULL,
    centro_costo_id bigint,
    nombre character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    username character varying(255) NOT NULL,
    telefono character varying(20),
    password character varying(255) NOT NULL,
    codigo_aprobacion character varying(255),
    avatar character varying(255),
    estado boolean DEFAULT true NOT NULL,
    email_verified_at timestamp(0) without time zone,
    remember_token character varying(100),
    ultimo_acceso timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: COLUMN usuarios.codigo_aprobacion; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.usuarios.codigo_aprobacion IS 'PIN hasheado para aprobaciones especiales';


--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.usuarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: activos_depreciaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_depreciaciones ALTER COLUMN id SET DEFAULT nextval('public.activos_depreciaciones_id_seq'::regclass);


--
-- Name: activos_fijos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_fijos ALTER COLUMN id SET DEFAULT nextval('public.activos_fijos_id_seq'::regclass);


--
-- Name: anticipos_proveedores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anticipos_proveedores ALTER COLUMN id SET DEFAULT nextval('public.anticipos_proveedores_id_seq'::regclass);


--
-- Name: aprobaciones_especiales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aprobaciones_especiales ALTER COLUMN id SET DEFAULT nextval('public.aprobaciones_especiales_id_seq'::regclass);


--
-- Name: asiento_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiento_detalles ALTER COLUMN id SET DEFAULT nextval('public.asiento_detalles_id_seq'::regclass);


--
-- Name: asientos_contables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables ALTER COLUMN id SET DEFAULT nextval('public.asientos_contables_id_seq'::regclass);


--
-- Name: asistencias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asistencias ALTER COLUMN id SET DEFAULT nextval('public.asistencias_id_seq'::regclass);


--
-- Name: bancos_cajas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bancos_cajas ALTER COLUMN id SET DEFAULT nextval('public.bancos_cajas_id_seq'::regclass);


--
-- Name: bodegas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bodegas ALTER COLUMN id SET DEFAULT nextval('public.bodegas_id_seq'::regclass);


--
-- Name: categorias_producto id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_producto ALTER COLUMN id SET DEFAULT nextval('public.categorias_producto_id_seq'::regclass);


--
-- Name: centros_costo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_costo ALTER COLUMN id SET DEFAULT nextval('public.centros_costo_id_seq'::regclass);


--
-- Name: cheques id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cheques ALTER COLUMN id SET DEFAULT nextval('public.cheques_id_seq'::regclass);


--
-- Name: cierres_caja id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja ALTER COLUMN id SET DEFAULT nextval('public.cierres_caja_id_seq'::regclass);


--
-- Name: clientes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id SET DEFAULT nextval('public.clientes_id_seq'::regclass);


--
-- Name: colaboradores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores ALTER COLUMN id SET DEFAULT nextval('public.colaboradores_id_seq'::regclass);


--
-- Name: compra_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compra_detalles ALTER COLUMN id SET DEFAULT nextval('public.compra_detalles_id_seq'::regclass);


--
-- Name: compras id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras ALTER COLUMN id SET DEFAULT nextval('public.compras_id_seq'::regclass);


--
-- Name: conciliaciones_bancarias id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conciliaciones_bancarias ALTER COLUMN id SET DEFAULT nextval('public.conciliaciones_bancarias_id_seq'::regclass);


--
-- Name: configuraciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones ALTER COLUMN id SET DEFAULT nextval('public.configuraciones_id_seq'::regclass);


--
-- Name: cuentas_cobrar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar ALTER COLUMN id SET DEFAULT nextval('public.cuentas_cobrar_id_seq'::regclass);


--
-- Name: cuentas_pagar id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar ALTER COLUMN id SET DEFAULT nextval('public.cuentas_pagar_id_seq'::regclass);


--
-- Name: datafast_liquidaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_liquidaciones ALTER COLUMN id SET DEFAULT nextval('public.datafast_liquidaciones_id_seq'::regclass);


--
-- Name: datafast_lotes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_lotes ALTER COLUMN id SET DEFAULT nextval('public.datafast_lotes_id_seq'::regclass);


--
-- Name: ejercicios_contables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ejercicios_contables ALTER COLUMN id SET DEFAULT nextval('public.ejercicios_contables_id_seq'::regclass);


--
-- Name: empresas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas ALTER COLUMN id SET DEFAULT nextval('public.empresas_id_seq'::regclass);


--
-- Name: etiquetas_productos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.etiquetas_productos ALTER COLUMN id SET DEFAULT nextval('public.etiquetas_productos_id_seq'::regclass);


--
-- Name: factura_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factura_detalles ALTER COLUMN id SET DEFAULT nextval('public.factura_detalles_id_seq'::regclass);


--
-- Name: factura_pagos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factura_pagos ALTER COLUMN id SET DEFAULT nextval('public.factura_pagos_id_seq'::regclass);


--
-- Name: facturas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturas ALTER COLUMN id SET DEFAULT nextval('public.facturas_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: guia_remision_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.guia_remision_detalles ALTER COLUMN id SET DEFAULT nextval('public.guia_remision_detalles_id_seq'::regclass);


--
-- Name: guias_remision id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.guias_remision ALTER COLUMN id SET DEFAULT nextval('public.guias_remision_id_seq'::regclass);


--
-- Name: horarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horarios ALTER COLUMN id SET DEFAULT nextval('public.horarios_id_seq'::regclass);


--
-- Name: horas_extras_aprobacion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horas_extras_aprobacion ALTER COLUMN id SET DEFAULT nextval('public.horas_extras_aprobacion_id_seq'::regclass);


--
-- Name: importaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones ALTER COLUMN id SET DEFAULT nextval('public.importaciones_id_seq'::regclass);


--
-- Name: inventario_movimientos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_movimientos ALTER COLUMN id SET DEFAULT nextval('public.inventario_movimientos_id_seq'::regclass);


--
-- Name: inventario_saldos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_saldos ALTER COLUMN id SET DEFAULT nextval('public.inventario_saldos_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: limites_descuento id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.limites_descuento ALTER COLUMN id SET DEFAULT nextval('public.limites_descuento_id_seq'::regclass);


--
-- Name: listas_precio id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precio ALTER COLUMN id SET DEFAULT nextval('public.listas_precio_id_seq'::regclass);


--
-- Name: log_cambios_criticos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_cambios_criticos ALTER COLUMN id SET DEFAULT nextval('public.log_cambios_criticos_id_seq'::regclass);


--
-- Name: log_documentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_documentos ALTER COLUMN id SET DEFAULT nextval('public.log_documentos_id_seq'::regclass);


--
-- Name: log_sesiones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_sesiones ALTER COLUMN id SET DEFAULT nextval('public.log_sesiones_id_seq'::regclass);


--
-- Name: marcas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas ALTER COLUMN id SET DEFAULT nextval('public.marcas_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: modulos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos ALTER COLUMN id SET DEFAULT nextval('public.modulos_id_seq'::regclass);


--
-- Name: movimientos_bancarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios ALTER COLUMN id SET DEFAULT nextval('public.movimientos_bancarios_id_seq'::regclass);


--
-- Name: nomina_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomina_detalles ALTER COLUMN id SET DEFAULT nextval('public.nomina_detalles_id_seq'::regclass);


--
-- Name: nominas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nominas ALTER COLUMN id SET DEFAULT nextval('public.nominas_id_seq'::regclass);


--
-- Name: nota_credito_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nota_credito_detalles ALTER COLUMN id SET DEFAULT nextval('public.nota_credito_detalles_id_seq'::regclass);


--
-- Name: notas_credito id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas_credito ALTER COLUMN id SET DEFAULT nextval('public.notas_credito_id_seq'::regclass);


--
-- Name: notificaciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones ALTER COLUMN id SET DEFAULT nextval('public.notificaciones_id_seq'::regclass);


--
-- Name: parametros_contables id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_contables ALTER COLUMN id SET DEFAULT nextval('public.parametros_contables_id_seq'::regclass);


--
-- Name: partidas_transito id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partidas_transito ALTER COLUMN id SET DEFAULT nextval('public.partidas_transito_id_seq'::regclass);


--
-- Name: perfiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perfiles ALTER COLUMN id SET DEFAULT nextval('public.perfiles_id_seq'::regclass);


--
-- Name: permisos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos ALTER COLUMN id SET DEFAULT nextval('public.permisos_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: plan_cuentas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_cuentas ALTER COLUMN id SET DEFAULT nextval('public.plan_cuentas_id_seq'::regclass);


--
-- Name: prefactura_abonos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefactura_abonos ALTER COLUMN id SET DEFAULT nextval('public.prefactura_abonos_id_seq'::regclass);


--
-- Name: prefactura_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefactura_detalles ALTER COLUMN id SET DEFAULT nextval('public.prefactura_detalles_id_seq'::regclass);


--
-- Name: prefacturas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefacturas ALTER COLUMN id SET DEFAULT nextval('public.prefacturas_id_seq'::regclass);


--
-- Name: prestamos_empleados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prestamos_empleados ALTER COLUMN id SET DEFAULT nextval('public.prestamos_empleados_id_seq'::regclass);


--
-- Name: presupuestos_metas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos_metas ALTER COLUMN id SET DEFAULT nextval('public.presupuestos_metas_id_seq'::regclass);


--
-- Name: producto_series id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_series ALTER COLUMN id SET DEFAULT nextval('public.producto_series_id_seq'::regclass);


--
-- Name: productos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos ALTER COLUMN id SET DEFAULT nextval('public.productos_id_seq'::regclass);


--
-- Name: proforma_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proforma_detalles ALTER COLUMN id SET DEFAULT nextval('public.proforma_detalles_id_seq'::regclass);


--
-- Name: proformas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proformas ALTER COLUMN id SET DEFAULT nextval('public.proformas_id_seq'::regclass);


--
-- Name: proveedores id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores ALTER COLUMN id SET DEFAULT nextval('public.proveedores_id_seq'::regclass);


--
-- Name: puestos_trabajo id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.puestos_trabajo ALTER COLUMN id SET DEFAULT nextval('public.puestos_trabajo_id_seq'::regclass);


--
-- Name: recepcion_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_detalles ALTER COLUMN id SET DEFAULT nextval('public.recepcion_detalles_id_seq'::regclass);


--
-- Name: recepcion_escaneos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos ALTER COLUMN id SET DEFAULT nextval('public.recepcion_escaneos_id_seq'::regclass);


--
-- Name: recepciones_bodega id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepciones_bodega ALTER COLUMN id SET DEFAULT nextval('public.recepciones_bodega_id_seq'::regclass);


--
-- Name: retencion_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retencion_detalles ALTER COLUMN id SET DEFAULT nextval('public.retencion_detalles_id_seq'::regclass);


--
-- Name: retenciones id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retenciones ALTER COLUMN id SET DEFAULT nextval('public.retenciones_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: rubros_nomina id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rubros_nomina ALTER COLUMN id SET DEFAULT nextval('public.rubros_nomina_id_seq'::regclass);


--
-- Name: secuenciales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secuenciales ALTER COLUMN id SET DEFAULT nextval('public.secuenciales_id_seq'::regclass);


--
-- Name: tipos_aprobacion id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_aprobacion ALTER COLUMN id SET DEFAULT nextval('public.tipos_aprobacion_id_seq'::regclass);


--
-- Name: transportistas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transportistas ALTER COLUMN id SET DEFAULT nextval('public.transportistas_id_seq'::regclass);


--
-- Name: traslado_detalles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_detalles ALTER COLUMN id SET DEFAULT nextval('public.traslado_detalles_id_seq'::regclass);


--
-- Name: traslado_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_items ALTER COLUMN id SET DEFAULT nextval('public.traslado_items_id_seq'::regclass);


--
-- Name: traslados id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados ALTER COLUMN id SET DEFAULT nextval('public.traslados_id_seq'::regclass);


--
-- Name: traslados_bodega id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega ALTER COLUMN id SET DEFAULT nextval('public.traslados_bodega_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Data for Name: activos_depreciaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.activos_depreciaciones (id, activo_id, "periodo_año", periodo_mes, monto, depreciacion_acumulada_al_periodo, valor_libro_al_periodo, created_at) FROM stdin;
\.


--
-- Data for Name: activos_fijos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.activos_fijos (id, empresa_id, codigo, nombre, descripcion, categoria, ubicacion, fecha_adquisicion, costo_adquisicion, valor_residual, vida_util_anios, metodo_depreciacion, depreciacion_acumulada, valor_en_libros, estado, cuenta_id, cuenta_depreciacion_id, notas, created_at, updated_at, deleted_at) FROM stdin;
\.


--
-- Data for Name: anticipos_proveedores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.anticipos_proveedores (id, empresa_id, proveedor_id, importacion_id, fecha, monto, saldo, banco_id, num_transferencia, asiento_id, estado, created_at) FROM stdin;
1	1	8	2	2026-06-06	1000.0000	1000.0000	2	TRF-2026-001	15	pendiente	2026-06-05 23:27:30
2	1	6	2	2026-04-15	5000.0000	5000.0000	1	TRF-2026-042	\N	pendiente	2026-04-15 09:30:00
3	1	7	3	2026-04-22	3500.0000	3500.0000	3	TRF-2026-051	\N	pendiente	2026-04-22 11:15:00
4	1	2	\N	2026-05-03	1200.0000	1200.0000	2	TRF-2026-067	\N	pendiente	2026-05-03 10:00:00
5	1	3	\N	2026-03-18	800.0000	0.0000	4	TRF-2026-019	\N	cruzado	2026-03-18 14:20:00
6	1	4	\N	2026-03-25	450.0000	0.0000	1	TRF-2026-023	\N	cruzado	2026-03-25 09:45:00
7	1	5	\N	2026-05-20	2000.0000	2000.0000	1	TRF-2026-088	\N	pendiente	2026-05-20 08:00:00
8	1	7	\N	2026-05-28	1500.0000	750.0000	3	TRF-2026-094	\N	pendiente	2026-05-28 16:30:00
\.


--
-- Data for Name: aprobaciones_especiales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.aprobaciones_especiales (id, tipo_aprobacion_id, aprobado_por, solicitado_por, empresa_id, tabla_referencia, registro_id, descripcion, valor_aprobado, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: asiento_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.asiento_detalles (id, asiento_id, cuenta_id, centro_costo_id, descripcion, debe, haber) FROM stdin;
5	3	86	\N	Venta de mercadería — Factura 001-001-000001	1626.2900	0.0000
6	3	93	\N	Venta de mercadería — Factura 001-001-000001	0.0000	1626.2900
7	4	87	\N	Pago servicios básicos	3478.9900	0.0000
8	4	88	\N	Pago servicios básicos	0.0000	3478.9900
9	5	93	\N	Compra de suministros de oficina	2778.6700	0.0000
10	5	91	\N	Compra de suministros de oficina	0.0000	2778.6700
11	6	92	\N	Nómina mensual	2470.8000	0.0000
12	6	90	\N	Nómina mensual	0.0000	2470.8000
13	7	92	\N	Cobro factura cliente — Transferencia	14949.3400	0.0000
14	7	86	\N	Cobro factura cliente — Transferencia	0.0000	14949.3400
15	8	84	\N	Pago proveedor — Factura 001-002-000045	589.4400	0.0000
16	8	91	\N	Pago proveedor — Factura 001-002-000045	0.0000	589.4400
17	9	19	\N	Venta de servicio técnico — OT-2026-0012	8851.4300	0.0000
18	9	84	\N	Venta de servicio técnico — OT-2026-0012	0.0000	8851.4300
19	10	84	\N	Depreciación mensual activos fijos	5707.3700	0.0000
20	10	90	\N	Depreciación mensual activos fijos	0.0000	5707.3700
21	11	83	\N	Anticipo cliente — Reserva equipo audio	7157.7500	0.0000
22	11	83	\N	Anticipo cliente — Reserva equipo audio	0.0000	7157.7500
23	12	93	\N	Ajuste inventario — conteo físico	13168.5000	0.0000
24	12	91	\N	Ajuste inventario — conteo físico	0.0000	13168.5000
25	13	274	\N	\N	13.0000	0.0000
26	13	274	\N	\N	0.0000	13.0000
27	14	274	\N	REVERSA: nn	0.0000	13.0000
28	14	274	\N	REVERSA: nn	13.0000	0.0000
29	15	315	\N	Pago ANT-0001	1000.0000	0.0000
30	15	277	\N	Transferencia ANT-0001	0.0000	1000.0000
31	16	633	\N	Pago 890809	7383.0000	0.0000
32	16	595	\N	Transferencia 890809	0.0000	7383.0000
33	17	633	\N	Pago 77777	339.2500	0.0000
34	17	595	\N	Transferencia 77777	0.0000	339.2500
50	23	633	\N	Pago TRF-009	16150.0000	0.0000
51	23	595	\N	Transferencia TRF-009	0.0000	16150.0000
52	24	633	\N	Pago TRF-334	11592.0000	0.0000
53	24	595	\N	Transferencia TRF-334	0.0000	11592.0000
54	25	604	\N	Compra 008-444-56777	6300.0000	0.0000
55	25	608	\N	IVA compra 008-444-56777	945.0000	0.0000
56	25	633	\N	CxP 008-444-56777	0.0000	7245.0000
57	26	633	\N	Pago trf-003	7245.0000	0.0000
58	26	595	\N	Transferencia trf-003	0.0000	7245.0000
62	28	604	\N	REVERSA: Compra 008-444-56777	0.0000	6300.0000
63	28	608	\N	REVERSA: IVA compra 008-444-56777	0.0000	945.0000
64	28	633	\N	REVERSA: CxP 008-444-56777	7245.0000	0.0000
65	29	604	\N	Compra 3333-2222-1111	580.0000	0.0000
66	29	608	\N	IVA compra 3333-2222-1111	87.0000	0.0000
67	29	633	\N	CxP 3333-2222-1111	0.0000	667.0000
68	30	633	\N	Pago TRF-009	667.0000	0.0000
69	30	595	\N	Transferencia TRF-009	0.0000	667.0000
70	31	604	\N	REVERSA: Compra 3333-2222-1111	0.0000	580.0000
71	31	608	\N	REVERSA: IVA compra 3333-2222-1111	0.0000	87.0000
72	31	633	\N	REVERSA: CxP 3333-2222-1111	667.0000	0.0000
73	32	568	\N	Compra 000-999-666-5	12.0000	0.0000
74	32	608	\N	IVA compra 000-999-666-5	1.8000	0.0000
75	32	633	\N	CxP 000-999-666-5	0.0000	13.8000
76	33	633	\N	Pago Pago #29	13.7900	0.0000
77	33	595	\N	Transferencia Pago #29	0.0000	13.7900
78	34	633	\N	Pago Pago #30	0.0100	0.0000
79	34	595	\N	Transferencia Pago #30	0.0000	0.0100
80	35	568	\N	REVERSA: Compra 000-999-666-5	0.0000	12.0000
81	35	608	\N	REVERSA: IVA compra 000-999-666-5	0.0000	1.8000
82	35	633	\N	REVERSA: CxP 000-999-666-5	13.8000	0.0000
83	36	633	\N	Pago TRF-009	667.0000	0.0000
84	36	595	\N	Transferencia TRF-009	0.0000	667.0000
85	37	604	\N	Compra 999-8888-7777	1176.0000	0.0000
86	37	608	\N	IVA compra 999-8888-7777	176.4000	0.0000
87	37	633	\N	CxP 999-8888-7777	0.0000	1352.4000
\.


--
-- Data for Name: asientos_contables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.asientos_contables (id, empresa_id, ejercicio_id, numero, fecha, concepto, documento_tipo, documento_id, documento_ref, total_debe, total_haber, es_automatico, estado, creado_por, created_at) FROM stdin;
3	1	7	AS-2026-0001	2026-05-15	Venta de mercadería — Factura 001-001-000001	FAC	\N	001-001-000405	1626.2900	1626.2900	t	1	1	2026-06-01 00:13:25
4	1	7	AS-2026-0002	2026-05-18	Pago servicios básicos	MANUAL	\N	\N	3478.9900	3478.9900	f	1	1	2026-06-01 00:13:25
5	1	7	AS-2026-0003	2026-05-23	Compra de suministros de oficina	COMPRA	\N	\N	2778.6700	2778.6700	t	1	1	2026-06-01 00:13:25
6	1	7	AS-2026-0004	2026-05-20	Nómina mensual	NOM	\N	\N	2470.8000	2470.8000	t	1	1	2026-06-01 00:13:25
7	1	7	AS-2026-0005	2026-06-01	Cobro factura cliente — Transferencia	CXC	\N	\N	14949.3400	14949.3400	t	1	1	2026-06-01 00:13:25
8	1	7	AS-2026-0006	2026-05-10	Pago proveedor — Factura 001-002-000045	COMPRA	\N	\N	589.4400	589.4400	t	1	1	2026-06-01 00:13:25
9	1	7	AS-2026-0007	2026-05-29	Venta de servicio técnico — OT-2026-0012	FAC	\N	001-001-000096	8851.4300	8851.4300	t	1	1	2026-06-01 00:13:25
10	1	7	AS-2026-0008	2026-05-12	Depreciación mensual activos fijos	MANUAL	\N	\N	5707.3700	5707.3700	f	1	1	2026-06-01 00:13:25
11	1	7	AS-2026-0009	2026-05-02	Anticipo cliente — Reserva equipo audio	FAC	\N	001-001-000451	7157.7500	7157.7500	t	1	1	2026-06-01 00:13:25
12	1	7	AS-2026-0010	2026-05-28	Ajuste inventario — conteo físico	INV	\N	\N	13168.5000	13168.5000	t	1	1	2026-06-01 00:13:25
13	1	1	AS-2026-0011	2026-06-01	nn	MANUAL	\N	\N	13.0000	13.0000	f	0	1	2026-06-01 00:16:30
14	1	1	AS-2026-0012	2026-06-01	ANULACIÓN AS-2026-0011: mal creado	MANUAL	13	AS-2026-0011	13.0000	13.0000	f	1	1	2026-06-01 00:17:14
15	1	1	AS-2026-0013	2026-06-06	Pago proveedor ANT-0001	BANCO	1	ANT-0001	1000.0000	1000.0000	t	1	1	2026-06-05 23:27:30
16	1	1	AS-2026-0014	2026-06-12	Pago proveedor 890809	BANCO	5	890809	7383.0000	7383.0000	t	1	1	2026-06-12 00:46:57
17	1	1	AS-2026-0015	2026-06-12	Pago proveedor 77777	BANCO	6	77777	339.2500	339.2500	t	1	1	2026-06-12 00:47:14
23	1	1	AS-2026-0021	2026-06-15	Pago proveedor TRF-009	BANCO	7	TRF-009	16150.0000	16150.0000	t	1	1	2026-06-15 17:49:21
24	1	1	AS-2026-0022	2026-06-18	Pago proveedor TRF-334	BANCO	8	TRF-334	11592.0000	11592.0000	t	1	1	2026-06-17 20:16:46
26	1	1	AS-2026-0024	2026-06-18	Pago proveedor trf-003	BANCO	9	trf-003	7245.0000	7245.0000	t	1	1	2026-06-18 18:44:17
25	1	1	AS-2026-0023	2026-06-18	Compra 008-444-56777	COMPRA	14	008-444-56777	7245.0000	7245.0000	t	0	1	2026-06-18 18:43:54
28	1	1	AS-2026-0025	2026-06-18	ANULACIÓN AS-2026-0023: Anulación compra 008-444-56777: El equipo no llego de la mejor manera y nos equivocamos en el registro	MANUAL	25	AS-2026-0023	7245.0000	7245.0000	f	1	1	2026-06-18 18:53:19
30	1	1	AS-2026-0027	2026-06-23	Pago proveedor TRF-009	BANCO	11	TRF-009	667.0000	667.0000	t	1	1	2026-06-23 16:08:06
29	1	1	AS-2026-0026	2026-06-23	Compra 3333-2222-1111	COMPRA	29	3333-2222-1111	667.0000	667.0000	t	0	1	2026-06-23 16:05:35
31	1	1	AS-2026-0028	2026-06-23	ANULACIÓN AS-2026-0026: Anulación compra 3333-2222-1111: no tenia los 667 en la caja chica	MANUAL	29	AS-2026-0026	667.0000	667.0000	f	1	1	2026-06-23 16:10:43
33	1	1	AS-2026-0030	2026-06-23	Pago proveedor Pago #29	BANCO	12	Pago #29	13.7900	13.7900	t	1	1	2026-06-23 18:33:11
34	1	1	AS-2026-0031	2026-06-23	Pago proveedor Pago #30	BANCO	12	Pago #30	0.0100	0.0100	t	1	1	2026-06-23 18:33:31
32	1	1	AS-2026-0029	2026-06-23	Compra 000-999-666-5	COMPRA	30	000-999-666-5	13.8000	13.8000	t	0	1	2026-06-23 18:31:42
35	1	1	AS-2026-0032	2026-06-23	ANULACIÓN AS-2026-0029: Anulación compra 000-999-666-5: mal pagada	MANUAL	32	AS-2026-0029	13.8000	13.8000	f	1	1	2026-06-23 18:33:50
36	1	1	AS-2026-0033	2026-06-23	Pago proveedor TRF-009	BANCO	11	TRF-009	667.0000	667.0000	t	1	1	2026-06-23 18:41:35
37	1	1	AS-2026-0034	2026-06-23	Compra 999-8888-7777	COMPRA	28	999-8888-7777	1352.4000	1352.4000	t	1	1	2026-06-23 18:46:27
\.


--
-- Data for Name: asistencias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.asistencias (id, colaborador_id, fecha, hora_entrada, hora_salida, minutos_atraso, horas_extra, tipo_extra, ip_entrada, ip_salida, observacion) FROM stdin;
\.


--
-- Data for Name: bancos_cajas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.bancos_cajas (id, empresa_id, cuenta_id, tipo, nombre, num_cuenta, tipo_cuenta, saldo_inicial, saldo_actual, estado, created_at, updated_at) FROM stdin;
5	1	412	caja	Caja General Taller	\N	\N	800.0000	800.0000	t	2026-06-03 01:01:34	2026-06-03 01:01:34
7	1	416	tarjeta	Datafast Terminal Matriz	TRM-001	\N	0.0000	2000.0000	t	2026-06-03 01:01:34	2026-06-03 01:26:03
4	1	412	caja	Caja General Matriz	\N	\N	2500.0000	5960.0000	t	2026-06-03 01:01:34	2026-06-09 23:19:18
3	1	414	banco	Banco Guayaquil Cta. Cte.	1122334455	corriente	8200.0000	16860.7500	t	2026-06-03 01:01:34	2026-06-12 05:47:13
1	1	414	banco	Banco Pichincha Cta. Cte.	2100456789	corriente	25000.0000	30498.0000	t	2026-06-03 01:01:34	2026-06-18 23:44:16
6	1	413	caja_chica	Caja Chica Administración	\N	\N	200.0000	200.0000	t	2026-06-03 01:01:34	2026-06-23 23:33:11
2	1	414	banco	Banco del Pacífico Cta. Ahorros	0987654321	ahorros	18500.5000	9129.2900	t	2026-06-03 01:01:34	2026-06-23 23:41:35
\.


--
-- Data for Name: bodegas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.bodegas (id, empresa_id, centro_costo_id, nombre, tipo, descripcion, estado, created_at, updated_at, es_virtual) FROM stdin;
1	1	1	Bodega Principal UIO	general	\N	t	\N	\N	f
2	1	\N	Bodega Principal	general	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58	f
3	1	\N	Bodega Taller	taller	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58	f
4	1	\N	Bodega Importaciones	importacion	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58	f
5	1	\N	Bodega Cuarentena	cuarentena	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58	f
6	1	\N	Bodega Reservas	reserva	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58	f
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: categorias_producto; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.categorias_producto (id, empresa_id, categoria_padre_id, nombre, descripcion, estado, created_at, updated_at) FROM stdin;
1	1	\N	Audio Profesional	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
2	1	\N	Iluminación	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
3	1	\N	Video y Proyección	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
4	1	\N	Repuestos y Partes	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
5	1	\N	Insumos y Accesorios	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
6	1	\N	Servicios	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
7	1	1	Micrófonos	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
8	1	1	Consolas de Mezcla	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
9	1	1	Amplificadores	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
10	1	1	Parlantes y Cabinas	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
11	1	1	Controladores DJ	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
12	1	1	Procesadores de Señal	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
13	1	1	Cables de Audio	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
14	1	2	Cabezas Móviles	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
15	1	2	Láseres	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
16	1	2	Controladores DMX	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
17	1	2	Efectos LED	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
18	1	4	Repuestos Electrónicos	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
19	1	4	Cables de Poder	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
20	1	4	Conectores	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
\.


--
-- Data for Name: centros_costo; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.centros_costo (id, empresa_id, nombre, codigo, tipo, es_taller, estado, created_at, updated_at) FROM stdin;
1	1	Altamira Matriz	MATRIZ	empresa	f	t	2026-05-31 00:59:41	2026-05-31 00:59:41
2	2	Altamira Import	IMPORT	empresa	f	t	2026-05-31 00:59:41	2026-05-31 00:59:41
3	1	Altamira Fix	FIX	centro_costo_interno	t	t	2026-05-31 00:59:41	2026-05-31 00:59:41
\.


--
-- Data for Name: cheques; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cheques (id, empresa_id, banco_caja_id, movimiento_id, numero, banco, cuenta, monto, fecha_emision, fecha_cobro, beneficiario, estado, observacion, created_at) FROM stdin;
\.


--
-- Data for Name: cierres_caja; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cierres_caja (id, empresa_id, banco_caja_id, centro_costo_id, fecha, usuario_apertura_id, usuario_cierre_id, monto_inicial, total_facturado, total_cobrado, total_efectivo, total_tarjeta, total_cheque, total_transferencia, total_notas_credito, diferencia, observaciones, estado, hora_apertura, hora_cierre, created_at) FROM stdin;
1	1	6	1	2026-06-03	1	1	100.0000	0.0000	1000.0000	100.0000	200.0000	500.0000	200.0000	0.0000	1000.0000	\N	cerrado	2026-06-03 01:07:55	2026-06-03 01:08:21	2026-06-03 01:07:55
2	1	6	1	2026-06-06	1	1	199.8800	0.0000	400.0000	100.0000	50.0000	200.0000	50.0000	0.0000	400.0000	\N	cerrado	2026-06-06 23:06:33	2026-06-06 23:07:07	2026-06-06 23:06:33
\.


--
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.clientes (id, empresa_id, tipo_identificacion, identificacion, razon_social, nombre_comercial, email, telefono, celular, direccion, ciudad, provincia, pais, tiene_credito, dias_credito, cupo_maximo, agente_retencion, es_cliente_nuevo, estado, created_at, updated_at) FROM stdin;
1	1	05	1712345678001	Comercial El Éxito S.A.	\N	contacto@elexito.ec	022345678	\N	\N	Quito	\N	ECUADOR	t	30	10000.00	f	f	t	2026-06-09 23:18:44	2026-06-09 23:18:44
2	1	05	0923456789001	Eventos & Producciones Guayaquil	\N	info@eventosgye.ec	042345678	\N	\N	Guayaquil	\N	ECUADOR	t	15	5000.00	f	f	t	2026-06-09 23:18:44	2026-06-09 23:18:44
3	1	05	1756789012001	Sonido Profesional del Norte Cía. Ltda.	\N	ventas@sonidopro.ec	062345678	\N	\N	Ibarra	\N	ECUADOR	f	0	0.00	t	f	t	2026-06-09 23:18:44	2026-06-09 23:18:44
\.


--
-- Data for Name: colaboradores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.colaboradores (id, empresa_id, puesto_id, horario_id, cedula_ruc, apellidos, nombres, email, telefono, celular, direccion, fecha_nacimiento, sexo, estado_civil, fecha_ingreso, fecha_salida, tipo_contrato, cargo, departamento, comision_porcentaje, sueldo_base, decimo_tercero, decimo_cuarto, fondos_reserva, banco, tipo_cuenta, numero_cuenta, usuario_id, estado, created_at, updated_at) FROM stdin;
1	1	\N	\N	1712345678	Maldonado Rivera	Carlos Andrés	carlos.maldonado@altamira.com	0991234567	\N	Av. Amazonas N23-45, Quito	\N	\N	\N	2022-01-10	\N	indefinido	Administrador	Administración	0.00	1200.00	acumula	acumula	acumula	Banco Pichincha	ahorros	2201234567	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
2	1	\N	\N	1798765432	Vásquez Torres	María José	maria.vasquez@altamira.com	0987654321	\N	Calle Sucre 12-34, Quito	\N	\N	\N	2021-03-15	\N	indefinido	Contadora	Contabilidad	0.00	1500.00	acumula	mensualiza	acumula	Banco Guayaquil	corriente	0981234567	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
3	1	\N	\N	1723456789	Paredes Godoy	Luis Fernando	luis.paredes@altamira.com	0976543210	\N	Av. 6 de Diciembre N45-12, Quito	\N	\N	\N	2023-06-01	\N	indefinido	Vendedor	Ventas	1.50	650.00	mensualiza	mensualiza	mensualiza	Banco Pichincha	ahorros	2209876543	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
4	1	\N	\N	1734567890	Moreno Salazar	Ana Gabriela	ana.moreno@altamira.com	0965432109	\N	Calle Colón 78-90, Quito	\N	\N	\N	2023-09-15	\N	indefinido	Vendedor	Ventas	1.50	650.00	mensualiza	mensualiza	mensualiza	Produbanco	ahorros	1234567890	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
5	1	\N	\N	1745678901	Villa Espinoza	Jorge Sebastián	jorge.villa@altamira.com	0954321098	\N	Av. República 34-56, Quito	\N	\N	\N	2022-08-20	\N	indefinido	Técnico	Taller	0.00	750.00	acumula	acumula	acumula	Banco del Pacífico	ahorros	5678901234	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
6	1	\N	\N	1756789012	Cárdenas Vega	Roberto Esteban	roberto.cardenas@altamira.com	0943210987	\N	Calle Veintimilla 56-78, Quito	\N	\N	\N	2024-01-05	\N	plazo_fijo	Técnico	Taller	0.00	700.00	mensualiza	mensualiza	mensualiza	Banco Pichincha	ahorros	2203456789	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
7	1	\N	\N	1767890123	Ruiz Andrade	Patricia Elizabet	patricia.ruiz@altamira.com	0932109876	\N	Av. Naciones Unidas N12-34, Quito	\N	\N	\N	2021-11-10	\N	indefinido	Bodeguero	Bodega	0.00	600.00	acumula	acumula	acumula	Banco Guayaquil	ahorros	0986543210	\N	t	2026-06-16 04:17:56	2026-06-16 04:17:56
8	1	\N	\N	1778901234	Herrera Castillo	Diego Mauricio	diego.herrera@altamira.com	0921098765	\N	Calle Ladrón de Guevara 23-45, Quito	\N	\N	\N	2024-04-01	\N	indefinido	Vendedor	Ventas	2.00	650.00	mensualiza	mensualiza	mensualiza	Produbanco	corriente	9876543210	\N	f	2026-06-16 04:17:56	2026-06-16 04:17:56
\.


--
-- Data for Name: compra_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.compra_detalles (id, compra_id, producto_id, cuenta_id, descripcion, cantidad, precio_unitario, descuento, subtotal, porcentaje_iva, valor_iva, total, es_activo_fijo, activo_fijo_id) FROM stdin;
2	2	\N	83	Mezcladora de audio 32 canales	2.0000	1850.0000	0.0000	3700.0000	15.00	555.0000	4255.0000	f	\N
3	2	\N	83	Amplificador de potencia 2000W	3.0000	1200.0000	0.0000	3600.0000	15.00	540.0000	4140.0000	f	\N
4	2	\N	83	Cables XLR profesionales x10	5.0000	45.0000	0.0000	225.0000	15.00	33.7500	258.7500	f	\N
5	3	\N	83	Cabeza móvil LED 200W	8.0000	890.0000	0.0000	7120.0000	15.00	1068.0000	8188.0000	f	\N
6	3	\N	83	Moving head spot 300W	4.0000	1450.0000	0.0000	5800.0000	15.00	870.0000	6670.0000	f	\N
7	4	\N	83	Cable de poder 3x14 AWG (rollo 100m)	3.0000	125.0000	0.0000	375.0000	15.00	56.2500	431.2500	f	\N
8	4	\N	83	Conectores speakon 4p x50	2.0000	89.0000	0.0000	178.0000	15.00	26.7000	204.7000	f	\N
9	4	\N	83	Rack case 12U con ruedas	2.0000	320.0000	0.0000	640.0000	15.00	96.0000	736.0000	f	\N
10	5	\N	121	Flete nacional Guayaquil-Quito	1.0000	380.0000	0.0000	380.0000	15.00	57.0000	437.0000	f	\N
11	5	\N	121	Servicio de carga y descarga	1.0000	120.0000	0.0000	120.0000	15.00	18.0000	138.0000	f	\N
12	6	\N	83	Controlador DJ profesional	5.0000	650.0000	0.0000	3250.0000	15.00	487.5000	3737.5000	f	\N
13	6	\N	83	Auriculares DJ closed-back	10.0000	185.0000	0.0000	1850.0000	15.00	277.5000	2127.5000	f	\N
14	6	\N	83	Interfaz de audio USB 4 canales	6.0000	220.0000	0.0000	1320.0000	15.00	198.0000	1518.0000	f	\N
15	7	\N	83	Condensadores electrolíticos surtidos	1.0000	85.0000	0.0000	85.0000	15.00	12.7500	97.7500	f	\N
16	7	\N	83	Transistores de potencia MOSFET	1.0000	120.0000	0.0000	120.0000	15.00	18.0000	138.0000	f	\N
17	7	\N	83	Soldadura de estaño 60/40 500g	5.0000	18.0000	0.0000	90.0000	15.00	13.5000	103.5000	f	\N
20	9	5	\N	Amplificador QSC GX5 500W Potencia	100.0000	420.0000	8400.0000	33600.0000	15.00	5040.0000	38640.0000	f	\N
21	10	17	\N	Cable Speakon 4P 10 metros	30.0000	15.0000	0.0000	450.0000	15.00	67.5000	517.5000	f	\N
22	11	13	\N	Cabeza Móvil Martin MAC Aura XB LED	8.0000	2200.0000	0.0000	17600.0000	15.00	2640.0000	20240.0000	f	\N
23	12	14	\N	Controlador DMX Chauvet Obey 40 32 Canales	190.0000	85.0000	0.0000	16150.0000	0.00	0.0000	16150.0000	f	\N
24	13	5	\N	Amplificador QSC GX5 500W Potencia	30.0000	420.0000	2520.0000	10080.0000	15.00	1512.0000	11592.0000	f	\N
25	14	5	\N	Amplificador QSC GX5 500W Potencia	15.0000	420.0000	0.0000	6300.0000	15.00	945.0000	7245.0000	f	\N
26	15	5	\N	Amplificador QSC GX5 500W Potencia	10.0000	420.0000	0.0000	4200.0000	15.00	630.0000	4830.0000	f	\N
27	15	18	\N	Cable de Poder Uso Rudo 3x14 AWG 5m	17.0000	8.0000	0.0000	136.0000	15.00	20.4000	156.4000	f	\N
28	16	1	\N	Micrófono Shure BLX24	10.0000	500.0000	0.0000	5000.0000	0.00	0.0000	5000.0000	f	\N
29	16	2	\N	Micrófono Shure SM7B	10.0000	500.0000	0.0000	5000.0000	0.00	0.0000	5000.0000	f	\N
30	16	3	\N	Consola Yamaha MG16XU	10.0000	500.0000	0.0000	5000.0000	0.00	0.0000	5000.0000	f	\N
53	24	51	\N	Micrófono Dinámico Shure SM58-LC	80.0000	89.0000	0.0000	7120.0000	0.00	0.0000	7120.0000	f	\N
54	24	52	\N	Micrófono Instrumental Shure SM57-LC	80.0000	79.0000	0.0000	6320.0000	0.00	0.0000	6320.0000	f	\N
55	24	53	\N	Sistema Inalámbrico Shure BLX288/PG58	24.0000	210.0000	0.0000	5040.0000	0.00	0.0000	5040.0000	f	\N
56	25	54	\N	Consola de Mezcla Yamaha MG20XU 20 Canales	20.0000	420.0000	0.0000	8400.0000	0.00	0.0000	8400.0000	f	\N
57	25	55	\N	Monitor de Estudio Yamaha HS8 8"	20.0000	380.0000	0.0000	7600.0000	0.00	0.0000	7600.0000	f	\N
58	25	56	\N	Procesador Digital Yamaha SPX2000	12.0000	650.0000	0.0000	7800.0000	0.00	0.0000	7800.0000	f	\N
59	25	57	\N	Amplificador de Potencia Yamaha P7000S	10.0000	780.0000	0.0000	7800.0000	0.00	0.0000	7800.0000	f	\N
60	26	58	\N	Cabeza Móvil Chauvet Pro Rogue R3 Wash	20.0000	850.0000	0.0000	17000.0000	0.00	0.0000	17000.0000	f	\N
61	26	59	\N	Controlador DMX Chauvet Obey 70	10.0000	180.0000	0.0000	1800.0000	0.00	0.0000	1800.0000	f	\N
62	26	60	\N	Par LED Chauvet SlimPAR Pro RGBA IP	20.0000	120.0000	0.0000	2400.0000	0.00	0.0000	2400.0000	f	\N
63	26	61	\N	Máquina de Humo Chauvet Nimbus Dry Ice	10.0000	290.0000	0.0000	2900.0000	0.00	0.0000	2900.0000	f	\N
64	27	9	\N	Controlador Pioneer DDJ-FLX6 4 Decks	3.0000	650.0000	0.0000	1950.0000	0.00	0.0000	1950.0000	f	\N
65	27	10	\N	Tornamesa Pioneer PLX-1000 Direct Drive	3.0000	480.0000	0.0000	1440.0000	0.00	0.0000	1440.0000	f	\N
66	27	62	\N	Auriculares DJ Pioneer HDJ-X5 Negro	8.0000	85.0000	0.0000	680.0000	0.00	0.0000	680.0000	f	\N
67	27	63	\N	Cable XLR Balanceado 10m Canare L-4E6S	50.0000	18.0000	0.0000	900.0000	0.00	0.0000	900.0000	f	\N
68	28	6	\N	Amplificador Crown XTi 2002 650W	2.0000	580.0000	0.0000	1160.0000	15.00	174.0000	1334.0000	f	\N
69	28	18	\N	Cable de Poder Uso Rudo 3x14 AWG 5m	2.0000	8.0000	0.0000	16.0000	15.00	2.4000	18.4000	f	\N
70	29	6	\N	Amplificador Crown XTi 2002 650W	1.0000	580.0000	0.0000	580.0000	15.00	87.0000	667.0000	f	\N
71	30	16	\N	Cable XLR Macho-Hembra 10 metros Neutrik	1.0000	12.0000	0.0000	12.0000	15.00	1.8000	13.8000	f	\N
72	31	15	\N	Efecto LED ADJ Mega Bar 50RGB RC	1.0000	120.0000	0.0000	120.0000	15.00	18.0000	138.0000	f	\N
73	32	59	\N	Controlador DMX Chauvet Obey 70	2.0000	180.0000	0.0000	360.0000	15.00	54.0000	414.0000	f	\N
74	32	18	\N	Cable de Poder Uso Rudo 3x14 AWG 5m	2.0000	8.0000	0.0000	16.0000	15.00	2.4000	18.4000	f	\N
\.


--
-- Data for Name: compras; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.compras (id, empresa_id, centro_costo_id, proveedor_id, importacion_id, bodega_id, tipo_documento, num_documento, num_autorizacion, fecha_emision, fecha_registro, fecha_vencimiento, dias_credito, subtotal_0, subtotal_iva, total_iva, total_ice, total, iva_asumido, gasto_no_deducible, sustento_tributario, asiento_id, tiene_pago, concepto, estado, created_by, created_at, updated_at) FROM stdin;
3	1	\N	1	\N	\N	FAC	002-001-000456	9876543210987654321098765432109876543210987654321	2026-05-03	2026-06-02	2026-05-03	0	0.0000	12920.0000	1938.0000	0.0000	14858.0000	f	f	\N	\N	f	Compra de equipos de iluminación	anulada	1	2026-06-02 02:47:39	2026-06-23 03:11:27
2	1	\N	2	\N	\N	FAC	001-001-000123	1234567890123456789012345678901234567890123456789	2026-04-18	2026-06-02	2026-05-18	30	0.0000	7525.0000	1128.7500	0.0000	8653.7500	f	f	\N	\N	f	Compra de equipos de audio profesional	anulada	1	2026-06-02 02:47:39	2026-06-23 03:11:31
9	1	1	8	\N	1	FAC	002-066666556-70707070	53654645	2026-06-13	2026-06-13	2026-06-13	0	0.0000	33600.0000	5040.0000	0.0000	38640.0000	t	f	7	\N	f	\N	pendiente	1	2026-06-13 23:43:31	2026-06-23 12:47:12
14	1	1	4	\N	2	FAC	008-444-56777	4566	2026-06-18	2026-06-18	2026-08-02	45	0.0000	6300.0000	945.0000	0.0000	7245.0000	t	f	3	25	t	\N	anulada	1	2026-06-18 04:46:19	2026-06-18 23:53:18
15	1	1	8	\N	2	FAC	009-88-777	1233	2026-06-20	2026-06-20	2026-08-19	60	0.0000	4336.0000	650.4000	0.0000	4986.4000	t	f	12	\N	f	\N	pendiente	1	2026-06-20 22:50:55	2026-06-20 22:50:55
10	1	1	8	\N	2	LIQ	999-222-44444	2324	2026-06-14	2026-06-14	2026-06-14	0	0.0000	450.0000	67.5000	0.0000	517.5000	t	f	4	\N	f	\N	pendiente	1	2026-06-14 00:13:54	2026-06-23 12:47:12
7	1	\N	2	\N	\N	FAC	001-001-000200	4444444444444444444444444444444444444444444444444	2026-05-28	2026-06-02	2026-05-18	30	0.0000	295.0000	44.2500	0.0000	339.2500	f	f	\N	\N	t	Repuestos y suministros para taller técnico	anulada	1	2026-06-02 02:47:39	2026-06-23 03:11:00
6	1	\N	3	\N	\N	FAC	001-003-000654	3333333333333333333333333333333333333333333333333	2026-05-23	2026-06-02	2026-05-03	15	0.0000	6420.0000	963.0000	0.0000	7383.0000	f	f	\N	\N	t	Compra de instrumentos y accesorios musicales	anulada	1	2026-06-02 02:47:39	2026-06-23 03:11:09
5	1	\N	5	\N	\N	FAC	003-001-000321	2222222222222222222222222222222222222222222222222	2026-05-18	2026-06-02	2026-05-18	30	0.0000	500.0000	75.0000	0.0000	575.0000	f	f	\N	\N	f	Servicio de transporte y logística	anulada	1	2026-06-02 02:47:39	2026-06-23 03:11:17
4	1	\N	4	\N	\N	FAC	001-002-000789	1111111111111111111111111111111111111111111111111	2026-05-13	2026-06-02	2026-06-02	45	0.0000	1193.0000	178.9500	0.0000	1371.9500	f	f	\N	\N	f	Compra de cables y accesorios varios	anulada	1	2026-06-02 02:47:39	2026-06-23 03:11:21
11	1	1	10	\N	5	FAC	77899999990000	77887	2026-06-14	2026-06-14	2026-06-14	0	0.0000	17600.0000	2640.0000	0.0000	20240.0000	t	f	1	\N	f	\N	pendiente	1	2026-06-14 05:22:28	2026-06-21 17:08:43
12	1	1	8	\N	5	FAC	9999888777	888	2026-06-14	2026-06-14	2026-08-13	60	16150.0000	0.0000	0.0000	0.0000	16150.0000	t	f	7	\N	t	\N	pendiente	1	2026-06-14 14:46:49	2026-06-21 17:08:43
13	1	1	9	\N	5	FAC	324737945793475	3242	2026-06-14	2026-06-14	2026-07-29	45	0.0000	10080.0000	1512.0000	0.0000	11592.0000	f	f	3	\N	t	\N	pendiente	1	2026-06-14 15:22:10	2026-06-21 17:08:43
16	1	\N	6	5	\N	FAC	IMP-TEST-001	\N	2026-06-15	2026-06-15	2026-06-15	0	15000.0000	0.0000	0.0000	0.0000	15000.0000	f	f	\N	\N	f	\N	anulada	1	2026-06-23 12:30:53	2026-06-23 20:40:09
27	1	1	14	4	4	EXT	PKN-2026-0001	\N	2026-04-20	2026-04-20	\N	0	4970.0000	0.0000	0.0000	0.0000	4970.0000	f	f	7	\N	f	\N	anulada	1	2026-06-23 17:42:17	2026-06-23 20:46:56
26	1	1	8	3	4	EXT	CHV-2026-INV-0456	\N	2026-04-15	2026-04-15	\N	0	24100.0000	0.0000	0.0000	0.0000	24100.0000	f	f	7	\N	f	\N	anulada	1	2026-06-23 17:42:17	2026-06-23 20:47:01
25	1	1	7	2	4	EXT	YMH-2026-INV-0234	\N	2026-04-01	2026-04-01	\N	0	31600.0000	0.0000	0.0000	0.0000	31600.0000	f	f	7	\N	f	\N	anulada	1	2026-06-23 17:42:17	2026-06-23 20:47:05
24	1	1	6	1	1	EXT	SHR-2026-FAC-001	\N	2026-02-15	2026-02-15	\N	0	18480.0000	0.0000	0.0000	0.0000	18480.0000	f	f	7	\N	f	\N	anulada	1	2026-06-23 17:42:17	2026-06-23 20:47:08
30	1	1	9	\N	2	FAC	000-999-666-5	123	2026-06-23	2026-06-23	2026-08-07	45	0.0000	12.0000	1.8000	0.0000	13.8000	t	t	3	32	t	\N	anulada	1	2026-06-23 23:30:19	2026-06-23 23:33:50
31	1	1	9	\N	2	FAC	002-999-345777	7766	2026-06-23	2026-06-23	2026-08-07	45	0.0000	120.0000	18.0000	0.0000	138.0000	t	t	87	\N	f	\N	pendiente	1	2026-06-23 23:37:49	2026-06-23 23:37:49
32	1	1	8	\N	2	FAC	55-9987-0987766	456	2026-06-23	2026-06-23	2026-08-22	60	0.0000	376.0000	56.4000	0.0000	432.4000	f	f	67	\N	f	\N	pendiente	1	2026-06-23 23:38:24	2026-06-23 23:38:24
29	1	1	4	\N	2	FAC	3333-2222-1111	123	2026-06-23	2026-06-23	2026-08-07	45	0.0000	580.0000	87.0000	0.0000	667.0000	f	f	2	29	t	\N	activa	1	2026-06-23 20:43:48	2026-06-23 23:41:35
28	1	1	6	\N	2	FAC	999-8888-7777	1234	2026-06-23	2026-06-23	2026-08-22	60	0.0000	1176.0000	176.4000	0.0000	1352.4000	t	f	1	37	f	\N	activa	1	2026-06-23 20:43:07	2026-06-23 23:46:26
\.


--
-- Data for Name: conciliaciones_bancarias; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.conciliaciones_bancarias (id, empresa_id, banco_caja_id, fecha_corte, saldo_banco, saldo_sistema, diferencia, descripcion, archivo_csv, estado, created_by, created_at) FROM stdin;
1	1	2	2026-06-03	16300.5000	17300.5000	-1000.0000	Gastos	\N	conciliada	1	2026-06-03 01:11:01
\.


--
-- Data for Name: configuraciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.configuraciones (id, empresa_id, clave, valor, tipo, descripcion, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cuentas_cobrar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_cobrar (id, empresa_id, cliente_id, factura_id, prefactura_id, monto, saldo, fecha_emision, fecha_vencimiento, forma_cobro, estado, asiento_cobro_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: cuentas_pagar; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.cuentas_pagar (id, empresa_id, proveedor_id, compra_id, monto, saldo, fecha_emision, fecha_vencimiento, aprobada, estado, asiento_pago_id, created_at, updated_at) FROM stdin;
9	1	4	14	7245.0000	0.0000	2026-06-18	2026-08-02	f	pagada	\N	2026-06-18 23:43:54	2026-06-18 23:44:16
12	1	9	30	13.8000	0.0000	2026-06-23	2026-08-07	f	pagada	\N	2026-06-23 23:31:41	2026-06-23 23:33:30
11	1	4	29	667.0000	0.0000	2026-06-23	2026-08-07	f	pagada	\N	2026-06-23 21:05:35	2026-06-23 23:41:35
13	1	6	28	1352.4000	1352.4000	2026-06-23	2026-08-22	f	pendiente	\N	2026-06-23 23:46:26	2026-06-23 23:46:26
\.


--
-- Data for Name: datafast_liquidaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.datafast_liquidaciones (id, lote_id, fecha_deposito, valor_bruto, comision_datafast, retencion_iva, retencion_ir, valor_neto, banco_destino_id, asiento_id, created_by, created_at) FROM stdin;
1	3	2026-06-06	2100.0000	10.0000	1.2000	10.0000	2078.8000	2	\N	1	2026-06-06 23:08:46
\.


--
-- Data for Name: datafast_lotes; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.datafast_lotes (id, empresa_id, banco_caja_id, numero_lote, fecha, total_vouchers, asiento_id, estado, created_by, created_at) FROM stdin;
1	1	7	LOT-20260525	2026-05-26	3250.0000	\N	liquidado	1	2026-06-03 01:01:34
2	1	7	LOT-20260528	2026-05-29	1890.5000	\N	liquidado	1	2026-06-03 01:01:34
3	1	7	LOT-20260601	2026-06-02	2100.0000	\N	liquidado	1	2026-06-03 01:01:34
\.


--
-- Data for Name: ejercicios_contables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.ejercicios_contables (id, empresa_id, anio, mes, descripcion, fecha_apertura, fecha_cierre, cerrado_por, estado, created_at) FROM stdin;
1	1	2026	7	Julio 2026	2026-06-01	\N	\N	abierto	2026-05-31 20:13:10
3	1	2026	2	Febrero 2026	2026-05-01	2026-05-31	\N	cerrado	2026-06-01 00:13:25
4	1	2026	3	Marzo 2026	2026-04-01	2026-04-30	\N	cerrado	2026-06-01 00:13:25
5	1	2026	4	Abril 2026	2026-03-01	2026-03-31	\N	cerrado	2026-06-01 00:13:25
6	1	2026	5	Mayo 2026	2026-02-01	2026-02-28	\N	cerrado	2026-06-01 00:13:25
7	1	2026	6	Junio 2026	2026-01-01	2026-01-31	\N	cerrado	2026-06-01 00:13:25
2	1	2026	1	Enero 2026	2026-06-01	2026-01-31	\N	cerrado	2026-06-01 00:13:25
\.


--
-- Data for Name: empresa_usuario; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.empresa_usuario (empresa_id, usuario_id) FROM stdin;
1	1
2	1
1	2
\.


--
-- Data for Name: empresas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.empresas (id, razon_social, nombre_comercial, ruc, direccion_matriz, direccion_establecimiento, email_notificaciones, telefono, logo, slogan, ambiente_sri, codigo_establecimiento, codigo_punto_emision, obligado_contabilidad, contribuyente_especial, numero_resolucion_agente_retencion, firma_electronica, clave_firma, estado, created_at, updated_at, deleted_at) FROM stdin;
1	ALTAMIRA LIGHT & SOUND CIA. LTDA.	Altamira Light & Sound	1711293454001	Quito, Ecuador	\N	admin@altamira.com	\N	\N	Ahora las luces se ven Diferente	1	001	001	t	f	\N	\N	\N	t	2026-05-31 00:59:41	2026-05-31 00:59:41	\N
2	ALTAMIRA IMPORT CIA. LTDA.	Altamira Import	1755265848001	Quito, Ecuador	\N	import@altamira.com	\N	\N	Solo un DJ sabe lo que otro DJ necesita	1	001	001	t	f	\N	\N	\N	t	2026-05-31 00:59:41	2026-05-31 00:59:41	\N
\.


--
-- Data for Name: erp_plan_cuentas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.erp_plan_cuentas (pln_id, pln_codigo, pln_descripcion, pln_obs, pln_estado, pln_grupo) FROM stdin;
12	1.1.01.01.08	Transferencia Bancarias 	\N	0	A
13	1.1.01.01.09	Reverso Depositos	\N	0	A
14	1.1.01.01.99	Cheque Devueltos	\N	0	A
15	1.1.01.02.	BANCOS LOCALES	\N	0	A
16	1.1.01.02.01	Banco Produbanco	\N	0	A
17	1.1.01.02.02	Banco Pichincha Cta Cte:	\N	0	A
18	1.1.01.02.03	Alianza del Valle Cia. Ltda. 	\N	0	A
19	1.1.02.	DEUDORES COMERCIALES Y OTRAS CUENTAS POR COBRAR	\N	0	A
20	1.1.02.01.	DOCUMENTOS Y CUENTAS POR COBRAR CLIENTES NO RELACIONADOS	\N	0	A
21	1.1.02.01.01	Cuentas por Cobrar Clientes	\N	0	A
22	1.1.02.01.02	Cuentas por Cobrar Clientes 2021	\N	0	A
23	1.1.02.02.	DOCUMENTOS Y CUENTAS POR COBRAR CLIENTES RELACIONADOS	\N	0	A
24	1.1.02.02.01	Cuentas por Cobrar Clientes Relacionados	\N	0	A
25	1.1.02.02.02	Intereses por Realizar - Clientes	\N	0	A
26	1.1.02.03.	OTRAS CUENTAS POR COBRAR RELACIONADAS	\N	0	A
27	1.1.02.03.01	Cuentas por Cobrar Marlen Rondal	\N	0	A
28	1.1.02.03.02	Cuentas por Cobrar Comisiones TC	\N	0	A
29	1.1.02.04.	OTRAS CUENTAS POR COBRAR NO RELACIONADAS	\N	0	A
30	1.1.02.04.01.	CUENTAS POR COBRAR EMPLEADOS	\N	0	A
31	1.1.02.04.01.01	Segundo Rigoberto Silva Matute 	\N	0	A
32	1.1.02.04.01.02	Maria Luz Cleotilde Guanotasig Caisa 	\N	0	A
33	1.1.02.04.01.03	Luis Fernando Andagoya Ramos 	\N	0	A
34	1.1.02.04.01.04	Juan Pablo Constante Pozo 	\N	0	A
35	1.1.02.04.02.	OTRAS CUENTAS POR COBRAR	\N	0	A
36	1.1.02.04.02.01	Comisiones TC por conciliar	\N	0	A
37	1.1.02.04.02.02	Valores por Justificar 	\N	0	A
38	1.1.02.05.	PROVISION CUENTAS INCOBRABLES	\N	0	A
39	1.1.02.05.01	Provision Cuentas Incobrables Come	\N	0	A
40	1.1.02.05.02	Provision Cuentas Incobrables Gene	\N	0	A
41	1.1.02.06.	OTRAS CTAS POR COBRAR	\N	0	A
42	1.1.02.06.01	Comisiones TC x Cobrar	\N	0	A
43	1.1.03.	INVENTARIOS	\N	0	A
44	1.1.03.01.	INVENTARIOS PRODUCTOS TERMINADOS	\N	0	A
45	1.1.03.01.01	Inventario productos terminados	\N	0	A
46	1.1.03.02.	MERCADERIAS EN TRANSITO	\N	0	A
47	1.1.03.02.01	Inventarios en Transito	\N	0	A
48	1.1.04.	SERVICIOS Y OTROS PAGOS ANTICIPADOS	\N	0	A
49	1.1.04.01.	SEGUROS PAGADOS POR ANTICIPADO	\N	0	A
50	1.1.04.01.01	Seguros pagados por anticipado	\N	0	A
51	1.1.04.02.	ARRIENDOS PAGADOS POR ANTICIPADO	\N	0	A
52	1.1.04.02.01	Arriendo pagado por anticipado	\N	0	A
53	1.1.04.03.	ANTICIPOS A PROVEEDORES	\N	0	A
54	1.1.04.03.01	Anticipo Pago a Proveedores	\N	0	A
55	1.1.04.03.02	Anticipo Proveedores Exterior 	\N	0	A
56	1.1.04.04.	OTROS ANTICIPOS ENTREGADOS	\N	0	A
57	1.1.04.04.01	Anticipo Varios	\N	0	A
58	1.1.04.04.02	Otras Ctas. por Cobrar	\N	0	A
59	1.1.04.04.03	Anticipo Sueldos 	\N	0	A
60	1.1.04.04.04	Prestamo Empleado 	\N	0	A
61	1.1.04.04.05	Anticipo Comisiones	\N	0	A
62	1.1.05.	ACTIVOS POR IMPUESTOS CORRIENTES	\N	0	A
63	1.1.05.01.	CREDITO TRIBUTARIO A FAVOR DE LA EMPRESA IVA	\N	0	A
64	1.1.05.01.01	IVA Pagado	\N	0	A
65	1.1.05.01.02	Retenciones IVA Recibidas	\N	0	A
66	1.1.05.01.03	Credito Tributario IVA	\N	0	A
67	1.1.05.01.04	Iva Pagado en Importaciones	\N	0	A
68	1.1.05.02.	CREDITO TRIBUTARIO A FAVOR DE LA EMPRESA I. R.	\N	0	A
69	1.1.05.02.01	Ret del Impto a la Renta Ejercicio	\N	0	A
70	1.1.05.03.	ANTICIPO DE IMPUESTO A LA RENTA	\N	0	A
71	1.1.05.03.01	Anticipo del Impuesto a la Renta de	\N	0	A
72	1.1.06.	OTROS ACTIVOS CORRIENTES	\N	0	A
73	1.1.06.01.	OTROS ACTIVOS CORRIENTES	\N	0	A
74	1.1.06.01.01	Otros Activos Corrientes	\N	0	A
75	1.2.	ACTIVO NO CORRIENTE	\N	0	A
76	1.2.01.	PROPIEDADES. PLANTA Y EQUIPO	\N	0	A
77	1.2.01.01	Terrenos	\N	0	A
78	1.2.01.02	Edificios	\N	0	A
79	1.2.01.03	Construcciones en Curso	\N	0	A
80	1.2.01.04	Instalaciones	\N	0	A
81	1.2.01.05	Muebles y Enseres - Equipos de Ofic	\N	0	A
82	1.2.01.06	Maquinaria y Equipo	\N	0	A
83	1.2.01.07	Equipo de Computacion y Software	\N	0	A
84	1.2.01.08	Vehculos. Equipo de Transporte	\N	0	A
85	1.2.01.09	Otras Propiedades. Planta y Equipo	\N	0	A
86	1.2.01.1.2.	DEPRECIACION ACUMULADA PROPIEDADES PLANTA Y EQUIPO	\N	0	A
87	1.2.01.1.2.01	Depreciacion Acumulada Propiedad, Planta y Equipo	\N	0	A
88	1.2.01.13.	DETERIORO ACUMULADO DE PROPIEDADES PLANTA Y EQUIPO	\N	0	A
89	1.2.01.13.01	Deterioro Acumulado Propiedad, Planta y Equipo	\N	0	A
90	1.2.03.	ACTIVOS INTANGIBLES	\N	0	A
91	1.2.03.01	Paquetes Informticos y Software	\N	0	A
92	1.2.03.05.	AMORTIZACION ACUMULADA ACTIVOS INTANGIBLES	\N	0	A
93	1.2.03.05.01	Amortizacion Acumulada Paquetes In	\N	0	A
94	1.2.03.05.02	Deterioro Acumulado Paquetes Info	\N	0	A
95	1.2.04.	ACTIVOS POR IMPUESTOS A LA RENTA DIFERIDOS	\N	0	A
96	1.2.04.01	Activos por Impuestos Diferidos	\N	0	A
97	1.2.05.	ACTIVOS FINANCIEROS NO CORRIENTES	\N	0	A
196	2.2.06.	PASIVO DIFERIDO	\N	0	A
98	1.2.05.01.	DOCUMENTOS Y CUENTAS POR COBRAR LARGO PLAZO	\N	0	A
99	1.2.05.01.01	Cuentas por Cobrar Clientes a Largo	\N	0	A
100	1.2.05.01.02	Intereses por Realizar - Cuentas	\N	0	A
101	1.2.05.02.	PROVISION CUENTAS INCOBRABLES DE ACTIVOS FINANCIEROS NO CORRIENTES	\N	0	A
102	1.2.05.02.01	Provisin Cuentas Incobrables de a	\N	0	A
103	1.2.06.	OTROS ACTIVOS NO CORRIENTES	\N	0	A
104	1.2.06.01.	OTROS ACTIVOS NO CORRIENTES	\N	0	A
105	1.2.06.01.01	Otros Activos no Corrientes	\N	0	A
106	1.2.06.01.02	Garantias Varias	\N	0	A
107	2.	PASIVO	\N	0	A
108	2.1.	PASIVO CORRIENTE	\N	0	A
109	2.1.01.	ACREEDORES COMERCIALES Y OTRAS CUENTAS POR COBRAR	\N	0	A
110	2.1.01.01.	ACREEDORES COMERCIALES LOCALES	\N	0	A
111	2.1.01.01.01	Proveedores Locales	\N	0	A
112	2.1.01.01.02.	Tarjeta Corporativa por pagar	\N	0	A
113	2.1.01.01.02.01	Diners Edwin Altamirano	\N	0	A
114	2.1.01.01.02.02	Diners Rondal	\N	0	A
115	2.1.01.01.02.03	Pacificard Edwin	\N	0	A
116	2.1.01.01.02.04	Pacificard  Rondal	\N	0	A
117	2.1.01.01.02.05	Banco del Produbanco	\N	0	A
118	2.1.01.01.02.06	Banco Guayaquil	\N	0	A
119	2.1.01.01.02.07	Visa Titanium 	\N	0	A
120	2.1.01.01.02.08	Otras Ctas Por Pagar Clientes	\N	0	A
121	2.1.01.02.	ACREEDORES COMERCIALES DEL EXTERIOR	\N	0	A
122	2.1.01.02.01	Proveedores del Exterior	\N	0	A
123	2.1.01.02.02	Intereses por Devengar - Proveedo	\N	0	A
124	2.1.01.03.	CUENTAS POR PAGAR RELACIONADAS	\N	0	A
125	2.1.01.03.01	Cuentas por Pagar 	\N	0	A
126	2.1.01.04.	CUENTAS POR PAGAR NO RELACIONADAS	\N	0	A
127	2.1.01.04.01	Claro	\N	0	A
128	2.1.01.04.02	CNT	\N	0	A
129	2.1.01.04.03	Ecuasanitas S.A.	\N	0	A
130	2.1.01.04.04	Datafast	\N	0	A
131	2.1.01.04.05	Epmaps	\N	0	A
132	2.1.01.04.06	EEQ	\N	0	A
133	2.1.01.04.07	Envios Clientes	\N	0	A
134	2.1.01.04.08	Megadatos	\N	0	A
135	2.1.01.04.30	Otras Ctas. Por Pagar 	\N	0	A
136	2.1.02.	OBLIGACIONES CON INSTITUCIONES FINANCIERAS	\N	0	A
137	2.1.02.01.	OBLIGACIONES FINANCIERAS LOCALES	\N	0	A
138	2.1.02.01.01	Sobregiros Bancarios	\N	0	A
139	2.1.03.	PROVISIONES	\N	0	A
140	2.1.03.01.	PROVISIONES LOCALES	\N	0	A
141	2.1.03.01.01	Depositos sin identificar - Clientes	\N	0	A
142	2.1.03.01.02	Multas Personal	\N	0	A
143	2.1.04.	OBLIGACIONES LABORALES Y FISCALES	\N	0	A
144	2.1.04.01.	OBLIGACIONES ADMINISTRACION TRIBUTARIA	\N	0	A
145	2.1.04.01.01	Ret en la Fuente Impto a la Renta p	\N	0	A
146	2.1.04.01.02	Retenciones IVA por Pagar	\N	0	A
147	2.1.04.01.03	IVA en Ventas	\N	0	A
148	2.1.04.01.04	Impuesto por Liquidar	\N	0	A
149	2.1.04.01.04.	IMPUESTOS POR LIQUIDAR	\N	0	A
150	2.1.04.01.04.01	Formulario 103	\N	0	A
151	2.1.04.01.04.02	Formulario 104	\N	0	A
152	2.1.04.01.05	Retenciones en la Fuente x Pagar	\N	0	A
153	2.1.04.02.	IMPUESTO A LA RENTA POR PAGAR	\N	0	A
154	2.1.04.02.01	Impuesto a la Renta por Pagar Ejerc	\N	0	A
155	2.1.04.03.	OBLIGACIONES CON EL IESS	\N	0	A
156	2.1.04.03.01	Aportes IESS por Pagar	\N	0	A
157	2.1.04.03.02	Prestamos IESS por Pagar	\N	0	A
158	2.1.04.03.03	Fondos de Reserva por Pagar	\N	0	A
159	2.1.04.03.99	PLANILLAS MENSUALES POR CANCELAR	\N	0	A
160	2.1.04.04.	OBLIGACIONES LABORALES	\N	0	A
161	2.1.04.04.01	Remuneraciones por Pagar	\N	0	A
162	2.1.04.04.02	Decimo Tercer Sueldo por Pagar	\N	0	A
163	2.1.04.04.03	Decimo Cuarto Sueldo por Pagar	\N	0	A
164	2.1.04.04.04	Vacaciones por Pagar	\N	0	A
165	2.1.04.04.05	Finiquitos por Pagar	\N	0	A
166	2.1.04.05.	PARTICIPACION TRABAJADORES POR PAGAR DEL EJERCICIO	\N	0	A
167	2.1.04.05.01	15% Participacin a Trabajadores	\N	0	A
168	2.1.04.06.	DIVIDENDOS POR PAGAR	\N	0	A
169	2.1.04.06.01	Dividendos por Pagar	\N	0	A
170	2.1.07.	PORCION CORRIENTE PROVISIONES POR BENEFICIOS EMPLEADOS	\N	0	A
171	2.1.07.01.	PROVISION JUBILACION PATRONAL	\N	0	A
172	2.1.07.01.01	Provision Jubilacin Patronal Porcin	\N	0	A
173	2.1.07.02.	OTROS BENEFICIOS A LARGO PLAZO PARA LOS EMPLEADOS	\N	0	A
174	2.1.07.02.01	Provision Desahucio Porcin Corriente	\N	0	A
175	2.1.08.	OTROS PASIVOS CORRIENTES	\N	0	A
176	2.1.08.01.	OTROS PASIVOS CORRIENTES	\N	0	A
177	2.1.08.01.01	Otros Pasivos Corrientes	\N	0	A
178	2.1.08.01.02	Mercaderia Temporal por Liquidar	\N	0	A
179	2.2.	PASIVO NO CORRIENTE	\N	0	A
180	2.2.01.	ACREEDORES COMERCIALES Y OTRAS CUENTAS POR PAGAR LP	\N	0	A
181	2.2.01.01.	ACREEDORES COMERCIALES LOCALES	\N	0	A
182	2.2.01.01.01	Proveedores LP	\N	0	A
183	2.2.01.01.02	Intereses por Devengar - Proveedo	\N	0	A
184	2.2.01.02.	CUENTAS POR PAGAR RELACIONADAS	\N	0	A
185	2.2.01.02.01	Cuentas por Pagar Relacionadas	\N	0	A
186	2.2.01.03.	CUENTAS POR PAGAR NO RELACIONADAS	\N	0	A
187	2.2.01.03.01	Cuentas por Pagar Terceros LP	\N	0	A
188	2.2.02.	OBLIGACIONES CON INSTITUCIONES FINANCIERAS	\N	0	A
189	2.2.03.	ANTICIPOS DE CLIENTES	\N	0	A
190	2.2.03.01	Anticipo de clientes	\N	0	A
191	2.2.04.	PROVISIONES POR BENEFICIOS A EMPLEADOS	\N	0	A
192	2.2.04.01.	PROVISION JUBILACION PATRONAL	\N	0	A
193	2.2.04.01.01	Provisin Jubilacin Patronal	\N	0	A
194	2.2.04.02.	OTROS BENEFICIOS A LARGO PLAZO PARA LOS EMPLEADOS	\N	0	A
195	2.2.04.02.01	Provisin Desahucio	\N	0	A
197	2.2.06.01.	INGRESOS DIFERIDOS	\N	0	A
198	2.2.06.01.01	Ingresos Diferidos	\N	0	A
199	2.2.06.02.	PASIVOS POR IMPUESTOS DIFERIDOS	\N	0	A
200	2.2.06.02.01	Pasivo por Impuesto Diferido ao 1	\N	0	A
201	2.2.07.01.	OTROS PASIVOS NO CORRIENTES	\N	0	A
202	2.2.07.01.01	Otros Pasivos no Corrientes	\N	0	A
203	3.	PATRIMONIO NETO	\N	0	A
204	3.1.	CAPITAL	\N	0	A
205	3.1.01.	CAPITAL SUSCRITO o ASIGNADO	\N	0	A
206	3.1.01.01	Capital Suscrito y Pagado	\N	0	A
207	3.1.02.	CAPITAL SUSCRITO NO PAGADO ACCIONES EN TESORERIA	\N	0	A
208	3.1.02.01	Capital Suscrito no pagado	\N	0	A
209	3.2.	APORTES DE ACCIONISTAS PARA FUTURA CAPITALIZACION	\N	0	A
210	3.2.01.	APORTES DE ACCIONISTAS PARA FUTURA CAPITALIZACION	\N	0	A
211	3.2.01.01	Aportes Futuras Capitalizacion	\N	0	A
212	3.3.	RESERVAS	\N	0	A
213	3.3.01.	RESERVA LEGAL	\N	0	A
214	3.3.01.01	Reserva Legal	\N	0	A
215	3.3.02.	RESERVAS FACULTATIVAS ESTATUTARIA	\N	0	A
216	3.3.02.01	Reservas Facultativas y Estatutaria	\N	0	A
217	3.3.03.	RESERVA DE CAPITAL	\N	0	A
218	3.3.03.01	Reserva de Capital	\N	0	A
219	3.3.04.	OTRAS RESERVAS	\N	0	A
220	3.3.04.01	Otras Reservas	\N	0	A
221	3.4.	OTROS RESULTADOS INTEGRALES	\N	0	A
222	3.4.01	Otros Resultado Integrales	\N	0	A
223	3.5.	RESULTADOS ACUMULADOS	\N	0	A
224	3.5.01.	GANANCIAS ACUMULADAS	\N	0	A
225	3.5.01.01	Ganancias 2021	\N	0	A
226	3.5.02.	PERDIDAS ACUMULADAS	\N	0	A
227	3.5.02.01	Perdidas Acumuladas	\N	0	A
228	3.5.02.02	Perdida Año 2021	\N	0	A
229	3.5.03.	RESULTADOS ACUMULADOS NIIF	\N	0	A
230	3.5.03.01	Resultados Acumulados NIIF	\N	0	A
231	3.7.	RESULTADOS DEL EJERCICIO	\N	0	A
232	3.7.01.	GANANCIA NETA DEL PERIODO	\N	0	A
233	3.7.01.01	Ganancia neta del periodo	\N	0	A
234	3.7.02.	PERDIDA NETA DEL PERIODO	\N	0	A
235	3.7.02.01	Perdida Neta del Periodo	\N	0	A
236	4.	INGRESOS	\N	0	A
237	4.1.	INGRESOS DE ACTIVIDADES ORDINARIAS	\N	0	A
238	4.1.01.	VENTA DE BIENES	\N	0	A
239	4.1.01.01	Ventas de Bienes	\N	0	A
240	4.1.02.	VENTA DE SERVICIOS	\N	0	A
241	4.1.02.01	Mantenimiento	\N	0	A
242	4.1.02.02	Aseroria 	\N	0	A
243	4.1.02.03	Instalaciones	\N	0	A
244	4.1.02.04	Repuestos 	\N	0	A
245	4.1.06.	DESCUENTO EN VENTAS	\N	0	A
246	4.1.06.01	Descuento en ventas	\N	0	A
247	4.1.07.	DEVOLUCIONES EN VENTAS	\N	0	A
248	4.1.07.01	Devolucion en Ventas	\N	0	A
249	4.3.	OTROS INGRESOS	\N	0	A
250	4.3.03.	DESCUENTO EN COMPRAS	\N	0	A
251	4.3.03.01	Descuento en Compras	\N	0	A
252	4.3.03.02	Multas	\N	0	A
253	4.3.05.	OTRAS RENTAS	\N	0	A
254	4.3.05.01	Otros Ingresos 0%	\N	0	A
255	4.3.05.02	Otros Ingresos 12%	\N	0	A
256	4.3.05.03	Intereses Ganados	\N	0	A
257	4.3.05.04	Otros 	\N	0	A
258	5.	COSTOS	\N	0	A
259	5.1.	COSTOS DE VENTAS	\N	0	A
260	5.1.01.	COSTO DE VENTAS - PRODUCTOS VENDIDOS	\N	0	A
261	5.1.01.01	Repuestos	\N	0	A
262	5.1.01.02	Envios	\N	0	A
263	5.1.01.99	Notas de Credito de Clientes	\N	0	A
264	5.1.08.	ARRENDAMIENTOS Y ALQUILERES	\N	0	A
265	5.1.08.01	Arriendo Oficinas	\N	0	A
266	5.1.08.02	Arriendo de Bodegas	\N	0	A
267	5.1.08.03	Arriendo de hosting/ web	\N	0	A
268	5.1.08.04	Alquiler de Equipos	\N	0	A
269	5.1.19.	IMPUESTOS CONTRIBUCIONES Y OTRAS	\N	0	A
270	5.1.19.01	Impuestos Municipales	\N	0	A
271	5.1.19.02	Afiliaciones y suscripciones	\N	0	A
272	6.	GASTOS	\N	0	A
273	6.01.	GASTOS DE OPERACION	\N	0	A
274	6.01.01.	SUELDOS SALARIOS Y DEMAS REMUNERACIONES	\N	0	A
275	6.01.01.01	Sueldos	\N	0	A
276	6.01.01.02	Horas Extras	\N	0	A
277	6.01.02.	APORTES A LA SEGURIDAD SOCIAL	\N	0	A
278	6.01.02.01	Aportes Patronales	\N	0	A
279	6.01.02.02	Fondos de Reserva	\N	0	A
280	6.01.03.	BENEFICIOS SOCIALES E INDEMNIZACIONES	\N	0	A
281	6.01.03.01	Decimo Tercer Sueldo	\N	0	A
282	6.01.03.02	Decimo Cuarto Sueldo	\N	0	A
283	6.01.03.03	Vacaciones	\N	0	A
284	6.01.03.04	Indemnizaciones- Desahucios	\N	0	A
285	6.01.03.05	Alimentacion a Empleados	\N	0	A
286	6.01.03.06	Uniformes	\N	0	A
287	6.01.03.07	Gastos Medicos de Personal	\N	0	A
288	6.01.03.08	Capacitacion	\N	0	A
289	6.01.03.09	Bonificaciones	\N	0	A
290	6.01.03.10	Gastos Seguro Medico 	\N	0	A
291	6.01.04.	GASTO PLANES DE BENEFICIO A EMPLEADOS	\N	0	A
292	6.01.04.01	Jubilacion Patronal	\N	0	A
293	6.01.04.02	Desahucio	\N	0	A
294	6.01.05.	HONORARIOS COMISIONES Y DIETAS A PERSONAS NATURALES	\N	0	A
295	6.01.05.01	Honorarios	\N	0	A
296	6.01.05.02	Comisiones	\N	0	A
297	6.01.05.03	Dietas	\N	0	A
298	6.01.06.	HONORARIOS	\N	0	A
299	6.01.06.01	Trabajos Ocasionales	\N	0	A
300	6.01.06.02	Honorarios a Extranjeros	\N	0	A
301	6.01.06.03	Aseroria Contable	\N	0	A
302	6.01.06.04	Mano de Obra	\N	0	A
303	6.01.06.05	Agente de Aduana	\N	0	A
304	6.01.07.	MANTENIMIENTO OFICINA	\N	0	A
305	6.01.07.01	Mantenimiento Equipos Computacion y Software	\N	0	A
306	6.01.07.02	Mantenimiento Oficinas	\N	0	A
307	6.01.07.03	Adecuaciones Instalaciones	\N	0	A
308	6.01.08.	ARRENDAMIENTO Y ALQUILER	\N	0	A
309	6.01.08.01	Arriendo Oficinas	\N	0	A
310	6.01.08.02	Arriendo Bodegas	\N	0	A
311	6.01.08.03	Arriendo de hosting/ web	\N	0	A
312	6.01.08.04	Arriendo Vivienda	\N	0	A
313	6.01.08.05	Arriendo de Equipos	\N	0	A
314	6.01.09.	COMISIONES VENTAS 	\N	0	A
315	6.01.09.01	Comisiones Ventas	\N	0	A
316	6.01.10.	PROMOCION Y PUBLICIDAD	\N	0	A
317	6.01.10.01	Publicidad y Propaganda	\N	0	A
318	6.01.10.02	Anuncios y Publicaciones	\N	0	A
319	6.01.11.	COMBUSTIBLE	\N	0	A
320	6.01.11.01	Combustible	\N	0	A
321	6.01.12.	MANTENIMIENTO Y REPUESTOS VEHICULOS	\N	0	A
322	6.01.12.01	Mantenimiento Vehiculos	\N	0	A
323	6.01.12.03	Repuestos Vehiculos	\N	0	A
324	6.01.12.05	Lubricantes y Aceites	\N	0	A
325	6.01.13.	SEGUROS Y REASEGUROS PRIMAS Y CESIONES	\N	0	A
326	6.01.13.01	Seguros de Vehiculos	\N	0	A
327	6.01.13.02	Seguros Generales	\N	0	A
328	6.01.13.03	SOAT	\N	0	A
329	6.01.14.	TRANSPORTE	\N	0	A
330	6.01.14.01	Envios Clientes	\N	0	A
331	6.01.14.02	Flete de Mercadera	\N	0	A
332	6.01.14.03	Ticket Areos	\N	0	A
333	6.01.14.04	Pasajes y Taxis	\N	0	A
334	6.01.14.05	Encomiendas y Envios	\N	0	A
335	6.01.15.	GASTO DE GESTION AGASAJOS A ACCIONISTAS TRABAJADORES Y CLIENTES	\N	0	A
336	6.01.15.01	Atencion a Clientes	\N	0	A
337	6.01.15.02	Alimentacion Personal	\N	0	A
338	6.01.15.03	Atencion a Proveedores	\N	0	A
339	6.01.15.04	Donaciones y Obsequios	\N	0	A
340	6.01.15.05	Gastos de Gestion	\N	0	A
341	6.01.15.06	Agasajo Navideño	\N	0	A
342	6.01.15.07	Atenciones Sociales	\N	0	A
343	6.01.16.	GASTOS DE VIAJE	\N	0	A
344	6.01.16.01	Hospedaje	\N	0	A
345	6.01.16.02	Movilizacion	\N	0	A
346	6.01.16.03	Alimentacion_VIAJES	\N	0	A
347	6.01.17.	SERVICIOS BASICOS	\N	0	A
348	6.01.17.01	Agua	\N	0	A
349	6.01.17.02	Energia Electrica	\N	0	A
350	6.01.17.03	Telefono Convencional	\N	0	A
351	6.01.17.04	Celular	\N	0	A
352	6.01.17.05	Internet	\N	0	A
353	6.01.17.06	Monitoreo-Alarma-Guardiana	\N	0	A
354	6.01.17.07	Television por Cable	\N	0	A
355	6.01.17.08	Alicuotas	\N	0	A
356	6.01.18.	NOTARIOS Y REGISTRADORAS DE LA PROPIEDAD O MERCANTILES	\N	0	A
357	6.01.18.01	Notarios	\N	0	A
358	6.01.18.02	Gastos Legales	\N	0	A
359	6.01.19.	IMPUESTOS CONTRIBUCIONES Y OTROS	\N	0	A
360	6.01.19.01	Impuestos Municipales	\N	0	A
361	6.01.19.02	Cuotas Camara de Comercio y Otros	\N	0	A
362	6.01.19.03	Retenciones Asumidas	\N	0	A
363	6.01.19.04	Gastos no Deducibles	\N	0	A
364	6.01.19.05	Afiliaciones	\N	0	A
365	6.01.19.06	Matricula Vehiculos	\N	0	A
366	6.01.19.07	Buro de Credito	\N	0	A
367	6.01.19.08	Impuesto Asumido Edwin Alt	\N	0	A
368	6.01.19.09	Comisiones IESS	\N	0	A
369	6.01.19.10	Comisiones Servicios Basico 	\N	0	A
370	6.01.19.11	Intereses SRI	\N	0	A
371	6.01.19.12	Suscripciones	\N	0	A
372	6.01.20.	SUMINISTROS Y MATERIALES	\N	0	A
373	6.01.20.01	Suministros de Oficina y Papeleria	\N	0	A
374	6.01.20.02	Utiles de Ferreteria y Limpieza	\N	0	A
375	6.01.20.03	Suministros de Computacion	\N	0	A
376	6.01.20.04	Suministros de Cafeteria	\N	0	A
377	6.01.20.05	Repuestos y Herramientas	\N	0	A
378	6.01.20.06	Bienes Menores	\N	0	A
379	6.01.20.07	Imprenta	\N	0	A
380	6.01.20.08	Suministros y Materiales	\N	0	A
381	6.01.20.09	Gastos Varios	\N	0	A
382	6.01.20.10	Equipos y Muebles de Oficina	\N	0	A
383	6.01.21.	GASTOS FINANCIEROS	\N	0	A
384	6.01.21.01.	INTERESES	\N	0	A
385	6.01.21.01.01	Intereses por prestamos Bancarios	\N	0	A
386	6.01.21.01.02	Costos Financieros	\N	0	A
387	6.01.21.02.	COMISIONES	\N	0	A
388	6.01.21.02.01	Comisiones Bancarias	\N	0	A
389	6.01.21.02.02	Comision Tarjetas de Credito	\N	0	A
390	6.01.21.02.03	Gastos Datafast	\N	0	A
391	6.01.21.02.04	Comisiones SRI	\N	0	A
392	6.01.21.02.05	Seguro Prestamo EA- B. PICHINCHA	\N	0	A
393	6.01.21.03.	GASTOS DE FINANCIAMIENTO DE ACTIVOS	\N	0	A
394	6.01.21.03.01	Gastos de Financiamiento	\N	0	A
395	6.01.21.04.	DIFERENCIA EN CAMBIO	\N	0	A
396	6.01.21.04.01	Diferencia en Cambio	\N	0	A
397	6.01.21.05.	OTROS GASTOS FINANCIEROS	\N	0	A
398	6.01.21.05.01	Notas de Debito-Chequeras.	\N	0	A
399	6.01.21.05.02	Impuesto Salida Divisas	\N	0	A
400	6.01.21.06.	DEPRECIACIONES ACTIVOS VENTAS	\N	0	A
401	6.01.21.06.01	Depreciacin Propiedades Planta y Eq	\N	0	A
402	6.01.21.07.	AMORTIZACIONES ACTIVOS VENTAS	\N	0	A
403	6.01.21.07.01	Amortizacion Intangibles	\N	0	A
404	6.01.21.07.02	Amortizacion Otros Activos	\N	0	A
405	6.01.21.08.	GASTO DETERIORO	\N	0	A
406	6.01.21.08.01	Deterioro Propiedad Planta y Equipo	\N	0	A
407	6.01.21.08.02	Deterioro Inventarios	\N	0	A
408	6.01.21.08.03	Deterioro Instrumentos Financieros	\N	0	A
409	6.01.21.08.04	Deterioro Intangibles	\N	0	A
410	6.01.21.08.05	Deterioro Cuentas por Cobrar	\N	0	A
411	6.01.21.08.06	Deterioro Otros Activos	\N	0	A
412	6.01.21.09.	VALOR NETO DE REALIZACION DE INVENTARIOS	\N	0	A
413	6.01.21.09.01	Valor neto de realizacin de inventario	\N	0	A
\.


--
-- Data for Name: etiquetas_productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.etiquetas_productos (id, empresa_id, compra_id, compra_detalle_id, producto_id, codigo_producto, correlativo_desde, correlativo_hasta, cantidad, generado_por, created_at) FROM stdin;
1	1	15	26	5	AMP-001	1	10	10	1	2026-06-23 03:04:18
2	1	15	27	18	CAB-003	1	17	17	1	2026-06-23 03:04:18
3	1	11	22	13	ILU-002	1	8	8	1	2026-06-23 03:04:52
4	1	10	21	17	CAB-002	18	47	30	1	2026-06-23 12:02:03
5	1	29	70	6	AMP-002	11	11	1	1	2026-06-23 20:45:13
6	1	28	68	6	AMP-002	12	13	2	1	2026-06-23 23:30:23
7	1	28	69	18	CAB-003	48	49	2	1	2026-06-23 23:30:23
8	1	30	71	16	CAB-001	50	50	1	1	2026-06-23 23:31:09
9	1	31	72	15	ILU-004	9	9	1	1	2026-06-23 23:38:38
10	1	12	23	14	ILU-003	10	199	190	1	2026-06-23 23:47:25
\.


--
-- Data for Name: factura_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.factura_detalles (id, factura_id, producto_id, codigo_producto, descripcion, unidad, cantidad, precio_unitario, descuento_pct, descuento_valor, subtotal, porcentaje_iva, valor_iva, valor_ice, total, numero_serie, costo_unitario) FROM stdin;
\.


--
-- Data for Name: factura_pagos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.factura_pagos (id, factura_id, forma_pago, valor, dias_credito, fecha_vencimiento, banco, num_cheque, num_voucher, estado) FROM stdin;
\.


--
-- Data for Name: facturas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.facturas (id, empresa_id, centro_costo_id, cliente_id, usuario_id, establecimiento, punto_emision, secuencial, numero_completo, fecha_emision, hora_emision, clave_acceso, autorizacion, fecha_hora_aut, estado_sri, observacion_sri, xml_doc, tipo_identificacion, identificacion, razon_social, email_cliente, telefono_cliente, direccion_cliente, subtotal_0, subtotal_15, subtotal_exento, descuento_total, total_ice, total_iva, total, asiento_id, guia_remision, observaciones, email_enviado, tipo, estado, tiene_descuento_especial, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: guia_remision_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.guia_remision_detalles (id, guia_id, producto_id, descripcion, cantidad, numero_serie) FROM stdin;
\.


--
-- Data for Name: guias_remision; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.guias_remision (id, empresa_id, factura_id, transportista_id, establecimiento, punto_emision, secuencial, numero_completo, fecha_emision, fecha_inicio_transporte, fecha_fin_transporte, origen, destino, ruta, motivo, clave_acceso, autorizacion, estado_sri, xml_doc, estado, created_at) FROM stdin;
\.


--
-- Data for Name: horarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.horarios (id, descripcion, hora_entrada, hora_salida, tolerancia_minutos, lunes, martes, miercoles, jueves, viernes, sabado, domingo) FROM stdin;
\.


--
-- Data for Name: horas_extras_aprobacion; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.horas_extras_aprobacion (id, colaborador_id, asistencia_id, fecha, horas_solicitadas, horas_aprobadas, tipo, valor_calculado, estado, aprobado_por, fecha_aprobacion, observacion, created_at) FROM stdin;
\.


--
-- Data for Name: importaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.importaciones (id, empresa_id, proveedor_id, nombre, num_invoice, agente_aduanero, pais_embarque, costo_fob, divisa, fecha_partida, fecha_llegada, fecha_liquidacion, total_costos_extra, costo_total, metodo_prorrateo, estado, observaciones, created_by, created_at, updated_at) FROM stdin;
1	1	6	IMPORTACIÓN SHURE Q1-2026	SHR-2026-0089	Agencia Aduanera Andes S.A.	ESTADOS UNIDOS	18500.0000	USD	2026-04-03	2026-04-28	2026-05-03	3200.0000	21700.0000	cantidad	liquidada	Micrófonos, inalámbricos y accesorios Shure. Liquidación completada.	1	2026-06-02 02:47:39	2026-06-02 02:47:39
3	1	8	IMPORTACIÓN CHAUVET Q2-2026	CHV-2026-0456	Agencia Aduanera Global Trade	ESTADOS UNIDOS	24500.0000	USD	2026-06-07	\N	\N	0.0000	24500.0000	cantidad	en_transito	Cabezas móviles y controladores DMX Chauvet Pro. En tránsito marítimo.	1	2026-06-02 02:47:39	2026-06-02 02:47:39
5	1	6	IMPORTACION-PRUEBA-PRORRATEO	\N	\N	China	15000.0000	USD	2026-05-01	2026-06-15	2026-06-23	50.0000	15050.0000	cantidad	liquidada	Importación de prueba para validar prorrateo	1	2026-06-23 12:30:30	2026-06-23 12:56:30
4	1	7	IMPORTACION-PEKIN-OO1	INV-2026-004	Roberto	China	4970.0000	USD	2026-06-01	2026-06-15	\N	0.0000	0.0000	cantidad	en_transito	\N	1	2026-06-23 12:23:33	2026-06-23 12:23:33
2	1	7	IMPORTACIÓN YAMAHA Q2-2026	YMH-2026-0234	Agencia Aduanera Ecuaduanas	JAPÓN	32000.0000	USD	2026-05-08	2026-05-28	\N	0.0000	32000.0000	cantidad	en_aduana	Consolas de mezcla y procesadores de señal Yamaha. En proceso de desaduanización.	1	2026-06-02 02:47:39	2026-06-23 17:05:03
\.


--
-- Data for Name: inventario_movimientos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventario_movimientos (id, producto_id, bodega_id, tipo, doc_tipo, doc_id, cantidad, costo_unitario, costo_total, stock_anterior, stock_nuevo, usuario_id, empresa_id, notas, created_at, liberado_at) FROM stdin;
2	5	2	entrada	COMPRA	14	15.0000	420.0000	6300.0000	0.0000	15.0000	1	1	Compra confirmada: 008-444-56777	2026-06-18 23:43:54	\N
4	5	2	salida	ANULACION	14	15.0000	420.0000	6300.0000	15.0000	0.0000	1	1	Anulación compra: El equipo no llego de la mejor manera y nos equivocamos en el registro	2026-06-18 23:53:18	\N
5	1	1	salida	ANULACION	16	10.0000	321.6667	3216.6670	8.0000	0.0000	1	1	Anulación compra: no es correcta	2026-06-23 20:40:09	\N
6	2	1	salida	ANULACION	16	10.0000	281.6667	2816.6670	5.0000	0.0000	1	1	Anulación compra: no es correcta	2026-06-23 20:40:09	\N
7	3	1	salida	ANULACION	16	10.0000	681.6667	6816.6670	3.0000	0.0000	1	1	Anulación compra: no es correcta	2026-06-23 20:40:09	\N
8	9	4	salida	ANULACION	27	3.0000	650.0000	1950.0000	3.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:46:56	\N
9	10	4	salida	ANULACION	27	3.0000	480.0000	1440.0000	3.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:46:56	\N
10	62	4	salida	ANULACION	27	8.0000	85.0000	680.0000	8.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:46:56	\N
11	63	4	salida	ANULACION	27	50.0000	18.0000	900.0000	50.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:46:56	\N
12	58	4	salida	ANULACION	26	20.0000	850.0000	17000.0000	20.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:01	\N
13	59	4	salida	ANULACION	26	10.0000	180.0000	1800.0000	10.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:01	\N
14	60	4	salida	ANULACION	26	20.0000	120.0000	2400.0000	20.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:01	\N
15	61	4	salida	ANULACION	26	10.0000	290.0000	2900.0000	10.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:01	\N
16	54	4	salida	ANULACION	25	20.0000	420.0000	8400.0000	20.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:04	\N
17	55	4	salida	ANULACION	25	20.0000	380.0000	7600.0000	20.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:04	\N
18	56	4	salida	ANULACION	25	12.0000	650.0000	7800.0000	12.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:05	\N
19	57	4	salida	ANULACION	25	10.0000	780.0000	7800.0000	10.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:05	\N
20	51	1	salida	ANULACION	24	80.0000	106.3900	8511.2000	80.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:08	\N
21	52	1	salida	ANULACION	24	80.0000	96.3900	7711.2000	80.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:08	\N
22	53	1	salida	ANULACION	24	24.0000	227.3900	5457.3600	24.0000	0.0000	1	1	Anulación compra: no son validas	2026-06-23 20:47:08	\N
23	6	2	entrada	COMPRA	29	1.0000	580.0000	580.0000	0.0000	1.0000	1	1	Reparación: compra 3333-2222-1111	2026-06-23 21:05:35	\N
24	6	2	salida	ANULACION	29	1.0000	580.0000	580.0000	1.0000	0.0000	1	1	Anulación compra: no tenia los 667 en la caja chica	2026-06-23 21:10:43	\N
25	16	2	entrada	COMPRA	30	1.0000	12.0000	12.0000	0.0000	1.0000	1	1	Recepción #5: 000-999-666-5	2026-06-23 23:31:41	\N
26	16	2	salida	ANULACION	30	1.0000	12.0000	12.0000	1.0000	0.0000	1	1	Anulación compra: mal pagada	2026-06-23 23:33:50	\N
27	6	2	entrada	COMPRA	28	2.0000	580.0000	1160.0000	0.0000	2.0000	1	1	Recepción #3: 999-8888-7777	2026-06-23 23:46:26	\N
28	18	2	entrada	COMPRA	28	2.0000	8.0000	16.0000	0.0000	2.0000	1	1	Recepción #3: 999-8888-7777	2026-06-23 23:46:26	\N
\.


--
-- Data for Name: inventario_saldos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.inventario_saldos (id, producto_id, bodega_id, stock_actual, stock_reservado, costo_promedio, updated_at, cantidad_reservada) FROM stdin;
4	4	1	2.0000	0.0000	2800.0000	2026-06-11 05:42:58	0.0000
6	6	1	4.0000	0.0000	580.0000	2026-06-11 05:42:58	0.0000
7	7	1	4.0000	0.0000	1200.0000	2026-06-11 05:42:58	0.0000
8	8	1	2.0000	0.0000	980.0000	2026-06-11 05:42:58	0.0000
9	9	1	5.0000	0.0000	650.0000	2026-06-11 05:42:58	0.0000
10	10	1	3.0000	0.0000	480.0000	2026-06-11 05:42:58	0.0000
11	11	1	4.0000	0.0000	220.0000	2026-06-11 05:42:58	0.0000
12	12	1	6.0000	0.0000	680.0000	2026-06-11 05:42:58	0.0000
13	13	1	2.0000	0.0000	2200.0000	2026-06-11 05:42:58	0.0000
14	14	1	8.0000	0.0000	85.0000	2026-06-11 05:42:58	0.0000
15	15	1	10.0000	0.0000	120.0000	2026-06-11 05:42:58	0.0000
16	16	1	50.0000	0.0000	12.0000	2026-06-11 05:42:58	0.0000
18	18	1	40.0000	0.0000	8.0000	2026-06-11 05:42:58	0.0000
19	19	1	100.0000	0.0000	2.5000	2026-06-11 05:42:58	0.0000
20	20	1	200.0000	0.0000	1.8000	2026-06-11 05:42:58	0.0000
21	21	1	20.0000	0.0000	8.0000	2026-06-11 05:42:58	0.0000
51	52	1	0.0000	0.0000	96.3900	2026-06-23 20:47:08	0.0000
52	53	1	0.0000	0.0000	227.3900	2026-06-23 20:47:08	0.0000
25	5	2	0.0000	0.0000	420.0000	2026-06-18 23:53:18	0.0000
5	5	1	0.0000	0.0000	0.0000	2026-06-21 17:08:43	0.0000
24	5	5	0.0000	0.0000	0.0000	2026-06-21 17:08:43	0.0000
85	16	2	0.0000	0.0000	12.0000	2026-06-23 23:33:50	0.0000
83	6	2	2.0000	0.0000	580.0000	2026-06-23 23:46:26	0.0000
88	18	2	2.0000	0.0000	8.0000	2026-06-23 23:46:26	0.0000
17	17	1	30.0000	0.0000	15.0000	2026-06-11 05:42:58	0.0000
1	1	1	0.0000	0.0000	321.6667	2026-06-23 20:40:09	0.0000
2	2	1	0.0000	0.0000	281.6667	2026-06-23 20:40:09	0.0000
3	3	1	0.0000	0.0000	681.6667	2026-06-23 20:40:09	0.0000
61	9	4	0.0000	0.0000	650.0000	2026-06-23 20:46:56	0.0000
62	10	4	0.0000	0.0000	480.0000	2026-06-23 20:46:56	0.0000
63	62	4	0.0000	0.0000	85.0000	2026-06-23 20:46:56	0.0000
64	63	4	0.0000	0.0000	18.0000	2026-06-23 20:46:56	0.0000
57	58	4	0.0000	0.0000	850.0000	2026-06-23 20:47:01	0.0000
58	59	4	0.0000	0.0000	180.0000	2026-06-23 20:47:01	0.0000
59	60	4	0.0000	0.0000	120.0000	2026-06-23 20:47:01	0.0000
60	61	4	0.0000	0.0000	290.0000	2026-06-23 20:47:01	0.0000
53	54	4	0.0000	0.0000	420.0000	2026-06-23 20:47:04	0.0000
54	55	4	0.0000	0.0000	380.0000	2026-06-23 20:47:04	0.0000
55	56	4	0.0000	0.0000	650.0000	2026-06-23 20:47:04	0.0000
56	57	4	0.0000	0.0000	780.0000	2026-06-23 20:47:05	0.0000
50	51	1	0.0000	0.0000	106.3900	2026-06-23 20:47:08	0.0000
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: limites_descuento; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.limites_descuento (id, perfil_id, porcentaje_maximo, puede_aprobar, porcentaje_aprobacion_max, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: listas_precio; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.listas_precio (id, empresa_id, producto_id, tipo, precio, descuento_max, vigencia_desde, vigencia_hasta) FROM stdin;
\.


--
-- Data for Name: log_cambios_criticos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.log_cambios_criticos (id, usuario_id, empresa_id, tabla, registro_id, campo, valor_anterior, valor_nuevo, ip_address, created_at) FROM stdin;
1	1	1	asientos_contables	13	estado	1	0 — mal creado	127.0.0.1	2026-06-01 00:17:14
3	1	1	asientos_contables	25	estado	1	0 — Anulación compra 008-444-56777: El equipo no llego de la mejor manera y nos equivocamos en el registro	127.0.0.1	2026-06-18 18:53:19
4	1	\N	compras	14	estado	activa	anulada — El equipo no llego de la mejor manera y nos equivocamos en el registro	127.0.0.1	2026-06-18 18:53:19
5	1	\N	compras	7	estado	pendiente	anulada — no son validas	127.0.0.1	2026-06-22 22:11:01
6	1	\N	compras	6	estado	pendiente	anulada — no son validas	127.0.0.1	2026-06-22 22:11:09
7	1	\N	compras	5	estado	pendiente	anulada — no son validas	127.0.0.1	2026-06-22 22:11:17
8	1	\N	compras	4	estado	pendiente	anulada — no son validas	127.0.0.1	2026-06-22 22:11:22
9	1	\N	compras	3	estado	pendiente	anulada — no son validas	127.0.0.1	2026-06-22 22:11:28
10	1	\N	compras	2	estado	pendiente	anulada — no son validas	127.0.0.1	2026-06-22 22:11:32
11	1	\N	compras	16	estado	activa	anulada — no es correcta	127.0.0.1	2026-06-23 15:40:09
12	1	\N	compras	27	estado	activa	anulada — no son validas	127.0.0.1	2026-06-23 15:46:57
13	1	\N	compras	26	estado	activa	anulada — no son validas	127.0.0.1	2026-06-23 15:47:01
14	1	\N	compras	25	estado	activa	anulada — no son validas	127.0.0.1	2026-06-23 15:47:05
15	1	\N	compras	24	estado	activa	anulada — no son validas	127.0.0.1	2026-06-23 15:47:09
16	1	1	asientos_contables	29	estado	1	0 — Anulación compra 3333-2222-1111: no tenia los 667 en la caja chica	127.0.0.1	2026-06-23 16:10:43
17	1	\N	compras	29	estado	activa	anulada — no tenia los 667 en la caja chica	127.0.0.1	2026-06-23 16:10:43
18	1	1	asientos_contables	32	estado	1	0 — Anulación compra 000-999-666-5: mal pagada	127.0.0.1	2026-06-23 18:33:50
19	1	\N	compras	30	estado	activa	anulada — mal pagada	127.0.0.1	2026-06-23 18:33:50
\.


--
-- Data for Name: log_documentos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.log_documentos (id, usuario_id, empresa_id, accion, modulo, tabla, registro_id, descripcion, ip_address, created_at, username, fecha) FROM stdin;
1	1	1	editar	contabilidad	plan_cuentas	2	Cuenta 1.1 actualizada	127.0.0.1	2026-05-31 09:16:56	admin	2026-05-31 14:16:56
2	1	1	editar	contabilidad	plan_cuentas	2	Cuenta 1.1 actualizada	127.0.0.1	2026-05-31 09:17:05	admin	2026-05-31 14:17:05
3	1	1	crear	contabilidad	plan_cuentas	195	Cuenta 1.1.1.1.01 - qowoei creada	127.0.0.1	2026-05-31 09:18:57	admin	2026-05-31 14:18:57
4	1	1	editar	contabilidad	plan_cuentas	2	Cuenta 1.1 desactivada	127.0.0.1	2026-05-31 09:47:30	admin	2026-05-31 14:47:30
5	1	1	editar	contabilidad	plan_cuentas	2	Cuenta 1.1 activada	127.0.0.1	2026-05-31 09:47:33	admin	2026-05-31 14:47:32
6	1	1	editar	contabilidad	plan_cuentas	1	Cuenta 1 desactivada	127.0.0.1	2026-05-31 09:47:36	admin	2026-05-31 14:47:36
7	1	1	editar	contabilidad	plan_cuentas	1	Cuenta 1 activada	127.0.0.1	2026-05-31 09:47:38	admin	2026-05-31 14:47:37
8	1	1	editar	contabilidad	plan_cuentas	52	Cuenta 1.2.2.2 desactivada	127.0.0.1	2026-05-31 09:59:20	admin	2026-05-31 14:59:20
9	1	1	editar	contabilidad	plan_cuentas	52	Cuenta 1.2.2.2 activada	127.0.0.1	2026-05-31 09:59:23	admin	2026-05-31 14:59:22
10	1	1	crear	contabilidad	plan_cuentas	196	Cuenta 0 - kdsmcko creada	127.0.0.1	2026-05-31 11:24:25	admin	2026-05-31 16:24:25
11	1	1	editar	contabilidad	plan_cuentas	196	Cuenta 0 desactivada	127.0.0.1	2026-05-31 11:24:52	admin	2026-05-31 16:24:51
12	1	1	crear	contabilidad	asientos_contables	13	Asiento AS-2026-0011: nn	127.0.0.1	2026-06-01 00:16:30	admin@altamira.com	2026-06-01 05:16:30
13	1	1	crear	contabilidad	asientos_contables	14	Asiento AS-2026-0012: ANULACIÓN AS-2026-0011: mal creado	127.0.0.1	2026-06-01 00:17:14	admin@altamira.com	2026-06-01 05:17:13
14	1	1	crear	contabilidad	asientos_contables	15	Asiento AS-2026-0013: Pago proveedor ANT-0001	127.0.0.1	2026-06-05 23:27:30	admin@altamira.com	2026-06-06 04:27:30
15	1	1	crear	inventario	bodegas	1	Bodega Bodega Principal UIO creada	127.0.0.1	2026-06-11 01:40:57	admin	\N
16	1	1	crear	contabilidad	asientos_contables	16	Asiento AS-2026-0014: Pago proveedor 890809	127.0.0.1	2026-06-12 00:46:57	admin@altamira.com	2026-06-12 05:46:56
17	1	1	crear	contabilidad	asientos_contables	17	Asiento AS-2026-0015: Pago proveedor 77777	127.0.0.1	2026-06-12 00:47:14	admin@altamira.com	2026-06-12 05:47:13
18	1	1	crear	contabilidad	asientos_contables	18	Asiento AS-2026-0016: Compra 002-066666556-70707070	127.0.0.1	2026-06-13 18:43:31	admin@altamira.com	2026-06-13 23:43:31
19	1	1	crear	contabilidad	asientos_contables	19	Asiento AS-2026-0017: Compra 999-222-44444	127.0.0.1	2026-06-13 19:13:55	admin@altamira.com	2026-06-14 00:13:55
20	1	1	crear	contabilidad	asientos_contables	20	Asiento AS-2026-0018: Compra 77899999990000	127.0.0.1	2026-06-14 00:22:29	admin@altamira.com	2026-06-14 05:22:28
21	1	1	crear	contabilidad	asientos_contables	21	Asiento AS-2026-0019: Compra 9999888777	127.0.0.1	2026-06-14 09:46:50	admin@altamira.com	2026-06-14 14:46:50
22	1	1	crear	contabilidad	asientos_contables	22	Asiento AS-2026-0020: Compra 324737945793475	127.0.0.1	2026-06-14 10:22:11	admin@altamira.com	2026-06-14 15:22:10
23	1	1	crear	contabilidad	asientos_contables	23	Asiento AS-2026-0021: Pago proveedor TRF-009	127.0.0.1	2026-06-15 17:49:21	admin@altamira.com	2026-06-15 22:49:21
24	1	1	crear	contabilidad	asientos_contables	24	Asiento AS-2026-0022: Pago proveedor TRF-334	127.0.0.1	2026-06-17 20:16:46	admin@altamira.com	2026-06-18 01:16:46
25	1	1	crear	contabilidad	asientos_contables	25	Asiento AS-2026-0023: Compra 008-444-56777	127.0.0.1	2026-06-18 18:43:54	admin@altamira.com	2026-06-18 23:43:54
26	1	1	crear	contabilidad	asientos_contables	26	Asiento AS-2026-0024: Pago proveedor trf-003	127.0.0.1	2026-06-18 18:44:17	admin@altamira.com	2026-06-18 23:44:16
28	1	1	crear	contabilidad	asientos_contables	28	Asiento AS-2026-0025: ANULACIÓN AS-2026-0023: Anulación compra 008-444-56777: El equipo no llego de la mejor manera y nos equivocamos en el registro	127.0.0.1	2026-06-18 18:53:19	admin@altamira.com	2026-06-18 23:53:18
29	1	1	confirmar	inventario	recepciones_bodega	4	Recepción #4 confirmada — estado: completada	127.0.0.1	2026-06-23 20:45:45	\N	\N
30	1	1	crear	contabilidad	asientos_contables	29	Asiento AS-2026-0026: Compra 3333-2222-1111	127.0.0.1	2026-06-23 16:05:35	admin@altamira.com	2026-06-23 21:05:35
31	1	1	crear	contabilidad	asientos_contables	30	Asiento AS-2026-0027: Pago proveedor TRF-009	127.0.0.1	2026-06-23 16:08:06	admin@altamira.com	2026-06-23 21:08:05
32	1	1	crear	contabilidad	asientos_contables	31	Asiento AS-2026-0028: ANULACIÓN AS-2026-0026: Anulación compra 3333-2222-1111: no tenia los 667 en la caja chica	127.0.0.1	2026-06-23 16:10:43	admin@altamira.com	2026-06-23 21:10:43
33	1	1	crear	contabilidad	asientos_contables	32	Asiento AS-2026-0029: Compra 000-999-666-5	127.0.0.1	2026-06-23 18:31:42	admin@altamira.com	2026-06-23 23:31:41
34	1	1	confirmar	inventario	recepciones_bodega	5	Recepción #5 confirmada — estado: completada	127.0.0.1	2026-06-23 23:31:41	\N	\N
35	1	1	crear	contabilidad	asientos_contables	33	Asiento AS-2026-0030: Pago proveedor Pago #29	127.0.0.1	2026-06-23 18:33:11	admin@altamira.com	2026-06-23 23:33:11
36	1	1	crear	contabilidad	asientos_contables	34	Asiento AS-2026-0031: Pago proveedor Pago #30	127.0.0.1	2026-06-23 18:33:31	admin@altamira.com	2026-06-23 23:33:30
37	1	1	crear	contabilidad	asientos_contables	35	Asiento AS-2026-0032: ANULACIÓN AS-2026-0029: Anulación compra 000-999-666-5: mal pagada	127.0.0.1	2026-06-23 18:33:50	admin@altamira.com	2026-06-23 23:33:50
38	1	1	crear	contabilidad	asientos_contables	36	Asiento AS-2026-0033: Pago proveedor TRF-009	127.0.0.1	2026-06-23 18:41:35	admin@altamira.com	2026-06-23 23:41:35
39	1	1	crear	contabilidad	asientos_contables	37	Asiento AS-2026-0034: Compra 999-8888-7777	127.0.0.1	2026-06-23 18:46:27	admin@altamira.com	2026-06-23 23:46:26
40	1	1	confirmar	inventario	recepciones_bodega	3	Recepción #3 confirmada — estado: completada	127.0.0.1	2026-06-23 23:46:26	\N	\N
\.


--
-- Data for Name: log_sesiones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.log_sesiones (id, usuario_id, email, tipo, ip_address, user_agent, empresa_id, created_at) FROM stdin;
1	1	admin@altamira.com	logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	1	2026-05-30 21:07:42
2	2	vendedor@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-05-30 21:08:42
3	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-05-30 23:36:42
4	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-05-31 08:28:26
5	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-05-31 14:26:38
6	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-05-31 23:50:55
7	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-01 08:24:14
8	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-01 08:24:24
9	1	admin@altamira.com	logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	1	2026-06-01 15:16:37
10	\N	vendedor@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-01 15:16:59
11	2	vendedor@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-01 15:17:25
12	2	vendedor@altamira.com	logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	1	2026-06-01 15:18:05
13	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-01 15:18:22
14	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-01 20:05:15
15	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-02 19:49:08
16	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-03 22:55:52
17	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-04 15:43:39
18	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-05 02:53:47
19	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36	\N	2026-06-05 13:43:39
20	1	admin@altamira.com	logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	1	2026-06-22 14:41:31
21	\N	vendedor@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:41:45
22	\N	vendedor@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:41:51
23	\N	vendedor@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:42:01
24	\N	vendedor@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:42:11
25	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:42:28
26	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:42:48
27	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:43:00
28	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:43:05
29	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:43:10
30	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:44:14
31	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:44:18
32	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:44:33
33	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:44:41
34	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:44:58
35	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:46:35
36	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:46:49
37	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:46:51
38	\N	admin@altamira.com	login_fail	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:48:27
39	2	vendedor@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:55:32
40	2	vendedor@altamira.com	logout	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	1	2026-06-22 14:55:44
41	1	admin@altamira.com	login_ok	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	\N	2026-06-22 14:56:05
\.


--
-- Data for Name: marcas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.marcas (id, empresa_id, nombre, descripcion, estado, created_at, updated_at) FROM stdin;
1	1	Shure	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
2	1	Yamaha	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
3	1	Pioneer DJ	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
4	1	QSC	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
5	1	Chauvet	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
6	1	Martin	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
7	1	Sennheiser	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
8	1	Behringer	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
9	1	JBL	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
10	1	Crown	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
11	1	Allen & Heath	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
12	1	Genérico	\N	t	2026-06-11 05:42:58	2026-06-11 05:42:58
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000001_create_cache_table	1
2	0001_01_01_000002_create_jobs_table	1
3	2026_05_30_100000_create_empresas_table	1
4	2026_05_30_100010_create_centros_costo_table	1
5	2026_05_30_100020_create_perfiles_table	1
6	2026_05_30_100030_create_usuarios_table	1
7	2026_05_30_100040_create_modulos_table	1
8	2026_05_30_100050_create_permisos_table	1
9	2026_05_30_100060_create_tipos_aprobacion_table	1
10	2026_05_30_100070_create_limites_descuento_table	1
11	2026_05_30_100080_create_aprobaciones_especiales_table	1
12	2026_05_30_100090_create_log_sesiones_table	1
13	2026_05_30_100100_create_log_documentos_table	1
14	2026_05_30_100110_create_log_cambios_criticos_table	1
15	2026_05_30_100120_create_configuraciones_table	1
16	2026_05_30_100130_create_secuenciales_table	1
17	2026_05_30_100140_create_presupuestos_metas_table	1
18	2026_05_30_100150_create_notificaciones_table	1
19	2026_05_30_200000_add_erp_columns_to_existing_tables	1
20	2026_05_30_210000_create_empresa_usuario_pivot	1
21	2026_05_30_223449_create_permission_tables	1
22	2026_05_30_300000_create_plan_cuentas_table	2
23	2026_05_31_000001_add_total_asientos_to_plan_cuentas	3
24	2026_05_31_000002_add_missing_columns_to_log_documentos	4
25	2026_06_02_000001_create_ejercicios_contables_table	5
26	2026_06_02_000002_create_asientos_contables_table	5
27	2026_06_02_000003_create_asiento_detalles_table	5
28	2026_06_03_000001_create_proveedores_table	6
29	2026_06_03_000002_create_compras_table	6
30	2026_06_03_000003_create_compra_detalles_table	6
31	2026_06_03_000004_create_cuentas_pagar_table	6
32	2026_06_03_000005_create_importaciones_table	6
33	2026_06_03_000006_create_anticipos_proveedores_table	6
34	2026_06_04_000001_create_bancos_cajas_table	7
35	2026_06_04_000002_create_movimientos_bancarios_table	7
36	2026_06_04_000003_create_cheques_table	7
37	2026_06_04_000004_create_cierres_caja_table	7
38	2026_06_04_000005_create_datafast_lotes_table	7
39	2026_06_04_000006_create_datafast_liquidaciones_table	7
40	2026_06_04_000007_create_conciliaciones_table	7
41	2026_05_30_300000_create_clientes_table	8
42	2026_05_30_300010_create_proveedores_table	8
43	2026_05_30_300020_create_transportistas_table	8
44	2026_05_30_400000_create_marcas_table	8
45	2026_05_30_400010_create_categorias_producto_table	8
46	2026_05_30_400020_create_bodegas_table	8
47	2026_05_30_400030_create_inventario_saldos_table	8
48	2026_05_30_400040_create_inventario_movimientos_table	8
49	2026_05_30_400050_create_productos_table	8
50	2026_05_30_400060_create_producto_series_table	8
51	2026_05_30_400070_create_traslados_table	8
52	2026_05_30_400080_create_traslado_items_table	8
53	2026_05_31_100000_create_activos_fijos_table	8
54	2026_05_31_100010_create_activos_depreciaciones_table	8
55	2026_06_04_000008_create_parametros_contables_table	9
56	2026_06_01_000001_recrear_clientes_schema_oficial	10
57	2026_06_01_000002_recrear_proveedores_schema_oficial	11
58	2026_06_01_000003_recrear_transportistas_schema_oficial	11
59	2026_06_06_100000_create_marcas_table	11
60	2026_06_06_100010_create_categorias_producto_table	11
61	2026_06_06_100020_create_bodegas_table	11
62	2026_06_06_100030_create_productos_table	11
63	2026_06_06_100040_create_producto_series_table	11
64	2026_06_06_100050_create_inventario_saldos_table	11
65	2026_06_06_100060_create_inventario_movimientos_table	11
66	2026_06_06_100070_create_traslados_bodega_table	11
67	2026_06_06_100080_create_traslado_detalles_table	11
68	2026_06_06_100090_create_listas_precio_table	11
69	2026_06_06_100100_create_activos_fijos_table	11
70	2026_06_06_200000_alter_secuenciales_add_inicializado	11
71	2026_06_06_200010_create_facturas_table	11
72	2026_06_06_200020_create_factura_detalles_table	11
73	2026_06_06_200030_create_factura_pagos_table	11
74	2026_06_06_200040_create_proformas_table	11
75	2026_06_06_200050_create_proforma_detalles_table	11
76	2026_06_06_200060_create_prefacturas_table	11
77	2026_06_06_200070_create_prefactura_detalles_table	11
78	2026_06_06_200080_create_prefactura_abonos_table	11
79	2026_06_06_200090_create_notas_credito_table	11
80	2026_06_06_200100_create_nota_credito_detalles_table	11
81	2026_06_06_200110_create_retenciones_table	11
82	2026_06_06_200120_create_retencion_detalles_table	11
83	2026_06_06_200130_create_guias_remision_table	11
84	2026_06_06_200140_create_guia_remision_detalles_table	11
85	2026_06_06_200150_create_cuentas_cobrar_table	11
86	2026_06_09_000001_fix_unique_clientes_softdelete	11
87	2026_06_09_000002_fix_unique_proveedores_softdelete	11
88	2026_06_09_000003_add_softdeletes_transportistas	11
89	2026_06_09_000010_remove_softdeletes_personas	11
90	2026_06_09_300000_alinear_columnas_schema_legacy	12
91	2026_06_15_500000_create_rrhh_tables	13
92	2026_06_17_100000_create_etiquetas_productos_table	14
93	2026_06_18_100000_add_reserva_columns_to_inventario	15
94	2026_06_19_100000_create_recepciones_bodega_table	15
95	2026_06_22_100000_create_nomina_tables	16
96	2026_06_21_100000_create_recepcion_escaneos_table	17
\.


--
-- Data for Name: model_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.model_has_permissions (permission_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: model_has_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.model_has_roles (role_id, model_type, model_id) FROM stdin;
\.


--
-- Data for Name: modulos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.modulos (id, nombre, clave, icono, orden, padre_id, estado, created_at, updated_at) FROM stdin;
1	Dashboard	dashboard	LayoutDashboard	1	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
2	Ventas	ventas	FileText	2	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
3	Compras	compras	ShoppingCart	3	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
4	Inventario	inventario	Package	4	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
5	Contabilidad	contabilidad	BookOpen	5	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
6	Bancos	bancos	Landmark	6	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
7	RRHH	rrhh	Users	7	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
8	Taller	taller	Wrench	8	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
9	Reportes	reportes	BarChart2	9	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
10	Configuración	configuracion	Settings	10	\N	t	2026-05-31 01:22:57	2026-05-31 01:22:57
\.


--
-- Data for Name: movimientos_bancarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.movimientos_bancarios (id, empresa_id, banco_caja_id, tipo, sub_tipo, fecha, monto, persona_tipo, persona_id, beneficiario, num_documento, num_cheque, fecha_cheque, descripcion, documento_tipo, documento_id, cuenta_contrapartida_id, asiento_id, conciliado, es_postfechado, anulado, created_by, created_at, updated_at) FROM stdin;
1	1	1	egreso	transferencia	2026-05-14	3500.0000	\N	\N	TecnoSound S.A.	TRF-001	\N	\N	Pago factura 001-001-000123	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
2	1	1	ingreso	deposito	2026-05-19	8200.0000	\N	\N	Cliente Varios	DEP-001	\N	\N	Depósito ventas semana	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
4	1	1	egreso	cheque	2026-05-26	580.0000	\N	\N	Empresa Eléctrica Quito	CHQ-0001	000001	\N	Pago planilla eléctrica mayo	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
5	1	3	ingreso	transferencia	2026-05-29	4500.0000	\N	\N	Distribuidora Musical	TRF-003	\N	\N	Cobro factura cliente	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
6	1	4	ingreso	efectivo	2026-05-31	1850.0000	\N	\N	Ventas mostrador	\N	\N	\N	Ventas efectivo del día	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
7	1	4	egreso	efectivo	2026-06-01	120.0000	\N	\N	Varios	\N	\N	\N	Gastos menores oficina	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
8	1	1	ingreso	deposito	2026-06-02	12500.0000	\N	\N	Depósito ventas	DEP-002	\N	\N	Ventas semana 23-30 mayo	\N	\N	\N	\N	f	f	f	1	2026-06-03 01:01:34	2026-06-03 01:01:34
3	1	2	egreso	transferencia	2026-05-24	1200.0000	\N	\N	IluPro S.A.	TRF-002	\N	\N	Pago factura equipos iluminación	\N	\N	\N	\N	t	f	f	1	2026-06-03 01:01:34	2026-06-03 01:11:12
9	1	7	ingreso	transferencia	2026-06-03	2000.0000	\N	\N	Carlos	1234567890	\N	\N	Plata de productos	\N	\N	281	\N	f	f	f	1	2026-06-03 01:26:03	2026-06-03 01:26:03
10	1	2	egreso	transferencia	2026-06-06	1000.0000	\N	\N	CHAUVET PROFESSIONAL LLC	TRF-2026-001	\N	\N	Anticipo proveedor — CHAUVET PROFESSIONAL LLC	ANTICIPO	1	\N	\N	f	f	f	1	2026-06-06 04:27:30	2026-06-06 04:27:30
11	1	1	egreso	transferencia	2026-05-20	3500.0000	\N	\N	TecnoSound S.A.	TRF-001	\N	\N	Pago factura 001-001-000123	\N	\N	472	\N	f	f	f	1	2026-06-09 23:19:17	2026-06-09 23:19:17
12	1	1	ingreso	deposito	2026-05-25	8200.0000	\N	\N	Cliente Varios	DEP-001	\N	\N	Depósito ventas semana	\N	\N	420	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
13	1	2	egreso	transferencia	2026-05-30	1200.0000	\N	\N	IluPro S.A.	TRF-002	\N	\N	Pago factura equipos iluminación	\N	\N	472	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
14	1	1	egreso	cheque	2026-06-01	580.0000	\N	\N	Empresa Eléctrica Quito	CHQ-0001	000001	\N	Pago planilla eléctrica mayo	\N	\N	570	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
15	1	3	ingreso	transferencia	2026-06-04	4500.0000	\N	\N	Distribuidora Musical	TRF-003	\N	\N	Cobro factura cliente	\N	\N	420	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
16	1	4	ingreso	efectivo	2026-06-06	1850.0000	\N	\N	Ventas mostrador	\N	\N	\N	Ventas efectivo del día	\N	\N	420	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
17	1	4	egreso	efectivo	2026-06-07	120.0000	\N	\N	Varios	\N	\N	\N	Gastos menores oficina	\N	\N	570	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
18	1	1	ingreso	deposito	2026-06-08	12500.0000	\N	\N	Depósito ventas	DEP-002	\N	\N	Ventas semana 23-30 mayo	\N	\N	420	\N	f	f	f	1	2026-06-09 23:19:18	2026-06-09 23:19:18
20	1	2	egreso	pago_proveedor	2026-06-12	7383.0000	proveedor	3	DISTRIBUIDORA MUSICAL DEL ECUADOR CIA. LTDA.	001-003-000654	\N	\N	890809	COMPRA	6	\N	\N	f	f	f	1	2026-06-12 05:46:56	2026-06-12 05:46:56
21	1	3	egreso	pago_proveedor	2026-06-12	339.2500	proveedor	2	TECNOLOGÍA Y SONIDO S.A.	001-001-000200	\N	\N	77777	COMPRA	7	\N	\N	f	f	f	1	2026-06-12 05:47:13	2026-06-12 05:47:13
22	1	1	egreso	pago_proveedor	2026-06-15	16150.0000	proveedor	8	CHAUVET PROFESSIONAL LLC	9999888777	\N	\N	TRF-009	COMPRA	12	\N	\N	f	f	f	1	2026-06-15 22:49:21	2026-06-15 22:49:21
23	1	1	egreso	pago_proveedor	2026-06-18	11592.0000	proveedor	9	Distribuidora Nacional de Audio S.A.	324737945793475	\N	\N	TRF-334	COMPRA	13	\N	\N	f	f	f	1	2026-06-18 01:16:45	2026-06-18 01:16:45
26	1	1	ingreso	pago_proveedor	2026-06-18	7245.0000	proveedor	4	\N	008-444-56777	\N	\N	Reversión pago — Anulación 008-444-56777: El equipo no llego de la mejor manera y nos equivocamos en el registro	ANULACION_COMPRA	14	\N	\N	f	f	f	1	2026-06-18 23:53:18	2026-06-18 23:53:18
24	1	1	egreso	pago_proveedor	2026-06-18	7245.0000	proveedor	4	CABLES Y ACCESORIOS DEL ECUADOR	008-444-56777	\N	\N	trf-003	COMPRA	14	\N	\N	f	f	t	1	2026-06-18 23:44:16	2026-06-18 23:53:18
28	1	6	ingreso	pago_proveedor	2026-06-23	667.0000	proveedor	4	\N	3333-2222-1111	\N	\N	Reversión pago — Anulación 3333-2222-1111: no tenia los 667 en la caja chica	ANULACION_COMPRA	29	\N	\N	f	f	f	1	2026-06-23 21:10:43	2026-06-23 21:10:43
27	1	6	egreso	pago_proveedor	2026-06-23	667.0000	proveedor	4	CABLES Y ACCESORIOS DEL ECUADOR	3333-2222-1111	\N	\N	TRF-009	COMPRA	29	\N	\N	f	f	t	1	2026-06-23 21:08:05	2026-06-23 21:10:43
30	1	2	egreso	pago_proveedor	2026-06-23	0.0100	proveedor	9	Distribuidora Nacional de Audio S.A.	000-999-666-5	\N	\N	Pago CxP	COMPRA	30	\N	\N	f	f	f	1	2026-06-23 23:33:30	2026-06-23 23:33:30
31	1	6	ingreso	pago_proveedor	2026-06-23	13.7900	proveedor	9	\N	000-999-666-5	\N	\N	Reversión pago — Anulación 000-999-666-5: mal pagada	ANULACION_COMPRA	30	\N	\N	f	f	f	1	2026-06-23 23:33:50	2026-06-23 23:33:50
29	1	6	egreso	pago_proveedor	2026-06-23	13.7900	proveedor	9	Distribuidora Nacional de Audio S.A.	000-999-666-5	\N	\N	Pago CxP	COMPRA	30	\N	\N	f	f	t	1	2026-06-23 23:33:11	2026-06-23 23:33:50
32	1	2	egreso	pago_proveedor	2026-06-23	667.0000	proveedor	4	CABLES Y ACCESORIOS DEL ECUADOR	3333-2222-1111	\N	\N	TRF-009	COMPRA	29	\N	\N	f	f	f	1	2026-06-23 23:41:35	2026-06-23 23:41:35
\.


--
-- Data for Name: nomina_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.nomina_detalles (id, nomina_id, colaborador_id, sueldo_base, horas_extras_50, horas_extras_100, comisiones, otros_ingresos, total_ingresos, aporte_personal_iess, descuento_atrasos, descuento_prestamos, descuento_anticipos, otros_egresos, total_egresos, neto_pagar, tipo_pago, num_cuenta, banco, estado, modificado_manualmente, created_at) FROM stdin;
1	1	6	700.00	0.00	0.00	0.00	96.66	796.66	75.28	0.00	0.00	0.00	0.00	75.28	721.38	transferencia	2203456789	Banco Pichincha	pagado	f	2026-06-22 15:25:35
2	1	1	1200.00	0.00	0.00	0.00	0.00	1200.00	113.40	0.00	0.00	0.00	0.00	113.40	1086.60	transferencia	2201234567	Banco Pichincha	pagado	f	2026-06-22 15:25:35
3	1	4	650.00	0.00	0.00	0.00	92.50	742.50	70.17	0.00	0.00	0.00	0.00	70.17	672.33	transferencia	1234567890	Produbanco	pagado	f	2026-06-22 15:25:35
4	1	3	650.00	0.00	0.00	0.00	92.50	742.50	70.17	0.00	0.00	0.00	0.00	70.17	672.33	transferencia	2209876543	Banco Pichincha	pagado	f	2026-06-22 15:25:35
5	1	7	600.00	0.00	0.00	0.00	0.00	600.00	56.70	0.00	0.00	0.00	0.00	56.70	543.30	transferencia	0986543210	Banco Guayaquil	pagado	f	2026-06-22 15:25:35
6	1	2	1500.00	0.00	0.00	0.00	38.33	1538.33	145.37	0.00	0.00	0.00	0.00	145.37	1392.96	transferencia	0981234567	Banco Guayaquil	pagado	f	2026-06-22 15:25:35
7	1	5	750.00	0.00	0.00	0.00	0.00	750.00	70.88	0.00	0.00	0.00	0.00	70.88	679.12	transferencia	5678901234	Banco del Pacífico	pagado	f	2026-06-22 15:25:35
8	2	6	700.00	0.00	0.00	0.00	96.66	796.66	75.28	0.00	0.00	0.00	0.00	75.28	721.38	transferencia	2203456789	Banco Pichincha	borrador	f	2026-06-22 15:25:35
9	2	1	1200.00	0.00	0.00	0.00	0.00	1200.00	113.40	0.00	0.00	0.00	0.00	113.40	1086.60	transferencia	2201234567	Banco Pichincha	borrador	f	2026-06-22 15:25:35
10	2	4	650.00	0.00	0.00	0.00	92.50	742.50	70.17	0.00	0.00	0.00	0.00	70.17	672.33	transferencia	1234567890	Produbanco	borrador	f	2026-06-22 15:25:35
11	2	3	650.00	0.00	0.00	0.00	92.50	742.50	70.17	0.00	0.00	0.00	0.00	70.17	672.33	transferencia	2209876543	Banco Pichincha	borrador	f	2026-06-22 15:25:35
12	2	7	600.00	0.00	0.00	0.00	0.00	600.00	56.70	0.00	0.00	0.00	0.00	56.70	543.30	transferencia	0986543210	Banco Guayaquil	borrador	f	2026-06-22 15:25:35
13	2	2	1500.00	0.00	0.00	0.00	38.33	1538.33	145.37	0.00	0.00	0.00	0.00	145.37	1392.96	transferencia	0981234567	Banco Guayaquil	borrador	f	2026-06-22 15:25:35
14	2	5	750.00	0.00	0.00	0.00	0.00	750.00	70.88	0.00	0.00	0.00	0.00	70.88	679.12	transferencia	5678901234	Banco del Pacífico	borrador	f	2026-06-22 15:25:35
\.


--
-- Data for Name: nominas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.nominas (id, empresa_id, periodo_tipo, anio, mes, quincena, fecha_emision, estado, total_ingresos, total_egresos, total_neto, asiento_id, generado_por, procesado_por, pagado_por, created_at) FROM stdin;
1	1	mensual	2026	5	\N	2026-05-30	pagado	6369.99	601.97	5768.02	\N	1	1	1	2026-06-22 15:25:35
2	1	mensual	2026	6	\N	2026-06-30	borrador	6369.99	601.97	5768.02	\N	1	\N	\N	2026-06-22 15:25:35
\.


--
-- Data for Name: nota_credito_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.nota_credito_detalles (id, nota_credito_id, producto_id, descripcion, cantidad, precio_unitario, total, bodega_destino_id, numero_serie) FROM stdin;
\.


--
-- Data for Name: notas_credito; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notas_credito (id, empresa_id, factura_id, cliente_id, usuario_id, establecimiento, punto_emision, secuencial, numero_completo, fecha_emision, motivo, tipo, subtotal, total_iva, total, clave_acceso, autorizacion, estado_sri, xml_doc, asiento_id, genera_saldo_favor, saldo_favor, estado, created_at) FROM stdin;
\.


--
-- Data for Name: notificaciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.notificaciones (id, usuario_id, titulo, mensaje, tipo, icono, url, leida, leida_at, created_at) FROM stdin;
\.


--
-- Data for Name: parametros_contables; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.parametros_contables (id, empresa_id, codigo, cuenta_id, descripcion) FROM stdin;
21	2	cta_caja_general	274	Caja General (cobros en efectivo)
22	2	cta_bancos_locales	277	Bancos Locales (cobros por transferencia)
23	2	cta_clientes_locales	280	Clientes Locales (ventas a crédito)
24	2	cta_ventas_locales	108	Venta de Mercaderías Locales
25	2	cta_iva_ventas	333	IVA en Ventas por Pagar
26	2	cta_anticipos_clientes	94	Anticipos de Clientes (reservas)
27	2	cta_proveedores_locales	315	Proveedores Locales (CxP)
28	2	cta_iva_compras	300	Crédito Tributario por IVA en Compras
29	2	cta_retencion_ir	335	Retenciones en la Fuente de IR por Pagar
30	2	cta_retencion_iva	332	Retenciones de IVA por Pagar
31	2	cta_inventario_mercaderia	289	Inventario de Mercadería
32	2	cta_comisiones_bancarias	369	Comisiones Bancarias y Pasarelas (Datafast)
33	2	cta_retencion_iva_cobrada	301	Crédito Tributario por Retenciones de IVA
34	2	cta_retencion_ir_cobrada	304	Crédito Tributario por Retenciones de IR
35	2	cta_sueldos_salarios	175	Sueldos y Salarios
36	2	cta_aporte_patronal	177	Aporte Patronal IESS 11.15%
37	2	cta_iess_por_pagar	337	Obligaciones con el IESS
38	2	cta_nomina_por_pagar	341	Nómina por Pagar
39	2	cta_anticipos_empleados	297	Préstamos y Anticipos a Empleados
40	2	cta_gastos_no_deducibles	245	Gastos No Deducibles Locales
44	1	cta_ajuste_inventario	669	cta_ajuste_inventario
12	1	cta_comisiones_bancarias	587	cta_comisiones_bancarias
13	1	cta_retencion_iva_cobrada	609	cta_retencion_iva_cobrada
14	1	cta_retencion_ir_cobrada	610	cta_retencion_ir_cobrada
15	1	cta_sueldos_salarios	557	cta_sueldos_salarios
16	1	cta_aporte_patronal	559	cta_aporte_patronal
17	1	cta_iess_por_pagar	643	cta_iess_por_pagar
18	1	cta_nomina_por_pagar	642	cta_nomina_por_pagar
19	1	cta_anticipos_empleados	602	cta_anticipos_empleados
1	1	cta_caja_general	593	cta_caja_general
2	1	cta_bancos_locales	595	cta_bancos_locales
42	1	cta_vouchers	597	cta_vouchers
3	1	cta_clientes_locales	599	cta_clientes_locales
4	1	cta_ventas_locales	537	cta_ventas_locales
5	1	cta_iva_ventas	640	cta_iva_ventas
6	1	cta_anticipos_clientes	653	cta_anticipos_clientes
41	1	cta_costo_ventas	666	cta_costo_ventas
7	1	cta_proveedores_locales	633	cta_proveedores_locales
8	1	cta_iva_compras	608	cta_iva_compras
9	1	cta_retencion_ir	637	cta_retencion_ir
10	1	cta_retencion_iva	638	cta_retencion_iva
43	1	cta_gasto_compras	568	cta_gasto_compras
11	1	cta_inventario_mercaderia	604	cta_inventario_mercaderia
20	1	cta_gastos_no_deducibles	590	cta_gastos_no_deducibles
\.


--
-- Data for Name: partidas_transito; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.partidas_transito (id, conciliacion_id, tipo, fecha, descripcion, monto, movimiento_id, conciliada, asiento_generado_id) FROM stdin;
1	1	sistema	2026-05-24	Pago factura equipos iluminación	1200.0000	3	f	\N
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: perfiles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.perfiles (id, nombre, descripcion, es_sistema, estado, created_at, updated_at) FROM stdin;
1	super_admin	Acceso total al sistema	t	t	\N	\N
2	admin	Administrador general	t	t	\N	\N
3	contador	Módulos contables y financieros	t	t	\N	\N
4	vendedor	Módulo de ventas	t	t	\N	\N
5	bodeguero	Módulo de inventario	t	t	\N	\N
6	tecnico	Módulo de taller	t	t	\N	\N
\.


--
-- Data for Name: permisos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permisos (id, perfil_id, modulo_id, ver, crear, editar, eliminar, anular, created_at, updated_at) FROM stdin;
1	1	1	t	t	t	t	t	\N	\N
2	1	2	t	t	t	t	t	\N	\N
3	1	3	t	t	t	t	t	\N	\N
4	1	4	t	t	t	t	t	\N	\N
5	1	5	t	t	t	t	t	\N	\N
6	1	6	t	t	t	t	t	\N	\N
7	1	7	t	t	t	t	t	\N	\N
8	1	8	t	t	t	t	t	\N	\N
9	1	9	t	t	t	t	t	\N	\N
10	1	10	t	t	t	t	t	\N	\N
11	2	1	t	t	t	t	t	\N	\N
12	2	2	t	t	t	t	t	\N	\N
13	2	3	t	t	t	t	t	\N	\N
14	2	4	t	t	t	t	t	\N	\N
15	2	5	t	f	f	f	f	\N	\N
16	2	6	t	t	t	t	t	\N	\N
17	2	7	t	t	t	t	t	\N	\N
18	2	8	t	t	t	t	t	\N	\N
19	2	9	t	t	t	t	t	\N	\N
20	2	10	t	t	t	t	t	\N	\N
21	3	1	f	f	f	f	f	\N	\N
22	3	2	t	f	f	f	f	\N	\N
23	3	3	t	t	t	t	t	\N	\N
24	3	4	t	f	f	f	f	\N	\N
25	3	5	t	t	t	t	t	\N	\N
26	3	6	t	t	t	t	t	\N	\N
27	3	7	f	f	f	f	f	\N	\N
28	3	8	f	f	f	f	f	\N	\N
29	3	9	t	f	f	f	f	\N	\N
30	3	10	f	f	f	f	f	\N	\N
31	4	1	f	f	f	f	f	\N	\N
32	4	2	t	t	t	f	f	\N	\N
33	4	3	f	f	f	f	f	\N	\N
34	4	4	t	f	f	f	f	\N	\N
35	4	5	f	f	f	f	f	\N	\N
36	4	6	f	f	f	f	f	\N	\N
37	4	7	f	f	f	f	f	\N	\N
38	4	8	f	f	f	f	f	\N	\N
39	4	9	t	f	f	f	f	\N	\N
40	4	10	f	f	f	f	f	\N	\N
41	5	1	f	f	f	f	f	\N	\N
42	5	2	t	f	f	f	f	\N	\N
43	5	3	f	f	f	f	f	\N	\N
44	5	4	t	t	t	t	f	\N	\N
45	5	5	f	f	f	f	f	\N	\N
46	5	6	f	f	f	f	f	\N	\N
47	5	7	f	f	f	f	f	\N	\N
48	5	8	f	f	f	f	f	\N	\N
49	5	9	f	f	f	f	f	\N	\N
50	5	10	f	f	f	f	f	\N	\N
51	6	1	f	f	f	f	f	\N	\N
52	6	2	f	f	f	f	f	\N	\N
53	6	3	f	f	f	f	f	\N	\N
54	6	4	f	f	f	f	f	\N	\N
55	6	5	f	f	f	f	f	\N	\N
56	6	6	f	f	f	f	f	\N	\N
57	6	7	f	f	f	f	f	\N	\N
58	6	8	t	t	t	t	f	\N	\N
59	6	9	f	f	f	f	f	\N	\N
60	6	10	f	f	f	f	f	\N	\N
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, name, guard_name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: plan_cuentas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.plan_cuentas (id, empresa_id, codigo, nombre, descripcion, tipo, padre_id, nivel, permite_asientos, estado, total_asientos) FROM stdin;
5	\N	6	GASTOS	\N	gasto	\N	1	f	t	0
402	\N	1	Activos	\N	activo	\N	1	f	t	0
21	\N	1.1.03	INVENTARIOS	\N	activo	403	3	f	t	0
22	\N	1.1.04	SERVICIOS Y OTROS PAGOS ANTICIPADOS	\N	activo	403	3	f	t	0
23	\N	1.1.05	ACTIVOS POR IMPUESTOS CORRIENTES	\N	activo	403	3	f	t	0
24	\N	1.1.06	OTROS ACTIVOS CORRIENTES	\N	activo	403	3	f	t	0
1	\N	2	Pasivos	\N	pasivo	\N	1	f	t	0
403	\N	1.1	Activo Corriente	\N	activo	402	2	f	t	0
2	\N	3	Patrimonio Neto	\N	patrimonio	\N	1	f	t	0
3	\N	4	Ingresos	\N	ingreso	\N	1	f	t	0
4	\N	5	Costos Operativos y Gastos	\N	gasto	\N	1	f	t	0
8	\N	2.2	Pasivo No Corriente	\N	pasivo	1	2	f	t	0
9	\N	3.1	Patrimonio Neto	\N	patrimonio	2	2	f	t	0
15	\N	4.1	Ingresos de Actividades Ordinarias	\N	ingreso	3	2	f	t	0
17	\N	5.1	Costo de Ventas y Producción	\N	gasto	4	2	f	t	0
11	\N	3.3	RESERVAS	\N	patrimonio	2	2	f	t	0
12	\N	3.4	OTROS RESULTADOS INTEGRALES	\N	patrimonio	2	2	f	t	0
13	\N	3.5	RESULTADOS ACUMULADOS	\N	patrimonio	2	2	f	t	0
14	\N	3.7	RESULTADOS DEL EJERCICIO	\N	patrimonio	2	2	f	t	0
16	\N	4.3	OTROS INGRESOS	\N	ingreso	3	2	f	t	0
18	\N	6.01	GASTOS DE OPERACION	\N	gasto	5	2	f	t	0
19	\N	3.4.01	Otros Resultado Integrales	\N	patrimonio	12	3	t	t	0
25	\N	1.2.01	PROPIEDADES. PLANTA Y EQUIPO	\N	activo	6	3	f	t	0
26	\N	1.2.03	ACTIVOS INTANGIBLES	\N	activo	6	3	f	t	0
28	\N	1.2.05	ACTIVOS FINANCIEROS NO CORRIENTES	\N	activo	6	3	f	t	0
29	\N	1.2.06	OTROS ACTIVOS NO CORRIENTES	\N	activo	6	3	f	t	0
30	\N	2.1.01	ACREEDORES COMERCIALES Y OTRAS CUENTAS POR COBRAR	\N	pasivo	7	3	f	t	0
31	\N	2.1.02	OBLIGACIONES CON INSTITUCIONES FINANCIERAS	\N	pasivo	7	3	f	t	0
32	\N	2.1.03	PROVISIONES	\N	pasivo	7	3	f	t	0
33	\N	2.1.04	OBLIGACIONES LABORALES Y FISCALES	\N	pasivo	7	3	f	t	0
34	\N	2.1.07	PORCION CORRIENTE PROVISIONES POR BENEFICIOS EMPLEADOS	\N	pasivo	7	3	f	t	0
35	\N	2.1.08	OTROS PASIVOS CORRIENTES	\N	pasivo	7	3	f	t	0
36	\N	2.2.01	ACREEDORES COMERCIALES Y OTRAS CUENTAS POR PAGAR LP	\N	pasivo	8	3	f	t	0
38	\N	2.2.03	ANTICIPOS DE CLIENTES	\N	pasivo	8	3	f	t	0
39	\N	2.2.04	PROVISIONES POR BENEFICIOS A EMPLEADOS	\N	pasivo	8	3	f	t	0
40	\N	2.2.06	PASIVO DIFERIDO	\N	pasivo	8	3	f	t	0
41	\N	3.1.01	CAPITAL SUSCRITO o ASIGNADO	\N	patrimonio	9	3	f	t	0
42	\N	3.1.02	CAPITAL SUSCRITO NO PAGADO ACCIONES EN TESORERIA	\N	patrimonio	9	3	f	t	0
43	\N	3.2.01	APORTES DE ACCIONISTAS PARA FUTURA CAPITALIZACION	\N	patrimonio	10	3	f	t	0
44	\N	3.3.01	RESERVA LEGAL	\N	patrimonio	11	3	f	t	0
45	\N	3.3.02	RESERVAS FACULTATIVAS ESTATUTARIA	\N	patrimonio	11	3	f	t	0
46	\N	3.3.03	RESERVA DE CAPITAL	\N	patrimonio	11	3	f	t	0
47	\N	3.3.04	OTRAS RESERVAS	\N	patrimonio	11	3	f	t	0
48	\N	3.5.01	GANANCIAS ACUMULADAS	\N	patrimonio	13	3	f	t	0
49	\N	3.5.02	PERDIDAS ACUMULADAS	\N	patrimonio	13	3	f	t	0
51	\N	3.7.01	GANANCIA NETA DEL PERIODO	\N	patrimonio	14	3	f	t	0
52	\N	3.7.02	PERDIDA NETA DEL PERIODO	\N	patrimonio	14	3	f	t	0
53	\N	4.1.01	VENTA DE BIENES	\N	ingreso	15	3	f	t	0
54	\N	4.1.02	VENTA DE SERVICIOS	\N	ingreso	15	3	f	t	0
55	\N	4.1.06	DESCUENTO EN VENTAS	\N	ingreso	15	3	f	t	0
56	\N	4.1.07	DEVOLUCIONES EN VENTAS	\N	ingreso	15	3	f	t	0
57	\N	4.3.03	DESCUENTO EN COMPRAS	\N	ingreso	16	3	f	t	0
58	\N	4.3.05	OTRAS RENTAS	\N	ingreso	16	3	f	t	0
60	\N	5.1.08	ARRENDAMIENTOS Y ALQUILERES	\N	gasto	17	3	f	t	0
61	\N	5.1.19	IMPUESTOS CONTRIBUCIONES Y OTRAS	\N	gasto	17	3	f	t	0
62	\N	6.01.01	SUELDOS SALARIOS Y DEMAS REMUNERACIONES	\N	gasto	18	3	f	t	0
63	\N	6.01.02	APORTES A LA SEGURIDAD SOCIAL	\N	gasto	18	3	f	t	0
64	\N	6.01.03	BENEFICIOS SOCIALES E INDEMNIZACIONES	\N	gasto	18	3	f	t	0
65	\N	6.01.04	GASTO PLANES DE BENEFICIO A EMPLEADOS	\N	gasto	18	3	f	t	0
67	\N	6.01.06	HONORARIOS	\N	gasto	18	3	f	t	0
68	\N	6.01.07	MANTENIMIENTO OFICINA	\N	gasto	18	3	f	t	0
69	\N	6.01.08	ARRENDAMIENTO Y ALQUILER	\N	gasto	18	3	f	t	0
70	\N	6.01.09	COMISIONES VENTAS	\N	gasto	18	3	f	t	0
71	\N	6.01.10	PROMOCION Y PUBLICIDAD	\N	gasto	18	3	f	t	0
72	\N	6.01.11	COMBUSTIBLE	\N	gasto	18	3	f	t	0
73	\N	6.01.12	MANTENIMIENTO Y REPUESTOS VEHICULOS	\N	gasto	18	3	f	t	0
74	\N	6.01.13	SEGUROS Y REASEGUROS PRIMAS Y CESIONES	\N	gasto	18	3	f	t	0
75	\N	6.01.14	TRANSPORTE	\N	gasto	18	3	f	t	0
77	\N	6.01.16	GASTOS DE VIAJE	\N	gasto	18	3	f	t	0
78	\N	6.01.17	SERVICIOS BASICOS	\N	gasto	18	3	f	t	0
79	\N	6.01.18	NOTARIOS Y REGISTRADORAS DE LA PROPIEDAD O MERCANTILES	\N	gasto	18	3	f	t	0
80	\N	6.01.19	IMPUESTOS CONTRIBUCIONES Y OTROS	\N	gasto	18	3	f	t	0
81	\N	6.01.20	SUMINISTROS Y MATERIALES	\N	gasto	18	3	f	t	0
82	\N	6.01.21	GASTOS FINANCIEROS	\N	gasto	18	3	f	t	0
83	\N	1.2.01.01	Terrenos	\N	activo	25	4	t	t	0
84	\N	1.2.01.02	Edificios	\N	activo	25	4	t	t	0
404	\N	1.1.01	EFECTIVO Y EQUIVALENTES AL EFECTIVO	\N	activo	403	3	f	t	0
405	\N	1.1.01.01	CAJA	\N	activo	404	4	f	t	0
593	\N	1.1.1.01	Caja General	\N	activo	411	4	t	t	0
86	\N	1.2.01.04	Instalaciones	\N	activo	25	4	t	t	0
87	\N	1.2.01.05	Muebles y Enseres - Equipos de Ofic	\N	activo	25	4	t	t	0
88	\N	1.2.01.06	Maquinaria y Equipo	\N	activo	25	4	t	t	0
90	\N	1.2.01.08	Vehculos. Equipo de Transporte	\N	activo	25	4	t	t	0
91	\N	1.2.01.09	Otras Propiedades. Planta y Equipo	\N	activo	25	4	t	t	0
92	\N	1.2.03.01	Paquetes Informticos y Software	\N	activo	26	4	t	t	0
93	\N	1.2.04.01	Activos por Impuestos Diferidos	\N	activo	27	4	t	t	0
94	\N	2.2.03.01	Anticipo de clientes	\N	pasivo	38	4	t	t	0
95	\N	3.1.01.01	Capital Suscrito y Pagado	\N	patrimonio	41	4	t	t	0
96	\N	3.1.02.01	Capital Suscrito no pagado	\N	patrimonio	42	4	t	t	0
97	\N	3.2.01.01	Aportes Futuras Capitalizacion	\N	patrimonio	43	4	t	t	0
98	\N	3.3.01.01	Reserva Legal	\N	patrimonio	44	4	t	t	0
100	\N	3.3.03.01	Reserva de Capital	\N	patrimonio	46	4	t	t	0
101	\N	3.3.04.01	Otras Reservas	\N	patrimonio	47	4	t	t	0
102	\N	3.5.01.01	Ganancias 2021	\N	patrimonio	48	4	t	t	0
103	\N	3.5.02.01	Perdidas Acumuladas	\N	patrimonio	49	4	t	t	0
104	\N	3.5.02.02	Perdida Año 2021	\N	patrimonio	49	4	t	t	0
105	\N	3.5.03.01	Resultados Acumulados NIIF	\N	patrimonio	50	4	t	t	0
106	\N	3.7.01.01	Ganancia neta del periodo	\N	patrimonio	51	4	t	t	0
107	\N	3.7.02.01	Perdida Neta del Periodo	\N	patrimonio	52	4	t	t	0
108	\N	4.1.01.01	Ventas de Bienes	\N	ingreso	53	4	t	t	0
109	\N	4.1.02.01	Mantenimiento	\N	ingreso	54	4	t	t	0
110	\N	4.1.02.02	Aseroria	\N	ingreso	54	4	t	t	0
112	\N	4.1.02.04	Repuestos	\N	ingreso	54	4	t	t	0
113	\N	4.1.06.01	Descuento en ventas	\N	ingreso	55	4	t	t	0
114	\N	4.1.07.01	Devolucion en Ventas	\N	ingreso	56	4	t	t	0
115	\N	4.3.03.01	Descuento en Compras	\N	ingreso	57	4	t	t	0
116	\N	4.3.03.02	Multas	\N	ingreso	57	4	t	t	0
117	\N	4.3.05.01	Otros Ingresos 0%	\N	ingreso	58	4	t	t	0
119	\N	4.3.05.03	Intereses Ganados	\N	ingreso	58	4	t	t	0
120	\N	4.3.05.04	Otros	\N	ingreso	58	4	t	t	0
121	\N	5.1.01.01	Repuestos	\N	gasto	59	4	t	t	0
122	\N	5.1.01.02	Envios	\N	gasto	59	4	t	t	0
123	\N	5.1.01.99	Notas de Credito de Clientes	\N	gasto	59	4	t	t	0
124	\N	5.1.08.01	Arriendo Oficinas	\N	gasto	60	4	t	t	0
125	\N	5.1.08.02	Arriendo de Bodegas	\N	gasto	60	4	t	t	0
127	\N	5.1.08.04	Alquiler de Equipos	\N	gasto	60	4	t	t	0
128	\N	5.1.19.01	Impuestos Municipales	\N	gasto	61	4	t	t	0
129	\N	5.1.19.02	Afiliaciones y suscripciones	\N	gasto	61	4	t	t	0
131	\N	1.1.02.01	DOCUMENTOS Y CUENTAS POR COBRAR CLIENTES NO RELACIONADOS	\N	activo	20	4	f	t	0
132	\N	1.1.02.02	DOCUMENTOS Y CUENTAS POR COBRAR CLIENTES RELACIONADOS	\N	activo	20	4	f	t	0
133	\N	1.1.02.03	OTRAS CUENTAS POR COBRAR RELACIONADAS	\N	activo	20	4	f	t	0
135	\N	1.1.02.05	PROVISION CUENTAS INCOBRABLES	\N	activo	20	4	f	t	0
136	\N	1.1.02.06	OTRAS CTAS POR COBRAR	\N	activo	20	4	f	t	0
137	\N	1.1.03.01	INVENTARIOS PRODUCTOS TERMINADOS	\N	activo	21	4	f	t	0
138	\N	1.1.03.02	MERCADERIAS EN TRANSITO	\N	activo	21	4	f	t	0
139	\N	1.1.04.01	SEGUROS PAGADOS POR ANTICIPADO	\N	activo	22	4	f	t	0
140	\N	1.1.04.02	ARRIENDOS PAGADOS POR ANTICIPADO	\N	activo	22	4	f	t	0
141	\N	1.1.04.03	ANTICIPOS A PROVEEDORES	\N	activo	22	4	f	t	0
142	\N	1.1.04.04	OTROS ANTICIPOS ENTREGADOS	\N	activo	22	4	f	t	0
143	\N	1.1.05.01	CREDITO TRIBUTARIO A FAVOR DE LA EMPRESA IVA	\N	activo	23	4	f	t	0
145	\N	1.1.05.03	ANTICIPO DE IMPUESTO A LA RENTA	\N	activo	23	4	f	t	0
146	\N	1.1.06.01	OTROS ACTIVOS CORRIENTES	\N	activo	24	4	f	t	0
147	\N	1.2.01.13	DETERIORO ACUMULADO DE PROPIEDADES PLANTA Y EQUIPO	\N	activo	25	4	f	t	0
148	\N	1.2.03.05	AMORTIZACION ACUMULADA ACTIVOS INTANGIBLES	\N	activo	26	4	f	t	0
149	\N	1.2.05.01	DOCUMENTOS Y CUENTAS POR COBRAR LARGO PLAZO	\N	activo	28	4	f	t	0
150	\N	1.2.05.02	PROVISION CUENTAS INCOBRABLES DE ACTIVOS FINANCIEROS NO CORRIENTES	\N	activo	28	4	f	t	0
151	\N	1.2.06.01	OTROS ACTIVOS NO CORRIENTES	\N	activo	29	4	f	t	0
152	\N	2.1.01.01	ACREEDORES COMERCIALES LOCALES	\N	pasivo	30	4	f	t	0
153	\N	2.1.01.02	ACREEDORES COMERCIALES DEL EXTERIOR	\N	pasivo	30	4	f	t	0
154	\N	2.1.01.03	CUENTAS POR PAGAR RELACIONADAS	\N	pasivo	30	4	f	t	0
156	\N	2.1.02.01	OBLIGACIONES FINANCIERAS LOCALES	\N	pasivo	31	4	f	t	0
157	\N	2.1.03.01	PROVISIONES LOCALES	\N	pasivo	32	4	f	t	0
158	\N	2.1.04.01	OBLIGACIONES ADMINISTRACION TRIBUTARIA	\N	pasivo	33	4	f	t	0
159	\N	2.1.04.02	IMPUESTO A LA RENTA POR PAGAR	\N	pasivo	33	4	f	t	0
160	\N	2.1.04.03	OBLIGACIONES CON EL IESS	\N	pasivo	33	4	f	t	0
161	\N	2.1.04.04	OBLIGACIONES LABORALES	\N	pasivo	33	4	f	t	0
162	\N	2.1.04.05	PARTICIPACION TRABAJADORES POR PAGAR DEL EJERCICIO	\N	pasivo	33	4	f	t	0
163	\N	2.1.04.06	DIVIDENDOS POR PAGAR	\N	pasivo	33	4	f	t	0
164	\N	2.1.07.01	PROVISION JUBILACION PATRONAL	\N	pasivo	34	4	f	t	0
594	\N	1.1.1.02	Cajas Chicas y Fondos Rotativos	\N	activo	411	4	t	t	0
167	\N	2.2.01.01	ACREEDORES COMERCIALES LOCALES	\N	pasivo	36	4	f	t	0
168	\N	2.2.01.02	CUENTAS POR PAGAR RELACIONADAS	\N	pasivo	36	4	f	t	0
169	\N	2.2.01.03	CUENTAS POR PAGAR NO RELACIONADAS	\N	pasivo	36	4	f	t	0
170	\N	2.2.04.01	PROVISION JUBILACION PATRONAL	\N	pasivo	39	4	f	t	0
172	\N	2.2.06.01	INGRESOS DIFERIDOS	\N	pasivo	40	4	f	t	0
173	\N	2.2.06.02	PASIVOS POR IMPUESTOS DIFERIDOS	\N	pasivo	40	4	f	t	0
175	\N	6.01.01.01	Sueldos	\N	gasto	62	4	t	t	0
176	\N	6.01.01.02	Horas Extras	\N	gasto	62	4	t	t	0
177	\N	6.01.02.01	Aportes Patronales	\N	gasto	63	4	t	t	0
178	\N	6.01.02.02	Fondos de Reserva	\N	gasto	63	4	t	t	0
179	\N	6.01.03.01	Decimo Tercer Sueldo	\N	gasto	64	4	t	t	0
180	\N	6.01.03.02	Decimo Cuarto Sueldo	\N	gasto	64	4	t	t	0
181	\N	6.01.03.03	Vacaciones	\N	gasto	64	4	t	t	0
182	\N	6.01.03.04	Indemnizaciones- Desahucios	\N	gasto	64	4	t	t	0
184	\N	6.01.03.06	Uniformes	\N	gasto	64	4	t	t	0
185	\N	6.01.03.07	Gastos Medicos de Personal	\N	gasto	64	4	t	t	0
186	\N	6.01.03.08	Capacitacion	\N	gasto	64	4	t	t	0
187	\N	6.01.03.09	Bonificaciones	\N	gasto	64	4	t	t	0
188	\N	6.01.03.10	Gastos Seguro Medico	\N	gasto	64	4	t	t	0
189	\N	6.01.04.01	Jubilacion Patronal	\N	gasto	65	4	t	t	0
190	\N	6.01.04.02	Desahucio	\N	gasto	65	4	t	t	0
191	\N	6.01.05.01	Honorarios	\N	gasto	66	4	t	t	0
192	\N	6.01.05.02	Comisiones	\N	gasto	66	4	t	t	0
193	\N	6.01.05.03	Dietas	\N	gasto	66	4	t	t	0
195	\N	6.01.06.02	Honorarios a Extranjeros	\N	gasto	67	4	t	t	0
196	\N	6.01.06.03	Aseroria Contable	\N	gasto	67	4	t	t	0
197	\N	6.01.06.04	Mano de Obra	\N	gasto	67	4	t	t	0
198	\N	6.01.06.05	Agente de Aduana	\N	gasto	67	4	t	t	0
200	\N	6.01.07.02	Mantenimiento Oficinas	\N	gasto	68	4	t	t	0
201	\N	6.01.07.03	Adecuaciones Instalaciones	\N	gasto	68	4	t	t	0
202	\N	6.01.08.01	Arriendo Oficinas	\N	gasto	69	4	t	t	0
203	\N	6.01.08.02	Arriendo Bodegas	\N	gasto	69	4	t	t	0
204	\N	6.01.08.03	Arriendo de hosting/ web	\N	gasto	69	4	t	t	0
205	\N	6.01.08.04	Arriendo Vivienda	\N	gasto	69	4	t	t	0
206	\N	6.01.08.05	Arriendo de Equipos	\N	gasto	69	4	t	t	0
207	\N	6.01.09.01	Comisiones Ventas	\N	gasto	70	4	t	t	0
208	\N	6.01.10.01	Publicidad y Propaganda	\N	gasto	71	4	t	t	0
209	\N	6.01.10.02	Anuncios y Publicaciones	\N	gasto	71	4	t	t	0
210	\N	6.01.11.01	Combustible	\N	gasto	72	4	t	t	0
212	\N	6.01.12.03	Repuestos Vehiculos	\N	gasto	73	4	t	t	0
213	\N	6.01.12.05	Lubricantes y Aceites	\N	gasto	73	4	t	t	0
214	\N	6.01.13.01	Seguros de Vehiculos	\N	gasto	74	4	t	t	0
215	\N	6.01.13.02	Seguros Generales	\N	gasto	74	4	t	t	0
216	\N	6.01.13.03	SOAT	\N	gasto	74	4	t	t	0
217	\N	6.01.14.01	Envios Clientes	\N	gasto	75	4	t	t	0
218	\N	6.01.14.02	Flete de Mercadera	\N	gasto	75	4	t	t	0
219	\N	6.01.14.03	Ticket Areos	\N	gasto	75	4	t	t	0
220	\N	6.01.14.04	Pasajes y Taxis	\N	gasto	75	4	t	t	0
222	\N	6.01.15.01	Atencion a Clientes	\N	gasto	76	4	t	t	0
223	\N	6.01.15.02	Alimentacion Personal	\N	gasto	76	4	t	t	0
224	\N	6.01.15.03	Atencion a Proveedores	\N	gasto	76	4	t	t	0
225	\N	6.01.15.04	Donaciones y Obsequios	\N	gasto	76	4	t	t	0
226	\N	6.01.15.05	Gastos de Gestion	\N	gasto	76	4	t	t	0
227	\N	6.01.15.06	Agasajo Navideño	\N	gasto	76	4	t	t	0
228	\N	6.01.15.07	Atenciones Sociales	\N	gasto	76	4	t	t	0
229	\N	6.01.16.01	Hospedaje	\N	gasto	77	4	t	t	0
231	\N	6.01.16.03	Alimentacion_VIAJES	\N	gasto	77	4	t	t	0
232	\N	6.01.17.01	Agua	\N	gasto	78	4	t	t	0
233	\N	6.01.17.02	Energia Electrica	\N	gasto	78	4	t	t	0
234	\N	6.01.17.03	Telefono Convencional	\N	gasto	78	4	t	t	0
235	\N	6.01.17.04	Celular	\N	gasto	78	4	t	t	0
236	\N	6.01.17.05	Internet	\N	gasto	78	4	t	t	0
238	\N	6.01.17.07	Television por Cable	\N	gasto	78	4	t	t	0
239	\N	6.01.17.08	Alicuotas	\N	gasto	78	4	t	t	0
240	\N	6.01.18.01	Notarios	\N	gasto	79	4	t	t	0
241	\N	6.01.18.02	Gastos Legales	\N	gasto	79	4	t	t	0
242	\N	6.01.19.01	Impuestos Municipales	\N	gasto	80	4	t	t	0
243	\N	6.01.19.02	Cuotas Camara de Comercio y Otros	\N	gasto	80	4	t	t	0
244	\N	6.01.19.03	Retenciones Asumidas	\N	gasto	80	4	t	t	0
245	\N	6.01.19.04	Gastos no Deducibles	\N	gasto	80	4	t	t	0
247	\N	6.01.19.06	Matricula Vehiculos	\N	gasto	80	4	t	t	0
248	\N	6.01.19.07	Buro de Credito	\N	gasto	80	4	t	t	0
249	\N	6.01.19.08	Impuesto Asumido Edwin Alt	\N	gasto	80	4	t	t	0
250	\N	6.01.19.09	Comisiones IESS	\N	gasto	80	4	t	t	0
251	\N	6.01.19.10	Comisiones Servicios Basico	\N	gasto	80	4	t	t	0
252	\N	6.01.19.11	Intereses SRI	\N	gasto	80	4	t	t	0
253	\N	6.01.19.12	Suscripciones	\N	gasto	80	4	t	t	0
275	\N	1.1.01.01.09	Reverso Depositos	\N	activo	405	5	t	t	0
276	\N	1.1.01.01.99	Cheque Devueltos	\N	activo	405	5	t	t	0
277	\N	1.1.01.02.01	Banco Produbanco	\N	activo	130	5	t	t	1
274	\N	1.1.01.01.08	Transferencia Bancarias	\N	activo	405	5	t	t	2
315	\N	2.1.01.01.01	Proveedores Locales	\N	pasivo	152	5	t	t	1
596	\N	1.1.1.04	Bancos del Exterior	\N	activo	411	4	t	t	0
255	\N	6.01.20.02	Utiles de Ferreteria y Limpieza	\N	gasto	81	4	t	t	0
256	\N	6.01.20.03	Suministros de Computacion	\N	gasto	81	4	t	t	0
258	\N	6.01.20.05	Repuestos y Herramientas	\N	gasto	81	4	t	t	0
259	\N	6.01.20.06	Bienes Menores	\N	gasto	81	4	t	t	0
260	\N	6.01.20.07	Imprenta	\N	gasto	81	4	t	t	0
261	\N	6.01.20.08	Suministros y Materiales	\N	gasto	81	4	t	t	0
262	\N	6.01.20.09	Gastos Varios	\N	gasto	81	4	t	t	0
263	\N	6.01.20.10	Equipos y Muebles de Oficina	\N	gasto	81	4	t	t	0
265	\N	6.01.21.01	INTERESES	\N	gasto	82	4	f	t	0
266	\N	6.01.21.02	COMISIONES	\N	gasto	82	4	f	t	0
268	\N	6.01.21.04	DIFERENCIA EN CAMBIO	\N	gasto	82	4	f	t	0
269	\N	6.01.21.05	OTROS GASTOS FINANCIEROS	\N	gasto	82	4	f	t	0
270	\N	6.01.21.06	DEPRECIACIONES ACTIVOS VENTAS	\N	gasto	82	4	f	t	0
271	\N	6.01.21.07	AMORTIZACIONES ACTIVOS VENTAS	\N	gasto	82	4	f	t	0
272	\N	6.01.21.08	GASTO DETERIORO	\N	gasto	82	4	f	t	0
273	\N	6.01.21.09	VALOR NETO DE REALIZACION DE INVENTARIOS	\N	gasto	82	4	f	t	0
278	\N	1.1.01.02.02	Banco Pichincha Cta Cte:	\N	activo	130	5	t	t	0
280	\N	1.1.02.01.01	Cuentas por Cobrar Clientes	\N	activo	131	5	t	t	0
281	\N	1.1.02.01.02	Cuentas por Cobrar Clientes 2021	\N	activo	131	5	t	t	0
282	\N	1.1.02.02.01	Cuentas por Cobrar Clientes Relacionados	\N	activo	132	5	t	t	0
283	\N	1.1.02.02.02	Intereses por Realizar - Clientes	\N	activo	132	5	t	t	0
284	\N	1.1.02.03.01	Cuentas por Cobrar Marlen Rondal	\N	activo	133	5	t	t	0
285	\N	1.1.02.03.02	Cuentas por Cobrar Comisiones TC	\N	activo	133	5	t	t	0
286	\N	1.1.02.05.01	Provision Cuentas Incobrables Come	\N	activo	135	5	t	t	0
287	\N	1.1.02.05.02	Provision Cuentas Incobrables Gene	\N	activo	135	5	t	t	0
288	\N	1.1.02.06.01	Comisiones TC x Cobrar	\N	activo	136	5	t	t	0
290	\N	1.1.03.02.01	Inventarios en Transito	\N	activo	138	5	t	t	0
291	\N	1.1.04.01.01	Seguros pagados por anticipado	\N	activo	139	5	t	t	0
292	\N	1.1.04.02.01	Arriendo pagado por anticipado	\N	activo	140	5	t	t	0
293	\N	1.1.04.03.01	Anticipo Pago a Proveedores	\N	activo	141	5	t	t	0
294	\N	1.1.04.03.02	Anticipo Proveedores Exterior	\N	activo	141	5	t	t	0
295	\N	1.1.04.04.01	Anticipo Varios	\N	activo	142	5	t	t	0
296	\N	1.1.04.04.02	Otras Ctas. por Cobrar	\N	activo	142	5	t	t	0
297	\N	1.1.04.04.03	Anticipo Sueldos	\N	activo	142	5	t	t	0
298	\N	1.1.04.04.04	Prestamo Empleado	\N	activo	142	5	t	t	0
300	\N	1.1.05.01.01	IVA Pagado	\N	activo	143	5	t	t	0
301	\N	1.1.05.01.02	Retenciones IVA Recibidas	\N	activo	143	5	t	t	0
302	\N	1.1.05.01.03	Credito Tributario IVA	\N	activo	143	5	t	t	0
303	\N	1.1.05.01.04	Iva Pagado en Importaciones	\N	activo	143	5	t	t	0
304	\N	1.1.05.02.01	Ret del Impto a la Renta Ejercicio	\N	activo	144	5	t	t	0
305	\N	1.1.05.03.01	Anticipo del Impuesto a la Renta de	\N	activo	145	5	t	t	0
306	\N	1.1.06.01.01	Otros Activos Corrientes	\N	activo	146	5	t	t	0
308	\N	1.2.03.05.01	Amortizacion Acumulada Paquetes In	\N	activo	148	5	t	t	0
309	\N	1.2.03.05.02	Deterioro Acumulado Paquetes Info	\N	activo	148	5	t	t	0
310	\N	1.2.05.01.01	Cuentas por Cobrar Clientes a Largo	\N	activo	149	5	t	t	0
311	\N	1.2.05.01.02	Intereses por Realizar - Cuentas	\N	activo	149	5	t	t	0
312	\N	1.2.05.02.01	Provisin Cuentas Incobrables de a	\N	activo	150	5	t	t	0
313	\N	1.2.06.01.01	Otros Activos no Corrientes	\N	activo	151	5	t	t	0
314	\N	1.2.06.01.02	Garantias Varias	\N	activo	151	5	t	t	0
316	\N	2.1.01.02.01	Proveedores del Exterior	\N	pasivo	153	5	t	t	0
318	\N	2.1.01.03.01	Cuentas por Pagar	\N	pasivo	154	5	t	t	0
319	\N	2.1.01.04.01	Claro	\N	pasivo	155	5	t	t	0
320	\N	2.1.01.04.02	CNT	\N	pasivo	155	5	t	t	0
321	\N	2.1.01.04.03	Ecuasanitas S.A.	\N	pasivo	155	5	t	t	0
322	\N	2.1.01.04.04	Datafast	\N	pasivo	155	5	t	t	0
323	\N	2.1.01.04.05	Epmaps	\N	pasivo	155	5	t	t	0
324	\N	2.1.01.04.06	EEQ	\N	pasivo	155	5	t	t	0
325	\N	2.1.01.04.07	Envios Clientes	\N	pasivo	155	5	t	t	0
326	\N	2.1.01.04.08	Megadatos	\N	pasivo	155	5	t	t	0
327	\N	2.1.01.04.30	Otras Ctas. Por Pagar	\N	pasivo	155	5	t	t	0
329	\N	2.1.03.01.01	Depositos sin identificar - Clientes	\N	pasivo	157	5	t	t	0
330	\N	2.1.03.01.02	Multas Personal	\N	pasivo	157	5	t	t	0
331	\N	2.1.04.01.01	Ret en la Fuente Impto a la Renta p	\N	pasivo	158	5	t	t	0
332	\N	2.1.04.01.02	Retenciones IVA por Pagar	\N	pasivo	158	5	t	t	0
333	\N	2.1.04.01.03	IVA en Ventas	\N	pasivo	158	5	t	t	0
335	\N	2.1.04.01.05	Retenciones en la Fuente x Pagar	\N	pasivo	158	5	t	t	0
334	\N	2.1.04.01.04	Impuesto por Liquidar	\N	pasivo	158	5	f	t	0
597	\N	1.1.1.05	Cuentas Virtuales y Pasarelas de Pago	\N	activo	411	4	t	t	0
10	\N	3.2	APORTES DE ACCIONISTAS PARA FUTURA CAPITALIZACION	\N	patrimonio	2	2	f	t	0
27	\N	1.2.04	ACTIVOS POR IMPUESTOS A LA RENTA DIFERIDOS	\N	activo	6	3	f	t	0
37	\N	2.2.02	OBLIGACIONES CON INSTITUCIONES FINANCIERAS	\N	pasivo	8	3	f	t	0
50	\N	3.5.03	RESULTADOS ACUMULADOS NIIF	\N	patrimonio	13	3	f	t	0
59	\N	5.1.01	COSTO DE VENTAS - PRODUCTOS VENDIDOS	\N	gasto	17	3	f	t	0
66	\N	6.01.05	HONORARIOS COMISIONES Y DIETAS A PERSONAS NATURALES	\N	gasto	18	3	f	t	0
76	\N	6.01.15	GASTO DE GESTION AGASAJOS A ACCIONISTAS TRABAJADORES Y CLIENTES	\N	gasto	18	3	f	t	0
85	\N	1.2.01.03	Construcciones en Curso	\N	activo	25	4	t	t	0
89	\N	1.2.01.07	Equipo de Computacion y Software	\N	activo	25	4	t	t	0
99	\N	3.3.02.01	Reservas Facultativas y Estatutaria	\N	patrimonio	45	4	t	t	0
337	\N	2.1.04.03.01	Aportes IESS por Pagar	\N	pasivo	160	5	t	t	0
338	\N	2.1.04.03.02	Prestamos IESS por Pagar	\N	pasivo	160	5	t	t	0
339	\N	2.1.04.03.03	Fondos de Reserva por Pagar	\N	pasivo	160	5	t	t	0
340	\N	2.1.04.03.99	PLANILLAS MENSUALES POR CANCELAR	\N	pasivo	160	5	t	t	0
341	\N	2.1.04.04.01	Remuneraciones por Pagar	\N	pasivo	161	5	t	t	0
342	\N	2.1.04.04.02	Decimo Tercer Sueldo por Pagar	\N	pasivo	161	5	t	t	0
344	\N	2.1.04.04.04	Vacaciones por Pagar	\N	pasivo	161	5	t	t	0
345	\N	2.1.04.04.05	Finiquitos por Pagar	\N	pasivo	161	5	t	t	0
346	\N	2.1.04.05.01	15% Participacin a Trabajadores	\N	pasivo	162	5	t	t	0
347	\N	2.1.04.06.01	Dividendos por Pagar	\N	pasivo	163	5	t	t	0
348	\N	2.1.07.01.01	Provision Jubilacin Patronal Porcin	\N	pasivo	164	5	t	t	0
349	\N	2.1.07.02.01	Provision Desahucio Porcin Corriente	\N	pasivo	165	5	t	t	0
350	\N	2.1.08.01.01	Otros Pasivos Corrientes	\N	pasivo	166	5	t	t	0
351	\N	2.1.08.01.02	Mercaderia Temporal por Liquidar	\N	pasivo	166	5	t	t	0
352	\N	2.2.01.01.01	Proveedores LP	\N	pasivo	167	5	t	t	0
354	\N	2.2.01.02.01	Cuentas por Pagar Relacionadas	\N	pasivo	168	5	t	t	0
355	\N	2.2.01.03.01	Cuentas por Pagar Terceros LP	\N	pasivo	169	5	t	t	0
356	\N	2.2.04.01.01	Provisin Jubilacin Patronal	\N	pasivo	170	5	t	t	0
357	\N	2.2.04.02.01	Provisin Desahucio	\N	pasivo	171	5	t	t	0
358	\N	2.2.06.01.01	Ingresos Diferidos	\N	pasivo	172	5	t	t	0
359	\N	2.2.06.02.01	Pasivo por Impuesto Diferido ao 1	\N	pasivo	173	5	t	t	0
360	\N	2.2.07.01.01	Otros Pasivos no Corrientes	\N	pasivo	174	5	t	t	0
361	\N	1.1.02.04.01	CUENTAS POR COBRAR EMPLEADOS	\N	activo	134	5	f	t	0
362	\N	1.1.02.04.02	OTRAS CUENTAS POR COBRAR	\N	activo	134	5	f	t	0
364	\N	2.1.01.01.02	Tarjeta Corporativa por pagar	\N	pasivo	152	5	f	t	0
365	\N	6.01.21.01.01	Intereses por prestamos Bancarios	\N	gasto	265	5	t	t	0
366	\N	6.01.21.01.02	Costos Financieros	\N	gasto	265	5	t	t	0
367	\N	6.01.21.02.01	Comisiones Bancarias	\N	gasto	266	5	t	t	0
368	\N	6.01.21.02.02	Comision Tarjetas de Credito	\N	gasto	266	5	t	t	0
369	\N	6.01.21.02.03	Gastos Datafast	\N	gasto	266	5	t	t	0
370	\N	6.01.21.02.04	Comisiones SRI	\N	gasto	266	5	t	t	0
371	\N	6.01.21.02.05	Seguro Prestamo EA- B. PICHINCHA	\N	gasto	266	5	t	t	0
372	\N	6.01.21.03.01	Gastos de Financiamiento	\N	gasto	267	5	t	t	0
373	\N	6.01.21.04.01	Diferencia en Cambio	\N	gasto	268	5	t	t	0
375	\N	6.01.21.05.02	Impuesto Salida Divisas	\N	gasto	269	5	t	t	0
376	\N	6.01.21.06.01	Depreciacin Propiedades Planta y Eq	\N	gasto	270	5	t	t	0
377	\N	6.01.21.07.01	Amortizacion Intangibles	\N	gasto	271	5	t	t	0
378	\N	6.01.21.07.02	Amortizacion Otros Activos	\N	gasto	271	5	t	t	0
379	\N	6.01.21.08.01	Deterioro Propiedad Planta y Equipo	\N	gasto	272	5	t	t	0
380	\N	6.01.21.08.02	Deterioro Inventarios	\N	gasto	272	5	t	t	0
381	\N	6.01.21.08.03	Deterioro Instrumentos Financieros	\N	gasto	272	5	t	t	0
382	\N	6.01.21.08.04	Deterioro Intangibles	\N	gasto	272	5	t	t	0
384	\N	6.01.21.08.06	Deterioro Otros Activos	\N	gasto	272	5	t	t	0
385	\N	6.01.21.09.01	Valor neto de realizacin de inventario	\N	gasto	273	5	t	t	0
386	\N	1.1.02.04.01.01	Segundo Rigoberto Silva Matute	\N	activo	361	6	t	t	0
387	\N	1.1.02.04.01.02	Maria Luz Cleotilde Guanotasig Caisa	\N	activo	361	6	t	t	0
388	\N	1.1.02.04.01.03	Luis Fernando Andagoya Ramos	\N	activo	361	6	t	t	0
389	\N	1.1.02.04.01.04	Juan Pablo Constante Pozo	\N	activo	361	6	t	t	0
390	\N	1.1.02.04.02.01	Comisiones TC por conciliar	\N	activo	362	6	t	t	0
391	\N	1.1.02.04.02.02	Valores por Justificar	\N	activo	362	6	t	t	0
392	\N	2.1.01.01.02.01	Diners Edwin Altamirano	\N	pasivo	364	6	t	t	0
394	\N	2.1.01.01.02.03	Pacificard Edwin	\N	pasivo	364	6	t	t	0
395	\N	2.1.01.01.02.04	Pacificard  Rondal	\N	pasivo	364	6	t	t	0
396	\N	2.1.01.01.02.05	Banco del Produbanco	\N	pasivo	364	6	t	t	0
397	\N	2.1.01.01.02.06	Banco Guayaquil	\N	pasivo	364	6	t	t	0
398	\N	2.1.01.01.02.07	Visa Titanium	\N	pasivo	364	6	t	t	0
399	\N	2.1.01.01.02.08	Otras Ctas Por Pagar Clientes	\N	pasivo	364	6	t	t	0
400	\N	2.1.04.01.04.01	Formulario 103	\N	pasivo	334	6	t	t	0
401	\N	2.1.04.01.04.02	Formulario 104	\N	pasivo	334	6	t	t	0
111	\N	4.1.02.03	Instalaciones	\N	ingreso	54	4	t	t	0
118	\N	4.3.05.02	Otros Ingresos 12%	\N	ingreso	58	4	t	t	0
126	\N	5.1.08.03	Arriendo de hosting/ web	\N	gasto	60	4	t	t	0
134	\N	1.1.02.04	OTRAS CUENTAS POR COBRAR NO RELACIONADAS	\N	activo	20	4	f	t	0
144	\N	1.1.05.02	CREDITO TRIBUTARIO A FAVOR DE LA EMPRESA I. R.	\N	activo	23	4	f	t	0
155	\N	2.1.01.04	CUENTAS POR PAGAR NO RELACIONADAS	\N	pasivo	30	4	f	t	0
165	\N	2.1.07.02	OTROS BENEFICIOS A LARGO PLAZO PARA LOS EMPLEADOS	\N	pasivo	34	4	f	t	0
166	\N	2.1.08.01	OTROS PASIVOS CORRIENTES	\N	pasivo	35	4	f	t	0
171	\N	2.2.04.02	OTROS BENEFICIOS A LARGO PLAZO PARA LOS EMPLEADOS	\N	pasivo	39	4	f	t	0
183	\N	6.01.03.05	Alimentacion a Empleados	\N	gasto	64	4	t	t	0
194	\N	6.01.06.01	Trabajos Ocasionales	\N	gasto	67	4	t	t	0
199	\N	6.01.07.01	Mantenimiento Equipos Computacion y Software	\N	gasto	68	4	t	t	0
211	\N	6.01.12.01	Mantenimiento Vehiculos	\N	gasto	73	4	t	t	0
221	\N	6.01.14.05	Encomiendas y Envios	\N	gasto	75	4	t	t	0
230	\N	6.01.16.02	Movilizacion	\N	gasto	77	4	t	t	0
237	\N	6.01.17.06	Monitoreo-Alarma-Guardiana	\N	gasto	78	4	t	t	0
246	\N	6.01.19.05	Afiliaciones	\N	gasto	80	4	t	t	0
254	\N	6.01.20.01	Suministros de Oficina y Papeleria	\N	gasto	81	4	t	t	0
257	\N	6.01.20.04	Suministros de Cafeteria	\N	gasto	81	4	t	t	0
267	\N	6.01.21.03	GASTOS DE FINANCIAMIENTO DE ACTIVOS	\N	gasto	82	4	f	t	0
279	\N	1.1.01.02.03	Alianza del Valle Cia. Ltda.	\N	activo	130	5	t	t	0
289	\N	1.1.03.01.01	Inventario productos terminados	\N	activo	137	5	t	t	0
299	\N	1.1.04.04.05	Anticipo Comisiones	\N	activo	142	5	t	t	0
307	\N	1.2.01.13.01	Deterioro Acumulado Propiedad, Planta y Equipo	\N	activo	147	5	t	t	0
317	\N	2.1.01.02.02	Intereses por Devengar - Proveedo	\N	pasivo	153	5	t	t	0
328	\N	2.1.02.01.01	Sobregiros Bancarios	\N	pasivo	156	5	t	t	0
336	\N	2.1.04.02.01	Impuesto a la Renta por Pagar Ejerc	\N	pasivo	159	5	t	t	0
343	\N	2.1.04.04.03	Decimo Cuarto Sueldo por Pagar	\N	pasivo	161	5	t	t	0
353	\N	2.2.01.01.02	Intereses por Devengar - Proveedo	\N	pasivo	167	5	t	t	0
363	\N	1.2.01.1.2.01	Depreciacion Acumulada Propiedad, Planta y Equipo	\N	activo	264	6	t	t	0
374	\N	6.01.21.05.01	Notas de Debito-Chequeras.	\N	gasto	269	5	t	t	0
383	\N	6.01.21.08.05	Deterioro Cuentas por Cobrar	\N	gasto	272	5	t	t	0
393	\N	2.1.01.01.02.02	Diners Rondal	\N	pasivo	364	6	t	t	0
406	\N	1.2.01.1	DEPRECIACION ACUMULADA PROPIEDADES PLANTA Y EQUIPO	\N	activo	25	4	f	t	0
407	\N	2.2.07	OTROS PASIVOS NO CORRIENTES	\N	pasivo	8	3	f	t	0
20	\N	1.1.02	DEUDORES COMERCIALES Y OTRAS CUENTAS POR COBRAR	\N	activo	403	3	f	t	0
130	\N	1.1.01.02	BANCOS LOCALES	\N	activo	404	4	f	t	0
174	\N	2.2.07.01	OTROS PASIVOS NO CORRIENTES	\N	pasivo	407	4	f	t	0
264	\N	1.2.01.1.2	DEPRECIACION ACUMULADA PROPIEDADES PLANTA Y EQUIPO	\N	activo	406	5	f	t	0
598	\N	1.1.2.01	Inversiones a Costo Amortizado — Corto Plazo	\N	activo	417	4	t	t	0
425	\N	1.1.4	Inventarios	\N	activo	403	3	f	t	0
430	\N	1.1.5	Activos por Impuestos Corrientes	\N	activo	403	3	f	t	0
435	\N	1.1.6	Gastos Pagados por Anticipado	\N	activo	403	3	f	t	0
6	\N	1.2	Activo No Corriente	\N	activo	402	2	f	t	0
439	\N	1.2.1	Propiedades, Planta y Equipo	\N	activo	6	3	f	t	0
412	\N	1.1.1.1	Caja General	\N	activo	411	4	t	t	0
413	\N	1.1.1.2	Cajas Chicas y Fondos	\N	activo	411	4	t	t	0
414	\N	1.1.1.3	Bancos Locales	\N	activo	411	4	t	t	0
415	\N	1.1.1.4	Bancos del Exterior	\N	activo	411	4	t	t	0
416	\N	1.1.1.5	Dinero Electronico / Pasarelas de Pago	\N	activo	411	4	t	t	0
418	\N	1.1.2.1	Inversiones a Costo Amortizado (Polizas)	\N	activo	417	4	t	t	0
420	\N	1.1.3.1	Clientes Locales	\N	activo	419	4	t	t	0
421	\N	1.1.3.2	Clientes del Exterior	\N	activo	419	4	t	t	0
422	\N	1.1.3.3	Anticipos a Proveedores	\N	activo	419	4	t	t	0
423	\N	1.1.3.4	Prestamos y Anticipos a Empleados	\N	activo	419	4	t	t	0
424	\N	1.1.3.5	(-) Provision Cuentas Incobrables	\N	activo	419	4	t	t	0
426	\N	1.1.4.1	Inventario de Mercaderia	\N	activo	425	4	t	t	0
428	\N	1.1.4.3	Inventario en Transito	\N	activo	425	4	t	t	0
429	\N	1.1.4.4	(-) Provision por Deterioro de Inventarios	\N	activo	425	4	t	t	0
431	\N	1.1.5.1	Credito Tributario por IVA	\N	activo	430	4	t	t	0
432	\N	1.1.5.2	Credito Tributario por Retenciones de IVA	\N	activo	430	4	t	t	0
433	\N	1.1.5.3	Credito Tributario por Retenciones de IR	\N	activo	430	4	t	t	0
434	\N	1.1.5.4	Anticipo de Impuesto a la Renta	\N	activo	430	4	t	t	0
436	\N	1.1.6.1	Seguros Pagados por Anticipado	\N	activo	435	4	t	t	0
437	\N	1.1.6.2	Arriendos Pagados por Anticipado	\N	activo	435	4	t	t	0
440	\N	1.2.1.1	Terrenos	\N	activo	439	4	t	t	0
441	\N	1.2.1.2	Edificios	\N	activo	439	4	t	t	0
442	\N	1.2.1.3	Instalaciones	\N	activo	439	4	t	t	0
443	\N	1.2.1.4	Muebles y Enseres	\N	activo	439	4	t	t	0
444	\N	1.2.1.5	Maquinaria y Equipos	\N	activo	439	4	t	t	0
445	\N	1.2.1.6	Equipos de Computacion	\N	activo	439	4	t	t	0
447	\N	1.2.1.8	Repuestos y Herramientas	\N	activo	439	4	t	t	0
419	\N	1.1.3	Cuentas y Documentos por Cobrar	\N	activo	403	3	f	t	0
450	\N	1.2.1.10.1	(-) Dep. Acum. Edificios	\N	activo	449	5	t	t	0
451	\N	1.2.1.10.2	(-) Dep. Acum. Instalaciones	\N	activo	449	5	t	t	0
452	\N	1.2.1.10.3	(-) Dep. Acum. Muebles y Enseres	\N	activo	449	5	t	t	0
453	\N	1.2.1.10.4	(-) Dep. Acum. Maquinaria y Equipos	\N	activo	449	5	t	t	0
455	\N	1.2.1.10.6	(-) Dep. Acum. Vehículos	\N	activo	449	5	t	t	0
457	\N	1.2.2	Propiedades de Inversión	\N	activo	6	3	f	t	0
461	\N	1.2.3	Activos Intangibles	\N	activo	6	3	f	t	0
465	\N	1.2.4	Activos Financieros y Cuentas por Cobrar a Largo Plazo	\N	activo	6	3	f	t	0
471	\N	2.1.1	Cuentas y Documentos por Pagar Comerciales	\N	pasivo	7	3	f	t	0
475	\N	2.1.2	Obligaciones con Instituciones Financieras	\N	pasivo	7	3	f	t	0
478	\N	2.1.3	Obligaciones con la Administración Tributaria	\N	pasivo	7	3	f	t	0
483	\N	2.1.4	Obligaciones Laborales y Sociales	\N	pasivo	7	3	f	t	0
493	\N	2.1.5	Otros Pasivos Corrientes	\N	pasivo	7	3	f	t	0
496	\N	2.1.6	Anticipos Recibidos de Clientes	\N	pasivo	7	3	f	t	0
505	\N	2.2.3	Anticipos a Largo Plazo	\N	pasivo	8	3	f	t	0
507	\N	2.2.4	Otras Cuentas por Pagar a Largo Plazo	\N	pasivo	8	3	f	t	0
510	\N	2.2.5	Provisiones a Largo Plazo	\N	pasivo	8	3	f	t	0
515	\N	2.2.6	Pasivos Diferidos	\N	pasivo	8	3	f	t	0
517	\N	3.1.1	Capital	\N	patrimonio	9	3	f	t	0
520	\N	3.1.2	Otros Resultados Integrales Acumulados	\N	patrimonio	9	3	f	t	0
521	\N	3.1.2.01	Superávit por Revaluación de PP&E	\N	patrimonio	520	4	t	t	0
522	\N	3.1.2.02	Ganancias y Pérdidas Actuariales	\N	patrimonio	520	4	t	t	0
524	\N	3.1.3	Resultados Acumulados	\N	patrimonio	9	3	f	t	0
526	\N	3.1.3.02	(-) Pérdidas Acumuladas	\N	patrimonio	524	4	t	t	0
527	\N	3.1.4	Resultados del Periodo	\N	patrimonio	9	3	f	t	0
528	\N	3.1.4.01	Utilidad del Periodo	\N	patrimonio	527	4	t	t	0
529	\N	3.1.4.02	(-) Pérdida del Periodo	\N	patrimonio	527	4	t	t	0
456	\N	1.2.1.11	(-) Deterioro Acumulado PPE	\N	activo	439	4	t	t	0
458	\N	1.2.2.1	Terrenos (Inversion)	\N	activo	457	4	t	t	0
459	\N	1.2.2.2	Edificios (Inversion)	\N	activo	457	4	t	t	0
460	\N	1.2.2.3	(-) Depreciacion Acumulada Inv.	\N	activo	457	4	t	t	0
462	\N	1.2.3.1	Software y Licencias	\N	activo	461	4	t	t	0
463	\N	1.2.3.2	Marcas y Patentes	\N	activo	461	4	t	t	0
466	\N	1.2.4.1	Activo por Impuesto Diferido	\N	activo	465	4	t	t	0
467	\N	1.2.5	Activos Financieros a Largo Plazo	\N	activo	6	3	f	t	0
468	\N	1.2.5.1	Cuentas y Documentos por Cobrar LP	\N	activo	467	4	t	t	0
469	\N	1.2.5.2	Inversiones a Costo Amortizado LP	\N	activo	467	4	t	t	0
470	\N	1.2.5.3	(-) Provision Cuentas Incobrables LP	\N	activo	467	4	t	t	0
472	\N	2.1.1.1	Proveedores Locales	\N	pasivo	471	4	t	t	0
473	\N	2.1.1.2	Proveedores del Exterior	\N	pasivo	471	4	t	t	0
476	\N	2.1.2.1	Prestamos Bancarios a Corto Plazo	\N	pasivo	475	4	t	t	0
477	\N	2.1.2.2	Tarjetas de Credito Corporativas	\N	pasivo	475	4	t	t	0
479	\N	2.1.3.1	Retenciones en la Fuente de IR por Pagar	\N	pasivo	478	4	t	t	0
480	\N	2.1.3.2	Retenciones de IVA por Pagar	\N	pasivo	478	4	t	t	0
481	\N	2.1.3.3	Impuesto a la Renta por Pagar	\N	pasivo	478	4	t	t	0
484	\N	2.1.4.1	Nomina por Pagar	\N	pasivo	483	4	t	t	0
485	\N	2.1.4.2	Obligaciones IESS Aporte Patronal 11.15%	\N	pasivo	483	4	t	t	0
486	\N	2.1.4.3	Obligaciones IESS Aporte Personal 9.45%	\N	pasivo	483	4	t	t	0
487	\N	2.1.4.4	Obligaciones IESS Prestamos	\N	pasivo	483	4	t	t	0
488	\N	2.1.4.5	Decimo Tercer Sueldo por Pagar	\N	pasivo	483	4	t	t	0
489	\N	2.1.4.6	Decimo Cuarto Sueldo por Pagar	\N	pasivo	483	4	t	t	0
490	\N	2.1.4.7	Vacaciones por Pagar	\N	pasivo	483	4	t	t	0
492	\N	2.1.4.9	Utilidades a Trabajadores 15% por Pagar	\N	pasivo	483	4	t	t	0
494	\N	2.1.5.1	Prestamos de Accionistas CP	\N	pasivo	493	4	t	t	0
495	\N	2.1.5.2	Dividendos por Pagar	\N	pasivo	493	4	t	t	0
497	\N	2.1.6.1	Porcion Corriente de Obligaciones LP	\N	pasivo	496	4	t	t	0
498	\N	2.1.6.2	Provisiones a Corto Plazo	\N	pasivo	496	4	t	t	0
500	\N	2.2.1.1	Proveedores Locales LP	\N	pasivo	499	4	t	t	0
501	\N	2.2.1.2	Proveedores del Exterior LP	\N	pasivo	499	4	t	t	0
502	\N	2.2.1.3	Documentos / Letras por Pagar LP	\N	pasivo	499	4	t	t	0
504	\N	2.2.2.1	Prestamos Bancarios LP	\N	pasivo	503	4	t	t	0
506	\N	2.2.3.1	Anticipos de Clientes LP	\N	pasivo	505	4	t	t	0
509	\N	2.2.4.2	Pasivos por Arrendamientos LP	\N	pasivo	507	4	t	t	0
511	\N	2.2.5.1	Provision Jubilacion Patronal	\N	pasivo	510	4	t	t	0
512	\N	2.2.5.2	Provision para Desahucio	\N	pasivo	510	4	t	t	0
513	\N	2.2.5.3	Provision por Garantias de Productos	\N	pasivo	510	4	t	t	0
514	\N	2.2.5.4	Provision por Litigios y Demandas	\N	pasivo	510	4	t	t	0
516	\N	2.2.6.1	Pasivo por Impuesto Diferido	\N	pasivo	515	4	t	t	0
519	\N	3.1.1.02	(-) Capital Suscrito No Pagado	\N	patrimonio	517	4	t	t	0
523	\N	3.1.2.03	Reservas Estatutarias	\N	patrimonio	520	4	t	t	0
411	\N	1.1.1	Efectivo y Equivalentes al Efectivo	\N	activo	403	3	f	t	0
417	\N	1.1.2	Inversiones Financieras a Corto Plazo	\N	activo	403	3	f	t	0
599	\N	1.1.3.01	Clientes Locales	\N	activo	419	4	t	t	0
454	\N	1.2.1.10.5	(-) Dep. Acum. Equipos de Computación	\N	activo	449	5	t	t	0
7	\N	2.1	Pasivo Corriente	\N	pasivo	1	2	f	t	0
499	\N	2.2.1	Cuentas y Documentos por Pagar a Largo Plazo	\N	pasivo	8	3	f	t	0
518	\N	3.1.1.01	Capital del propietario	\N	patrimonio	517	4	t	t	0
525	\N	3.1.3.01	Ganancias Acumuladas	\N	patrimonio	524	4	t	t	0
531	\N	3.1.5	Aportes de Socios o Accionistas	\N	patrimonio	9	3	f	t	0
534	\N	3.1.6	Retiros personales y/o gastos familiares	\N	patrimonio	9	3	t	t	0
536	\N	4.1.1	Venta de Bienes	\N	ingreso	15	3	f	t	0
537	\N	4.1.1.01	Venta de Mercaderías — Mercado Local	\N	ingreso	536	4	t	t	0
538	\N	4.1.1.02	Venta de Mercaderías al Exterior	\N	ingreso	536	4	t	t	0
539	\N	4.1.2	Prestación de Servicios	\N	ingreso	15	3	f	t	0
540	\N	4.1.2.01	Ingresos por Servicios Técnicos y Mantenimiento	\N	ingreso	539	4	t	t	0
542	\N	4.1.3	(-) Descuentos, Devoluciones y Rebajas en Ventas	\N	ingreso	15	3	f	t	0
543	\N	4.1.3.01	(-) Devoluciones en Ventas	\N	ingreso	542	4	t	t	0
544	\N	4.1.3.02	(-) Descuentos y Rebajas en Ventas	\N	ingreso	542	4	t	t	0
545	\N	4.2	Otros Ingresos No Operacionales	\N	ingreso	3	2	f	t	0
547	\N	4.2.1.01	Intereses y Rendimientos Financieros Ganados	\N	ingreso	546	4	t	t	0
549	\N	4.2.1.03	Ingresos por Arrendamientos	\N	ingreso	546	4	t	t	0
555	\N	5.2	Gastos Operativos	\N	gasto	4	2	f	t	0
556	\N	5.2.1	Gastos de Personal	\N	gasto	555	3	f	t	0
557	\N	5.2.1.01	Sueldos, Salarios y Horas Extras	\N	gasto	556	4	t	t	0
558	\N	5.2.1.02	Comisiones y Bonos	\N	gasto	556	4	t	t	0
559	\N	5.2.1.03	Aporte Patronal IESS (11.15%)	\N	gasto	556	4	t	t	0
560	\N	5.2.1.04	Décimo Tercer Sueldo	\N	gasto	556	4	t	t	0
561	\N	5.2.1.05	Décimo Cuarto Sueldo	\N	gasto	556	4	t	t	0
563	\N	5.2.1.07	Fondos de Reserva	\N	gasto	556	4	t	t	0
564	\N	5.2.1.08	Capacitación al Personal	\N	gasto	556	4	t	t	0
565	\N	5.2.1.09	Uniformes y Equipos de Protección Personal	\N	gasto	556	4	t	t	0
567	\N	5.2.2	Gastos Generales y Administrativos	\N	gasto	555	3	f	t	0
568	\N	5.2.2.01	Honorarios Profesionales y Asesorías	\N	gasto	567	4	t	t	2
569	\N	5.2.2.02	Arrendamientos de Locales y Bodegas	\N	gasto	567	4	t	t	0
571	\N	5.2.2.04	Mantenimiento y Reparación de Instalaciones	\N	gasto	567	4	t	t	0
572	\N	5.2.2.05	Mantenimiento y Reparación de Vehículos	\N	gasto	567	4	t	t	0
573	\N	5.2.2.06	Suministros y Materiales de Oficina	\N	gasto	567	4	t	t	0
575	\N	5.2.2.08	Combustibles y Lubricantes	\N	gasto	567	4	t	t	0
576	\N	5.2.2.09	Seguros de Vehículos y Mercadería	\N	gasto	567	4	t	t	0
578	\N	5.2.2.11	Publicidad, Marketing y Redes Sociales	\N	gasto	567	4	t	t	0
579	\N	5.2.3	Gastos de Depreciación y Amortización	\N	gasto	555	3	f	t	0
580	\N	5.2.3.01	Depreciación de Propiedades, Planta y Equipo	\N	gasto	579	4	t	t	0
581	\N	5.2.3.02	Amortización de Activos Intangibles	\N	gasto	579	4	t	t	0
582	\N	5.2.4	Gastos por Provisiones	\N	gasto	555	3	f	t	0
583	\N	5.2.4.01	Gasto por Cuentas Incobrables	\N	gasto	582	4	t	t	0
585	\N	5.3	Gastos Financieros	\N	gasto	4	2	f	t	0
586	\N	5.3.1.01	Intereses por Préstamos Bancarios	\N	gasto	\N	4	t	t	0
588	\N	5.3.1.03	Pérdida por Diferencia en Cambio	\N	gasto	\N	4	t	t	0
589	\N	5.4	Otros Gastos y No Deducibles	\N	gasto	4	2	f	t	0
590	\N	5.4.1.01	Gastos No Deducibles	\N	gasto	\N	4	t	t	0
591	\N	5.4.1.02	Pérdida en Venta de Activos Fijos	\N	gasto	\N	4	t	t	0
592	\N	5.4.1.03	Otros Gastos Extraordinarios	\N	gasto	\N	4	t	t	0
427	\N	1.1.4.2	Inventario de Materia Prima y Suministros	\N	activo	425	4	t	t	0
446	\N	1.2.1.7	Vehiculos y Equipos de Transporte	\N	activo	439	4	t	t	0
448	\N	1.2.1.9	Construcciones en Curso	\N	activo	439	4	t	t	0
464	\N	1.2.3.3	(-) Amortizacion Acumulada Intangibles	\N	activo	461	4	t	t	0
474	\N	2.1.1.3	Anticipos de Clientes	\N	pasivo	471	4	t	t	0
482	\N	2.1.3.4	IVA Ventas por Pagar	\N	pasivo	478	4	t	t	0
491	\N	2.1.4.8	Fondos de Reserva por Pagar	\N	pasivo	483	4	t	t	0
508	\N	2.2.4.1	Prestamos de Accionistas LP	\N	pasivo	507	4	t	t	0
530	\N	3.1.4.03	Resultados Acumulados NIIF	\N	patrimonio	527	4	t	t	0
533	\N	3.1.5.02	(-) Perdida del Periodo	\N	patrimonio	531	4	t	t	0
535	\N	3.1.6.01	Aportes para Futuras Capitalizaciones	\N	patrimonio	534	4	t	t	0
546	\N	4.2.1	Otros Ingresos	\N	ingreso	545	3	f	t	0
550	\N	4.2.1.09	Otros Ingresos Varios	\N	ingreso	546	4	t	t	0
552	\N	5.1.1.2	Costo de Ventas Mercancias Importadas	\N	gasto	17	4	t	t	0
553	\N	5.1.1.3	Costo de Prestacion de Servicios	\N	gasto	17	4	t	t	0
554	\N	5.1.1.4	Ajustes por Faltantes o Mermas	\N	gasto	17	4	t	t	0
566	\N	5.2.1.10	Uniformes y Equipos de Proteccion	\N	gasto	556	4	t	t	0
584	\N	5.2.4.02	Gasto Provision Jubilacion y Desahucio	\N	gasto	582	4	t	t	0
551	\N	5.1.1.1	Costo de Ventas Mercancias Locales	\N	gasto	17	4	t	t	0
600	\N	1.1.3.02	Clientes del Exterior	\N	activo	419	4	t	t	0
601	\N	1.1.3.03	Anticipos a Proveedores	\N	activo	419	4	t	t	0
602	\N	1.1.3.04	Préstamos y Anticipos a Empleados	\N	activo	419	4	t	t	0
603	\N	1.1.3.05	(-) Provisión Cuentas Incobrables	\N	activo	419	4	t	t	0
605	\N	1.1.4.02	Inventario de Materia Prima y Suministros	\N	activo	425	4	t	t	0
606	\N	1.1.4.03	Inventario en Tránsito — Importaciones en Curso	\N	activo	425	4	t	t	0
607	\N	1.1.4.04	(-) Provisión por Deterioro de Inventarios	\N	activo	425	4	t	t	0
609	\N	1.1.5.02	Crédito Tributario por Retenciones de IVA	\N	activo	430	4	t	t	0
610	\N	1.1.5.03	Crédito Tributario por Retenciones de IR	\N	activo	430	4	t	t	0
611	\N	1.1.5.04	Anticipo de Impuesto a la Renta	\N	activo	430	4	t	t	0
612	\N	1.1.5.05	IVA en Compras Pendiente de Clasificar	\N	activo	430	4	t	t	0
613	\N	1.1.6.01	Seguros Pagados por Anticipado	\N	activo	435	4	t	t	0
614	\N	1.1.6.02	Arriendos Pagados por Anticipado	\N	activo	435	4	t	t	0
438	\N	1.1.7	Otros Activos Corrientes	\N	activo	403	3	t	t	0
615	\N	1.2.1.01	Terrenos	\N	activo	439	4	t	t	0
616	\N	1.2.1.02	Edificios	\N	activo	439	4	t	t	0
617	\N	1.2.1.03	Instalaciones	\N	activo	439	4	t	t	0
618	\N	1.2.1.04	Muebles y Enseres	\N	activo	439	4	t	t	0
619	\N	1.2.1.05	Maquinaria y Equipos	\N	activo	439	4	t	t	0
620	\N	1.2.1.06	Equipos de Computación	\N	activo	439	4	t	t	0
621	\N	1.2.1.07	Vehículos y Equipos de Transporte	\N	activo	439	4	t	t	0
622	\N	1.2.1.08	Repuestos y Herramientas de Uso Prolongado	\N	activo	439	4	t	t	0
623	\N	1.2.1.09	Construcciones en Curso	\N	activo	439	4	t	t	0
449	\N	1.2.1.10	(-) Depreciación Acumulada PP&E	\N	activo	439	4	f	t	0
624	\N	1.2.2.01	Terrenos de Inversión	\N	activo	457	4	t	t	0
625	\N	1.2.2.02	Edificios de Inversión	\N	activo	457	4	t	t	0
626	\N	1.2.2.03	(-) Depreciación Acumulada Propiedades de Inversión	\N	activo	457	4	t	t	0
627	\N	1.2.3.01	Software y Licencias	\N	activo	461	4	t	t	0
628	\N	1.2.3.02	Marcas y Patentes	\N	activo	461	4	t	t	0
629	\N	1.2.3.03	(-) Amortización Acumulada de Intangibles	\N	activo	461	4	t	t	0
630	\N	1.2.4.01	Cuentas y Documentos por Cobrar a Largo Plazo	\N	activo	465	4	t	t	0
631	\N	1.2.4.02	Inversiones a Costo Amortizado a Largo Plazo	\N	activo	465	4	t	t	0
632	\N	1.2.4.03	(-) Provisión Cuentas Incobrables a Largo Plazo	\N	activo	465	4	t	t	0
634	\N	2.1.1.02	Proveedores del Exterior	\N	pasivo	471	4	t	t	0
635	\N	2.1.2.01	Préstamos Bancarios a Corto Plazo	\N	pasivo	475	4	t	t	0
636	\N	2.1.2.02	Tarjetas de Crédito Corporativas	\N	pasivo	475	4	t	t	0
637	\N	2.1.3.01	Retenciones en la Fuente de IR por Pagar	\N	pasivo	478	4	t	t	0
638	\N	2.1.3.02	Retenciones de IVA por Pagar	\N	pasivo	478	4	t	t	0
639	\N	2.1.3.03	Impuesto a la Renta por Pagar del Ejercicio	\N	pasivo	478	4	t	t	0
640	\N	2.1.3.04	IVA en Ventas por Liquidar al SRI	\N	pasivo	478	4	t	t	0
641	\N	2.1.3.05	IVA Retenido por Liquidar al SRI	\N	pasivo	478	4	t	t	0
642	\N	2.1.4.01	Nómina por Pagar	\N	pasivo	483	4	t	t	0
643	\N	2.1.4.02	Obligaciones con el IESS — Aporte Patronal (11.15%)	\N	pasivo	483	4	t	t	0
644	\N	2.1.4.03	Obligaciones con el IESS — Aporte Personal (9.45%)	\N	pasivo	483	4	t	t	0
645	\N	2.1.4.04	Descuentos IESS — Préstamos Quirografarios e Hipotecarios	\N	pasivo	483	4	t	t	0
646	\N	2.1.4.05	Décimo Tercer Sueldo por Pagar	\N	pasivo	483	4	t	t	0
647	\N	2.1.4.06	Décimo Cuarto Sueldo por Pagar	\N	pasivo	483	4	t	t	0
648	\N	2.1.4.07	Vacaciones por Pagar	\N	pasivo	483	4	t	t	0
649	\N	2.1.4.08	Fondos de Reserva por Pagar	\N	pasivo	483	4	t	t	0
650	\N	2.1.4.09	Participación Trabajadores en Utilidades por Pagar	\N	pasivo	483	4	t	t	0
651	\N	2.1.5.01	Porción Corriente de Obligaciones a Largo Plazo	\N	pasivo	493	4	t	t	0
652	\N	2.1.5.02	Provisiones Diversas a Corto Plazo	\N	pasivo	493	4	t	t	0
653	\N	2.1.6.01	Anticipos de Clientes — Corto Plazo	\N	pasivo	496	4	t	t	0
654	\N	2.2.1.01	Proveedores Locales a Largo Plazo	\N	pasivo	499	4	t	t	0
655	\N	2.2.1.02	Proveedores del Exterior a Largo Plazo	\N	pasivo	499	4	t	t	0
656	\N	2.2.1.03	Documentos y Letras por Pagar a Largo Plazo	\N	pasivo	499	4	t	t	0
503	\N	2.2.2	Obligaciones Financieras a Largo Plazo	\N	pasivo	8	3	f	t	0
657	\N	2.2.2.01	Préstamos Bancarios a Largo Plazo	\N	pasivo	503	4	t	t	0
658	\N	2.2.3.01	Anticipos de Clientes a Largo Plazo	\N	pasivo	505	4	t	t	0
659	\N	2.2.4.01	Préstamos de Accionistas a Largo Plazo	\N	pasivo	507	4	t	t	0
660	\N	2.2.5.01	Provisión por Garantías de Productos	\N	pasivo	510	4	t	t	0
661	\N	2.2.5.02	Provisión por Litigios y Demandas	\N	pasivo	510	4	t	t	0
662	\N	2.2.6.01	Pasivos Diferidos	\N	pasivo	515	4	t	t	0
562	\N	5.2.1.06	Vacaciones	\N	gasto	556	4	t	t	0
570	\N	5.2.2.03	Servicios Básicos	\N	gasto	567	4	t	t	0
587	\N	5.3.1.02	Comisiones Bancarias y Pasarelas de Pago	\N	gasto	\N	4	t	t	0
604	\N	1.1.4.01	Inventario de Mercaderías	\N	activo	425	4	t	t	10
608	\N	1.1.5.01	Crédito Tributario IVA Compras	\N	activo	430	4	t	t	12
532	\N	3.1.5.01	Aportes para Futuras Capitalizaciones	\N	patrimonio	531	4	t	t	0
541	\N	4.1.2.02	Ingresos por Servicios de Capacitación y Asesorías	\N	ingreso	539	4	t	t	0
663	\N	4.1.2.03	Ingresos por Alquiler de Equipos	\N	ingreso	539	4	t	t	0
548	\N	4.2.1.02	Ganancia en Venta de Propiedades, Planta y Equipo	\N	ingreso	546	4	t	t	0
664	\N	4.2.1.04	Otros Ingresos No Operacionales	\N	ingreso	546	4	t	t	0
665	\N	5.1.1	Costo de Ventas	\N	gasto	17	3	f	t	0
666	\N	5.1.1.01	Costo de Ventas de Mercaderías Locales	\N	gasto	665	4	t	t	0
667	\N	5.1.1.02	Costo de Ventas de Mercaderías Importadas	\N	gasto	665	4	t	t	0
668	\N	5.1.1.03	Costo de Prestación de Servicios Técnicos	\N	gasto	665	4	t	t	0
669	\N	5.1.1.04	Ajustes por Faltantes o Mermas de Inventario	\N	gasto	665	4	t	t	0
574	\N	5.2.2.07	Gastos de Viaje, Movilización y Viáticos	\N	gasto	567	4	t	t	0
577	\N	5.2.2.10	Impuestos, Tasas y Contribuciones Locales	\N	gasto	567	4	t	t	0
670	\N	5.2.2.12	Gastos de Representación y Atención a Clientes	\N	gasto	567	4	t	t	0
671	\N	5.2.2.13	Suscripciones y Membresías	\N	gasto	567	4	t	t	0
672	\N	5.2.2.14	Correo, Mensajería y Envíos	\N	gasto	567	4	t	t	0
595	\N	1.1.1.03	Bancos Locales	\N	activo	411	4	t	t	9
633	\N	2.1.1.01	Proveedores Locales	\N	pasivo	471	4	t	t	21
\.


--
-- Data for Name: prefactura_abonos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.prefactura_abonos (id, prefactura_id, fecha, valor, forma_pago, banco, num_comprobante, asiento_id, usuario_id, created_at) FROM stdin;
\.


--
-- Data for Name: prefactura_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.prefactura_detalles (id, prefactura_id, producto_id, descripcion, cantidad, precio_unitario, total) FROM stdin;
\.


--
-- Data for Name: prefacturas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.prefacturas (id, empresa_id, centro_costo_id, cliente_id, usuario_id, numero, fecha_emision, total, total_abonado, saldo_pendiente, asiento_id, factura_id, observaciones, estado, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: prestamos_empleados; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.prestamos_empleados (id, colaborador_id, tipo, monto_total, saldo, cuota, fecha, descripcion, estado, created_by, created_at) FROM stdin;
\.


--
-- Data for Name: presupuestos_metas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.presupuestos_metas (id, empresa_id, centro_costo_id, mes, anio, meta_ventas, meta_cobros, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: producto_series; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.producto_series (id, producto_id, bodega_id, numero_serie, estado, doc_entrada_tipo, doc_entrada_id, doc_salida_tipo, doc_salida_id, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.productos (id, empresa_id, marca_id, categoria_id, bodega_default_id, codigo, nombre, descripcion, tipo, unidad, requiere_serie, pvp, pvd, costo, descuento_maximo, porcentaje_iva, porcentaje_ice, stock_minimo, stock_maximo, cuenta_inventario_id, cuenta_costo_id, cuenta_ventas_id, estado, observaciones, created_at, updated_at, deleted_at, tiene_ice, codigo_externo, ref_importacion, cuenta_inventario, cuenta_costo_ventas, cuenta_ventas) FROM stdin;
4	1	11	8	\N	CON-002	Consola Allen & Heath SQ-5 48 Canales Digital	\N	producto	unidad	t	3950.0000	3500.0000	2800.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
7	1	9	10	\N	PAR-001	Parlante JBL SRX835P 15" Activo 2000W	\N	producto	unidad	t	1750.0000	1550.0000	1200.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
8	1	9	10	\N	PAR-002	Subwoofer JBL SRX818SP 18" Activo 1000W	\N	producto	unidad	t	1450.0000	1280.0000	980.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
9	1	3	11	\N	DJ-001	Controlador Pioneer DDJ-FLX6 4 Decks	\N	producto	unidad	t	950.0000	820.0000	650.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
10	1	3	11	\N	DJ-002	Tornamesa Pioneer PLX-1000 Direct Drive	\N	producto	unidad	t	720.0000	640.0000	480.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
11	1	8	12	\N	PRO-001	Procesador de Señal DBX DriveRack PA2	\N	producto	unidad	f	340.0000	300.0000	220.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
12	1	5	14	\N	ILU-001	Cabeza Móvil Chauvet Intimidator Spot 375Z IRC	\N	producto	unidad	t	980.0000	860.0000	680.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
13	1	6	14	\N	ILU-002	Cabeza Móvil Martin MAC Aura XB LED	\N	producto	unidad	t	3100.0000	2750.0000	2200.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
14	1	5	16	\N	ILU-003	Controlador DMX Chauvet Obey 40 32 Canales	\N	producto	unidad	f	130.0000	115.0000	85.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
15	1	5	17	\N	ILU-004	Efecto LED ADJ Mega Bar 50RGB RC	\N	producto	unidad	f	180.0000	160.0000	120.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
19	1	12	18	\N	REP-001	Transistor de Potencia IRFP250 MOSFET	\N	repuesto	unidad	f	6.0000	5.0000	2.5000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
20	1	12	20	\N	REP-002	Conector XLR 3P Macho Neutrik NC3MXX	\N	repuesto	unidad	f	4.5000	3.5000	1.8000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
21	1	12	18	\N	REP-003	Soldadura de Estaño 60/40 Rollo 250g	\N	insumo	rollo	f	15.0000	12.0000	8.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
22	1	12	6	\N	SRV-001	Servicio de Reparación Electrónica — Hora	\N	servicio	hora	f	45.0000	40.0000	0.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
23	1	12	6	\N	SRV-002	Alquiler Sistema de Sonido Completo — Día	\N	servicio	dia	f	350.0000	300.0000	0.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
24	1	12	6	\N	SRV-003	Instalación y Configuración de Equipos	\N	servicio	servicio	f	120.0000	100.0000	0.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-11 05:42:58	\N	f	\N	\N	\N	\N	\N
1	1	1	7	\N	MIC-001	Micrófono Inalámbrico Shure BLX24/SM58	\N	producto	unidad	f	480.0000	420.0000	501.6667	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 12:56:30	\N	f	\N	\N	\N	\N	\N
2	1	1	7	\N	MIC-002	Micrófono Condensador Shure SM7B	\N	producto	unidad	f	420.0000	370.0000	501.6667	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 12:56:30	\N	f	\N	\N	\N	\N	\N
3	1	2	8	\N	CON-001	Consola Yamaha MG16XU 16 Canales USB	\N	producto	unidad	t	980.0000	850.0000	501.6667	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 12:56:30	\N	f	\N	\N	\N	\N	\N
5	1	4	9	\N	AMP-001	Amplificador QSC GX5 500W Potencia	\N	producto	unidad	t	620.0000	550.0000	420.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 17:05:03	\N	f	\N	\N	\N	\N	\N
17	1	12	13	\N	CAB-002	Cable Speakon 4P 10 metros	\N	producto	unidad	f	28.0000	23.0000	15.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 17:05:03	\N	f	\N	\N	\N	\N	\N
16	1	12	13	\N	CAB-001	Cable XLR Macho-Hembra 10 metros Neutrik	\N	producto	unidad	f	22.0000	18.0000	12.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 23:31:41	\N	f	\N	\N	\N	\N	\N
51	1	1	7	\N	SHR-001	Micrófono Dinámico Shure SM58-LC	\N	producto	UND	f	165.0000	135.0000	106.3900	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
52	1	1	7	\N	SHR-002	Micrófono Instrumental Shure SM57-LC	\N	producto	UND	f	148.0000	120.0000	96.3900	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
53	1	1	7	\N	SHR-003	Sistema Inalámbrico Shure BLX288/PG58	\N	producto	UND	f	360.0000	290.0000	227.3900	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
6	1	10	9	\N	AMP-002	Amplificador Crown XTi 2002 650W	\N	producto	unidad	t	850.0000	750.0000	580.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 23:46:26	\N	f	\N	\N	\N	\N	\N
18	1	12	19	\N	CAB-003	Cable de Poder Uso Rudo 3x14 AWG 5m	\N	producto	unidad	f	15.0000	12.0000	8.0000	0.00	15.00	0.00	2.0000	\N	\N	\N	\N	t	\N	2026-06-11 05:42:58	2026-06-23 23:46:26	\N	f	\N	\N	\N	\N	\N
54	1	2	8	\N	YAM-001	Consola de Mezcla Yamaha MG20XU 20 Canales	\N	producto	UND	f	680.0000	550.0000	420.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
55	1	2	10	\N	YAM-002	Monitor de Estudio Yamaha HS8 8"	\N	producto	UND	f	620.0000	500.0000	380.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
56	1	2	12	\N	YAM-003	Procesador Digital Yamaha SPX2000	\N	producto	UND	f	1050.0000	850.0000	650.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
57	1	2	9	\N	YAM-004	Amplificador de Potencia Yamaha P7000S	\N	producto	UND	f	1250.0000	1020.0000	780.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
58	1	5	14	\N	CHV-001	Cabeza Móvil Chauvet Pro Rogue R3 Wash	\N	producto	UND	f	1350.0000	1100.0000	850.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
59	1	5	16	\N	CHV-002	Controlador DMX Chauvet Obey 70	\N	producto	UND	f	280.0000	230.0000	180.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
60	1	5	17	\N	CHV-003	Par LED Chauvet SlimPAR Pro RGBA IP	\N	producto	UND	f	195.0000	160.0000	120.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
61	1	5	17	\N	CHV-004	Máquina de Humo Chauvet Nimbus Dry Ice	\N	producto	UND	f	450.0000	370.0000	290.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
62	1	3	11	\N	DJ-003	Auriculares DJ Pioneer HDJ-X5 Negro	\N	producto	UND	f	145.0000	115.0000	85.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
63	1	12	13	\N	CAB-010	Cable XLR Balanceado 10m Canare L-4E6S	\N	producto	UND	f	32.0000	26.0000	18.0000	10.00	15.00	0.00	0.0000	0.0000	\N	\N	\N	t	\N	2026-06-23 17:42:17	2026-06-23 17:42:26	\N	f	\N	\N	\N	\N	\N
\.


--
-- Data for Name: proforma_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proforma_detalles (id, proforma_id, producto_id, descripcion, cantidad, precio_unitario, descuento_pct, subtotal, porcentaje_iva, total) FROM stdin;
\.


--
-- Data for Name: proformas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proformas (id, empresa_id, centro_costo_id, cliente_id, usuario_id, numero, fecha_emision, fecha_vencimiento, subtotal, descuento_total, total_iva, total, observaciones, factura_id, estado, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: proveedores; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.proveedores (id, empresa_id, tipo, tipo_identificacion, identificacion, razon_social, nombre_comercial, email, telefono, direccion, ciudad, pais, divisa, tiene_credito, dias_credito, estado, created_at, updated_at) FROM stdin;
1	1	nacional	RUC	1234567890001	SD_compsny	Juan	grt@sd.com	0987634567	Caupicho	Quito	Ecuador	USD	t	30	t	2026-06-01 20:50:52	2026-06-01 21:02:00
2	1	nacional	04	0990012345001	TECNOLOGÍA Y SONIDO S.A.	TecnoSound	ventas@tecnosound.com	042-123456	\N	Guayaquil	ECUADOR	USD	t	30	t	2026-06-02 02:47:39	2026-06-02 02:47:39
3	1	nacional	04	1790045678001	DISTRIBUIDORA MUSICAL DEL ECUADOR CIA. LTDA.	DisMusicEC	info@dismusicec.com	02-345-6789	\N	Quito	ECUADOR	USD	t	15	t	2026-06-02 02:47:39	2026-06-02 02:47:39
4	1	nacional	04	0991234567001	CABLES Y ACCESORIOS DEL ECUADOR	CablesEC	ventas@cablesec.com	04-567-8901	\N	Guayaquil	ECUADOR	USD	t	45	t	2026-06-02 02:47:39	2026-06-02 02:47:39
5	1	nacional	04	1791234567001	SERVICIOS LOGÍSTICOS ANDINOS S.A.	LogiAndina	logistica@logiandina.com	02-111-2222	\N	Quito	ECUADOR	USD	t	30	t	2026-06-02 02:47:39	2026-06-02 02:47:39
6	1	internacional	08	CHN-SHURE-001	SHURE INCORPORATED	Shure Inc.	orders@shure.com	+1-800-025-5679	\N	Niles, Illinois	ESTADOS UNIDOS	USD	t	60	t	2026-06-02 02:47:39	2026-06-02 02:47:39
7	1	internacional	08	CHN-YAMAHA-001	YAMAHA CORPORATION	Yamaha Corp.	export@yamaha.com	+81-3-5488-6600	\N	Hamamatsu	JAPÓN	USD	t	90	t	2026-06-02 02:47:39	2026-06-02 02:47:39
8	1	internacional	08	CHN-CHAUVET-001	CHAUVET PROFESSIONAL LLC	Chauvet Pro	sales@chauvetprofessional.com	\N	\N	Sunrise, Florida	ESTADOS UNIDOS	USD	t	60	t	2026-06-02 02:47:39	2026-06-02 02:47:39
9	1	nacional	04	1790012345001	Distribuidora Nacional de Audio S.A.	\N	ventas@distnaudio.ec	022901234	\N	Quito	ECUADOR	USD	t	45	t	2026-06-09 23:18:44	2026-06-09 23:18:44
10	1	nacional	04	0990123456001	TecnoImport Ecuador Cía. Ltda.	\N	compras@tecnoimport.ec	042901234	\N	Guayaquil	ECUADOR	USD	f	0	t	2026-06-09 23:18:44	2026-06-09 23:18:44
11	2	internacional	04	CN-GZ-001	Guangzhou Audio Equipment Co. Ltd.	\N	export@gzaudio.cn	+86 20 8888 9999	\N	Guangzhou	China	CNY	t	60	t	2026-06-09 23:18:44	2026-06-09 23:18:44
12	2	internacional	04	EIN-45-678901	ProSound USA Inc.	\N	sales@prosoundusa.com	+1 305 555 0100	\N	Miami	Estados Unidos	USD	t	30	t	2026-06-09 23:18:44	2026-06-09 23:18:44
14	1	internacional	08	JPN-PIONEER-001	PIONEER DJ CORPORATION	\N	\N	\N	\N	\N	JAPÓN	USD	f	0	t	2026-06-23 17:42:17	2026-06-23 17:42:17
\.


--
-- Data for Name: puestos_trabajo; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.puestos_trabajo (id, empresa_id, nombre, cargo, departamento, estado) FROM stdin;
\.


--
-- Data for Name: recepcion_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.recepcion_detalles (id, recepcion_id, compra_detalle_id, producto_id, cantidad_esperada, cantidad_recibida, estado) FROM stdin;
1	1	26	5	10.0000	0.0000	pendiente
2	1	27	18	17.0000	0.0000	pendiente
3	2	21	17	30.0000	0.0000	pendiente
6	4	70	6	1.0000	1.0000	completado
7	5	71	16	1.0000	1.0000	completado
8	6	72	15	1.0000	0.0000	pendiente
9	7	73	59	2.0000	0.0000	pendiente
10	7	74	18	2.0000	0.0000	pendiente
4	3	68	6	2.0000	2.0000	completado
5	3	69	18	2.0000	2.0000	completado
11	8	23	14	190.0000	0.0000	pendiente
\.


--
-- Data for Name: recepcion_escaneos; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.recepcion_escaneos (id, recepcion_id, recepcion_detalle_id, producto_id, codigo_escaneado, correlativo, usuario_id, created_at) FROM stdin;
1	2	3	17	CAB-002-000018	18	1	2026-06-23 12:05:13
2	2	3	17	CAB-002-000034	34	1	2026-06-23 12:05:29
3	4	6	6	AMP-002-000011	11	1	2026-06-23 20:45:41
4	5	7	16	CAB-001-000050	50	1	2026-06-23 23:31:37
5	3	4	6	AMP-002-000012	12	1	2026-06-23 23:45:49
6	3	4	6	AMP-002-000013	13	1	2026-06-23 23:46:05
7	3	5	18	CAB-003-000048	48	1	2026-06-23 23:46:10
8	3	5	18	CAB-003-000049	49	1	2026-06-23 23:46:21
\.


--
-- Data for Name: recepciones_bodega; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.recepciones_bodega (id, empresa_id, compra_id, bodega_id, estado, recibido_por, fecha_recepcion, observacion, created_at, updated_at) FROM stdin;
1	1	15	2	pendiente	\N	\N	\N	2026-06-20 22:50:55	2026-06-20 22:50:55
2	1	10	2	pendiente	\N	\N	\N	2026-06-23 12:02:03	2026-06-23 12:02:03
4	1	29	2	completada	1	2026-06-23	\N	2026-06-23 20:43:48	2026-06-23 20:45:45
5	1	30	2	completada	1	2026-06-23	\N	2026-06-23 23:30:19	2026-06-23 23:31:41
6	1	31	2	pendiente	\N	\N	\N	2026-06-23 23:37:49	2026-06-23 23:37:49
7	1	32	2	pendiente	\N	\N	\N	2026-06-23 23:38:24	2026-06-23 23:38:24
3	1	28	2	completada	1	2026-06-23	\N	2026-06-23 20:43:07	2026-06-23 23:46:26
8	1	12	5	pendiente	\N	\N	\N	2026-06-23 23:47:25	2026-06-23 23:47:25
\.


--
-- Data for Name: retencion_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.retencion_detalles (id, retencion_id, impuesto_id, tipo, codigo, porcentaje, base_imponible, valor_retenido) FROM stdin;
\.


--
-- Data for Name: retenciones; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.retenciones (id, empresa_id, factura_id, compra_id, cliente_id, usuario_id, establecimiento, punto_emision, secuencial, numero_completo, fecha_emision, identificacion, razon_social, num_comp_retenido, total, clave_acceso, autorizacion, estado_sri, xml_doc, asiento_id, estado, created_at) FROM stdin;
\.


--
-- Data for Name: role_has_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.role_has_permissions (permission_id, role_id) FROM stdin;
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, name, guard_name, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: rubros_nomina; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.rubros_nomina (id, codigo, descripcion, grupo, tipo_valor, valor, operacion, cuenta_contable, afecta_iess, afecta_renta, estado) FROM stdin;
\.


--
-- Data for Name: secuenciales; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.secuenciales (id, empresa_id, tipo_documento, establecimiento, punto_emision, siguiente, created_at, updated_at, inicializado_desde_migracion) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
Y6LHBeYVazVLDBuwq3f9brUYdnc8noLHLsbZt14U	1	127.0.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36	YTo1OntzOjY6Il90b2tlbiI7czo0MDoiRFF4bkRPY2ZBaGFiN013WHhHYkNLeVdLNExEV1RVTGF5RWVRZ3B5ZCI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTtzOjE3OiJlbXByZXNhX2FjdGl2YV9pZCI7aToxO3M6NjoiX2ZsYXNoIjthOjI6e3M6MzoibmV3IjthOjA6e31zOjM6Im9sZCI7YTowOnt9fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjU2OiJodHRwOi8vMTI3LjAuMC4xOjgwMDEvY29tcHJhcy9mYWN0dXJhcy8xMi9ldGlxdWV0YXMtZGF0YSI7czo1OiJyb3V0ZSI7czozMToiY29tcHJhcy5mYWN0dXJhcy5ldGlxdWV0YXMtZGF0YSI7fX0=	1782258479
\.


--
-- Data for Name: tipos_aprobacion; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.tipos_aprobacion (id, nombre, clave, descripcion, activo, created_at, updated_at) FROM stdin;
2	Descuento Excedido	descuento_excedido	Descuento mayor al límite permitido	t	\N	\N
3	Precio Bajo Costo	precio_bajo_costo	Precio menor al costo del producto	t	\N	\N
4	Anulación Factura	anulacion_factura	Anulación de factura emitida	t	\N	\N
5	Anulación Nota Crédito	anulacion_nota_credito	Anulación de nota de crédito	t	\N	\N
6	Modificación Precio Base	modificacion_precio_base	Cambio en precio base de producto	t	\N	\N
7	Crédito Excedido	credito_excedido	Cliente supera límite de crédito	t	\N	\N
8	Cierre de Período	cierre_periodo	Cierre de período contable	t	\N	\N
9	Devolución Mercadería	devolucion_mercaderia	Devolución de productos	t	\N	\N
10	Baja de Activo	baja_activo	Dar de baja un activo fijo	t	\N	\N
11	Transferencia Bodega	transferencia_bodega	Transferencia de stock entre bodegas	t	\N	\N
12	Ajuste Inventario	ajuste_inventario	Ajuste manual de inventario	t	\N	\N
13	Modificación Asiento	modificacion_asiento	Modificación de asiento contable aprobado	t	\N	\N
14	Cheque Posfechado	cheque_posfechado	Recepción de pago con cheque posfechado	t	\N	\N
15	Extensión de Plazo	extension_plazo	Extensión de plazo de pago a cliente	t	\N	\N
16	Descuento Compra	descuento_compra	Descuento adicional en orden de compra	t	\N	\N
17	Egreso Caja Mayor	egreso_caja_mayor	Egreso de caja mayor al límite autorizado	t	\N	\N
18	Reactivación OT	reactivacion_ot	Reactivación de orden de trabajo cerrada	t	\N	\N
\.


--
-- Data for Name: transportistas; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.transportistas (id, identificacion, razon_social, placa, email, telefono, direccion, estado, created_at, updated_at) FROM stdin;
1	1790567890001	Servicio de Courier Express S.A.	PBZ-1234	\N	099 123 4567	\N	t	2026-06-09 23:19:11	2026-06-09 23:19:11
2	1792345678001	Transportes Rápidos del Ecuador	QAB-5678	\N	098 765 4321	\N	t	2026-06-09 23:19:11	2026-06-09 23:19:11
\.


--
-- Data for Name: traslado_detalles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.traslado_detalles (id, traslado_id, producto_id, numero_serie, cantidad_enviada, cantidad_recibida) FROM stdin;
\.


--
-- Data for Name: traslado_items; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.traslado_items (id, traslado_id, producto_id, cantidad_enviada, cantidad_recibida, notas, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: traslados; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.traslados (id, empresa_id, bodega_origen_id, bodega_destino_id, estado, usuario_origen_id, usuario_destino_id, fecha_traslado, fecha_confirmacion, notas_origen, notas_destino, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: traslados_bodega; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.traslados_bodega (id, empresa_id, bodega_origen_id, bodega_destino_id, numero, fecha, estado, enviado_por, recibido_por, fecha_recepcion, observacion, created_at) FROM stdin;
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.usuarios (id, empresa_id, perfil_id, centro_costo_id, nombre, email, username, telefono, password, codigo_aprobacion, avatar, estado, email_verified_at, remember_token, ultimo_acceso, created_at, updated_at, deleted_at) FROM stdin;
2	1	4	\N	Vendedor Prueba	vendedor@altamira.com	vendedor	\N	$2y$12$Pequ7VaO/DhPL0GAkrGKvuLdj0ypz1vQZTTOCCzukYTNSBrkfuwrS	\N	\N	t	\N	\N	\N	2026-05-31 01:31:27	2026-05-31 01:31:27	\N
1	1	1	\N	Administrador Sistema	admin@altamira.com	admin	\N	$2y$12$NDPeRFF.89x.YWh6Bl9/0OoPQxaOgncvxcHeGvRIPzEIlrIfzdrPa	$2y$12$cyFrCMceWu1YKl2rz0Ykre5FqdNBY5wH3Ob.gVCSZOkF2ThE/AxOO	\N	t	\N	o7szcSzzGFihnwQDtGVMyOOvlZMSSk33lUC00SFI9A1Q7Mdu7SuJCS8o3wdC	\N	2026-05-31 01:31:26	2026-05-31 01:31:26	\N
\.


--
-- Name: activos_depreciaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.activos_depreciaciones_id_seq', 1, false);


--
-- Name: activos_fijos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.activos_fijos_id_seq', 1, false);


--
-- Name: anticipos_proveedores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.anticipos_proveedores_id_seq', 8, true);


--
-- Name: aprobaciones_especiales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.aprobaciones_especiales_id_seq', 1, false);


--
-- Name: asiento_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.asiento_detalles_id_seq', 87, true);


--
-- Name: asientos_contables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.asientos_contables_id_seq', 37, true);


--
-- Name: asistencias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.asistencias_id_seq', 1, false);


--
-- Name: bancos_cajas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.bancos_cajas_id_seq', 9, true);


--
-- Name: bodegas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.bodegas_id_seq', 6, true);


--
-- Name: categorias_producto_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_producto_id_seq', 20, true);


--
-- Name: centros_costo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.centros_costo_id_seq', 3, true);


--
-- Name: cheques_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cheques_id_seq', 1, false);


--
-- Name: cierres_caja_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cierres_caja_id_seq', 2, true);


--
-- Name: clientes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_seq', 3, true);


--
-- Name: colaboradores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.colaboradores_id_seq', 8, true);


--
-- Name: compra_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compra_detalles_id_seq', 74, true);


--
-- Name: compras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compras_id_seq', 32, true);


--
-- Name: conciliaciones_bancarias_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.conciliaciones_bancarias_id_seq', 1, true);


--
-- Name: configuraciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.configuraciones_id_seq', 1, false);


--
-- Name: cuentas_cobrar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_cobrar_id_seq', 1, false);


--
-- Name: cuentas_pagar_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cuentas_pagar_id_seq', 13, true);


--
-- Name: datafast_liquidaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.datafast_liquidaciones_id_seq', 1, true);


--
-- Name: datafast_lotes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.datafast_lotes_id_seq', 4, true);


--
-- Name: ejercicios_contables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.ejercicios_contables_id_seq', 7, true);


--
-- Name: empresas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresas_id_seq', 2, true);


--
-- Name: etiquetas_productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.etiquetas_productos_id_seq', 10, true);


--
-- Name: factura_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.factura_detalles_id_seq', 1, false);


--
-- Name: factura_pagos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.factura_pagos_id_seq', 1, false);


--
-- Name: facturas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.facturas_id_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: guia_remision_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.guia_remision_detalles_id_seq', 1, false);


--
-- Name: guias_remision_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.guias_remision_id_seq', 1, false);


--
-- Name: horarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.horarios_id_seq', 1, false);


--
-- Name: horas_extras_aprobacion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.horas_extras_aprobacion_id_seq', 1, false);


--
-- Name: importaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.importaciones_id_seq', 5, true);


--
-- Name: inventario_movimientos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.inventario_movimientos_id_seq', 28, true);


--
-- Name: inventario_saldos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.inventario_saldos_id_seq', 88, true);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: limites_descuento_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.limites_descuento_id_seq', 1, false);


--
-- Name: listas_precio_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.listas_precio_id_seq', 1, false);


--
-- Name: log_cambios_criticos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.log_cambios_criticos_id_seq', 19, true);


--
-- Name: log_documentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.log_documentos_id_seq', 40, true);


--
-- Name: log_sesiones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.log_sesiones_id_seq', 41, true);


--
-- Name: marcas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.marcas_id_seq', 12, true);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 96, true);


--
-- Name: modulos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.modulos_id_seq', 10, true);


--
-- Name: movimientos_bancarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.movimientos_bancarios_id_seq', 32, true);


--
-- Name: nomina_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.nomina_detalles_id_seq', 14, true);


--
-- Name: nominas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.nominas_id_seq', 2, true);


--
-- Name: nota_credito_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.nota_credito_detalles_id_seq', 1, false);


--
-- Name: notas_credito_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.notas_credito_id_seq', 1, false);


--
-- Name: notificaciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.notificaciones_id_seq', 1, false);


--
-- Name: parametros_contables_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.parametros_contables_id_seq', 44, true);


--
-- Name: partidas_transito_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.partidas_transito_id_seq', 1, true);


--
-- Name: perfiles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.perfiles_id_seq', 6, true);


--
-- Name: permisos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permisos_id_seq', 60, true);


--
-- Name: permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permissions_id_seq', 1, false);


--
-- Name: plan_cuentas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.plan_cuentas_id_seq', 672, true);


--
-- Name: prefactura_abonos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.prefactura_abonos_id_seq', 1, false);


--
-- Name: prefactura_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.prefactura_detalles_id_seq', 1, false);


--
-- Name: prefacturas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.prefacturas_id_seq', 1, false);


--
-- Name: prestamos_empleados_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.prestamos_empleados_id_seq', 1, false);


--
-- Name: presupuestos_metas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.presupuestos_metas_id_seq', 1, false);


--
-- Name: producto_series_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.producto_series_id_seq', 1, false);


--
-- Name: productos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.productos_id_seq', 63, true);


--
-- Name: proforma_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proforma_detalles_id_seq', 1, false);


--
-- Name: proformas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proformas_id_seq', 1, false);


--
-- Name: proveedores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.proveedores_id_seq', 14, true);


--
-- Name: puestos_trabajo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.puestos_trabajo_id_seq', 1, false);


--
-- Name: recepcion_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.recepcion_detalles_id_seq', 11, true);


--
-- Name: recepcion_escaneos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.recepcion_escaneos_id_seq', 8, true);


--
-- Name: recepciones_bodega_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.recepciones_bodega_id_seq', 8, true);


--
-- Name: retencion_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.retencion_detalles_id_seq', 1, false);


--
-- Name: retenciones_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.retenciones_id_seq', 1, false);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 1, false);


--
-- Name: rubros_nomina_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.rubros_nomina_id_seq', 1, false);


--
-- Name: secuenciales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.secuenciales_id_seq', 1, false);


--
-- Name: tipos_aprobacion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.tipos_aprobacion_id_seq', 18, true);


--
-- Name: transportistas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.transportistas_id_seq', 2, true);


--
-- Name: traslado_detalles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.traslado_detalles_id_seq', 1, false);


--
-- Name: traslado_items_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.traslado_items_id_seq', 1, false);


--
-- Name: traslados_bodega_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.traslados_bodega_id_seq', 1, false);


--
-- Name: traslados_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.traslados_id_seq', 1, false);


--
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 2, true);


--
-- Name: activos_depreciaciones activos_depreciaciones_activo_id_periodo_año_periodo_mes_uniqu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_depreciaciones
    ADD CONSTRAINT "activos_depreciaciones_activo_id_periodo_año_periodo_mes_uniqu" UNIQUE (activo_id, "periodo_año", periodo_mes);


--
-- Name: activos_depreciaciones activos_depreciaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_depreciaciones
    ADD CONSTRAINT activos_depreciaciones_pkey PRIMARY KEY (id);


--
-- Name: activos_fijos activos_fijos_empresa_id_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_fijos
    ADD CONSTRAINT activos_fijos_empresa_id_codigo_unique UNIQUE (empresa_id, codigo);


--
-- Name: activos_fijos activos_fijos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_fijos
    ADD CONSTRAINT activos_fijos_pkey PRIMARY KEY (id);


--
-- Name: anticipos_proveedores anticipos_proveedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anticipos_proveedores
    ADD CONSTRAINT anticipos_proveedores_pkey PRIMARY KEY (id);


--
-- Name: aprobaciones_especiales aprobaciones_especiales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aprobaciones_especiales
    ADD CONSTRAINT aprobaciones_especiales_pkey PRIMARY KEY (id);


--
-- Name: asiento_detalles asiento_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiento_detalles
    ADD CONSTRAINT asiento_detalles_pkey PRIMARY KEY (id);


--
-- Name: asientos_contables asientos_contables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables
    ADD CONSTRAINT asientos_contables_pkey PRIMARY KEY (id);


--
-- Name: asistencias asistencias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asistencias
    ADD CONSTRAINT asistencias_pkey PRIMARY KEY (id);


--
-- Name: bancos_cajas bancos_cajas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bancos_cajas
    ADD CONSTRAINT bancos_cajas_pkey PRIMARY KEY (id);


--
-- Name: bodegas bodegas_empresa_id_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bodegas
    ADD CONSTRAINT bodegas_empresa_id_nombre_unique UNIQUE (empresa_id, nombre);


--
-- Name: bodegas bodegas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bodegas
    ADD CONSTRAINT bodegas_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: categorias_producto categorias_producto_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_producto
    ADD CONSTRAINT categorias_producto_pkey PRIMARY KEY (id);


--
-- Name: centros_costo centros_costo_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_costo
    ADD CONSTRAINT centros_costo_codigo_unique UNIQUE (codigo);


--
-- Name: centros_costo centros_costo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_costo
    ADD CONSTRAINT centros_costo_pkey PRIMARY KEY (id);


--
-- Name: cheques cheques_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cheques
    ADD CONSTRAINT cheques_pkey PRIMARY KEY (id);


--
-- Name: cierres_caja cierres_caja_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja
    ADD CONSTRAINT cierres_caja_pkey PRIMARY KEY (id);


--
-- Name: clientes clientes_empresa_identificacion_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_empresa_identificacion_unique UNIQUE (empresa_id, identificacion);


--
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id);


--
-- Name: colaboradores colaboradores_cedula_ruc_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_cedula_ruc_unique UNIQUE (cedula_ruc);


--
-- Name: colaboradores colaboradores_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_email_unique UNIQUE (email);


--
-- Name: colaboradores colaboradores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_pkey PRIMARY KEY (id);


--
-- Name: compra_detalles compra_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compra_detalles
    ADD CONSTRAINT compra_detalles_pkey PRIMARY KEY (id);


--
-- Name: compras compras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (id);


--
-- Name: conciliaciones_bancarias conciliaciones_bancarias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conciliaciones_bancarias
    ADD CONSTRAINT conciliaciones_bancarias_pkey PRIMARY KEY (id);


--
-- Name: configuraciones configuraciones_empresa_id_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_empresa_id_clave_unique UNIQUE (empresa_id, clave);


--
-- Name: configuraciones configuraciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_pkey PRIMARY KEY (id);


--
-- Name: cuentas_cobrar cuentas_cobrar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_pkey PRIMARY KEY (id);


--
-- Name: cuentas_pagar cuentas_pagar_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_pkey PRIMARY KEY (id);


--
-- Name: datafast_liquidaciones datafast_liquidaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_liquidaciones
    ADD CONSTRAINT datafast_liquidaciones_pkey PRIMARY KEY (id);


--
-- Name: datafast_lotes datafast_lotes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_lotes
    ADD CONSTRAINT datafast_lotes_pkey PRIMARY KEY (id);


--
-- Name: ejercicios_contables ejercicios_contables_empresa_id_anio_mes_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ejercicios_contables
    ADD CONSTRAINT ejercicios_contables_empresa_id_anio_mes_unique UNIQUE (empresa_id, anio, mes);


--
-- Name: ejercicios_contables ejercicios_contables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ejercicios_contables
    ADD CONSTRAINT ejercicios_contables_pkey PRIMARY KEY (id);


--
-- Name: empresa_usuario empresa_usuario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_usuario
    ADD CONSTRAINT empresa_usuario_pkey PRIMARY KEY (empresa_id, usuario_id);


--
-- Name: empresas empresas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_pkey PRIMARY KEY (id);


--
-- Name: empresas empresas_ruc_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas
    ADD CONSTRAINT empresas_ruc_unique UNIQUE (ruc);


--
-- Name: etiquetas_productos etiquetas_productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.etiquetas_productos
    ADD CONSTRAINT etiquetas_productos_pkey PRIMARY KEY (id);


--
-- Name: factura_detalles factura_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factura_detalles
    ADD CONSTRAINT factura_detalles_pkey PRIMARY KEY (id);


--
-- Name: factura_pagos factura_pagos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factura_pagos
    ADD CONSTRAINT factura_pagos_pkey PRIMARY KEY (id);


--
-- Name: facturas facturas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturas
    ADD CONSTRAINT facturas_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: guia_remision_detalles guia_remision_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.guia_remision_detalles
    ADD CONSTRAINT guia_remision_detalles_pkey PRIMARY KEY (id);


--
-- Name: guias_remision guias_remision_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.guias_remision
    ADD CONSTRAINT guias_remision_pkey PRIMARY KEY (id);


--
-- Name: horarios horarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horarios
    ADD CONSTRAINT horarios_pkey PRIMARY KEY (id);


--
-- Name: horas_extras_aprobacion horas_extras_aprobacion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horas_extras_aprobacion
    ADD CONSTRAINT horas_extras_aprobacion_pkey PRIMARY KEY (id);


--
-- Name: importaciones importaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones
    ADD CONSTRAINT importaciones_pkey PRIMARY KEY (id);


--
-- Name: inventario_movimientos inventario_movimientos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_movimientos
    ADD CONSTRAINT inventario_movimientos_pkey PRIMARY KEY (id);


--
-- Name: inventario_saldos inventario_saldos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_saldos
    ADD CONSTRAINT inventario_saldos_pkey PRIMARY KEY (id);


--
-- Name: inventario_saldos inventario_saldos_producto_id_bodega_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_saldos
    ADD CONSTRAINT inventario_saldos_producto_id_bodega_id_unique UNIQUE (producto_id, bodega_id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: limites_descuento limites_descuento_perfil_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.limites_descuento
    ADD CONSTRAINT limites_descuento_perfil_id_unique UNIQUE (perfil_id);


--
-- Name: limites_descuento limites_descuento_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.limites_descuento
    ADD CONSTRAINT limites_descuento_pkey PRIMARY KEY (id);


--
-- Name: listas_precio listas_precio_empresa_id_producto_id_tipo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precio
    ADD CONSTRAINT listas_precio_empresa_id_producto_id_tipo_unique UNIQUE (empresa_id, producto_id, tipo);


--
-- Name: listas_precio listas_precio_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precio
    ADD CONSTRAINT listas_precio_pkey PRIMARY KEY (id);


--
-- Name: log_cambios_criticos log_cambios_criticos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_cambios_criticos
    ADD CONSTRAINT log_cambios_criticos_pkey PRIMARY KEY (id);


--
-- Name: log_documentos log_documentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_documentos
    ADD CONSTRAINT log_documentos_pkey PRIMARY KEY (id);


--
-- Name: log_sesiones log_sesiones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_sesiones
    ADD CONSTRAINT log_sesiones_pkey PRIMARY KEY (id);


--
-- Name: marcas marcas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas
    ADD CONSTRAINT marcas_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: modulos modulos_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_clave_unique UNIQUE (clave);


--
-- Name: modulos modulos_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_nombre_unique UNIQUE (nombre);


--
-- Name: modulos modulos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_pkey PRIMARY KEY (id);


--
-- Name: movimientos_bancarios movimientos_bancarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios
    ADD CONSTRAINT movimientos_bancarios_pkey PRIMARY KEY (id);


--
-- Name: nomina_detalles nomina_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomina_detalles
    ADD CONSTRAINT nomina_detalles_pkey PRIMARY KEY (id);


--
-- Name: nominas nominas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nominas
    ADD CONSTRAINT nominas_pkey PRIMARY KEY (id);


--
-- Name: nota_credito_detalles nota_credito_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nota_credito_detalles
    ADD CONSTRAINT nota_credito_detalles_pkey PRIMARY KEY (id);


--
-- Name: notas_credito notas_credito_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas_credito
    ADD CONSTRAINT notas_credito_pkey PRIMARY KEY (id);


--
-- Name: notificaciones notificaciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones
    ADD CONSTRAINT notificaciones_pkey PRIMARY KEY (id);


--
-- Name: parametros_contables parametros_contables_empresa_id_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_contables
    ADD CONSTRAINT parametros_contables_empresa_id_codigo_unique UNIQUE (empresa_id, codigo);


--
-- Name: parametros_contables parametros_contables_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_contables
    ADD CONSTRAINT parametros_contables_pkey PRIMARY KEY (id);


--
-- Name: partidas_transito partidas_transito_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partidas_transito
    ADD CONSTRAINT partidas_transito_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: perfiles perfiles_nombre_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perfiles
    ADD CONSTRAINT perfiles_nombre_unique UNIQUE (nombre);


--
-- Name: perfiles perfiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perfiles
    ADD CONSTRAINT perfiles_pkey PRIMARY KEY (id);


--
-- Name: permisos permisos_perfil_id_modulo_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_perfil_id_modulo_id_unique UNIQUE (perfil_id, modulo_id);


--
-- Name: permisos permisos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_pkey PRIMARY KEY (id);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: plan_cuentas plan_cuentas_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_cuentas
    ADD CONSTRAINT plan_cuentas_codigo_unique UNIQUE (codigo);


--
-- Name: plan_cuentas plan_cuentas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_cuentas
    ADD CONSTRAINT plan_cuentas_pkey PRIMARY KEY (id);


--
-- Name: prefactura_abonos prefactura_abonos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefactura_abonos
    ADD CONSTRAINT prefactura_abonos_pkey PRIMARY KEY (id);


--
-- Name: prefactura_detalles prefactura_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefactura_detalles
    ADD CONSTRAINT prefactura_detalles_pkey PRIMARY KEY (id);


--
-- Name: prefacturas prefacturas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefacturas
    ADD CONSTRAINT prefacturas_pkey PRIMARY KEY (id);


--
-- Name: prestamos_empleados prestamos_empleados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prestamos_empleados
    ADD CONSTRAINT prestamos_empleados_pkey PRIMARY KEY (id);


--
-- Name: presupuestos_metas presupuestos_metas_empresa_id_centro_costo_id_mes_anio_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos_metas
    ADD CONSTRAINT presupuestos_metas_empresa_id_centro_costo_id_mes_anio_unique UNIQUE (empresa_id, centro_costo_id, mes, anio);


--
-- Name: presupuestos_metas presupuestos_metas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos_metas
    ADD CONSTRAINT presupuestos_metas_pkey PRIMARY KEY (id);


--
-- Name: producto_series producto_series_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_series
    ADD CONSTRAINT producto_series_pkey PRIMARY KEY (id);


--
-- Name: producto_series producto_series_producto_id_numero_serie_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_series
    ADD CONSTRAINT producto_series_producto_id_numero_serie_unique UNIQUE (producto_id, numero_serie);


--
-- Name: productos productos_empresa_id_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_empresa_id_codigo_unique UNIQUE (empresa_id, codigo);


--
-- Name: productos productos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pkey PRIMARY KEY (id);


--
-- Name: proforma_detalles proforma_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proforma_detalles
    ADD CONSTRAINT proforma_detalles_pkey PRIMARY KEY (id);


--
-- Name: proformas proformas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proformas
    ADD CONSTRAINT proformas_pkey PRIMARY KEY (id);


--
-- Name: proveedores proveedores_empresa_identificacion_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_empresa_identificacion_unique UNIQUE (empresa_id, identificacion);


--
-- Name: proveedores proveedores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_pkey PRIMARY KEY (id);


--
-- Name: puestos_trabajo puestos_trabajo_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.puestos_trabajo
    ADD CONSTRAINT puestos_trabajo_pkey PRIMARY KEY (id);


--
-- Name: recepcion_detalles recepcion_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_detalles
    ADD CONSTRAINT recepcion_detalles_pkey PRIMARY KEY (id);


--
-- Name: recepcion_escaneos recepcion_escaneos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos
    ADD CONSTRAINT recepcion_escaneos_pkey PRIMARY KEY (id);


--
-- Name: recepcion_escaneos recepcion_escaneos_recepcion_id_codigo_escaneado_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos
    ADD CONSTRAINT recepcion_escaneos_recepcion_id_codigo_escaneado_unique UNIQUE (recepcion_id, codigo_escaneado);


--
-- Name: recepciones_bodega recepciones_bodega_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepciones_bodega
    ADD CONSTRAINT recepciones_bodega_pkey PRIMARY KEY (id);


--
-- Name: retencion_detalles retencion_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retencion_detalles
    ADD CONSTRAINT retencion_detalles_pkey PRIMARY KEY (id);


--
-- Name: retenciones retenciones_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retenciones
    ADD CONSTRAINT retenciones_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: rubros_nomina rubros_nomina_codigo_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rubros_nomina
    ADD CONSTRAINT rubros_nomina_codigo_unique UNIQUE (codigo);


--
-- Name: rubros_nomina rubros_nomina_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rubros_nomina
    ADD CONSTRAINT rubros_nomina_pkey PRIMARY KEY (id);


--
-- Name: secuenciales secuenciales_empresa_id_tipo_documento_establecimiento_punto_em; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secuenciales
    ADD CONSTRAINT secuenciales_empresa_id_tipo_documento_establecimiento_punto_em UNIQUE (empresa_id, tipo_documento, establecimiento, punto_emision);


--
-- Name: secuenciales secuenciales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secuenciales
    ADD CONSTRAINT secuenciales_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: tipos_aprobacion tipos_aprobacion_clave_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_aprobacion
    ADD CONSTRAINT tipos_aprobacion_clave_unique UNIQUE (clave);


--
-- Name: tipos_aprobacion tipos_aprobacion_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tipos_aprobacion
    ADD CONSTRAINT tipos_aprobacion_pkey PRIMARY KEY (id);


--
-- Name: transportistas transportistas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transportistas
    ADD CONSTRAINT transportistas_pkey PRIMARY KEY (id);


--
-- Name: traslado_detalles traslado_detalles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_detalles
    ADD CONSTRAINT traslado_detalles_pkey PRIMARY KEY (id);


--
-- Name: traslado_items traslado_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_items
    ADD CONSTRAINT traslado_items_pkey PRIMARY KEY (id);


--
-- Name: traslados_bodega traslados_bodega_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega
    ADD CONSTRAINT traslados_bodega_pkey PRIMARY KEY (id);


--
-- Name: traslados traslados_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados
    ADD CONSTRAINT traslados_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_email_unique UNIQUE (email);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_username_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_username_unique UNIQUE (username);


--
-- Name: bodegas_tipo_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX bodegas_tipo_index ON public.bodegas USING btree (tipo);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: categorias_producto_empresa_id_parent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX categorias_producto_empresa_id_parent_id_index ON public.categorias_producto USING btree (empresa_id, categoria_padre_id);


--
-- Name: clientes_empresa_id_identificacion_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX clientes_empresa_id_identificacion_unique ON public.clientes USING btree (empresa_id, identificacion);


--
-- Name: idx_asientos_ejercicio; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_asientos_ejercicio ON public.asientos_contables USING btree (ejercicio_id);


--
-- Name: idx_asientos_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_asientos_empresa ON public.asientos_contables USING btree (empresa_id);


--
-- Name: idx_asientos_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_asientos_fecha ON public.asientos_contables USING btree (fecha);


--
-- Name: idx_mov_banco; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_banco ON public.movimientos_bancarios USING btree (banco_caja_id);


--
-- Name: idx_mov_empresa; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_empresa ON public.movimientos_bancarios USING btree (empresa_id);


--
-- Name: idx_mov_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_mov_fecha ON public.movimientos_bancarios USING btree (fecha);


--
-- Name: inventario_movimientos_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventario_movimientos_created_at_index ON public.inventario_movimientos USING btree (created_at);


--
-- Name: inventario_movimientos_doc_tipo_doc_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventario_movimientos_doc_tipo_doc_id_index ON public.inventario_movimientos USING btree (doc_tipo, doc_id);


--
-- Name: inventario_movimientos_producto_id_bodega_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventario_movimientos_producto_id_bodega_id_index ON public.inventario_movimientos USING btree (producto_id, bodega_id);


--
-- Name: inventario_saldos_bodega_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX inventario_saldos_bodega_id_index ON public.inventario_saldos USING btree (bodega_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: marcas_empresa_id_nombre_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX marcas_empresa_id_nombre_index ON public.marcas USING btree (empresa_id, nombre);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: plan_cuentas_empresa_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX plan_cuentas_empresa_id_index ON public.plan_cuentas USING btree (empresa_id);


--
-- Name: plan_cuentas_padre_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX plan_cuentas_padre_id_index ON public.plan_cuentas USING btree (padre_id);


--
-- Name: producto_series_bodega_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX producto_series_bodega_id_estado_index ON public.producto_series USING btree (bodega_id, estado);


--
-- Name: productos_categoria_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX productos_categoria_id_index ON public.productos USING btree (categoria_id);


--
-- Name: productos_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX productos_empresa_id_estado_index ON public.productos USING btree (empresa_id, estado);


--
-- Name: productos_marca_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX productos_marca_id_index ON public.productos USING btree (marca_id);


--
-- Name: proveedores_empresa_id_identificacion_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX proveedores_empresa_id_identificacion_unique ON public.proveedores USING btree (empresa_id, identificacion);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: traslado_items_producto_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX traslado_items_producto_id_index ON public.traslado_items USING btree (producto_id);


--
-- Name: traslado_items_traslado_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX traslado_items_traslado_id_index ON public.traslado_items USING btree (traslado_id);


--
-- Name: traslados_bodega_destino_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX traslados_bodega_destino_id_index ON public.traslados USING btree (bodega_destino_id);


--
-- Name: traslados_bodega_origen_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX traslados_bodega_origen_id_index ON public.traslados USING btree (bodega_origen_id);


--
-- Name: traslados_empresa_id_estado_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX traslados_empresa_id_estado_index ON public.traslados USING btree (empresa_id, estado);


--
-- Name: activos_depreciaciones activos_depreciaciones_activo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_depreciaciones
    ADD CONSTRAINT activos_depreciaciones_activo_id_foreign FOREIGN KEY (activo_id) REFERENCES public.activos_fijos(id) ON DELETE CASCADE;


--
-- Name: activos_fijos activos_fijos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activos_fijos
    ADD CONSTRAINT activos_fijos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: anticipos_proveedores anticipos_proveedores_asiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anticipos_proveedores
    ADD CONSTRAINT anticipos_proveedores_asiento_id_foreign FOREIGN KEY (asiento_id) REFERENCES public.asientos_contables(id);


--
-- Name: anticipos_proveedores anticipos_proveedores_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anticipos_proveedores
    ADD CONSTRAINT anticipos_proveedores_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: anticipos_proveedores anticipos_proveedores_importacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anticipos_proveedores
    ADD CONSTRAINT anticipos_proveedores_importacion_id_foreign FOREIGN KEY (importacion_id) REFERENCES public.importaciones(id);


--
-- Name: anticipos_proveedores anticipos_proveedores_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.anticipos_proveedores
    ADD CONSTRAINT anticipos_proveedores_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: aprobaciones_especiales aprobaciones_especiales_aprobado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aprobaciones_especiales
    ADD CONSTRAINT aprobaciones_especiales_aprobado_por_foreign FOREIGN KEY (aprobado_por) REFERENCES public.usuarios(id);


--
-- Name: aprobaciones_especiales aprobaciones_especiales_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aprobaciones_especiales
    ADD CONSTRAINT aprobaciones_especiales_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: aprobaciones_especiales aprobaciones_especiales_solicitado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aprobaciones_especiales
    ADD CONSTRAINT aprobaciones_especiales_solicitado_por_foreign FOREIGN KEY (solicitado_por) REFERENCES public.usuarios(id);


--
-- Name: aprobaciones_especiales aprobaciones_especiales_tipo_aprobacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aprobaciones_especiales
    ADD CONSTRAINT aprobaciones_especiales_tipo_aprobacion_id_foreign FOREIGN KEY (tipo_aprobacion_id) REFERENCES public.tipos_aprobacion(id);


--
-- Name: asiento_detalles asiento_detalles_asiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiento_detalles
    ADD CONSTRAINT asiento_detalles_asiento_id_foreign FOREIGN KEY (asiento_id) REFERENCES public.asientos_contables(id) ON DELETE CASCADE;


--
-- Name: asiento_detalles asiento_detalles_centro_costo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiento_detalles
    ADD CONSTRAINT asiento_detalles_centro_costo_id_foreign FOREIGN KEY (centro_costo_id) REFERENCES public.centros_costo(id);


--
-- Name: asiento_detalles asiento_detalles_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asiento_detalles
    ADD CONSTRAINT asiento_detalles_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.plan_cuentas(id);


--
-- Name: asientos_contables asientos_contables_creado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables
    ADD CONSTRAINT asientos_contables_creado_por_foreign FOREIGN KEY (creado_por) REFERENCES public.usuarios(id);


--
-- Name: asientos_contables asientos_contables_ejercicio_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables
    ADD CONSTRAINT asientos_contables_ejercicio_id_foreign FOREIGN KEY (ejercicio_id) REFERENCES public.ejercicios_contables(id);


--
-- Name: asientos_contables asientos_contables_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asientos_contables
    ADD CONSTRAINT asientos_contables_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: asistencias asistencias_colaborador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.asistencias
    ADD CONSTRAINT asistencias_colaborador_id_foreign FOREIGN KEY (colaborador_id) REFERENCES public.colaboradores(id) ON DELETE CASCADE;


--
-- Name: bancos_cajas bancos_cajas_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bancos_cajas
    ADD CONSTRAINT bancos_cajas_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.plan_cuentas(id);


--
-- Name: bancos_cajas bancos_cajas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bancos_cajas
    ADD CONSTRAINT bancos_cajas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: bodegas bodegas_centro_costo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bodegas
    ADD CONSTRAINT bodegas_centro_costo_id_foreign FOREIGN KEY (centro_costo_id) REFERENCES public.centros_costo(id) ON DELETE SET NULL;


--
-- Name: bodegas bodegas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bodegas
    ADD CONSTRAINT bodegas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: categorias_producto categorias_producto_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_producto
    ADD CONSTRAINT categorias_producto_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: categorias_producto categorias_producto_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias_producto
    ADD CONSTRAINT categorias_producto_parent_id_foreign FOREIGN KEY (categoria_padre_id) REFERENCES public.categorias_producto(id) ON DELETE SET NULL;


--
-- Name: centros_costo centros_costo_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.centros_costo
    ADD CONSTRAINT centros_costo_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cheques cheques_banco_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cheques
    ADD CONSTRAINT cheques_banco_caja_id_foreign FOREIGN KEY (banco_caja_id) REFERENCES public.bancos_cajas(id);


--
-- Name: cheques cheques_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cheques
    ADD CONSTRAINT cheques_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cheques cheques_movimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cheques
    ADD CONSTRAINT cheques_movimiento_id_foreign FOREIGN KEY (movimiento_id) REFERENCES public.movimientos_bancarios(id);


--
-- Name: cierres_caja cierres_caja_banco_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja
    ADD CONSTRAINT cierres_caja_banco_caja_id_foreign FOREIGN KEY (banco_caja_id) REFERENCES public.bancos_cajas(id);


--
-- Name: cierres_caja cierres_caja_centro_costo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja
    ADD CONSTRAINT cierres_caja_centro_costo_id_foreign FOREIGN KEY (centro_costo_id) REFERENCES public.centros_costo(id);


--
-- Name: cierres_caja cierres_caja_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja
    ADD CONSTRAINT cierres_caja_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cierres_caja cierres_caja_usuario_apertura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja
    ADD CONSTRAINT cierres_caja_usuario_apertura_id_foreign FOREIGN KEY (usuario_apertura_id) REFERENCES public.usuarios(id);


--
-- Name: cierres_caja cierres_caja_usuario_cierre_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cierres_caja
    ADD CONSTRAINT cierres_caja_usuario_cierre_id_foreign FOREIGN KEY (usuario_cierre_id) REFERENCES public.usuarios(id);


--
-- Name: clientes clientes_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: colaboradores colaboradores_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: colaboradores colaboradores_horario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_horario_id_foreign FOREIGN KEY (horario_id) REFERENCES public.horarios(id) ON DELETE SET NULL;


--
-- Name: colaboradores colaboradores_puesto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_puesto_id_foreign FOREIGN KEY (puesto_id) REFERENCES public.puestos_trabajo(id) ON DELETE SET NULL;


--
-- Name: colaboradores colaboradores_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.colaboradores
    ADD CONSTRAINT colaboradores_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: compra_detalles compra_detalles_compra_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compra_detalles
    ADD CONSTRAINT compra_detalles_compra_id_foreign FOREIGN KEY (compra_id) REFERENCES public.compras(id) ON DELETE CASCADE;


--
-- Name: compra_detalles compra_detalles_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compra_detalles
    ADD CONSTRAINT compra_detalles_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.plan_cuentas(id);


--
-- Name: compras compras_asiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_asiento_id_foreign FOREIGN KEY (asiento_id) REFERENCES public.asientos_contables(id);


--
-- Name: compras compras_centro_costo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_centro_costo_id_foreign FOREIGN KEY (centro_costo_id) REFERENCES public.centros_costo(id);


--
-- Name: compras compras_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id);


--
-- Name: compras compras_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: compras compras_importacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_importacion_id_foreign FOREIGN KEY (importacion_id) REFERENCES public.importaciones(id);


--
-- Name: compras compras_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: conciliaciones_bancarias conciliaciones_bancarias_banco_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conciliaciones_bancarias
    ADD CONSTRAINT conciliaciones_bancarias_banco_caja_id_foreign FOREIGN KEY (banco_caja_id) REFERENCES public.bancos_cajas(id);


--
-- Name: conciliaciones_bancarias conciliaciones_bancarias_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conciliaciones_bancarias
    ADD CONSTRAINT conciliaciones_bancarias_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id);


--
-- Name: conciliaciones_bancarias conciliaciones_bancarias_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.conciliaciones_bancarias
    ADD CONSTRAINT conciliaciones_bancarias_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: configuraciones configuraciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuraciones
    ADD CONSTRAINT configuraciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: cuentas_cobrar cuentas_cobrar_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: cuentas_cobrar cuentas_cobrar_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_cobrar
    ADD CONSTRAINT cuentas_cobrar_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cuentas_pagar cuentas_pagar_asiento_pago_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_asiento_pago_id_foreign FOREIGN KEY (asiento_pago_id) REFERENCES public.asientos_contables(id);


--
-- Name: cuentas_pagar cuentas_pagar_compra_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_compra_id_foreign FOREIGN KEY (compra_id) REFERENCES public.compras(id);


--
-- Name: cuentas_pagar cuentas_pagar_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: cuentas_pagar cuentas_pagar_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: datafast_liquidaciones datafast_liquidaciones_asiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_liquidaciones
    ADD CONSTRAINT datafast_liquidaciones_asiento_id_foreign FOREIGN KEY (asiento_id) REFERENCES public.asientos_contables(id);


--
-- Name: datafast_liquidaciones datafast_liquidaciones_banco_destino_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_liquidaciones
    ADD CONSTRAINT datafast_liquidaciones_banco_destino_id_foreign FOREIGN KEY (banco_destino_id) REFERENCES public.bancos_cajas(id);


--
-- Name: datafast_liquidaciones datafast_liquidaciones_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_liquidaciones
    ADD CONSTRAINT datafast_liquidaciones_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id);


--
-- Name: datafast_liquidaciones datafast_liquidaciones_lote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_liquidaciones
    ADD CONSTRAINT datafast_liquidaciones_lote_id_foreign FOREIGN KEY (lote_id) REFERENCES public.datafast_lotes(id);


--
-- Name: datafast_lotes datafast_lotes_asiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_lotes
    ADD CONSTRAINT datafast_lotes_asiento_id_foreign FOREIGN KEY (asiento_id) REFERENCES public.asientos_contables(id);


--
-- Name: datafast_lotes datafast_lotes_banco_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_lotes
    ADD CONSTRAINT datafast_lotes_banco_caja_id_foreign FOREIGN KEY (banco_caja_id) REFERENCES public.bancos_cajas(id);


--
-- Name: datafast_lotes datafast_lotes_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_lotes
    ADD CONSTRAINT datafast_lotes_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id);


--
-- Name: datafast_lotes datafast_lotes_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.datafast_lotes
    ADD CONSTRAINT datafast_lotes_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: ejercicios_contables ejercicios_contables_cerrado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ejercicios_contables
    ADD CONSTRAINT ejercicios_contables_cerrado_por_foreign FOREIGN KEY (cerrado_por) REFERENCES public.usuarios(id);


--
-- Name: ejercicios_contables ejercicios_contables_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ejercicios_contables
    ADD CONSTRAINT ejercicios_contables_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: empresa_usuario empresa_usuario_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_usuario
    ADD CONSTRAINT empresa_usuario_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: empresa_usuario empresa_usuario_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa_usuario
    ADD CONSTRAINT empresa_usuario_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: etiquetas_productos etiquetas_productos_compra_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.etiquetas_productos
    ADD CONSTRAINT etiquetas_productos_compra_id_foreign FOREIGN KEY (compra_id) REFERENCES public.compras(id);


--
-- Name: etiquetas_productos etiquetas_productos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.etiquetas_productos
    ADD CONSTRAINT etiquetas_productos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: etiquetas_productos etiquetas_productos_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.etiquetas_productos
    ADD CONSTRAINT etiquetas_productos_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: factura_detalles factura_detalles_factura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factura_detalles
    ADD CONSTRAINT factura_detalles_factura_id_foreign FOREIGN KEY (factura_id) REFERENCES public.facturas(id) ON DELETE CASCADE;


--
-- Name: factura_pagos factura_pagos_factura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.factura_pagos
    ADD CONSTRAINT factura_pagos_factura_id_foreign FOREIGN KEY (factura_id) REFERENCES public.facturas(id) ON DELETE CASCADE;


--
-- Name: facturas facturas_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturas
    ADD CONSTRAINT facturas_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: facturas facturas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.facturas
    ADD CONSTRAINT facturas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: guia_remision_detalles guia_remision_detalles_guia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.guia_remision_detalles
    ADD CONSTRAINT guia_remision_detalles_guia_id_foreign FOREIGN KEY (guia_id) REFERENCES public.guias_remision(id) ON DELETE CASCADE;


--
-- Name: guias_remision guias_remision_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.guias_remision
    ADD CONSTRAINT guias_remision_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: horas_extras_aprobacion horas_extras_aprobacion_aprobado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horas_extras_aprobacion
    ADD CONSTRAINT horas_extras_aprobacion_aprobado_por_foreign FOREIGN KEY (aprobado_por) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: horas_extras_aprobacion horas_extras_aprobacion_asistencia_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horas_extras_aprobacion
    ADD CONSTRAINT horas_extras_aprobacion_asistencia_id_foreign FOREIGN KEY (asistencia_id) REFERENCES public.asistencias(id) ON DELETE SET NULL;


--
-- Name: horas_extras_aprobacion horas_extras_aprobacion_colaborador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.horas_extras_aprobacion
    ADD CONSTRAINT horas_extras_aprobacion_colaborador_id_foreign FOREIGN KEY (colaborador_id) REFERENCES public.colaboradores(id) ON DELETE CASCADE;


--
-- Name: importaciones importaciones_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones
    ADD CONSTRAINT importaciones_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id);


--
-- Name: importaciones importaciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones
    ADD CONSTRAINT importaciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: importaciones importaciones_proveedor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.importaciones
    ADD CONSTRAINT importaciones_proveedor_id_foreign FOREIGN KEY (proveedor_id) REFERENCES public.proveedores(id);


--
-- Name: inventario_movimientos inventario_movimientos_bodega_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_movimientos
    ADD CONSTRAINT inventario_movimientos_bodega_id_foreign FOREIGN KEY (bodega_id) REFERENCES public.bodegas(id);


--
-- Name: inventario_movimientos inventario_movimientos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_movimientos
    ADD CONSTRAINT inventario_movimientos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: inventario_movimientos inventario_movimientos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_movimientos
    ADD CONSTRAINT inventario_movimientos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: inventario_saldos inventario_saldos_bodega_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.inventario_saldos
    ADD CONSTRAINT inventario_saldos_bodega_id_foreign FOREIGN KEY (bodega_id) REFERENCES public.bodegas(id) ON DELETE CASCADE;


--
-- Name: limites_descuento limites_descuento_perfil_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.limites_descuento
    ADD CONSTRAINT limites_descuento_perfil_id_foreign FOREIGN KEY (perfil_id) REFERENCES public.perfiles(id) ON DELETE CASCADE;


--
-- Name: listas_precio listas_precio_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precio
    ADD CONSTRAINT listas_precio_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: listas_precio listas_precio_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.listas_precio
    ADD CONSTRAINT listas_precio_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: log_cambios_criticos log_cambios_criticos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_cambios_criticos
    ADD CONSTRAINT log_cambios_criticos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE SET NULL;


--
-- Name: log_cambios_criticos log_cambios_criticos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_cambios_criticos
    ADD CONSTRAINT log_cambios_criticos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: log_documentos log_documentos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_documentos
    ADD CONSTRAINT log_documentos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE SET NULL;


--
-- Name: log_documentos log_documentos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_documentos
    ADD CONSTRAINT log_documentos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: log_sesiones log_sesiones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_sesiones
    ADD CONSTRAINT log_sesiones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE SET NULL;


--
-- Name: log_sesiones log_sesiones_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.log_sesiones
    ADD CONSTRAINT log_sesiones_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: marcas marcas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.marcas
    ADD CONSTRAINT marcas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: modulos modulos_padre_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_padre_id_foreign FOREIGN KEY (padre_id) REFERENCES public.modulos(id) ON DELETE SET NULL;


--
-- Name: movimientos_bancarios movimientos_bancarios_asiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios
    ADD CONSTRAINT movimientos_bancarios_asiento_id_foreign FOREIGN KEY (asiento_id) REFERENCES public.asientos_contables(id);


--
-- Name: movimientos_bancarios movimientos_bancarios_banco_caja_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios
    ADD CONSTRAINT movimientos_bancarios_banco_caja_id_foreign FOREIGN KEY (banco_caja_id) REFERENCES public.bancos_cajas(id);


--
-- Name: movimientos_bancarios movimientos_bancarios_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios
    ADD CONSTRAINT movimientos_bancarios_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id);


--
-- Name: movimientos_bancarios movimientos_bancarios_cuenta_contrapartida_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios
    ADD CONSTRAINT movimientos_bancarios_cuenta_contrapartida_id_foreign FOREIGN KEY (cuenta_contrapartida_id) REFERENCES public.plan_cuentas(id);


--
-- Name: movimientos_bancarios movimientos_bancarios_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.movimientos_bancarios
    ADD CONSTRAINT movimientos_bancarios_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: nomina_detalles nomina_detalles_colaborador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomina_detalles
    ADD CONSTRAINT nomina_detalles_colaborador_id_foreign FOREIGN KEY (colaborador_id) REFERENCES public.colaboradores(id);


--
-- Name: nomina_detalles nomina_detalles_nomina_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomina_detalles
    ADD CONSTRAINT nomina_detalles_nomina_id_foreign FOREIGN KEY (nomina_id) REFERENCES public.nominas(id) ON DELETE CASCADE;


--
-- Name: nominas nominas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nominas
    ADD CONSTRAINT nominas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: nominas nominas_generado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nominas
    ADD CONSTRAINT nominas_generado_por_foreign FOREIGN KEY (generado_por) REFERENCES public.usuarios(id);


--
-- Name: nominas nominas_pagado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nominas
    ADD CONSTRAINT nominas_pagado_por_foreign FOREIGN KEY (pagado_por) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: nominas nominas_procesado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nominas
    ADD CONSTRAINT nominas_procesado_por_foreign FOREIGN KEY (procesado_por) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: nota_credito_detalles nota_credito_detalles_nota_credito_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nota_credito_detalles
    ADD CONSTRAINT nota_credito_detalles_nota_credito_id_foreign FOREIGN KEY (nota_credito_id) REFERENCES public.notas_credito(id) ON DELETE CASCADE;


--
-- Name: notas_credito notas_credito_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas_credito
    ADD CONSTRAINT notas_credito_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: notas_credito notas_credito_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas_credito
    ADD CONSTRAINT notas_credito_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: notas_credito notas_credito_factura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notas_credito
    ADD CONSTRAINT notas_credito_factura_id_foreign FOREIGN KEY (factura_id) REFERENCES public.facturas(id);


--
-- Name: notificaciones notificaciones_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notificaciones
    ADD CONSTRAINT notificaciones_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: parametros_contables parametros_contables_cuenta_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_contables
    ADD CONSTRAINT parametros_contables_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES public.plan_cuentas(id);


--
-- Name: parametros_contables parametros_contables_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_contables
    ADD CONSTRAINT parametros_contables_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: partidas_transito partidas_transito_asiento_generado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partidas_transito
    ADD CONSTRAINT partidas_transito_asiento_generado_id_foreign FOREIGN KEY (asiento_generado_id) REFERENCES public.asientos_contables(id);


--
-- Name: partidas_transito partidas_transito_conciliacion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partidas_transito
    ADD CONSTRAINT partidas_transito_conciliacion_id_foreign FOREIGN KEY (conciliacion_id) REFERENCES public.conciliaciones_bancarias(id) ON DELETE CASCADE;


--
-- Name: partidas_transito partidas_transito_movimiento_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.partidas_transito
    ADD CONSTRAINT partidas_transito_movimiento_id_foreign FOREIGN KEY (movimiento_id) REFERENCES public.movimientos_bancarios(id);


--
-- Name: permisos permisos_modulo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_modulo_id_foreign FOREIGN KEY (modulo_id) REFERENCES public.modulos(id) ON DELETE CASCADE;


--
-- Name: permisos permisos_perfil_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permisos
    ADD CONSTRAINT permisos_perfil_id_foreign FOREIGN KEY (perfil_id) REFERENCES public.perfiles(id) ON DELETE CASCADE;


--
-- Name: plan_cuentas plan_cuentas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_cuentas
    ADD CONSTRAINT plan_cuentas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE SET NULL;


--
-- Name: plan_cuentas plan_cuentas_padre_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plan_cuentas
    ADD CONSTRAINT plan_cuentas_padre_id_foreign FOREIGN KEY (padre_id) REFERENCES public.plan_cuentas(id) ON DELETE SET NULL;


--
-- Name: prefactura_abonos prefactura_abonos_prefactura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefactura_abonos
    ADD CONSTRAINT prefactura_abonos_prefactura_id_foreign FOREIGN KEY (prefactura_id) REFERENCES public.prefacturas(id);


--
-- Name: prefactura_detalles prefactura_detalles_prefactura_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefactura_detalles
    ADD CONSTRAINT prefactura_detalles_prefactura_id_foreign FOREIGN KEY (prefactura_id) REFERENCES public.prefacturas(id) ON DELETE CASCADE;


--
-- Name: prefacturas prefacturas_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefacturas
    ADD CONSTRAINT prefacturas_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: prefacturas prefacturas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prefacturas
    ADD CONSTRAINT prefacturas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: prestamos_empleados prestamos_empleados_colaborador_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prestamos_empleados
    ADD CONSTRAINT prestamos_empleados_colaborador_id_foreign FOREIGN KEY (colaborador_id) REFERENCES public.colaboradores(id) ON DELETE CASCADE;


--
-- Name: prestamos_empleados prestamos_empleados_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.prestamos_empleados
    ADD CONSTRAINT prestamos_empleados_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: presupuestos_metas presupuestos_metas_centro_costo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos_metas
    ADD CONSTRAINT presupuestos_metas_centro_costo_id_foreign FOREIGN KEY (centro_costo_id) REFERENCES public.centros_costo(id) ON DELETE SET NULL;


--
-- Name: presupuestos_metas presupuestos_metas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.presupuestos_metas
    ADD CONSTRAINT presupuestos_metas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: producto_series producto_series_bodega_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_series
    ADD CONSTRAINT producto_series_bodega_id_foreign FOREIGN KEY (bodega_id) REFERENCES public.bodegas(id) ON DELETE CASCADE;


--
-- Name: producto_series producto_series_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.producto_series
    ADD CONSTRAINT producto_series_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id) ON DELETE CASCADE;


--
-- Name: productos productos_bodega_default_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_bodega_default_id_foreign FOREIGN KEY (bodega_default_id) REFERENCES public.bodegas(id) ON DELETE SET NULL;


--
-- Name: productos productos_categoria_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES public.categorias_producto(id) ON DELETE SET NULL;


--
-- Name: productos productos_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: productos productos_marca_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_marca_id_foreign FOREIGN KEY (marca_id) REFERENCES public.marcas(id) ON DELETE SET NULL;


--
-- Name: proforma_detalles proforma_detalles_proforma_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proforma_detalles
    ADD CONSTRAINT proforma_detalles_proforma_id_foreign FOREIGN KEY (proforma_id) REFERENCES public.proformas(id) ON DELETE CASCADE;


--
-- Name: proformas proformas_cliente_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proformas
    ADD CONSTRAINT proformas_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES public.clientes(id);


--
-- Name: proformas proformas_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proformas
    ADD CONSTRAINT proformas_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: proveedores proveedores_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.proveedores
    ADD CONSTRAINT proveedores_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: puestos_trabajo puestos_trabajo_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.puestos_trabajo
    ADD CONSTRAINT puestos_trabajo_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: recepcion_detalles recepcion_detalles_compra_detalle_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_detalles
    ADD CONSTRAINT recepcion_detalles_compra_detalle_id_foreign FOREIGN KEY (compra_detalle_id) REFERENCES public.compra_detalles(id);


--
-- Name: recepcion_detalles recepcion_detalles_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_detalles
    ADD CONSTRAINT recepcion_detalles_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: recepcion_detalles recepcion_detalles_recepcion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_detalles
    ADD CONSTRAINT recepcion_detalles_recepcion_id_foreign FOREIGN KEY (recepcion_id) REFERENCES public.recepciones_bodega(id) ON DELETE CASCADE;


--
-- Name: recepcion_escaneos recepcion_escaneos_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos
    ADD CONSTRAINT recepcion_escaneos_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: recepcion_escaneos recepcion_escaneos_recepcion_detalle_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos
    ADD CONSTRAINT recepcion_escaneos_recepcion_detalle_id_foreign FOREIGN KEY (recepcion_detalle_id) REFERENCES public.recepcion_detalles(id) ON DELETE CASCADE;


--
-- Name: recepcion_escaneos recepcion_escaneos_recepcion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos
    ADD CONSTRAINT recepcion_escaneos_recepcion_id_foreign FOREIGN KEY (recepcion_id) REFERENCES public.recepciones_bodega(id) ON DELETE CASCADE;


--
-- Name: recepcion_escaneos recepcion_escaneos_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepcion_escaneos
    ADD CONSTRAINT recepcion_escaneos_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id);


--
-- Name: recepciones_bodega recepciones_bodega_bodega_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepciones_bodega
    ADD CONSTRAINT recepciones_bodega_bodega_id_foreign FOREIGN KEY (bodega_id) REFERENCES public.bodegas(id);


--
-- Name: recepciones_bodega recepciones_bodega_compra_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepciones_bodega
    ADD CONSTRAINT recepciones_bodega_compra_id_foreign FOREIGN KEY (compra_id) REFERENCES public.compras(id);


--
-- Name: recepciones_bodega recepciones_bodega_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepciones_bodega
    ADD CONSTRAINT recepciones_bodega_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: recepciones_bodega recepciones_bodega_recibido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recepciones_bodega
    ADD CONSTRAINT recepciones_bodega_recibido_por_foreign FOREIGN KEY (recibido_por) REFERENCES public.usuarios(id);


--
-- Name: retencion_detalles retencion_detalles_retencion_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retencion_detalles
    ADD CONSTRAINT retencion_detalles_retencion_id_foreign FOREIGN KEY (retencion_id) REFERENCES public.retenciones(id) ON DELETE CASCADE;


--
-- Name: retenciones retenciones_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.retenciones
    ADD CONSTRAINT retenciones_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: secuenciales secuenciales_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.secuenciales
    ADD CONSTRAINT secuenciales_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: traslado_detalles traslado_detalles_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_detalles
    ADD CONSTRAINT traslado_detalles_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: traslado_detalles traslado_detalles_traslado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_detalles
    ADD CONSTRAINT traslado_detalles_traslado_id_foreign FOREIGN KEY (traslado_id) REFERENCES public.traslados_bodega(id) ON DELETE CASCADE;


--
-- Name: traslado_items traslado_items_producto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_items
    ADD CONSTRAINT traslado_items_producto_id_foreign FOREIGN KEY (producto_id) REFERENCES public.productos(id);


--
-- Name: traslado_items traslado_items_traslado_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslado_items
    ADD CONSTRAINT traslado_items_traslado_id_foreign FOREIGN KEY (traslado_id) REFERENCES public.traslados(id) ON DELETE CASCADE;


--
-- Name: traslados_bodega traslados_bodega_bodega_destino_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega
    ADD CONSTRAINT traslados_bodega_bodega_destino_id_foreign FOREIGN KEY (bodega_destino_id) REFERENCES public.bodegas(id);


--
-- Name: traslados_bodega traslados_bodega_bodega_origen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega
    ADD CONSTRAINT traslados_bodega_bodega_origen_id_foreign FOREIGN KEY (bodega_origen_id) REFERENCES public.bodegas(id);


--
-- Name: traslados traslados_bodega_destino_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados
    ADD CONSTRAINT traslados_bodega_destino_id_foreign FOREIGN KEY (bodega_destino_id) REFERENCES public.bodegas(id);


--
-- Name: traslados_bodega traslados_bodega_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega
    ADD CONSTRAINT traslados_bodega_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id);


--
-- Name: traslados_bodega traslados_bodega_enviado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega
    ADD CONSTRAINT traslados_bodega_enviado_por_foreign FOREIGN KEY (enviado_por) REFERENCES public.usuarios(id);


--
-- Name: traslados traslados_bodega_origen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados
    ADD CONSTRAINT traslados_bodega_origen_id_foreign FOREIGN KEY (bodega_origen_id) REFERENCES public.bodegas(id);


--
-- Name: traslados_bodega traslados_bodega_recibido_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados_bodega
    ADD CONSTRAINT traslados_bodega_recibido_por_foreign FOREIGN KEY (recibido_por) REFERENCES public.usuarios(id);


--
-- Name: traslados traslados_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados
    ADD CONSTRAINT traslados_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: traslados traslados_usuario_destino_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados
    ADD CONSTRAINT traslados_usuario_destino_id_foreign FOREIGN KEY (usuario_destino_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: traslados traslados_usuario_origen_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.traslados
    ADD CONSTRAINT traslados_usuario_origen_id_foreign FOREIGN KEY (usuario_origen_id) REFERENCES public.usuarios(id);


--
-- Name: usuarios usuarios_centro_costo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_centro_costo_id_foreign FOREIGN KEY (centro_costo_id) REFERENCES public.centros_costo(id) ON DELETE SET NULL;


--
-- Name: usuarios usuarios_empresa_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_empresa_id_foreign FOREIGN KEY (empresa_id) REFERENCES public.empresas(id) ON DELETE CASCADE;


--
-- Name: usuarios usuarios_perfil_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_perfil_id_foreign FOREIGN KEY (perfil_id) REFERENCES public.perfiles(id);


--
-- PostgreSQL database dump complete
--

\unrestrict dbOXqTXgY9FqjlpGVP0SkDMgTNearrInoPCDJ3tZ97GNt1gfM4T7NMWnMYWuQ5L

