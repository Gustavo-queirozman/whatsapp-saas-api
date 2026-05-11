<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateEvolutionWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = (string) config('evolution.webhook_secret', '');
        $headerName = (string) config('evolution.webhook_secret_header', 'X-Evolution-Webhook-Secret');
        $receivedSecret = (string) $request->header($headerName, '');

        if ($expectedSecret === '' || ! hash_equals($expectedSecret, $receivedSecret)) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Token de webhook invalido.',
        ], 401);
    }
}
