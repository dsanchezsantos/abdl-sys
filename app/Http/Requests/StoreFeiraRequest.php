<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFeiraRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "nome" => "required|string|max:255",
            "data_inicio" => "required|date",
            "data_fim" => "required|date|after_or_equal:data_inicio",
            "endpoint_url" => "required|url|max:500",
            "evento_id_api" => "required|string",
            "user_id_api" => "required|string",
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            "data_fim.after_or_equal" => "A data de fim não pode ser anterior à data de início.",
            "endpoint_url.required" => "A URL do endpoint é obrigatória.",
            "endpoint_url.url" => "A URL do endpoint informada não é válida.",
        ];
    }
}
