<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->fresh());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        // Lista explícita: no permite elevar permisos ni alterar la verificación.
        $request->user()->forceFill($request->validated())->save();

        return new UserResource($request->user()->fresh());
    }
}
