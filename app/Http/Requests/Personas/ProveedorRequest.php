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

        $identificacionRules = ['nullable', 'string', 'max:20'];
        if ($tipo === 'nacional') {
            $identificacionRules = [
                'required',
                'string',
                'regex:/^\d{10}(\d{3})?$/',
                Rule::unique('proveedores', 'identificacion')->where(fn($q) => $q
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', 'nacional')
                )->ignore($proveedorId),
            ];
        }

        return [
            'tipo'             => ['required', 'in:nacional,internacional'],
            'tipo_identificacion' => ['nullable', 'string', 'max:20'],
            'identificacion'   => $identificacionRules,
            'razon_social'     => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'direccion'        => ['nullable', 'string', 'max:300'],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:255'],
            'ciudad'           => ['required_if:tipo,internacional', 'nullable', 'string', 'max:100'],
            'pais'             => ['required_if:tipo,internacional', 'nullable', 'string', 'max:100'],
            'divisa'           => ['required_if:tipo,internacional', 'nullable', 'string', 'max:10'],
            'tiene_credito'    => ['boolean'],
            'dias_credito'     => ['required_if:tiene_credito,true', 'nullable', 'integer', 'min:1'],
            'estado'           => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'identificacion.regex' => 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos)',
            'ciudad.required_if'   => 'La ciudad es obligatoria para proveedores internacionales',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->tipo === 'nacional') {
            $this->merge(['pais' => 'Ecuador']);
        }
    }
}
