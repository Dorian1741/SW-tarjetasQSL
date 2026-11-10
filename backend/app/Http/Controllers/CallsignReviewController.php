<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewCallsignRequest;
use App\Http\Resources\CallsignResource;
use App\Models\Callsign;
use App\Services\CallsignService;
use Illuminate\Support\Facades\Gate;

class CallsignReviewController extends Controller
{
    public function store(ReviewCallsignRequest $request, Callsign $callsign, CallsignService $service): CallsignResource
    {
        Gate::authorize('review', $callsign);

        return new CallsignResource($service->review($request->user(), $callsign, $request->validated('status'), $request->validated('reason')));
    }
}
