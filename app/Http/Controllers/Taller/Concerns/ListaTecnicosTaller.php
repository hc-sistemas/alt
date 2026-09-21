<?php

namespace App\Http\Controllers\Taller\Concerns;

use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

trait ListaTecnicosTaller
{
    /**
     * Usuarios con perfil "tecnico" que tienen acceso a la empresa activa.
     * Si ninguno cumple, se ofrecen todos los usuarios activos de la empresa
     * (mismo criterio en el listado, el ingreso y el detalle de la OT).
     */
    private function listaTecnicos(): Collection
    {
        $empresaId = session('empresa_activa_id');

        $deEmpresa = fn() => Usuario::where('estado', true)
            ->whereHas('empresas', fn($q) => $q->where('empresas.id', $empresaId))
            ->select('id', 'nombre')
            ->orderBy('nombre');

        $tecnicos = $deEmpresa()
            ->whereHas('perfil', fn($q) => $q->where('nombre', 'tecnico'))
            ->get();

        return $tecnicos->isNotEmpty() ? $tecnicos : $deEmpresa()->get();
    }
}
