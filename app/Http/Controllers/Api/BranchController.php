<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::all();
        return response()->json([
            'data' => $branches
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code',
            'locationId' => 'required|exists:locations,id',
            'addressLine1' => 'required|string|max:255',
            'addressLine2' => 'nullable|string|max:255',
            'addressLine3' => 'nullable|string|max:255',
            'phone' => 'required|string|max:10',
            'email' => 'required|email|max:100',
            'yieldRatioDoor' => 'required|numeric|min:0|max:100',
            'yieldRatioWarehouse' => 'required|numeric|min:0|max:100',
            'region' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'state' => 'required|exists:states,id',
            'discount' => 'nullable|numeric|min:0|max:100',
            'discountType' => 'nullable|string|in:percentage,flat',
        ]);

        $branch = Branch::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'code' => $data['code'],
            'location_id' => $data['locationId'],
            'address_line_1' => $data['addressLine1'],
            'address_line_2' => $data['addressLine2'],
            'address_line_3' => $data['addressLine3'],
            'phone' => $data['phone'],
            'yield_ratio_door' => $data['yieldRatioDoor'],
            'yield_ratio_warehouse' => $data['yieldRatioWarehouse'],
            'region' => $data['region'],
            'pincode' => $data['pincode'],
            'state_id' => $data['state'],
            'discount' => $data['discount'],
            'discount_type' => $data['discountType'],
        ]);

        return response()->json([
            'message' => 'Branch created successfully!',
            'data' => $branch
        ], 201);
    }

    public function show(Branch $branch)
    {
        return response()->json($branch);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:5|unique:branches,code,' . $branch->id,
            'locationId' => 'required|exists:locations,id',
            'addressLine1' => 'required|string|max:255',
            'addressLine2' => 'nullable|string|max:255',
            'addressLine3' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'yieldRatioDoor' => 'required|numeric|min:0|max:100',
            'yieldRatioWarehouse' => 'required|numeric|min:0|max:100',
            'region' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'state' => 'required|exists:states,id',
            'discount' => 'nullable|numeric|min:0|max:100',
            'discountType' => 'nullable|string|in:percentage,flat',
        ]);

        $branch->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'code' => $data['code'],
            'location_id' => $data['locationId'],
            'address_line_1' => $data['addressLine1'],
            'address_line_2' => $data['addressLine2'],
            'address_line_3' => $data['addressLine3'],
            'phone' => $data['phone'],
            'yield_ratio_door' => $data['yieldRatioDoor'],
            'yield_ratio_warehouse' => $data['yieldRatioWarehouse'],
            'region' => $data['region'],
            'pincode' => $data['pincode'],
            'state_id' => $data['state'],
            'discount' => $data['discount'],
            'discount_type' => $data['discountType'],
        ]);

        return response()->json([
            'message' => 'Branch updated successfully!',
            'data' => $branch
        ]);
    }

    public function destroy(Branch $branch)
    {
        $branch->update(['is_active' => false]);

        return response()->json([
            'message' => 'Branch deleted successfully!'
        ]);
    }

    public function activate($id)
    {
        $branch = Branch::findOrFail($id);
        $branch->update(['is_active' => true]);

        return response()->json([
            'message' => 'Branch activated successfully!'
        ]);
    }
}
