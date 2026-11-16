<?php

namespace App\Http\Controllers;

use App\Http\Resources\IntegrationCredentialResource;
use App\Models\IntegrationCredential;
use App\Services\DomainLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IntegrationCredentialController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return IntegrationCredentialResource::collection(IntegrationCredential::with('callsign')->where('user_id', $request->user()->id)->latest('created_at')->orderBy('id')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'callsign_id' => ['required', 'integer', Rule::exists('callsigns', 'id')->where('user_id', $request->user()->id)], 'abilities' => ['required', 'array', 'min:1', 'max:2'], 'abilities.*' => ['required', 'distinct', Rule::in(['qsos:read', 'qsos:write'])], 'expires_in_days' => ['required', 'integer', 'min:1', 'max:90']]);

        return DB::transaction(function () use ($request, $data) {
            DomainLock::acquire();
            $claim = $request->user()->callsigns()->whereKey($data['callsign_id'])->whereNotNull('reserved_callsign')->where('verification_status', 'verified')->firstOrFail();
            abort_if(IntegrationCredential::where('user_id', $request->user()->id)->whereNull('revoked_at')->where('expires_at', '>', now())->count() >= 20, 422, 'Máximo 20 integraciones activas.');
            $credential = IntegrationCredential::create(['user_id' => $request->user()->id, 'callsign_id' => $claim->id, 'name' => $data['name'], 'token_hash' => str_repeat('0', 64), 'security_fingerprint' => IntegrationCredential::fingerprint($request->user()), 'abilities' => $data['abilities'], 'expires_at' => now()->addDays($data['expires_in_days'])]);

            return $this->issue($request, $credential, 'integration.created', 201);
        }, 3);
    }

    private function issue(Request $request, IntegrationCredential $credential, string $event, int $status): JsonResponse
    {
        $plain = 'nexus_'.$credential->id.'.'.Str::random(64);
        $credential->update(['token_hash' => hash('sha256', $plain), 'security_fingerprint' => IntegrationCredential::fingerprint($request->user())]);
        DB::table('security_events')->insert(['user_id' => $request->user()->id, 'event' => $event.'.'.$credential->id, 'created_at' => now()]);

        return response()->json(['data' => (new IntegrationCredentialResource($credential->load('callsign')))->resolve($request), 'token' => $plain], $status)->header('Cache-Control', 'private, no-store');
    }

    public function rotate(Request $request, IntegrationCredential $credential): JsonResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 404);
        $data = $request->validate(['expires_in_days' => ['required', 'integer', 'min:1', 'max:90']]);

        return DB::transaction(function () use ($request, $credential, $data) {
            DomainLock::acquire();
            $credential->refresh();
            abort_if($credential->revoked_at, 409, 'Crea una nueva integración para reemplazar una revocada.');
            abort_unless($credential->callsign->reserved_callsign && $credential->callsign->verification_status === 'verified', 409);
            $credential->update(['expires_at' => now()->addDays($data['expires_in_days'])]);

            return $this->issue($request, $credential, 'integration.rotated', 200);
        }, 3);
    }

    public function destroy(Request $request, IntegrationCredential $credential): Response
    {
        abort_unless($credential->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $credential) {
            DomainLock::acquire();
            $credential->update(['revoked_at' => now()]);
            DB::table('security_events')->insert(['user_id' => $request->user()->id, 'event' => 'integration.revoked.'.$credential->id, 'created_at' => now()]);
        });

        return response()->noContent();
    }
}
