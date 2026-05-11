<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        app(CurrentCompany::class)->set(null);

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $requestedCompanyId = $request->header('X-Company-Id');

        $membershipQuery = $user->companyMemberships()
            ->where('is_active', true)
            ->with(['company', 'role.permissions']);

        if ($requestedCompanyId !== null) {
            $membershipQuery->where('company_id', (int) $requestedCompanyId);
        }

        $membership = $membershipQuery->orderBy('company_id')->first();

        if ($membership === null) {
            return $this->forbiddenResponse();
        }

        $company = $membership->company;

        app(CurrentCompany::class)->set($company);

        $request->attributes->set('current_company', $company);
        $request->attributes->set('current_company_id', $company->getKey());

        $user->setRelation('currentCompany', $company);
        return $next($request);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Acesso negado para a empresa informada.',
        ], 403);
    }
}
