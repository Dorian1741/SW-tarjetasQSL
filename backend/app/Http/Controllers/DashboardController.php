<?php

namespace App\Http\Controllers;

use App\Http\Resources\QsoResource;
use App\Models\QslCard;
use App\Models\QsoMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $id = $request->user()->id;

        return response()->json(['data' => [
            'total_qsos' => $request->user()->qsos()->count(),
            'confirmed_qsos' => $request->user()->qsos()->where('status', 'confirmed')->count(),
            'issued_cards' => QslCard::where('sender_user_id', $id)->where('status', 'ready')->count(),
            'pending_matches' => QsoMatch::where('status', 'candidate')->where(fn ($q) => $q->whereHas('qsoA', fn ($q) => $q->where('user_id', $id))->orWhereHas('qsoB', fn ($q) => $q->where('user_id', $id)))->count(),
            'recent_activity' => QsoResource::collection($request->user()->qsos()->with('callsign')->latest('id')->limit(5)->get()),
        ]]);
    }
}
