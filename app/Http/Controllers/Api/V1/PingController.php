<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class PingController extends BaseController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'pong',
            'time' => now()->toIso8601String(),
        ]);
    }
}


