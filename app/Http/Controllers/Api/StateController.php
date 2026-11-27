<?php

namespace App\Http\Controllers\Api;

use App\Models\State;
use App\Http\Controllers\Controller;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $state = State::select('id', 'name', 'gst_code', 'is_active')->orderBy('id')->get();

        return response()->json([
           'data' => $state
        ]);
    }
}
