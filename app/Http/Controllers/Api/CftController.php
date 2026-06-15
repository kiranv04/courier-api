<?php

namespace App\Http\Controllers\Api;

use App\Models\Cft;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $cfts = Cft::all();
        return response()->json([
            'data' => $cfts
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cft_value' => 'required|decimal:1,2|min:5',
        ]);

        $cft = Cft::create($data);

        return response()->json([
            'message' => 'CFT created successfully!',
            'data' => $cft
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cft $cft)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cft $cft)
    {
        $data = $request->validate([
            'cft_value' => 'required|decimal:1,2|min:5',
        ]);

        $cft->update($data);

        return response()->json([
            'message' => 'CFT updated successfully!',
            'data' => $cft
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cft $cft)
    {
        $cft->update(['is_active' => false]);

        return response()->json([
            'message' => 'CFT deactivated successfully!',
        ]);
    }

    public function activate($id)
    {
        $cft = Cft::findOrFail($id);
        $cft->update(['is_active' => true]);

        return response()->json([
            'message' => 'CFT activated successfully!',
            'data' => $cft
        ]);
    }
}
