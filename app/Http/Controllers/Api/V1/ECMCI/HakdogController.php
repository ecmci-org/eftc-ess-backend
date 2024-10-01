<?php

namespace App\Http\Controllers\Api\V1\ECMCI;

use App\Http\Controllers\Controller;

class HakdogController extends Controller
{
    public function getHakdog()
    {
        return response()->json(['message' => 'hakdog v2.']);
    }
}
