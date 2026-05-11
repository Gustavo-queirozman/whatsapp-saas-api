<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Auth\Requests\LoginRequest;
use App\Domain\Auth\Resources\UserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Credenciais invalidas.',
            ], 422);
        }

        $user->load(['tenants.workspaces.whatsappInstances']);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $user->createToken($request->input('device_name', 'frontend'))->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource(
            $request->user()->load(['tenants.workspaces.whatsappInstances'])
        );
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Sessao encerrada com sucesso.',
        ]);
    }
}
