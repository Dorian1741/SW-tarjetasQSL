<?php

namespace App\Http\Controllers;

use App\Models\OperatorPost;
use App\Models\OperatorProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCommunityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $posts = OperatorPost::with('profile.callsign')
            ->whereHas('profile', fn (Builder $profile) => $profile->visible())
            ->latest('id')->paginate(12);
        $posts->through(fn (OperatorPost $post) => [
            'id' => $post->id,
            'content' => $post->content,
            'created_at' => $post->created_at,
            'author' => $this->summary($post->profile),
        ]);

        return response()->json([
            'posts' => $posts,
            'operators' => OperatorProfile::visible()->with('callsign')->latest('id')->limit(6)->get()->map($this->summary(...)),
            'stats' => ['operators' => OperatorProfile::visible()->count(), 'publications' => $posts->total()],
        ])->header('Cache-Control', 'no-store');
    }

    public function directory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9\/]*$/'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $search = strtoupper(trim($data['q'] ?? ''));
        $profiles = OperatorProfile::visible()->with('callsign')
            ->when($search !== '', fn (Builder $profile) => $profile->whereHas('callsign', fn (Builder $callsign) => $callsign->where('normalized_callsign', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(12);
        $profiles->through($this->summary(...));

        return response()->json($profiles)->header('Cache-Control', 'no-store');
    }

    private function summary(OperatorProfile $profile): array
    {
        return [
            'callsign' => $profile->callsign->normalized_callsign,
            'biography' => $profile->biography,
            'country_code' => $profile->country_code,
            'photo_url' => $profile->photo_path ? '/api/v1/public/operator-photos/'.$profile->id : null,
            'public_url' => '/operators/'.rawurlencode($profile->callsign->normalized_callsign),
        ];
    }
}
