<?php

namespace App\Http\Controllers;

use App\Models\Callsign;
use App\Models\OwnershipRequest;
use App\Services\DomainLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnershipRequestController extends Controller
{
    public function store(Request $request, Callsign $callsign): JsonResponse
    {
        abort_unless($callsign->user_id === $request->user()->id, 404);
        $data = $request->validate(['evidence' => ['required', 'string', 'min:20', 'max:2000']]);
        $record = DB::transaction(function () use ($request, $callsign, $data) {
            DomainLock::acquire();
            $callsign->refresh();
            abort_unless($callsign->reserved_callsign && $callsign->verification_status !== 'verified', 409);
            abort_if(OwnershipRequest::where('callsign_id', $callsign->id)->where('status', 'pending')->exists(), 409, 'Ya existe una solicitud pendiente.');
            $record = OwnershipRequest::create(['user_id' => $request->user()->id, 'callsign_id' => $callsign->id, 'evidence' => $data['evidence']]);
            DB::table('security_events')->insert(['user_id' => $request->user()->id, 'event' => 'ownership.requested.'.$callsign->id, 'created_at' => now()]);

            return $record;
        }, 3);

        return response()->json(['data' => $record->only(['id', 'callsign_id', 'status', 'created_at'])], 201);
    }

    public function index(Request $request, Callsign $callsign): JsonResponse
    {
        abort_unless($callsign->user_id === $request->user()->id || $request->user()->is_admin, 404);

        return response()->json(['data' => OwnershipRequest::where('callsign_id', $callsign->id)->latest('id')->get(['id', 'evidence', 'status', 'reviewed_at', 'created_at'])]);
    }
}
