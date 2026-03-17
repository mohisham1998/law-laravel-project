<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadLawRequest;
use App\Models\LegalCase;
use App\Models\CaseLaw;
use App\Models\RequiredLaw;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LawController extends Controller
{
    public function store(UploadLawRequest $request, string $id): JsonResponse
    {
        $case = LegalCase::where('user_id', $request->user()->id)->findOrFail($id);
        $requiredLaw = RequiredLaw::where('case_id', $case->id)->findOrFail($request->required_law_id);

        $file = $request->file('law_file');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs("cases/{$case->id}/laws", $filename, 'local');

        $law = CaseLaw::create([
            'case_id' => $case->id,
            'required_law_id' => $requiredLaw->id,
            'law_name' => $request->law_name,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'encoding' => 'UTF-8',
        ]);

        $requiredLaw->update(['is_uploaded' => true]);
        $remaining = $case->requiredLaws()->where('is_uploaded', false)->count();

        return response()->json([
            'data' => [
                'id' => $law->id,
                'law_name' => $law->law_name,
                'filename' => $law->filename,
                'file_size' => $law->file_size,
                'created_at' => $law->created_at?->toIso8601String(),
            ],
            'meta' => [
                'message' => 'Law uploaded successfully',
                'remaining_laws' => $remaining,
            ],
        ], 201);
    }
}
