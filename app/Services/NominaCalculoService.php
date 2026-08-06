<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Colaborador;
use App\Models\HorasExtrasAprobacion;
use App\Models\PrestamoEmpleado;

// Cálculo del detalle de nómina de UN colaborador para UN período — extraído
// de NominaController::calcularDetalle() para poder reutilizarse también al
// crear un colaborador nuevo (debe aparecer automáticamente en cualquier
// nómina en borrador del período vigente, ver ColaboradorController::store()),
// sin duplicar esta lógica en dos controllers.
class NominaCalculoService
{
    public function calcularDetalle(Colaborador $col, array $data, int $nominaId): array
    {
        $esQuincenal = $data['periodo_tipo'] === 'quincenal';
        $anio        = (int) $data['anio'];
        $mes         = (int) $data['mes'];

        // Sueldo base (dividido a la mitad si es quincenal)
        $sueldo = $esQuincenal
            ? round((float) $col->sueldo_base / 2, 2)
            : (float) $col->sueldo_base;

        // Horas extras aprobadas del período
        $baseQuery = HorasExtrasAprobacion::where('colaborador_id', $col->id)
            ->where('estado', 'aprobado')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes);

        // Si es quincenal filtramos por días de la quincena
        if ($esQuincenal) {
            $diaInicio = $data['quincena'] === 1 ? 1 : 16;
            $diaFin    = $data['quincena'] === 1 ? 15 : (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
            $baseQuery->whereDay('fecha', '>=', $diaInicio)
                ->whereDay('fecha', '<=', $diaFin);
        }

        $extras50  = (float) (clone $baseQuery)->where('tipo', 'suplementaria')->sum('valor_calculado');
        $extras100 = (float) (clone $baseQuery)->where('tipo', 'extraordinaria')->sum('valor_calculado');

        // Décimos mensualizados (solo nómina mensual)
        $otrosIngresos = 0.0;
        if (!$esQuincenal) {
            $SBU = 460.0; // Salario Básico Unificado Ecuador 2026
            if ($col->decimo_tercero === 'mensualiza') {
                $otrosIngresos += round((float) $col->sueldo_base / 12, 2);
            }
            if ($col->decimo_cuarto === 'mensualiza') {
                $otrosIngresos += round($SBU / 12, 2);
            }
            if ($col->fondos_reserva === 'mensualiza') {
                // Fondos de reserva: aplica desde mes 13 de contrato.
                // abs(): diffInMonths() en Carbon 3 es firmado (negativo porque
                // fecha_ingreso siempre es anterior a now()); sin abs() esta condición
                // nunca se cumplía para ningún colaborador, sin importar su antigüedad.
                $mesesContrato = (int) abs(now()->diffInMonths($col->fecha_ingreso));
                if ($mesesContrato >= 13) {
                    $otrosIngresos += round((float) $col->sueldo_base * 0.0833, 2);
                }
            }
        }

        $totalIngresos = round($sueldo + $extras50 + $extras100 + $otrosIngresos, 2);

        // Aporte personal IESS 9.45%
        $aportePersonal = round($totalIngresos * 0.0945, 2);

        // Descuento por atrasos (minutos_atraso del período)
        $minutosAtraso = Asistencia::where('colaborador_id', $col->id)
            ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)->sum('minutos_atraso');

        // Valor por minuto = sueldo_base / (30 días * 8h * 60min)
        $descuentoAtraso = round((float) $minutosAtraso * ((float) $col->sueldo_base / 14400), 2);

        // Préstamos (cuota mensual o mitad si quincenal)
        $descuentoPrestamos = PrestamoEmpleado::cuotasMes($col->id, $data['periodo_tipo']);

        // Anticipos
        $descuentoAnticipos = 0.0; // Se descuentan en nómina mensual completa únicamente
        if (!$esQuincenal) {
            $descuentoAnticipos = PrestamoEmpleado::anticiposPendientes($col->id);
        }

        $totalEgresos = round($aportePersonal + $descuentoAtraso + $descuentoPrestamos + $descuentoAnticipos, 2);
        $netoPagar    = round($totalIngresos - $totalEgresos, 2);

        return [
            'nomina_id'              => $nominaId,
            'colaborador_id'         => $col->id,
            'sueldo_base'            => $sueldo,
            'horas_extras_50'        => $extras50,
            'horas_extras_100'       => $extras100,
            'comisiones'             => 0,
            'otros_ingresos'         => $otrosIngresos,
            'total_ingresos'         => $totalIngresos,
            'aporte_personal_iess'   => $aportePersonal,
            'descuento_atrasos'      => $descuentoAtraso,
            'descuento_prestamos'    => $descuentoPrestamos,
            'descuento_anticipos'    => $descuentoAnticipos,
            'otros_egresos'          => 0,
            'total_egresos'          => $totalEgresos,
            'neto_pagar'             => $netoPagar,
            'tipo_pago'              => $col->tipo_cuenta ? 'transferencia' : null,
            'num_cuenta'             => $col->numero_cuenta,
            'banco'                  => $col->banco,
            'estado'                 => 'borrador',
            'modificado_manualmente' => false,
            'created_at'             => now(),
        ];
    }
}
