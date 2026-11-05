# Módulo C — Bancos y conciliación

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-06_Bancos_y_Conciliacion.pdf`, `01` §5.3. Decisiones: DEC-007, 011, 026, 027, 037, 038, 041, 042, 044.

**Prompt de arranque:**
> Implementa el módulo "Bancos y Conciliación" de Altamira ERP según `docs/Oct2026/02-06_Bancos_y_Conciliacion.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. El módulo de Parámetros contables (B) y el de Configuración (A) deben estar terminados; si no lo están, dímelo antes de empezar. Hoy los bancos solo existen como cuentas y no mueven información: parte de ahí.

**Tareas**
1. **Estructura.** Una sola entrada de menú con cuatro pestañas: Cuentas, Movimientos, Conciliación y Tarjetas. Reemplaza los módulos de Bancos y de Conciliación actuales.
2. **Cuentas.** Nombre, tipo (corriente o ahorros), número de cuenta y saldo inicial. La cuenta contable se asigna sola y no es editable:

   | Banco | Tipo | Cuenta |
   |---|---|---|
   | Banco Pichincha | Corriente | 1.01.02.01.01 |
   | ProduBanco | Ahorros | 1.01.02.02.01 |
   | Banco de Guayaquil | Ahorros | 1.01.02.02.02 |
   | Bancos del exterior | — | 1.01.02.03 |

   Si el banco no tiene cuenta para su tipo, no se guarda. El saldo es saldo inicial más movimientos no anulados, nunca digitado. El saldo inicial se registra con asiento de apertura (contrapartida pendiente: DEC-011).
3. **Movimientos.** Cada pago con transferencia, cheque o tarjeta liquidada crea un movimiento en el banco elegido. Banco obligatorio en transferencia y cheque. Referencia obligatoria. **Prefactura y nota de venta no generan movimiento.** Anulación: queda marcado como anulado, no se borra y no cuenta en saldo ni conciliación. El libro muestra fecha, concepto, referencia, monto, debe, haber, saldo, conciliado (Sí/No), fecha de conciliación y comentario.
4. **Conciliación.** Elegir banco y fecha de corte, ingresar el saldo del estado de cuenta, importar CSV/Excel con plantilla por banco y emparejar por monto y fecha. Dos grupos de pendientes: del libro y no en el banco (cheques no cobrados, depósitos en tránsito) y del banco y no en el libro (notas de débito y crédito). Cálculo: `saldo libro + depósitos en tránsito − cheques no cobrados ± notas del banco = saldo según banco`. Con diferencia cero, el período queda conciliado y no se modifica sin anular la conciliación; con diferencia, exige observación y va a 6.01.14. Las notas se registran desde la misma pantalla (comisión a 6.01.10, intereses a 4.03.01), crean el movimiento y quedan conciliadas.
5. **Cheques emitidos.** Campos: banco, ¿qué paga? (cuenta por pagar u otro pago), cuenta por pagar, categoría, N° de cheque (único por banco), monto, beneficiario, fecha de emisión, fecha de cobro (si es posterior, post fechado) y observación. Se eliminan "Banco emisor" y "N° Cuenta". Descuenta el saldo al emitirse; pasa a "cobrado" solo al conciliar. Anular genera reverso y el número queda como anulado. No puede superar el saldo pendiente de la factura. Asientos: PC-BAN-17 y PC-BAN-19. El de "otro pago" (PC-BAN-18) está pendiente (DEC-027).
6. **Configuración de cobros.** Quitar la opción "No registrar en Bancos" para transferencia y cheque. En tarjeta, confirmar la opción "por liquidar" (envía a 1.01.03.04 y deja pendiente en Tarjetas). Definir o eliminar la "caja por defecto". Agregar caja y banco de Altamira Import (Quinche), mostrar la cuenta contable antes de guardar, aplicar los cambios solo a facturas nuevas y mostrar qué facturas usan cada configuración. Hoy la configuración está en la tabla `configuraciones` (claves `cobro_*`) y `CobroBancoService`: reutilizar, no duplicar.
7. **Reportes.** Ajustar los 3 existentes (estado de cuenta con saldo inicial/final y conciliado, movimientos con filtro por tipo y documento de origen, reporte de caja con los depósitos). Agregar 8: conciliación bancaria, cheques pendientes de cobro, cheques post fechados recibidos, tarjetas por liquidar, liquidaciones de tarjetas, saldos consolidados, libro frente a mayor y diferencias de conciliación.
8. **Validaciones.** Débito = crédito; dos decimales; movimiento conciliado no se modifica; no se permite saldo negativo sin confirmación del usuario (DEC-041 está pendiente: implementar la advertencia con confirmación y avisarlo).

**Pruebas:** BAN-01 a BAN-11, CHQ-01 a CHQ-05 y CCO-01 a CCO-04 (tabla 6 del PDF). BAN-04 pide "y el saldo debe subir en cuentas por cobrar": está en revisión (DEC-044); validar solo el movimiento en el banco.

**No hacer:** liquidar lotes de tarjeta (módulo E), cheques recibidos de clientes (módulo H), la caja (módulo D).

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
