<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\IntegritySentinelService;
use Illuminate\Http\Request;

class IntegritySentinelController extends Controller
{
    public function show(Request $request)
    {
        $refresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOL);
        $payload = $refresh
            ? IntegritySentinelService::run(true)
            : IntegritySentinelService::lastOrRun();

        return response()->json($payload);
    }
}

