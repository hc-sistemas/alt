# Instrucciones de ejecución por módulo

Un archivo por módulo, cada uno para un chat distinto. Cada archivo trae su prompt de arranque, tareas, pruebas, pendientes y las reglas comunes.

Fuentes: PDFs y Excel de `docs/Oct2026/`.

## Orden recomendado

| Orden | Módulo | Por qué en ese orden |
|---|---|---|
| 1 | A. Configuración general | Centros de costo, permisos y límites los usan todos |
| 2 | B. Parámetros contables y plan de cuentas | Todos los asientos dependen de esto |
| 3 | C. Bancos y conciliación | Ventas, Caja, Tarjetas y Nómina escriben aquí |
| 4 | D. Caja | Depende de Bancos (depósitos) |
| 5 | E. Tarjetas | Depende de Bancos y Ventas |
| 6 | F. Inventario | Las categorías definen las cuentas de Ventas y Compras |
| 7 | G. Compras e importaciones | Depende de Inventario, Bancos y Parámetros |
| 8 | H. Ventas y cobranza | Depende de Inventario, Bancos, Caja y Tarjetas |
| 9 | I. Contabilidad | Cierres y reportes dependen de todo lo anterior |
| 10 | J. RRHH y nómina | Depende de Parámetros y Bancos |
| 11 | K. Taller | Depende de Inventario y Ventas |

Los módulos 1 y 2 deben terminar primero. Del 3 en adelante se puede paralelizar respetando las dependencias de la tabla. Cada chat debe tocar solo los archivos de su módulo para no pisarse con otro.

## Archivos

- [A_configuracion_general.md](A_configuracion_general.md)
- [B_parametros_contables.md](B_parametros_contables.md)
- [C_bancos_conciliacion.md](C_bancos_conciliacion.md)
- [D_caja.md](D_caja.md)
- [E_tarjetas.md](E_tarjetas.md)
- [F_inventario.md](F_inventario.md)
- [G_compras_importaciones.md](G_compras_importaciones.md)
- [H_ventas_cobranza.md](H_ventas_cobranza.md)
- [I_contabilidad.md](I_contabilidad.md)
- [J_rrhh_nomina.md](J_rrhh_nomina.md)
- [K_taller.md](K_taller.md)
