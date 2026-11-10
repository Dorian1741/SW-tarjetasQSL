<?php

namespace App\Http\Controllers;

use App\Http\Resources\QslCardResource;
use App\Models\QslCard;
use App\Models\QsoMatch;
use App\Services\QslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QslCardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return QslCardResource::collection(QslCard::with('match.qsoA.callsign', 'match.qsoB.callsign')->where(fn ($q) => $q->where('sender_user_id', $request->user()->id)->orWhere('receiver_user_id', $request->user()->id))->latest('id')->paginate(20));
    }

    public function store(Request $request, QsoMatch $match, QslService $service): QslCardResource
    {
        Gate::authorize('view', $match);

        return new QslCardResource($service->issue($request->user(), $match));
    }

    public function show(QslCard $card): QslCardResource
    {
        Gate::authorize('view', $card);

        return new QslCardResource($card->load('match.qsoA.callsign', 'match.qsoB.callsign'));
    }

    public function verify(QslCard $card): JsonResponse
    {
        return (new QslCardResource($card->load('match.qsoA.callsign', 'match.qsoB.callsign')))->response()->header('Cache-Control', 'no-store');
    }

    public function download(QslCard $card): StreamedResponse
    {
        Gate::authorize('view', $card);
        abort_unless($card->status === 'ready' && $card->match->status === 'confirmed', 409, 'Tarjeta revocada o no disponible.');
        abort_unless(Storage::disk('local')->exists($card->file_path), 404);

        return Storage::disk('local')->download($card->file_path, $card->public_id.'.png', ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }
}
