<?php

namespace App\Http\Controllers;

use App\Http\Requests\CallsignLookupRequest;
use App\Services\CallsignLookupService;
use Illuminate\Http\JsonResponse;

class CallsignLookupController extends Controller
{
    public function __invoke(CallsignLookupRequest $request, CallsignLookupService $service): JsonResponse
    {
        $result = $service->lookup($request->validated('callsign'), $request->validated('provider', 'auto'));

        return response()->json(['data' => $result], $result['directory_status'] === 'unavailable' ? 503 : 200)->header('Cache-Control', 'private, no-store');
    }
}
