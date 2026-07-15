<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AprobacionController extends Controller
{
    public function validar(Request $request): JsonResponse
    {
        $request->validate([
            'tipo'       => 'required|string',
            'codigo'     => 'required|string',
            'motivo'     => 'nullable|string|max:500',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
        ]);

        $empresaId  = session('empresa_activa_id');
        $tipo       = $request->input('tipo');
        $codigo     = $request->input('codigo');
        $porcentaje = $request->filled('porcentaje') ? (float) $request->input('porcentaje') : null;

        $tipoAprobacion = DB::table('tipos_aprobacion')
            ->where('clave', $tipo)
            ->where('activo', true)
            ->first();

        if (!$tipoAprobacion) {
            return response()->json([
                'valido'  => false,
                'mensaje' => 'Tipo de aprobación no configurado.',
            ], 422);
        }

        // Candidatos: cualquier usuario activo de la empresa con código de
        // aprobación configurado. La capacidad real de aprobar (puede_aprobar
        // y el % hasta el que puede hacerlo) se valida DESPUÉS de identificar
        // quién ingresó el código, para poder dar un mensaje claro y distinto
        // entre "código incorrecto" y "sin permisos suficientes".
        $candidatos = DB::table('usuarios')
            ->leftJoin('limites_descuento', 'limites_descuento.perfil_id', '=', 'usuarios.perfil_id')
            ->where('usuarios.empresa_id', $empresaId)
            ->where('usuarios.estado', true)
            ->whereNotNull('usuarios.codigo_aprobacion')
            ->select(
                'usuarios.id',
                'usuarios.codigo_aprobacion',
                'limites_descuento.puede_aprobar',
                'limites_descuento.porcentaje_aprobacion_max'
            )
            ->get();

        $aprobador = null;
        foreach ($candidatos as $candidato) {
            if (Hash::check($codigo, $candidato->codigo_aprobacion)) {
                $aprobador = $candidato;
                break;
            }
        }

        if (!$aprobador) {
            return response()->json([
                'valido'  => false,
                'mensaje' => 'Código incorrecto.',
            ], 422);
        }

        if (!$aprobador->puede_aprobar) {
            return response()->json([
                'valido'  => false,
                'mensaje' => 'Este usuario no tiene permisos para aprobar excepciones.',
            ], 422);
        }

        $maxAprobador = (float) $aprobador->porcentaje_aprobacion_max;

        if ($porcentaje !== null && $maxAprobador < $porcentaje) {
            return response()->json([
                'valido'  => false,
                'mensaje' => "El límite de aprobación de este usuario ({$maxAprobador}%) es menor al solicitado ({$porcentaje}%).",
            ], 422);
        }

        $aprobacionId = DB::table('aprobaciones_especiales')->insertGetId([
            'tipo_aprobacion_id' => $tipoAprobacion->id,
            'aprobado_por'       => $aprobador->id,
            'solicitado_por'     => auth()->id(),
            'empresa_id'         => $empresaId,
            'descripcion'        => $request->input('motivo'),
            // Si se conoce el % exacto solicitado se aprueba solo hasta ahí;
            // si no (aprobación previa a conocer el detalle línea por línea),
            // se registra el tope real que este aprobador puede autorizar.
            'valor_aprobado'     => $porcentaje ?? $maxAprobador,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return response()->json([
            'valido'        => true,
            'aprobacion_id' => $aprobacionId,
        ]);
    }
}
