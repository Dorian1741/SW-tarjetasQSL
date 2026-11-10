<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(NotificationDelivery::where('user_id', $request->user()->id)->latest('id')->paginate(20, ['id', 'kind', 'payload', 'status', 'read_at', 'created_at']));
    }

    public function update(Request $request, NotificationDelivery $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => now()]);

        return response()->json(['data' => ['id' => $notification->id, 'read_at' => $notification->read_at]]);
    }
}
