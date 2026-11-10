<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQsoRequest;
use App\Http\Resources\QsoResource;
use App\Models\Qso;
use App\Services\QsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QsoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:32'], 'band' => ['nullable', 'string', 'max:16'], 'mode' => ['nullable', 'string', 'max:16'], 'status' => ['nullable', 'string', 'max:16'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), ['after_or_equal:from'])]]);
        $query = $request->user()->qsos()->with('callsign');
        if (! empty($filters['search'])) {
            $query->where('normalized_contacted_callsign', 'like', '%'.strtoupper($filters['search']).'%');
        }
        foreach (['band', 'mode', 'status'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('started_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('started_at', '<=', $filters['to'].' 23:59:59');
        }

        return QsoResource::collection($query->orderByDesc('started_at')->orderByDesc('id')->paginate(20)->withQueryString());
    }

    public function store(StoreQsoRequest $request, QsoService $service): JsonResponse
    {
        return (new QsoResource($service->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Qso $qso): QsoResource
    {
        Gate::authorize('view', $qso);

        return new QsoResource($qso->load('callsign'));
    }

    public function destroy(Qso $qso, QsoService $service): Response
    {
        Gate::authorize('update', $qso);
        $service->withdraw($qso);

        return response()->noContent();
    }
}
