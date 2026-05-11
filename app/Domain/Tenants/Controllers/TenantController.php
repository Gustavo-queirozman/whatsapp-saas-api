<?php

namespace App\Domain\Tenants\Controllers;

use App\Domain\Tenants\Resources\TenantResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $tenants = $request->user()
            ->tenants()
            ->with(['workspaces.whatsappInstances'])
            ->get();

        return TenantResource::collection($tenants);
    }
}
