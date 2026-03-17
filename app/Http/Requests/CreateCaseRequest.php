<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxMb = config('legal.max_file_size_mb', 10);
        $maxBytes = $maxMb * 1024 * 1024;

        return [
            'title' => ['required', 'string', 'max:500'],
            'intake_text' => ['required', 'string'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => [
                'required',
                'file',
                "max:{$maxBytes}",
                'mimes:txt,md',
                'mimetypes:text/plain,text/markdown',
            ],
        ];
    }
}
