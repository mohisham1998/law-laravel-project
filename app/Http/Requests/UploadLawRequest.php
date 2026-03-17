<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxBytes = config('legal.max_file_size_mb', 10) * 1024 * 1024;
        return [
            'law_name' => ['required', 'string', 'max:500'],
            'required_law_id' => ['required', 'integer', 'exists:required_laws,id'],
            'law_file' => ['required', 'file', "max:{$maxBytes}", 'mimes:txt,md', 'mimetypes:text/plain,text/markdown'],
        ];
    }
}
