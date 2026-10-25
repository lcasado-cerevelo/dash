<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta si usas policies
    }

    public function rules(): array
    {
        return [
            // 🔹 TUS REGLAS ACTUALES DEL CLIENTE (ejemplos)
            // 'name' => ['required','string','max:255'],
            // 'email' => ['required','email','max:255','unique:customers,email,'.($this->customer->id ?? 'NULL')],
            // ... billing/shipping ...

            // 🔹 Bloque anidado para customer_clients
            'customer_clients' => ['array'],
            'customer_clients.*.id' => ['nullable','integer','exists:customer_clients,id'],
            'customer_clients.*.external_code' => ['nullable','string','max:50'],

            // Requerimos al menos 'name' para considerar la fila válida
            'customer_clients.*.name' => ['nullable','string','max:255'],

            // Si se envía 'name', exigimos el resto (según tu esquema NOT NULL)
            'customer_clients.*.email' => ['nullable','email','max:255'],
            'customer_clients.*.contact' => ['nullable','string','max:255'],

            'customer_clients.*.country' => ['required_with:customer_clients.*.name','string','max:255'],
            'customer_clients.*.state'   => ['required_with:customer_clients.*.name','string','max:255'],
            'customer_clients.*.city'    => ['required_with:customer_clients.*.name','string','max:255'],
            'customer_clients.*.phone'   => ['required_with:customer_clients.*.name','string','max:255'],
            'customer_clients.*.zip'     => ['required_with:customer_clients.*.name','string','max:255'],
            'customer_clients.*.address' => ['required_with:customer_clients.*.name','string'],
            'customer_clients.*.notes'   => ['nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_clients.*.country.required_with' => 'Country es requerido cuando indicas Name.',
            'customer_clients.*.state.required_with'   => 'State es requerido cuando indicas Name.',
            'customer_clients.*.city.required_with'    => 'City es requerido cuando indicas Name.',
            'customer_clients.*.phone.required_with'   => 'Phone es requerido cuando indicas Name.',
            'customer_clients.*.zip.required_with'     => 'Zip es requerido cuando indicas Name.',
            'customer_clients.*.address.required_with' => 'Address es requerido cuando indicas Name.',
        ];
    }
}
