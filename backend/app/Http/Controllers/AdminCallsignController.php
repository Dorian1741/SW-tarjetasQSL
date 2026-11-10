<?php

namespace App\Http\Controllers;

use App\Http\Resources\CallsignResource;
use App\Models\Callsign;
use App\Services\CallsignService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminCallsignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->is_admin, 404);
        $request->validate(['status' => ['nullable', 'in:pending,verified,rejected,disputed']]);

        return CallsignResource::collection(Callsign::whereNotNull('reserved_callsign')->when($request->filled('status'), fn ($q) => $q->where('verification_status', $request->input('status')))->latest('id')->paginate(20));
    }

    public function destroy(Request $request, Callsign $callsign, CallsignService $service): Response
    {
        $service->release($request->user(), $callsign);

        return response()->noContent();
    }
}
