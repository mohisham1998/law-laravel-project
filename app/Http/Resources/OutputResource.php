<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_number' => $this->agent_number,
            'filename' => $this->filename,
            'content_type' => $this->content_type,
            'file_size' => $this->file_size,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
