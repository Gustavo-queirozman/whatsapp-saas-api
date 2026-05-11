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
    public function login(LoginRequest $request): \Illuminate\Http\JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais invalidas.',
            ], 422);
        }

        $user->load([
            'companies' => fn ($query) => $query->wherePivot('is_active', true),
            'companyMemberships.role.permissions',
        ]);

        $currentCompany = $user->companies->first();

        if ($currentCompany !== null) {
            $user->setRelation('currentCompany', $currentCompany);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $user->createToken($request->input('device_name', 'frontend'))->plainTextToken,
                'user' => (new UserResource($user))->resolve($request),
            ],
        ]);
    }

    public function me(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user()->load([
            'companies' => fn ($query) => $query->wherePivot('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'data' => (new UserResource($user))->resolve($request),
        ]);
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Sessao encerrada com sucesso.',
            ],
        ]);
    }
}
