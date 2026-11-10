<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOperatorProfileRequest;
use App\Http\Resources\OperatorProfileResource;
use App\Http\Resources\QslCardResource;
use App\Models\OperatorProfile;
use App\Models\QslCard;
use App\Services\CallsignService;
use App\Services\OperatorProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperatorProfileController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $profile = OperatorProfile::with('callsign', 'user')->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => $profile ? (new OperatorProfileResource($profile))->resolve($request) : null]);
    }

    public function update(UpdateOperatorProfileRequest $request, OperatorProfileService $service): OperatorProfileResource
    {
        return new OperatorProfileResource($service->update($request->user(), $request->validated()));
    }

    public function upload(Request $request, OperatorProfileService $service): OperatorProfileResource
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096']]);

        return new OperatorProfileResource($service->photo($request->user(), $request->file('photo')));
    }

    public function show(Request $request, string $callsign, CallsignService $normalizer): OperatorProfileResource
    {
        $call = $normalizer->validate($callsign);
        $profile = OperatorProfile::with('callsign', 'user')->whereHas('callsign', fn ($q) => $q->where('reserved_callsign', $call))->firstOrFail();
        abort_unless($profile->isVisible(), 404);

        return new OperatorProfileResource($profile);
    }

    public function history(Request $request, string $callsign, CallsignService $normalizer): AnonymousResourceCollection
    {
        $profile = OperatorProfile::with('callsign', 'user')->whereHas('callsign', fn ($q) => $q->where('reserved_callsign', $normalizer->validate($callsign)))->firstOrFail();
        abort_unless($profile->isVisible() && $profile->show_qsl_history, 404);

        return QslCardResource::collection(QslCard::with('match.qsoA.callsign', 'match.qsoB.callsign')->where('sender_user_id', $profile->user_id)->where('status', 'ready')->whereHas('match', fn ($q) => $q->where('status', 'confirmed'))->latest('id')->paginate(12));
    }

    public function photo(Request $request, OperatorProfile $profile): StreamedResponse
    {
        $profile->load('user', 'callsign');
        abort_unless($profile->isVisible() && $profile->photo_path && Storage::disk('local')->exists($profile->photo_path), 404);

        return Storage::disk('local')->response($profile->photo_path, null, ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }
}
