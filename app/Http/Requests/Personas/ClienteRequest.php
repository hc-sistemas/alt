<?php

namespace App\Http\Requests\Personas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_identificacion' => ['required', 'in:04,05,06,07'],
            'identificacion' => [
                'required',
                'string',
                'max:20',
                Rule::unique('clientes')
                    ->where('empresa_id', session('empresa_activa_id'))
                    ->ignore($this->route('cliente')?->id),
            ],
            'razon_social'     => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'email'            => ['nullable', 'email', 'max:200'],
            'telefono'         => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:20'],
            'celular'          => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:20'],
            'direccion'        => ['nullable', 'string', 'max:300'],
            'ciudad'           => ['nullable', 'string', 'max:100'],
            'provincia'        => ['nullable', 'string', 'max:100'],
            'pais'             => ['nullable', 'string', 'max:100'],
            'tiene_credito'    => ['boolean'],
            'dias_credito'     => ['integer', 'min:0'],
            'cupo_maximo'      => ['nullable', 'numeric', 'min:0'],
            'agente_retencion' => ['boolean'],
            'es_cliente_nuevo' => ['boolean'],
            'estado'           => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $tipo = $this->input('tipo_identificacion');
            $id   = $this->input('identificacion', '');

            if ($tipo === '04' && !preg_match('/^\d{13}$/', $id)) {
                $v->errors()->add('identificacion', 'El RUC debe tener exactamente 13 dígitos numéricos.');
            } elseif ($tipo === '05' && !preg_match('/^\d{10}$/', $id)) {
                $v->errors()->add('identificacion', 'La cédula debe tener exactamente 10 dígitos numéricos.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dias_credito' => $this->tiene_credito ? ($this->dias_credito ?? 0) : 0,
            'cupo_maximo'  => $this->cupo_maximo ?? 0,
            'pais'         => $this->pais ?: 'ECUADOR',
        ]);
    }
}
