<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selected_model' => ['sometimes', 'string', 'max:255'],
            'confidence_threshold' => ['sometimes', 'numeric', 'min:0.50', 'max:0.90'],
        ];
    }
}
