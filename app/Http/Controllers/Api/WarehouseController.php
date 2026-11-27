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
            'phone' => 'required|regex:/^[6-9]\d{9}$/',
            'email' => 'required|email|max:125',
            'addressLine1' => 'required|string|max:255',
            'addressLine2' => 'nullable|string|max:255',
            'addressLine3' => 'nullable|string|max:255',
            'location_id' => 'required|exists:locations,id',
            'region' => 'nullable|string|max:255',
            'pincode' => 'nullable|digits:6',
            'state' => 'required|exists:states,id'
        ]);

        $warehouse = Warehouse::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'code' => $data['code'],
            'location_id' => $data['location_id'],
            'address_line_1' => $data['addressLine1'],
            'address_line_2' => $data['addressLine2'] ?? null,
            'address_line_3' => $data['addressLine3'] ?? null,
            'phone' => $data['phone'],
            'region' => $data['region'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'state_id' => $data['state'],
        ]);

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
        return response()->json([
            'data' => $warehouse
        ]);
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
            'addressLine1' => 'required|string|max:255',
            'addressLine2' => 'nullable|string|max:255',
            'addressLine3' => 'nullable|string|max:255',
            'phone' => 'nullable|regex:/^[6-9]\d{9}$/',
            'email' => 'nullable|email|max:125',
            'region' => 'nullable|string|max:255',
            'pincode' => 'nullable|digits:6',
            'state' => 'required|exists:states,id'
        ]);

        $warehouse->update([
            'name' => $data['name'] ?? $warehouse->name,
            'email' => $data['email'] ?? $warehouse->email,
            'code' => $data['code'] ?? $warehouse->code,
            'location_id' => $data['location_id'] ?? $warehouse->location_id,
            'address_line_1' => $data['addressLine1'],
            'address_line_2' => $data['addressLine2'] ?? null,
            'address_line_3' => $data['addressLine3'] ?? null,
            'phone' => $data['phone'] ?? $warehouse->phone,
            'region' => $data['region'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'state_id' => $data['state'],
        ]);

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
