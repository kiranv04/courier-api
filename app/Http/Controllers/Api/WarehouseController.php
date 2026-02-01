<?php

namespace App\Http\Controllers\Api;

use App\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::all();
        return response()->json([
            'data' => $warehouses
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100|unique:warehouses,code',
            'location_id' => 'required|exists:locations,id',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:125',
        ]);

        $warehouse = Warehouse::create($data);

        return response()->json([
            'message' => 'Warehouse created successfully',
            'data' => $warehouse
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:100|unique:warehouses,code,' . $warehouse->id,
            'location_id' => 'sometimes|required|exists:locations,id',
            'address' => 'sometimes|required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:125',
        ]);

        $warehouse->update($data);

        return response()->json([
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        $warehouse->update(['is_active' => false]);

        return response()->json([
            'message' => 'Warehouse deleted successfully'
        ]);
    }

    public function activate($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update(['is_active' => true]);

        return response()->json([
            'message' => 'Warehouse activated successfully',
            'data' => $warehouse
        ]);
    }
}
