<?php

namespace App\Http\Requests;

use App\Enums\FeiraStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateFeiraRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('data_fim') && !$this->has('data_inicio')) {
            $feira = $this->route('feira');
            if ($feira && $feira->data_inicio) {
                $this->merge([
                    'data_inicio' => $feira->data_inicio->format('Y-m-d'),
                ]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nome' => 'sometimes|required|string|max:255',
            'data_inicio' => 'sometimes|required|date',
            'data_fim' => 'sometimes|required|date|after_or_equal:data_inicio',
            'endpoint_url' => 'sometimes|required|url|max:500',
            'api_username' => 'nullable|string|max:255',
            'api_password' => 'nullable|string|max:255',
            'evento_id_api' => 'sometimes|required|string|max:255',
            'user_id_api' => 'sometimes|required|string|max:255',
            'status' => ['sometimes', 'required', new Enum(FeiraStatus::class)],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'nome.required' => 'O nome da feira é obrigatório.',
            'data_inicio.required' => 'A data de início é obrigatória.',
            'data_fim.required' => 'A data de fim é obrigatória.',
            'data_fim.after_or_equal' => 'A data de fim não pode ser anterior à data de início.',
            'endpoint_url.required' => 'A URL do endpoint é obrigatória.',
            'endpoint_url.url' => 'A URL do endpoint informada não é válida.',
            'evento_id_api.required' => 'O ID do evento é obrigatório.',
            'user_id_api.required' => 'O ID do usuário é obrigatório.',
            'status.required' => 'O status é obrigatório.',
            'status.Illuminate\Validation\Rules\Enum' => 'O status informado é inválido.',
        ];
    }
}
