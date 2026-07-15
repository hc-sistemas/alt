<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8.5px; color:#1A1A2E; padding:20px 24px; }

.header { display:table; width:100%; margin-bottom:14px; padding-bottom:10px; border-bottom:3px solid #1A1A2E; }
.h-left  { display:table-cell; vertical-align:middle; width:65%; }
.h-right { display:table-cell; vertical-align:middle; text-align:right; width:35%; }
.empresa  { font-size:15px; font-weight:bold; }
.titulo   { font-size:12px; font-weight:bold; margin-top:3px; color:#1F2D3D; }
.sub      { font-size:7.5px; color:#555770; margin-top:2px; }
.badge    { display:inline-block; background:#E11D48; color:white; font-size:8px;
            font-weight:bold; padding:3px 10px; border-radius:3px; letter-spacing:.5px; }

.toc { border:1px solid #E8EAF0; border-radius:6px; padding:10px 14px; margin-bottom:14px;
       background:#F9FAFB; }
.toc-title { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px;
             color:#555770; margin-bottom:6px; }
.toc-item { display:table; width:100%; padding:2px 0; }
.toc-num  { display:table-cell; width:20px; font-weight:bold; color:#E11D48; font-size:8px; }
.toc-nom  { display:table-cell; font-size:8px; color:#1A1A2E; }
.toc-dot  { display:table-cell; border-bottom:1px dotted #CCC; }
.toc-pag  { display:table-cell; width:20px; text-align:right; font-size:7.5px; color:#888; }

.sec { margin-bottom:16px; }
.sec-header { display:table; width:100%; background:#1F2D3D; color:white; padding:6px 10px;
              margin-bottom:0; border-radius:4px 4px 0 0; }
.sec-num   { display:table-cell; width:30px; font-size:11px; font-weight:bold; color:#F59E0B; vertical-align:middle; }
.sec-title { display:table-cell; font-size:10px; font-weight:bold; vertical-align:middle; }
.sec-sub   { display:table-cell; text-align:right; font-size:7px; color:#9CA3AF; vertical-align:middle; }
.sec-body  { border:1px solid #D8DCE6; border-top:none; padding:10px 12px;
             border-radius:0 0 4px 4px; background:white; }

.step { display:table; width:100%; margin-bottom:6px; padding:5px 8px;
        border:1px solid #E8EAF0; border-radius:4px; background:#FAFAFA; }
.step-num  { display:table-cell; width:22px; height:22px; border-radius:50%;
             background:#E11D48; color:white; font-weight:bold; font-size:8px;
             text-align:center; vertical-align:middle; }
.step-body { display:table-cell; padding-left:8px; vertical-align:middle; }
.step-title { font-weight:bold; font-size:8px; color:#1A1A2E; }
.step-desc  { font-size:7.5px; color:#555770; margin-top:1px; }

.tip  { background:#FEF9EC; border-left:3px solid #F59E0B; padding:5px 10px;
        margin:6px 0; border-radius:0 4px 4px 0; font-size:7.5px; color:#92400E; }
.info { background:#EFF6FF; border-left:3px solid #3B82F6; padding:5px 10px;
        margin:6px 0; border-radius:0 4px 4px 0; font-size:7.5px; color:#1E40AF; }

table.tabla { width:100%; border-collapse:collapse; margin:6px 0; }
table.tabla thead th { background:#1F2D3D; color:white; padding:4px 8px; font-size:7px;
                       font-weight:bold; text-align:left; text-transform:uppercase; }
table.tabla thead th.r { text-align:right; }
table.tabla tbody td { padding:4px 8px; border-bottom:1px solid #E8EAF0; font-size:7.5px; }
table.tabla tbody td.cod { font-family:monospace; font-weight:bold; color:#E11D48; }
table.tabla tbody td.badge-ok  { text-align:center; }
table.tabla tbody tr:nth-child(even) { background:#F9FAFB; }

.badge-ok  { display:inline-block; background:#DCFCE7; color:#166534; font-size:6.5px;
             font-weight:bold; padding:1px 6px; border-radius:8px; }
.badge-warn { display:inline-block; background:#FEF9C3; color:#854D0E; font-size:6.5px;
              font-weight:bold; padding:1px 6px; border-radius:8px; }

.grid2 { display:table; width:100%; }
.g2l   { display:table-cell; width:49%; vertical-align:top; padding-right:6px; }
.g2r   { display:table-cell; width:49%; vertical-align:top; padding-left:6px; }

.footer { margin-top:16px; padding-top:6px; border-top:1px solid #D8DCE6; display:table; width:100%; }
.f-left  { display:table-cell; font-size:6px; color:#888; }
.f-right { display:table-cell; text-align:right; font-size:6px; color:#888; }
</style>
</head>
<body>

<!-- CABECERA -->
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->razon_social ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Manual de RRHH — Recursos Humanos</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} &nbsp;·&nbsp; ERP Altamira v2.0 &nbsp;·&nbsp; Generado: {{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="h-right">
        <span class="badge">RRHH</span>
        <div class="sub" style="margin-top:6px;">
            6 secciones · Colaboradores, Asistencia, Horas Extras,<br>
            Nómina, Liquidaciones y Préstamos
        </div>
    </div>
</div>

<!-- TABLA DE CONTENIDO -->
<div class="toc">
    <div class="toc-title">Contenido</div>
    <div class="toc-item">
        <div class="toc-num">1.</div>
        <div class="toc-nom">Colaboradores — Registro y gestión de personal</div>
        <div class="toc-dot"></div>
        <div class="toc-pag">1</div>
    </div>
    <div class="toc-item">
        <div class="toc-num">2.</div>
        <div class="toc-nom">Asistencia — Registro de entrada y salida diaria</div>
        <div class="toc-dot"></div>
        <div class="toc-pag">1</div>
    </div>
    <div class="toc-item">
        <div class="toc-num">3.</div>
        <div class="toc-nom">Horas Extras — Solicitud, aprobación y rechazo</div>
        <div class="toc-dot"></div>
        <div class="toc-pag">2</div>
    </div>
    <div class="toc-item">
        <div class="toc-num">4.</div>
        <div class="toc-nom">Nómina — Generación, procesamiento y pagos</div>
        <div class="toc-dot"></div>
        <div class="toc-pag">2</div>
    </div>
    <div class="toc-item">
        <div class="toc-num">5.</div>
        <div class="toc-nom">Liquidaciones — Cálculo de finiquito al terminar la relación laboral</div>
        <div class="toc-dot"></div>
        <div class="toc-pag">3</div>
    </div>
    <div class="toc-item">
        <div class="toc-num">6.</div>
        <div class="toc-nom">Préstamos a Empleados — Registro y descuento por rol</div>
        <div class="toc-dot"></div>
        <div class="toc-pag">3</div>
    </div>
</div>

<!-- SECCIÓN 1: COLABORADORES -->
<div class="sec">
    <div class="sec-header">
        <div class="sec-num">1</div>
        <div class="sec-title">Colaboradores</div>
        <div class="sec-sub">Menú: RRHH → Colaboradores</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Módulo para gestionar el padrón de empleados activos de la empresa. Cada colaborador
            tiene datos de contacto, cargo, fecha de ingreso y estado laboral.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Registrar nuevo colaborador</div>
                        <div class="step-desc">Clic en <strong>+ Nuevo</strong>. Completar: nombre completo, cédula/RUC, cargo, departamento, fecha de ingreso, teléfono, email.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Editar datos</div>
                        <div class="step-desc">Clic en el ícono de edición (<strong>✎</strong>) de la fila correspondiente. Modificar los campos necesarios y guardar.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">Activar / Desactivar colaborador</div>
                        <div class="step-desc">Usar el toggle de <strong>Estado</strong>. Los colaboradores inactivos no aparecen en nómina ni asistencia.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <div class="step-title">Filtros de búsqueda</div>
                        <div class="step-desc">Buscar por nombre, cédula o cargo con la barra de búsqueda superior. Filtrar por estado activo/inactivo.</div>
                    </div>
                </div>
            </div>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Descripción</th>
                    <th>Requerido</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="cod">nombre</td>
                    <td>Nombre completo del colaborador</td>
                    <td class="badge-ok"><span class="badge-ok">Sí</span></td>
                </tr>
                <tr>
                    <td class="cod">identificacion</td>
                    <td>Cédula o RUC (10 o 13 dígitos)</td>
                    <td class="badge-ok"><span class="badge-ok">Sí</span></td>
                </tr>
                <tr>
                    <td class="cod">cargo</td>
                    <td>Puesto de trabajo dentro de la empresa</td>
                    <td class="badge-ok"><span class="badge-ok">Sí</span></td>
                </tr>
                <tr>
                    <td class="cod">sueldo_base</td>
                    <td>Salario mensual base (usado en nómina)</td>
                    <td class="badge-ok"><span class="badge-ok">Sí</span></td>
                </tr>
                <tr>
                    <td class="cod">fecha_ingreso</td>
                    <td>Fecha de inicio de la relación laboral</td>
                    <td class="badge-ok"><span class="badge-ok">Sí</span></td>
                </tr>
                <tr>
                    <td class="cod">horario_id</td>
                    <td>Horario oficial asignado (ver recuadro debajo)</td>
                    <td><span class="badge-warn">No</span></td>
                </tr>
                <tr>
                    <td class="cod">decimo_tercero / decimo_cuarto / fondos_reserva</td>
                    <td>Cada uno: <strong>acumula</strong> (se paga en la fecha legal) o <strong>mensualiza</strong> (se paga prorrateado cada mes junto al sueldo)</td>
                    <td><span class="badge-warn">No</span></td>
                </tr>
            </tbody>
        </table>

        <div class="tip"><strong>Importante:</strong> El sueldo base del colaborador es el valor que se usa automáticamente al generar la nómina mensual. Mantenerlo actualizado evita errores de liquidación.</div>

        <div class="info" style="margin-top:8px;">
            <strong>Horario del colaborador (control de atrasos y horas extras):</strong> en el desplegable <strong>Horario</strong> del formulario se elige una de las plantillas ya creadas (hora de entrada, hora de salida y minutos de tolerancia). Si el horario que se necesita todavía no existe, el botón <strong>Nuevo horario</strong> abre un mini-formulario dentro del mismo modal — al guardar, la plantilla queda creada y asignada automáticamente al colaborador sin salir de la ficha. Este horario es el que usan los módulos de <strong>Asistencia</strong> y <strong>Horas Extras</strong> para calcular minutos de atraso y horas extra; un colaborador sin horario asignado ("— Sin horario —") no acumula atrasos ni horas extra automáticas. El horario se puede cambiar en cualquier momento editando la ficha — el cambio aplica de inmediato a los cálculos futuros (no afecta registros de asistencia ya guardados).
        </div>

        <div class="info" style="margin-top:6px;">
            <strong>Acceso al sistema (Usuario vinculado):</strong> en la sección "Seguridad y Sistema" del formulario se puede crear, en el mismo paso, el usuario del ERP para ese colaborador (username, contraseña y perfil de permisos). Si el colaborador se desactiva (o se aprueba su liquidación), su usuario se <strong>bloquea automáticamente</strong> — no hace falta ir a Configuración → Usuarios a bloquearlo aparte.
        </div>
    </div>
</div>

<!-- SECCIÓN 2: ASISTENCIA -->
<div class="sec">
    <div class="sec-header">
        <div class="sec-num">2</div>
        <div class="sec-title">Asistencia</div>
        <div class="sec-sub">Menú: RRHH → Asistencia</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Control diario de entradas y salidas del personal. El sistema registra la hora
            exacta de cada evento y calcula automáticamente las horas trabajadas.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Registrar entrada</div>
                        <div class="step-desc">Seleccionar el colaborador en el desplegable y pulsar <strong>Registrar Entrada</strong>. El sistema guarda la hora actual automáticamente.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Registrar salida</div>
                        <div class="step-desc">Al finalizar la jornada, seleccionar el colaborador y pulsar <strong>Registrar Salida</strong>. Se calcula el tiempo trabajado.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">Ver historial</div>
                        <div class="step-desc">El listado muestra todos los registros del día y semana. Filtrar por colaborador o rango de fechas para ver períodos anteriores.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <div class="step-title">Validaciones</div>
                        <div class="step-desc">No se puede registrar salida sin entrada previa. El sistema advierte si hay registros duplicados para el mismo día.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="info"><strong>Nota:</strong> Los registros de asistencia son usados como referencia para el cálculo de horas extras. Un colaborador sin registro de entrada ese día no puede tener horas extras aprobadas automáticamente.</div>
    </div>
</div>

<!-- SECCIÓN 3: HORAS EXTRAS -->
<div class="sec">
    <div class="sec-header">
        <div class="sec-num">3</div>
        <div class="sec-title">Horas Extras</div>
        <div class="sec-sub">Menú: RRHH → Horas Extras</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Gestión de horas adicionales trabajadas fuera de la jornada ordinaria.
            Las horas extras aprobadas se incluyen automáticamente en la nómina del período.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Ver solicitudes pendientes</div>
                        <div class="step-desc">La tabla muestra todas las horas extras con estado <strong>Pendiente</strong>. Se indica colaborador, fecha, horas y motivo.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Aprobar horas extras</div>
                        <div class="step-desc">Clic en <strong>Aprobar</strong> (✓ verde). Las horas quedan en estado Aprobado y se incluirán en la próxima nómina.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">Rechazar horas extras</div>
                        <div class="step-desc">Clic en <strong>Rechazar</strong> (✗ rojo). Se puede agregar un motivo de rechazo para notificar al colaborador.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <div class="step-title">Tipos de horas extras</div>
                        <div class="step-desc">Ecuador: 50% suplementarias (hasta 4h), 100% extraordinarias (nocturnas/fines de semana). El sistema calcula el valor automáticamente.</div>
                    </div>
                </div>
            </div>
        </div>

        <table class="tabla">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Recargo</th>
                    <th>Aplicación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Suplementarias</strong></td>
                    <td>50%</td>
                    <td>Lun-Vie entre 24h00 y hasta 4h adicionales</td>
                </tr>
                <tr>
                    <td><strong>Extraordinarias</strong></td>
                    <td>100%</td>
                    <td>Sábados, domingos, feriados o nocturnas</td>
                </tr>
            </tbody>
        </table>

        <div class="tip" style="margin-top:8px;"><strong>Candados legales (Código de Trabajo):</strong> el sistema bloquea automáticamente la aprobación si se supera el máximo permitido — <strong>4 horas por día</strong> y <strong>12 horas por semana</strong> por colaborador. Si ya se aprobaron horas esa semana, el formulario muestra cuántas horas quedan disponibles antes de intentar aprobar una solicitud nueva.</div>

        <div class="info" style="margin-top:6px;"><strong>Desglose visual 50%/100%:</strong> tanto en Horas Extras como en el detalle de Nómina, un ícono de reloj abre un modal que separa cuántas horas de esa solicitud se pagan al 50% (suplementarias) y cuántas al 100% (extraordinarias), con el valor en dólares de cada tramo — útil para verificar el cálculo antes de aprobar o de procesar la nómina.</div>
    </div>
</div>

<!-- SECCIÓN 4: NÓMINA -->
<div class="sec">
    <div class="sec-header">
        <div class="sec-num">4</div>
        <div class="sec-title">Nómina</div>
        <div class="sec-sub">Menú: RRHH → Nómina</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Generación mensual de roles de pago. El sistema calcula automáticamente ingresos,
            descuentos (IESS, préstamos, anticipos) y genera PDFs individuales o masivos.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Generar nómina del período</div>
                        <div class="step-desc">Seleccionar mes/año y pulsar <strong>Generar Nómina</strong>. El sistema crea líneas de detalle para todos los colaboradores activos.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Revisar y ajustar líneas</div>
                        <div class="step-desc">Abrir la nómina generada. Cada línea muestra sueldo base + HH.EE - descuentos. Se puede editar manualmente cualquier valor.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">Procesar nómina</div>
                        <div class="step-desc">Pulsar <strong>Procesar</strong> para cerrar la nómina al cálculo. En este paso el sistema genera automáticamente el <strong>asiento contable</strong> (Sueldos y Aporte Patronal al DEBE; IESS Personal, IESS Patronal, Préstamos y Nómina por Pagar al HABER) — no es necesario crearlo a mano en Contabilidad. Después de procesar, los valores quedan bloqueados para edición.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num">4</div>
                    <div class="step-body">
                        <div class="step-title">Registrar pagos</div>
                        <div class="step-desc">Pulsar <strong>Pagar</strong> para marcar la nómina como pagada. Registra la fecha de pago y el método (transferencia, efectivo, etc.). Este paso es el movimiento de caja/banco — el asiento contable de la obligación ya se generó al Procesar.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">5</div>
                    <div class="step-body">
                        <div class="step-title">PDF individual (Rol de Pago)</div>
                        <div class="step-desc">En el detalle de cada colaborador, clic en <strong>PDF</strong> para descargar el rol de pago individual firmado.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">6</div>
                    <div class="step-body">
                        <div class="step-title">ZIP masivo de roles</div>
                        <div class="step-desc">Clic en <strong>Descargar ZIP</strong> para obtener todos los roles de pago de la nómina en un solo archivo comprimido.</div>
                    </div>
                </div>
            </div>
        </div>

        <table class="tabla" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Componente</th>
                    <th>Tipo</th>
                    <th>Cálculo automático</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Sueldo base</strong></td>
                    <td><span class="badge-ok">Ingreso</span></td>
                    <td>Tomado del perfil del colaborador</td>
                </tr>
                <tr>
                    <td><strong>Horas extras</strong></td>
                    <td><span class="badge-ok">Ingreso</span></td>
                    <td>HH.EE aprobadas × valor hora × recargo (50%/100%)</td>
                </tr>
                <tr>
                    <td><strong>Aporte IESS personal</strong></td>
                    <td><span class="badge-warn">Descuento</span></td>
                    <td>9.45% del sueldo imponible</td>
                </tr>
                <tr>
                    <td><strong>Impuesto a la Renta</strong></td>
                    <td><span class="badge-warn">Descuento</span></td>
                    <td>Según tabla progresiva SRI (si aplica)</td>
                </tr>
                <tr>
                    <td><strong>13° sueldo (proporcional)</strong></td>
                    <td><span class="badge-ok">Ingreso</span></td>
                    <td>Sueldo ÷ 12 (provisión mensual)</td>
                </tr>
                <tr>
                    <td><strong>14° sueldo (proporcional)</strong></td>
                    <td><span class="badge-ok">Ingreso</span></td>
                    <td>SBU ÷ 12 (provisión mensual)</td>
                </tr>
                <tr>
                    <td><strong>Vacaciones (provisión)</strong></td>
                    <td><span class="badge-ok">Ingreso</span></td>
                    <td>Sueldo ÷ 24 (15 días anuales)</td>
                </tr>
            </tbody>
        </table>

        <div class="tip" style="margin-top:8px;"><strong>Flujo de estados de nómina:</strong> Borrador → Procesada → Pagada. Una vez Pagada, la nómina no puede eliminarse ni modificarse. Para correcciones usar ajuste manual en la siguiente nómina.</div>

        <div class="info" style="margin-top:6px;"><strong>Formatos de exportación disponibles:</strong> PDF individual por colaborador y ZIP masivo con todos los roles de la nómina. Por ahora no existe una exportación tabular a Excel de la nómina completa (útil para entregar a Contabilidad en hoja de cálculo); si se necesita, es una mejora pendiente a solicitar.</div>
    </div>
</div>

<!-- SECCIÓN 5: LIQUIDACIONES -->
<div class="sec">
    <div class="sec-header">
        <div class="sec-num">5</div>
        <div class="sec-title">Liquidaciones (Finiquitos)</div>
        <div class="sec-sub">Menú: RRHH → Liquidaciones</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Cálculo del finiquito legal al terminar la relación laboral con un colaborador:
            décimos pendientes, vacaciones no gozadas y fondos de reserva proporcionales al tiempo trabajado.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Calcular</div>
                        <div class="step-desc">Elegir el colaborador a liquidar. El sistema calcula automáticamente décimo tercero, décimo cuarto, vacaciones y fondos de reserva proporcionales a los meses realmente trabajados.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Guardar borrador</div>
                        <div class="step-desc">Revisar los valores calculados y guardar como borrador. En este estado todavía se puede recalcular o corregir antes de confirmar.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-body">
                        <div class="step-title">Aprobar</div>
                        <div class="step-desc">Al aprobar la liquidación: el colaborador queda <strong>desactivado</strong>, su <strong>usuario del sistema se bloquea automáticamente</strong>, se genera el <strong>asiento contable</strong> del finiquito (vinculado a la liquidación para trazabilidad) y se emite el <strong>PDF del finiquito</strong> firmado.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tip"><strong>Importante:</strong> una liquidación aprobada no se puede deshacer desde la ficha — si el colaborador vuelve a trabajar en la empresa, debe registrarse como un colaborador nuevo con una nueva fecha de ingreso.</div>
    </div>
</div>

<!-- SECCIÓN 6: PRÉSTAMOS A EMPLEADOS -->
<div class="sec">
    <div class="sec-header">
        <div class="sec-num">6</div>
        <div class="sec-title">Préstamos a Empleados</div>
        <div class="sec-sub">Menú: RRHH → Préstamos</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Registro de préstamos o anticipos entregados a colaboradores, con descuento automático
            de la cuota mensual en el rol de pago hasta saldar el préstamo completo.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-body">
                        <div class="step-title">Registrar préstamo</div>
                        <div class="step-desc">Elegir colaborador, monto total y número de cuotas (o valor de cuota mensual). El sistema calcula el saldo pendiente.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-body">
                        <div class="step-title">Descuento automático en nómina</div>
                        <div class="step-desc">Mientras el préstamo tenga saldo pendiente, cada nómina que se procese descuenta la cuota correspondiente y reduce el saldo — sin necesidad de registrarlo manualmente cada mes.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="info"><strong>Nota:</strong> el saldo del préstamo se actualiza en el mismo momento en que se procesa la nómina (no al pagarla), manteniendo el saldo siempre consistente con el asiento contable generado.</div>
    </div>
</div>

<!-- FOOTER -->
<div class="footer">
    <div class="f-left">ERP Altamira · Manual de RRHH · {{ $empresa->razon_social ?? 'Altamira' }}</div>
    <div class="f-right">Versión 2.0 · Generado: {{ now()->format('d/m/Y H:i') }}</div>
</div>

</body>
</html>
