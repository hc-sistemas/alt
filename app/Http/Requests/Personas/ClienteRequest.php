<?php

namespace App\Http\Requests\Personas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = session('empresa_activa_id');
        $clienteId = $this->route('cliente')?->id;

        return [
            'ruc_cedula' => [
                'required',
                'string',
                'regex:/^\d{10}(\d{3})?$/',
                Rule::unique('clientes')->where('empresa_id', $empresaId)->ignore($clienteId),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'pais' => ['nullable', 'string', 'max:100'],
            'tiene_credito' => ['boolean'],
            'dias_credito' => ['required_if:tiene_credito,true', 'nullable', 'integer', 'min:1'],
            'cupo_credito' => ['nullable', 'numeric', 'min:0'],
            'es_agente_retencion' => ['boolean'],
            'estado' => ['boolean'],
            'observaciones' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->pais) {
            $this->merge(['pais' => 'Ecuador']);
        }
    }
}
