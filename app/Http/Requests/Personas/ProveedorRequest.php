<?php

namespace App\Http\Requests\Personas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dias_credito' => $this->dias_credito ?? 0,
            'pais'         => $this->tipo === 'nacional' ? 'ECUADOR' : ($this->pais ?: ''),
            'divisa'       => $this->divisa ?? 'USD',
        ]);
    }

    public function rules(): array
    {
        return [
            'tipo'              => ['required', 'in:nacional,internacional'],
            'tipo_identificacion' => ['required', 'in:04,05,06'],
            'identificacion'    => [
                'required', 'string', 'max:20',
                Rule::unique('proveedores')
                    ->where('empresa_id', session('empresa_activa_id'))
                    ->ignore($this->route('proveedor')?->id),
            ],
            'razon_social'      => ['required', 'string', 'max:200'],
            'nombre_comercial'  => ['nullable', 'string', 'max:200'],
            'email'             => ['nullable', 'email', 'max:200'],
            'telefono'          => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:20'],
            'direccion'         => ['nullable', 'string', 'max:300'],
            'ciudad'            => ['required_if:tipo,internacional', 'nullable', 'string', 'max:100'],
            'pais'              => ['nullable', 'string', 'max:100'],
            'divisa'            => ['required_if:tipo,internacional', 'nullable', 'string', 'max:10'],
            'tiene_credito'     => ['boolean'],
            'dias_credito'      => ['integer', 'min:0'],
            'estado'            => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->tipo !== 'nacional') {
                return;
            }

            $id    = $this->identificacion ?? '';
            $tipoId = $this->tipo_identificacion;

            if ($tipoId === '04' && !preg_match('/^\d{13}$/', $id)) {
                $v->errors()->add('identificacion', 'El RUC debe tener exactamente 13 dígitos numéricos.');
            } elseif ($tipoId === '05' && !preg_match('/^\d{10}$/', $id)) {
                $v->errors()->add('identificacion', 'La cédula debe tener exactamente 10 dígitos numéricos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'ciudad.required_if' => 'La ciudad es obligatoria para proveedores internacionales.',
            'divisa.required_if' => 'La divisa es obligatoria para proveedores internacionales.',
        ];
    }
}
