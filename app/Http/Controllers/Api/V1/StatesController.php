<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatesController extends BaseController
{
    public function background(Request $request): JsonResponse
    {
        if (!Schema::hasTable('Wo_States')) {
            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'data' => [
                    'image_url' => null,
                    'matched_name' => null,
                ],
            ]);
        }

        $candidates = collect([
            $request->query('city'),
            $request->query('region'),
            $request->query('state'),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => mb_strtolower($value))
            ->values();

        if ($candidates->isEmpty()) {
            return response()->json([
                'api_status' => 200,
                'api_text' => 'success',
                'data' => [
                    'image_url' => null,
                    'matched_name' => null,
                ],
            ]);
        }

        $stateRow = null;

        // Prefer exact city/locality matches first (city-wise backgrounds).
        foreach ($candidates as $candidate) {
            $stateRow = DB::table('Wo_States')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate)])
                ->first();

            if ($stateRow) {
                break;
            }
        }

        if (!$stateRow) {
            foreach ($candidates as $candidate) {
                $stateRow = DB::table('Wo_States')
                    ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($candidate) . '%'])
                    ->first();

                if ($stateRow) {
                    break;
                }
            }
        }

        $imageUrl = null;
        if ($stateRow && !empty($stateRow->photo)) {
            $imageUrl = $this->resolvePhotoUrl((string) $stateRow->photo);
        }

        return response()->json([
            'api_status' => 200,
            'api_text' => 'success',
            'data' => [
                'image_url' => $imageUrl,
                'matched_name' => $stateRow->name ?? null,
            ],
        ]);
    }

    private function resolvePhotoUrl(string $photo): string
    {
        $photo = trim($photo);
        if ($photo === '') {
            return '';
        }

        if (filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }

        $photo = ltrim($photo, '/');

        if (str_starts_with($photo, 'storage/')) {
            return asset($photo);
        }

        if (file_exists(public_path($photo))) {
            return asset($photo);
        }

        if (file_exists(public_path('storage/' . $photo))) {
            return asset('storage/' . $photo);
        }

        return asset('storage/' . $photo);
    }
}

