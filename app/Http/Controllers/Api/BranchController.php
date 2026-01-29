<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::select('id', 'name', 'code', 'location_id', 'address', 'phone', 'email', 'yield_ratio', 'is_active')
            ->orderBy('id')
            ->get();
        return response()->json($branches);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:branches,code',
            'location_id' => 'required|exists:locations,id',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'yield_ratio' => 'required|numeric|min:0|max:100',
        ]);

        $branch = Branch::create($data);

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
            'location_id' => 'required|exists:locations,id',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'yield_ratio' => 'required|numeric|min:0|max:100',
        ]);

        $branch->update($data);

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
