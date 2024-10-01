<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class TestController extends Controller
{
    public function getString()
    {
        return response()->json(['message' => 'This is a sample API response.']);
    }
}
