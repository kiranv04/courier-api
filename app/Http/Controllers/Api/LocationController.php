<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $location = Location::select('id', 'state_id', 'name', 'short_code', 'pincode', 'is_active')->orderBy('id')->get();

        return response()->json([
            'data' => $location
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:125',
            'short_code' => 'required|string|max:4|unique:locations,short_code',
            'pincode' => 'required|string|max:6|unique:locations,pincode',
        ]);

        $location = Location::create($data);

        return response()->json([
            'message' => 'Location created successfully!',
            'data' => $location
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location)
    {
        return response()->json([
            'data' => $location
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $data = $request->validate([
            'state_id' => 'required|exists:states,id',
            'name' => 'required|string|max:125',
            'short_code' => 'required|string|max:4|unique:locations,short_code,' . $location->id,
            'pincode' => 'required|string|max:6|unique:locations,pincode,' . $location->id,
        ]);

        $location->update($data);

        return response()->json([
            'message' => 'Location updated!',
            'data' => $location
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Location $location)
    {
        $location->update(['is_active' => false]);

        return response()->json([
            'message' => 'Location deactivated successfully'
        ]);
    }

    public function actrivate($id): JsonResponse 
    {
        $model = Location::findOr($id);
        $model->update(['is_active' => true]);

        return response()->json([
            'message' => 'Location reactivated!'
        ]);    
    }
}
