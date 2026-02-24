<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use function PHPSTORM_META\type;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->has('type')) {
            $query->where('customer_type', $request->type);
        }

        $customers = $query->with('addresses')->get();

        return response()->json([
            'data' => $customers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'customer_type' => 'required|in:cash,corporate',
            'type' => 'required|in:individual,company',
            'gst_number' => 'nullable|string|max:50',
            'gst_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'pan_number' => 'nullable|string|max:50',
            'pan_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'aadhar_number' => 'nullable|string|max:50',
            'aadhar_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'contact_person' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|string|max:20',
            'same_address' => 'boolean',
            // Billing – always required
            'billing_name'            => 'required|string|max:150',
            'billing_company_name'    => 'nullable|string|max:255',
            'billing_address_line1'   => 'required|string|max:255',
            'billing_address_line2'   => 'nullable|string|max:255',
            'billing_address_line3'   => 'nullable|string|max:255',
            'billing_city'            => 'required|string|max:100',
            'billing_pincode'         => 'required|digits:6',
            'billing_phone'           => 'required|string|max:20',
            'billing_email'           => 'nullable|email|max:100',
            'billing_state_id'        => 'required|exists:states,id',
            // Shipping (required only when different)
            'shipping_name'             => 'required_if:same_address,false|string|max:150',
            'shipping_company_name'     => 'nullable|string|max:255',
            'shipping_address_line1'    => 'required_if:sameAddress,false|string|max:255',
            'shipping_address_line2'    => 'nullable|string|max:255',
            'shipping_city'             => 'required_if:same_address,false|string|max:100',
            'shipping_state_id'         => 'required_if:same_address,false|exists:states,id',
            'shipping_pincode'          => 'required_if:same_address,false|digits:6',
            'shipping_phone'            => 'required_if:same_address,false|string|max:20',
            'shipping_email'            => 'nullable|email|max:100',
        ]);

        if ($request->hasFile('gst_image_path')) {
            $path = $request->file('gst_image_path')->store('customers/gst', 'public');
            $data['gst_image_path'] = $path;
        }

        if ($request->hasFile('pan_image_path')) {
            $path = $request->file('pan_image_path')->store('customers/pan', 'public');
            $data['pan_image_path'] = $path;
        }

        if ($request->hasFile('aadhar_image_path')) {
            $path = $request->file('aadhar_image_path')->store('customers/aadhar', 'public');
            $data['aadhar_image_path'] = $path;
        }

        $data['customer_code'] = Customer::generateCustomerCode();
        $data['created_by'] = auth()->id();

        $customer = Customer::create($data);

        $sameAddress = $request->boolean('same_address');

        $billingAddress = [
            'address_type'        => $sameAddress ? 'both' : 'billing',
            'contact_person'      => $data['billing_name'],
            'contact_phone'       => $data['billing_phone'],
            'email'               => $data['billing_email'] ?? null,
            'address_line1'       => $data['billing_address_line1'],
            'address_line2'       => $data['billing_address_line2'] ?? null,
            'city'                => $data['billing_city'],
            'state_id'            => $data['billing_state_id'],
            'pincode'             => $data['billing_pincode'],
            'gst_number'          => $data['gst_number'] ?? null,
            'is_default_pickup'   => $sameAddress ? true : false,
        ];

        // print_r($billingAddress); exit;

        $customer->addresses()->create($billingAddress);

        // Shipping address only if different
        if (!$sameAddress) {
            $shippingAddress = [
                'address_type'        => 'shipping',
                'contact_person'      => $data['shipping_name'],
                'contact_phone'       => $data['shipping_phone'],
                'email'               => $data['shipping_email'] ?? null,
                'address_line1'       => $data['shipping_address_line1'],
                'address_line2'       => $data['shipping_address_line2'] ?? null,
                'city'                => $data['shipping_city'],
                'state_id'            => $data['shipping_state_id'],
                'pincode'             => $data['shipping_pincode'],
                'gst_number'          => $data['gst_number'] ?? null,
                'is_default_pickup'   => false,
            ];

            $customer->addresses()->create($shippingAddress);
        }

        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->update(['is_active' => false]);

        return response()->json([
            'message' => 'Customer deactivated successfully'
        ]);
    }

    public function activate($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->update(['is_active' => true]);

        return response()->json([
            'message' => 'Customer activated successfully'
        ]);
    }
}
