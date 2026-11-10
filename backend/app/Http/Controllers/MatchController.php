<?php

namespace App\Http\Controllers;

use App\Http\Resources\MatchResource;
use App\Models\QsoMatch;
use App\Services\QsoMatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MatchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'in:candidate,confirmed,disputed,rejected']]);
        $query = QsoMatch::with('qsoA.callsign', 'qsoB.callsign')->where(function ($q) use ($request) {
            $q->whereHas('qsoA', fn ($q) => $q->where('user_id', $request->user()->id))->orWhereHas('qsoB', fn ($q) => $q->where('user_id', $request->user()->id));
        });
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return MatchResource::collection($query->latest('id')->paginate(20));
    }

    public function update(Request $request, QsoMatch $match, QsoMatchingService $service): MatchResource
    {
        Gate::authorize('update', $match);
        $data = $request->validate(['decision' => ['required', 'in:confirm,reject,dispute']]);

        return new MatchResource($service->decide($request->user(), $match, $data['decision']));
    }
}
