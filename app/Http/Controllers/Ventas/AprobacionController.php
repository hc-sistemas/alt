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
            'tipo'   => 'required|string',
            'codigo' => 'required|string',
            'motivo' => 'nullable|string|max:500',
        ]);

        $empresaId = session('empresa_activa_id');
        $tipo      = $request->input('tipo');
        $codigo    = $request->input('codigo');

        $tipoAprobacion = DB::table('tipos_aprobacion')
            ->where('clave', $tipo)
            ->first();

        $perfilesAutorizados = $tipoAprobacion
            ? json_decode($tipoAprobacion->perfiles_autorizados ?? '[]', true)
            : [];

        if ($tipoAprobacion && !empty($perfilesAutorizados)) {
            $usuarios = DB::table('usuarios')
                ->join('perfiles', 'usuarios.perfil_id', '=', 'perfiles.id')
                ->where('usuarios.empresa_id', $empresaId)
                ->where('usuarios.estado', true)
                ->whereIn('perfiles.nombre', $perfilesAutorizados)
                ->whereNotNull('usuarios.codigo_aprobacion')
                ->select('usuarios.id', 'usuarios.codigo_aprobacion')
                ->get();
        } else {
            $usuarios = DB::table('usuarios')
                ->join('limites_descuento', 'limites_descuento.perfil_id', '=', 'usuarios.perfil_id')
                ->where('usuarios.empresa_id', $empresaId)
                ->where('usuarios.estado', true)
                ->where('limites_descuento.puede_aprobar', true)
                ->whereNotNull('usuarios.codigo_aprobacion')
                ->select('usuarios.id', 'usuarios.codigo_aprobacion')
                ->get();
        }

        foreach ($usuarios as $usuario) {
            if (Hash::check($codigo, $usuario->codigo_aprobacion)) {
                $aprobacionId = DB::table('aprobaciones_especiales')->insertGetId([
                    'empresa_id'        => $empresaId,
                    'tipo_aprobacion_id' => $tipoAprobacion->id ?? null,
                    'aprobado_por'      => $usuario->id,
                    'solicitado_por'    => auth()->id(),
                    'descripcion'       => $request->input('motivo'),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                return response()->json([
                    'valido'        => true,
                    'aprobacion_id' => $aprobacionId,
                ]);
            }
        }

        return response()->json([
            'valido'  => false,
            'mensaje' => 'Código incorrecto.',
        ], 422);
    }
}
