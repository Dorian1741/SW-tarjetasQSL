<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStationCardRequest;
use App\Http\Resources\StationCardResource;
use App\Models\StationCard;
use App\Services\StationCardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StationCardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return StationCardResource::collection(StationCard::with('callsign')->where('user_id', $request->user()->id)->latest('id')->paginate(12));
    }

    public function gallery(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9\/]*$/']]);
        $search = strtoupper(trim($data['q'] ?? ''));

        return StationCardResource::collection(StationCard::visible()->with('callsign')
            ->when($search !== '', fn (Builder $card) => $card->whereHas('callsign', fn (Builder $callsign) => $callsign->where('normalized_callsign', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(12));
    }

    public function store(SaveStationCardRequest $request, StationCardService $service): StationCardResource
    {
        return new StationCardResource($service->save($request->user(), $request->validated(), $request->file('image')));
    }

    public function show(StationCard $stationCard): StationCardResource
    {
        Gate::authorize('view', $stationCard);

        return new StationCardResource($stationCard->load('callsign'));
    }

    public function update(SaveStationCardRequest $request, StationCard $stationCard, StationCardService $service): StationCardResource
    {
        return new StationCardResource($service->save($request->user(), $request->validated(), $request->file('image'), $stationCard));
    }

    public function visibility(Request $request, StationCard $stationCard, StationCardService $service): StationCardResource
    {
        Gate::authorize('update', $stationCard);
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $service->assertPublishable($request->user(), $stationCard->callsign, (bool) $data['is_public']);
        $stationCard->update($data);

        return new StationCardResource($stationCard->load('callsign'));
    }

    public function image(StationCard $stationCard): StreamedResponse
    {
        Gate::authorize('view', $stationCard);

        return $this->file($stationCard);
    }

    public function publicImage(StationCard $stationCard): StreamedResponse
    {
        abort_unless(StationCard::visible()->whereKey($stationCard->id)->exists(), 404);

        return $this->file($stationCard);
    }

    private function file(StationCard $card): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($card->file_path), 404);

        return Storage::disk('local')->response($card->file_path, $card->public_id.'.png', ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function destroy(StationCard $stationCard): Response
    {
        Gate::authorize('delete', $stationCard);
        $path = $stationCard->file_path;
        $stationCard->delete();
        Storage::disk('local')->delete($path);

        return response()->noContent();
    }
}
