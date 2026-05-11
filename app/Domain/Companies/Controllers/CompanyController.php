<?php

namespace App\Domain\Companies\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Resources\CompanyResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $companies = $request->user()
            ->companies()
            ->wherePivot('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => CompanyResource::collection($companies)->resolve($request),
        ]);
    }
}
