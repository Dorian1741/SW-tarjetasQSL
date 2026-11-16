<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportIntegrationQsoRequest;
use App\Http\Resources\MatchResource;
use App\Http\Resources\QslCardResource;
use App\Http\Resources\QsoResource;
use App\Models\IntegrationReceipt;
use App\Models\QsoMatch;
use App\Services\LogbookIntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationQsoController extends Controller
{
    private function representation(Request $request, IntegrationReceipt $receipt): array
    {
        $qso = $receipt->qso->load('callsign');
        $match = QsoMatch::with('qsoA.callsign', 'qsoB.callsign', 'cards.match.qsoA.callsign', 'cards.match.qsoB.callsign')->where(fn ($q) => $q->where('qso_a_id', $qso->id)->orWhere('qso_b_id', $qso->id))->orderByRaw("CASE WHEN status='confirmed' THEN 0 WHEN status='candidate' THEN 1 ELSE 2 END")->latest('matched_at')->latest('id')->first();

        return ['external_id' => $receipt->external_id, 'qso' => (new QsoResource($qso))->resolve($request), 'match' => $match ? (new MatchResource($match))->resolve($request) : null, 'qsl_cards' => $match ? QslCardResource::collection($match->cards)->resolve($request) : []];
    }

    public function store(ImportIntegrationQsoRequest $request, LogbookIntegrationService $service): JsonResponse
    {
        abort_unless($request->isJson(), 415, 'Envía Content-Type: application/json.');
        [$receipt,$created] = $service->import($request->attributes->get('integration'), $request->validated());

        return response()->json(['data' => $this->representation($request, $receipt), 'replayed' => ! $created], $created ? 201 : 200);
    }

    public function show(Request $request, string $externalId): JsonResponse
    {
        $credential = $request->attributes->get('integration');
        abort_unless(in_array('qsos:read', $credential->abilities, true), 403);
        $receipt = IntegrationReceipt::where('integration_credential_id', $credential->id)->where('external_id', $externalId)->firstOrFail();

        return response()->json(['data' => $this->representation($request, $receipt)]);
    }

    public function index(Request $request): JsonResponse
    {
        $credential = $request->attributes->get('integration');
        abort_unless(in_array('qsos:read', $credential->abilities, true), 403);
        $records = IntegrationReceipt::where('integration_credential_id', $credential->id)->orderBy('id')->paginate(20, ['external_id', 'qso_id', 'created_at']);

        return response()->json($records);
    }
}
