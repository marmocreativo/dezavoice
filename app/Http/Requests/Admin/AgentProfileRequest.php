<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AgentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el acceso lo controla el middleware admin.access
    }

    protected function prepareForValidation(): void
    {
        // El navegador envía \r\n en los textareas; se normaliza para no inflar el conteo ni el prompt.
        foreach (['formas_de_pago', 'menu', 'instrucciones_adicionales'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => str_replace("\r\n", "\n", $this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nombre_negocio' => ['nullable', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo_solicitud' => ['required', 'in:'.implode(',', array_keys(\App\Models\AgentProfile::REQUEST_TYPES))],
            'direccion' => ['nullable', 'string', 'max:255'],
            'horario' => ['nullable', 'string', 'max:255'],
            'tiempo_preparacion' => ['nullable', 'string', 'max:100'],
            'formas_de_pago' => ['nullable', 'string', 'max:1000'],
            'menu' => ['nullable', 'string', 'max:8000'],
            'instrucciones_adicionales' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'string' => ':Attribute debe ser texto.',
            'max' => ':Attribute no puede superar :max caracteres.',
            'required' => 'El campo :attribute es obligatorio.',
            'in' => ':Attribute no es válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_negocio' => 'nombre del negocio',
            'descripcion' => 'descripción',
            'tipo_solicitud' => 'tipo de solicitud',
            'direccion' => 'dirección',
            'horario' => 'horario',
            'tiempo_preparacion' => 'tiempo de preparación',
            'formas_de_pago' => 'formas de pago',
            'menu' => 'menú',
            'instrucciones_adicionales' => 'instrucciones adicionales',
        ];
    }
}