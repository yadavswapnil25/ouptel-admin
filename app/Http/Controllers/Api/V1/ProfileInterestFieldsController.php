<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProfileInterestService;
use Illuminate\Http\JsonResponse;

class ProfileInterestFieldsController extends Controller
{
    public function index(): JsonResponse
    {
        $fields = ProfileInterestService::getActiveFields()
            ->map(static fn ($field) => [
                'field_key' => (string) $field->field_key,
                'label' => (string) $field->label,
                'placeholder' => (string) ($field->placeholder ?? ''),
                'sort_order' => (int) ($field->sort_order ?? 0),
            ])
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'api_status' => 200,
            'data' => [
                'fields' => $fields,
            ],
        ]);
    }
}
