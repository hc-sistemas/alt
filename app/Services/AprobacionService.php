<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Utilidades comunes a las anulaciones/eliminaciones de documentos de venta
 * (proformas, prefacturas): PIN de aprobación especial y rol SuperAdmin.
 */
class AprobacionService
{
    public function esSuperAdmin(): bool
    {
        return DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', Auth::id())
            ->value('perfiles.nombre') === 'super_admin';
    }

    /**
     * Aprobación válida para anular: pedida por el usuario actual, del tipo
     * indicado y todavía sin consumir. Devuelve la fila o null.
     */
    public function disponible(int $aprobacionId, string $tipoClave): ?object
    {
        return DB::table('aprobaciones_especiales')
            ->join('tipos_aprobacion', 'tipos_aprobacion.id', '=', 'aprobaciones_especiales.tipo_aprobacion_id')
            ->where('aprobaciones_especiales.id', $aprobacionId)
            ->where('aprobaciones_especiales.solicitado_por', Auth::id())
            ->where('tipos_aprobacion.clave', $tipoClave)
            ->whereNull('aprobaciones_especiales.registro_id')
            ->select('aprobaciones_especiales.id')
            ->first();
    }

    /** Marca la aprobación como consumida por el documento. */
    public function consumir(int $aprobacionId, string $tabla, int $registroId): void
    {
        DB::table('aprobaciones_especiales')
            ->where('id', $aprobacionId)
            ->update([
                'tabla_referencia' => $tabla,
                'registro_id'      => $registroId,
                'updated_at'       => now(),
            ]);
    }
}
