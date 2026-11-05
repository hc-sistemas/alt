# Módulo J — RRHH, asistencia y nómina

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-09_RRHH_y_Nomina.pdf`, `01` §5.8. Decisiones: DEC-009, 013 a 020.

**Prompt de arranque:**
> Implementa los cambios del módulo "RRHH, Asistencia y Nómina" de Altamira ERP según `docs/Oct2026/02-09_RRHH_y_Nomina.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Parámetros (B) y Bancos (C) deben estar hechos. Las pantallas de Préstamos y Anticipos y de Liquidaciones **no se revisaron** y están fuera de alcance de esta versión: no las rediseñes.

**Tareas**
1. **Asistencia.** Dos pares de botones: "Registrar entrada / salida" y "Registrar entrada / salida de almuerzo". Resumen del día por colaborador (entrada, salida, almuerzo, atraso, horas extra). Vincular cada usuario con un colaborador activo (hoy el administrador muestra "Tu usuario no está vinculado a ningún colaborador activo"). El commit reciente "todos los empleados pueden marcar su asistencia" ya existe: partir de ahí, no duplicar.
2. **Colaboradores por empresa.** UIO: Luis (no en nómina), María, Daniel, Rubén, Edison, Fabián, Caisa. Import: Steeven. Faltan María del Carmen Rondal, Cesar Daniel Rondal y Steeven Arteaga. Fabián, Jhonny y Ángel facturan por servicio y se registran como proveedores, no en nómina. Cada colaborador pertenece a una sola empresa y la nómina nunca mezcla dos RUC. Campos obligatorios: empresa, cédula, nombres, cargo, departamento, fecha de ingreso, tipo de relación y contrato (DEC-020), afiliación IESS, sueldo base con vigencia, forma de pago (transferencia exige banco y cuenta) y estado.
3. **Departamentos.** Unir Contabilidad y Financiero en "Administración y Finanzas" y crear Recursos Humanos, Logística, Servicio Técnico, Eventos y Limpieza. Descripción obligatoria. Respetar la eliminación de colaboradores con verificación de dependencias ya implementada.
4. **Horas extras.** 50% en días laborables y 100% en nocturnas, fines de semana y feriados. Valor hora = sueldo ÷ 240 (DEC-018 pendiente). Máximo 4 h al día y 12 a la semana. Salen de las marcaciones, no se digitan. Solo las aprobadas pasan a nómina.
5. **Nómina mensual.** Flujo: Generar, Revisar, Procesar, Pagar. Estados: Borrador, Procesada y Pagada. Una procesada no se modifica; se anula con reverso. Ingresos: sueldo, horas extras, fondos de reserva (8,33% con más de 12 meses), alimentación (DEC-015) y otros. Egresos: IESS personal 9,45%, atrasos, préstamos y otros. Información para la empresa: aporte patronal 11,15% y provisiones.
6. **Asientos** PC-NOM-01 a 07 y 09 al procesar; PC-NOM-10 al pagar, con **banco o caja obligatorio**. Agregar las contrapartidas que faltan (módulo B). El `CLAUDE.md` indica que `NominaController::procesar()` y `pagar()` usan una sola transacción sin catch silencioso: mantener.
7. **Rol de pagos.** Encabezado (logo, empleador, RUC, nombre comercial, dirección por empresa, período), datos del colaborador, ingresos y egresos con su base de cálculo, información para la empresa, neto en números y letras, firmas y pie. Fondo blanco, una página A4 por colaborador, en PDF. Ejemplo de prueba con sueldo 482,00: IESS personal 45,55, patronal 53,73, fondos de reserva 40,17.
8. **Validaciones:** sin empresa no se procesa; transferencia sin banco no se guarda; neto negativo no se procesa; préstamo no descuenta más de su saldo.

**Pruebas:** RRHH-01 a RRHH-12. Faltan pruebas de asistencia: proponerlas.

**Pendientes del contador que no se implementan:** DEC-009 (participación de trabajadores), DEC-016 (fondos de reserva: pago), DEC-017 (décimo cuarto), DEC-019 (liquidaciones).

---

## Reglas comunes (aplican a este chat)

1. **Fuente de verdad.** Las cuentas contables viven solo en `01_Parametros_Contables`. Los módulos citan el ID (por ejemplo `PC-VEN-06`) y no copian códigos. Si falta una cuenta, se agrega al parámetro; no se escribe en el código a mano.
2. **Plan de cuentas.** El plan vigente según estos documentos es el **V4** (`Plan_de_cuentas_Final.xlsx`). El `CLAUDE.md` describe una migración previa al "Plan 2025" (`2026_10_05_100000_reemplazar_plan_cuentas_2025`), con formato `1.01.01.01`. Antes de tocar cuentas, el chat debe comprobar en la BD cuál plan está cargado y **avisar al usuario de cualquier diferencia**. No asumir.
3. **Base de datos.**
   - Solo la BD `altamira`. En producción el Postgres es compartido: no tocar `lideres` ni `sae_alt`.
   - **No modificar el esquema ni los datos de la tabla `productos`** (datos migrados del sistema legacy). Si una subcategoría necesita guardar sus cuentas, usar una tabla aparte y relacionarla, nunca columnas nuevas en `productos`.
   - Migraciones idempotentes: `Schema::hasTable()` / `Schema::hasColumn()` antes de crear o agregar. Seeders con `firstOrCreate()`.
   - Respetar los nombres reales de columnas de la tabla del `CLAUDE.md` y `public $timestamps = false;` en las tablas que no tienen timestamps.
4. **Contabilidad lista antes de operar (20/09/2026).** Las operaciones que generan asientos siguen bloqueándose si no hay período abierto o falta un parámetro (`AsientoService::validarConfiguracion()`). No revertir eso. Si se agregan eventos nuevos, hay que agregarlos a la validación.
5. **Asientos.** Siempre débito = crédito, montos con dos decimales, no escribir en cuentas padre (`Permite_Asientos = NO`), centro de costo obligatorio en cuentas de los grupos 4, 5 y 6.
6. **Exportaciones.** PDF y Excel se generan de forma síncrona. Solo el ZIP de nómina usa cola (`ExportarNominaZipJob`). No tocar eso.
7. **UI.** Inertia v2 + React 19 + TypeScript sin `any`. Colores con variables CSS, no clases fijas de Tailwind. Botones PDF/Excel al final de los filtros, unidos como `btn-group`. Rutas con Ziggy (`Tighten\Ziggy`).
8. **Pruebas.** Todas las pruebas de aceptación se ejecutan con una empresa o período de prueba, nunca con datos reales.
9. **Registro.** Al terminar, el chat resume: qué cambió, qué quedó pendiente y qué decisiones (DEC-xxx) bloquearon algo. No marcar nada como implementado sin haberlo probado.
10. **Decisiones abiertas.** Si una tarea depende de un DEC pendiente, **no inventar la regla**: implementar solo lo definido y dejar la parte pendiente señalada, o preguntar al usuario.
11. **Commits.** No hacer commit ni push sin que el usuario lo pida.
12. **Lectura de PDFs.** Los PDF de los módulos tienen 3 a 6 páginas y se leen completos con la herramienta Read. El 01 tiene 19 páginas y también se lee completo (sin el parámetro `pages`, porque falta `pdftoppm`).

---

## Lista de verificación al cerrar este chat

- [ ] Se leyó `CLAUDE.md` y el PDF del módulo.
- [ ] Migraciones idempotentes y probadas dos veces.
- [ ] Ninguna cuenta contable escrita a mano en código: todas salen de parámetros.
- [ ] Débito = crédito en todo asiento nuevo.
- [ ] Pruebas de aceptación del módulo ejecutadas y resultado anotado.
- [ ] `npm run build` sin errores de TypeScript.
- [ ] Lista de decisiones (DEC) que quedaron pendientes y por qué.
- [ ] Nada se marcó como "implementado" sin probarse.
- [ ] Sin commit ni push no pedidos.
