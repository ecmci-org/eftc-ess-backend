<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class SampolController extends Controller
{
    public function getSampol()
    {
        return response()->json(['message' => 'This is a sample lang to.']);
    }
}
