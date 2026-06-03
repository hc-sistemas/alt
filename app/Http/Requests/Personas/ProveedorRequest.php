<?php

namespace App\Http\Requests\Personas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = session('empresa_activa_id');
        $proveedorId = $this->route('proveedor')?->id;
        $tipo = $this->tipo;

        $rucRules = ['nullable', 'string', 'max:20'];
        if ($tipo === 'nacional') {
            $rucRules = [
                'required',
                'string',
                'regex:/^\d{10}(\d{3})?$/',
                Rule::unique('proveedores')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', 'nacional')
                )->ignore($proveedorId),
            ];
        }

        return [
            'tipo' => ['required', 'in:nacional,internacional'],
            'ruc_cedula' => $rucRules,
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'ciudad' => ['required_if:tipo,internacional', 'nullable', 'string', 'max:100'],
            'pais' => ['required_if:tipo,internacional', 'nullable', 'string', 'max:100'],
            'divisa' => ['required_if:tipo,internacional', 'nullable', 'string', 'max:10'],
            'tiene_credito' => ['boolean'],
            'dias_credito' => ['required_if:tiene_credito,true', 'nullable', 'integer', 'min:1'],
            'estado' => ['boolean'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruc_cedula.regex' => 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos)',
            'ciudad.required_if' => 'La ciudad es obligatoria para proveedores internacionales',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->tipo === 'nacional') {
            $this->merge(['pais' => 'Ecuador']);
        }
    }
}
