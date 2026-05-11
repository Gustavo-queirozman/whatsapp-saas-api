<?php

namespace App\Domain\System\Controllers;

use App\Http\Controllers\Controller;

class HealthCheckController extends Controller
{
    public function show()
    {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
            'environment' => config('app.env'),
            'timestamp' => now()->toAtomString(),
        ]);
    }
}
