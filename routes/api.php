<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('routes/domains/system.php');
    require base_path('routes/domains/auth.php');
    require base_path('routes/domains/tenants.php');
});
