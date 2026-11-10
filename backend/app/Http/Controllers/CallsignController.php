<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCallsignRequest;
use App\Http\Resources\CallsignResource;
use App\Models\Callsign;
use App\Services\CallsignService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CallsignController extends Controller
{
    public function update(Request $request, Callsign $callsign, CallsignService $service): CallsignResource
    {
        return new CallsignResource($service->primary($request->user(), $callsign));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return CallsignResource::collection($request->user()->callsigns()->latest()->paginate(20));
    }

    public function store(StoreCallsignRequest $request, CallsignService $service): CallsignResource
    {
        return new CallsignResource($service->claim($request->user(), $request->validated('callsign'), $request->validated('country_code')));
    }
}
