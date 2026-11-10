<?php

namespace App\Http\Controllers;

use App\Models\OperatorPost;
use App\Models\OperatorProfile;
use App\Services\CallsignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OperatorSocialController extends Controller
{
    private function profile(string $call): OperatorProfile
    {
        $normalized = app(CallsignService::class)->validate($call);
        $profile = OperatorProfile::with('user', 'callsign')->whereHas('callsign', fn ($q) => $q->where('reserved_callsign', $normalized))->firstOrFail();
        abort_unless($profile->isVisible(), 404);

        return $profile;
    }

    public function follow(Request $request, string $callsign): Response
    {
        $profile = $this->profile($callsign);
        abort_if($profile->user_id === $request->user()->id, 422, 'No puedes seguirte a ti mismo.');
        DB::table('operator_follows')->insertOrIgnore(['follower_id' => $request->user()->id, 'followed_id' => $profile->user_id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->noContent();
    }

    public function unfollow(Request $request, string $callsign): Response
    {
        $profile = $this->profile($callsign);
        DB::table('operator_follows')->where('follower_id', $request->user()->id)->where('followed_id', $profile->user_id)->delete();

        return response()->noContent();
    }

    public function posts(Request $request, string $callsign): JsonResponse
    {
        $profile = $this->profile($callsign);

        return response()->json(OperatorPost::where('user_id', $profile->user_id)->latest('id')->paginate(20, ['id', 'content', 'created_at']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);
        $profile = OperatorProfile::with('callsign', 'user')->where('user_id', $request->user()->id)->firstOrFail();
        abort_unless($profile->isVisible(), 403, 'Publica primero tu perfil verificado.');
        $post = OperatorPost::create(['user_id' => $request->user()->id, 'content' => $data['content']]);

        return response()->json(['data' => $post->only(['id', 'content', 'created_at'])], 201);
    }

    public function destroy(Request $request, OperatorPost $post): Response
    {
        abort_unless($post->user_id === $request->user()->id, 404);
        $post->delete();

        return response()->noContent();
    }
}
