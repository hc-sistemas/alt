<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Aislamiento por empresa (02-01 §2.1): un registro que pertenece a una empresa solo se puede abrir, editar,
 * anular o eliminar desde esa empresa. Se aplica donde Laravel convierte el ID de la URL en el modelo
 * ({factura}, {compra}, {cliente}…): con otra empresa activa el registro «no existe» y la respuesta es 404.
 *
 * Es una sola regla central para todas las rutas, en vez de recordar el filtro en cada método de cada
 * controlador. No aplica a catálogos compartidos (plan de cuentas, productos) ni a usuarios.
 */
trait PerteneceAEmpresaActiva
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $consulta = $this->where($field ?? $this->getRouteKeyName(), $value);
        $empresaId = session('empresa_activa_id');

        // Sin empresa activa no se encuentra nada (cerrado por defecto)
        return $consulta->where($this->getTable() . '.empresa_id', $empresaId ?: 0)->first();
    }
}
