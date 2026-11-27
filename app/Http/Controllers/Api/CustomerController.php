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
        $this->validateFilters($request, ['type' => 'nullable|in:cash,corporate']);

        $query = Customer::query();

        if (!auth()->user()->hasAnyRole(['super-admin', 'admin'])) {
            $query->where('branch_id', auth()->user()->owner_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->has('type')) {
            $query->where('customer_type', $request->type);
        }

        $customers = $query->with('addresses')->get();

        $customers->transform(fn ($customer) => $this->withDocumentUrls($customer));

        return response()->json([
            'data' => $customers
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Multipart forms send "0"/"1"; normalise so required_if:same_address,false works.
        $request->merge(['same_address' => $request->boolean('same_address')]);

        $data = $request->validate([
            // Head office must pick a branch, otherwise the customer would be invisible to every branch
            'branch_id' => [\Illuminate\Validation\Rule::requiredIf(fn () => $this->isHeadOffice()), 'nullable', 'exists:branches,id'],
            'company_name' => 'required|string|max:255',
            'customer_type' => 'required|in:cash,corporate',
            'type' => 'required|in:individual,company',
            'gst_number' => 'nullable|regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/',
            'gst_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'pan_number' => 'nullable|regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/',
            'pan_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'aadhar_number' => 'nullable|digits:12',
            'aadhar_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'contact_person' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|regex:/^[6-9]\d{9}$/',
            'same_address' => 'boolean',

            // Billing – always required
            'billing_name'            => 'required|string|max:150',
                        'billing_address_line1'   => 'required|string|max:255',
            'billing_address_line2'   => 'nullable|string|max:255',
                        'billing_city'            => 'required|string|max:100',
            'billing_pincode'         => 'required|digits:6',
            'billing_phone'           => 'required|regex:/^[6-9]\d{9}$/',
            'billing_email'           => 'nullable|email|max:100',
            'billing_state_id'        => 'required|exists:states,id',
            // Shipping (required only when different)
            'shipping_name'             => 'required_if:same_address,false|string|max:150',
                        'shipping_address_line1'    => 'required_if:same_address,false|required_with:shipping_name|string|max:255',
            'shipping_address_line2'    => 'nullable|string|max:255',
            'shipping_city'             => 'required_if:same_address,false|required_with:shipping_name|string|max:100',
            'shipping_state_id'         => 'required_if:same_address,false|required_with:shipping_name|exists:states,id',
            'shipping_pincode'          => 'required_if:same_address,false|required_with:shipping_name|digits:6',
            'shipping_phone'            => 'required_if:same_address,false|required_with:shipping_name|regex:/^[6-9]\d{9}$/',
            'shipping_email'            => 'nullable|email|max:100',
        ]);

        if ($request->hasFile('gst_image_path')) {
            $path = $request->file('gst_image_path')->store('customers/gst', 'local');
            $data['gst_image_path'] = $path;
        }

        if ($request->hasFile('pan_image_path')) {
            $path = $request->file('pan_image_path')->store('customers/pan', 'local');
            $data['pan_image_path'] = $path;
        }

        if ($request->hasFile('aadhar_image_path')) {
            $path = $request->file('aadhar_image_path')->store('customers/aadhar', 'local');
            $data['aadhar_image_path'] = $path;
        }

        $data['customer_code'] = Customer::generateCustomerCode();

        try {
        $customer = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
        $customer = Customer::create([
            'branch_id' => auth()->user()->hasAnyRole(['super-admin', 'admin'])
                ? $data['branch_id'] ?? null
                : auth()->user()->owner_id,
            'customer_code' => $data['customer_code'],
            'company_name' => $data['company_name'],
            'type' => $data['type'],
            'customer_type' => $data['customer_type'],
            'gst_number' => $data['gst_number'] ?? null,
            'gst_image_path' => $data['gst_image_path'] ?? null,
            'pan_number' => $data['pan_number'] ?? null,
            'pan_image_path' => $data['pan_image_path'] ?? null,
            'aadhar_number' => $data['aadhar_number'] ?? null,
            'aadhar_image_path' => $data['aadhar_image_path'] ?? null,
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'created_by' => auth()->id(),
        ]);

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

        return $customer;
        });
        } catch (\Throwable $e) {
            // Uploaded KYC files must not outlive a failed create
            foreach (['gst_image_path', 'pan_image_path', 'aadhar_image_path'] as $f) {
                if (! empty($data[$f]) && is_string($data[$f])) {
                    $this->deleteDocument($data[$f]);
                }
            }
            return response()->json([
                'message' => 'Failed to create customer',
                'error'   => $this->errorDetail($e),
            ], 500);
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
        $this->authorizeCustomer($customer);

        $customer->load('addresses');

        return response()->json([
            'data' => $this->withDocumentUrls($customer)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $this->authorizeCustomer($customer);

        if ($request->has('same_address')) {
            $request->merge(['same_address' => $request->boolean('same_address')]);
        }

        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'company_name' => 'sometimes|required|string|max:255',
            'customer_type' => 'sometimes|required|in:cash,corporate',
            'type' => 'sometimes|required|in:individual,company',
            'gst_number' => 'nullable|regex:/^[0-9]{2}[A-Za-z]{5}[0-9]{4}[A-Za-z][1-9A-Za-z]Z[0-9A-Za-z]$/',
            'gst_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'pan_number' => 'nullable|regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/',
            'pan_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'aadhar_number' => 'nullable|digits:12',
            'aadhar_image_path' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'contact_person' => 'nullable|string|max:150',
            'contact_phone' => 'nullable|regex:/^[6-9]\d{9}$/',
            'same_address' => 'boolean',

            'billing_name'          => 'sometimes|required|string|max:150',
            'billing_address_line1' => 'required_with:billing_name|string|max:255',
            'billing_address_line2' => 'nullable|string|max:255',
            'billing_city'          => 'required_with:billing_name|string|max:100',
            'billing_pincode'       => 'required_with:billing_name|digits:6',
            'billing_phone'         => 'required_with:billing_name|regex:/^[6-9]\d{9}$/',
            'billing_email'         => 'nullable|email|max:100',
            'billing_state_id'      => 'required_with:billing_name|exists:states,id',

            'shipping_name'          => 'required_if:same_address,false|string|max:150',
            'shipping_address_line1' => 'required_if:same_address,false|required_with:shipping_name|string|max:255',
            'shipping_address_line2' => 'nullable|string|max:255',
            'shipping_city'          => 'required_if:same_address,false|required_with:shipping_name|string|max:100',
            'shipping_pincode'       => 'required_if:same_address,false|required_with:shipping_name|digits:6',
            'shipping_phone'         => 'required_if:same_address,false|required_with:shipping_name|regex:/^[6-9]\d{9}$/',
            'shipping_email'         => 'nullable|email|max:100',
            'shipping_state_id'      => 'required_if:same_address,false|required_with:shipping_name|exists:states,id',
        ]);

        if ($request->hasFile('gst_image_path')) {
            // Delete old image if exists
            if ($customer->gst_image_path) {
                $this->deleteDocument($customer->gst_image_path);
            }
            $path = $request->file('gst_image_path')->store('customers/gst', 'local');
            $data['gst_image_path'] = $path;
        }

        if ($request->hasFile('pan_image_path')) {
            // Delete old image if exists
            if ($customer->pan_image_path) {
                $this->deleteDocument($customer->pan_image_path);
            }
            $path = $request->file('pan_image_path')->store('customers/pan', 'local');
            $data['pan_image_path'] = $path;
        }

        if ($request->hasFile('aadhar_image_path')) {
            // Delete old image if exists
            if ($customer->aadhar_image_path) {
                $this->deleteDocument($customer->aadhar_image_path);
            }
            $path = $request->file('aadhar_image_path')->store('customers/aadhar', 'local');
            $data['aadhar_image_path'] = $path;
        }

        if (!auth()->user()->hasAnyRole(['super-admin', 'admin'])) {
            unset($data['branch_id']); // don't allow branch change for non-admins
        }

        $addressFields = [
            'same_address',
            'billing_name', 'billing_address_line1', 'billing_address_line2',
            'billing_city', 'billing_pincode', 'billing_phone', 'billing_email', 'billing_state_id',
            'shipping_name', 'shipping_address_line1', 'shipping_address_line2',
            'shipping_city', 'shipping_pincode', 'shipping_phone', 'shipping_email', 'shipping_state_id',
        ];
        $addressData = array_intersect_key($data, array_flip($addressFields));
        $data = array_diff_key($data, array_flip($addressFields));

        $customer->update($data);

        if (array_key_exists('billing_name', $addressData)) {
            $sameAddress = $request->boolean('same_address');
            $gstNumber = $data['gst_number'] ?? $customer->gst_number;

            $billingAddress = $customer->addresses()->whereIn('address_type', ['billing', 'both'])->first()
                ?? $customer->addresses()->make();

            $billingAddress->fill([
                'address_type'       => $sameAddress ? 'both' : 'billing',
                'contact_person'     => $addressData['billing_name'],
                'contact_phone'      => $addressData['billing_phone'],
                'email'              => $addressData['billing_email'] ?? null,
                'address_line1'      => $addressData['billing_address_line1'],
                'address_line2'      => $addressData['billing_address_line2'] ?? null,
                'city'               => $addressData['billing_city'],
                'state_id'           => $addressData['billing_state_id'],
                'pincode'            => $addressData['billing_pincode'],
                'gst_number'         => $gstNumber,
                'is_default_pickup'  => $sameAddress,
            ])->save();

            $shippingAddress = $customer->addresses()->where('address_type', 'shipping')->first();

            if ($sameAddress) {
                $shippingAddress?->delete();
            } elseif (array_key_exists('shipping_name', $addressData)) {
                $shippingAddress ??= $customer->addresses()->make(['address_type' => 'shipping']);

                $shippingAddress->fill([
                    'contact_person'    => $addressData['shipping_name'],
                    'contact_phone'     => $addressData['shipping_phone'],
                    'email'             => $addressData['shipping_email'] ?? null,
                    'address_line1'     => $addressData['shipping_address_line1'],
                    'address_line2'     => $addressData['shipping_address_line2'] ?? null,
                    'city'              => $addressData['shipping_city'],
                    'state_id'          => $addressData['shipping_state_id'],
                    'pincode'           => $addressData['shipping_pincode'],
                    'gst_number'        => $gstNumber,
                    'is_default_pickup' => false,
                ])->save();
            }
        }

        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $this->withDocumentUrls($customer->load('addresses'))
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $this->authorizeCustomer($customer);

        $customer->update(['is_active' => false]);

        return response()->json([
            'message' => 'Customer deactivated successfully'
        ]);
    }

    public function activate($id)
    {
        $customer = Customer::findOrFail($id);
        $this->authorizeCustomer($customer);
        $customer->update(['is_active' => true]);

        return response()->json([
            'message' => 'Customer activated successfully'
        ]);
    }

    /**
     * Stream a KYC document (gst | pan | aadhar) to an authorised user.
     * Files live on the private 'local' disk; legacy files on the 'public' disk are still found.
     */
    public function document(Customer $customer, string $type)
    {
        abort_unless(in_array($type, ['gst', 'pan', 'aadhar'], true), 404);
        $this->authorizeCustomer($customer);

        $path = $customer->getRawOriginal("{$type}_image_path");
        abort_unless($path, 404);

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return response()->file(Storage::disk($disk)->path($path), [
                    'Cache-Control' => 'private, no-store',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        abort(404);
    }

    /** Replace stored KYC paths with authenticated download URLs for API output. */
    private function withDocumentUrls(Customer $customer): Customer
    {
        foreach (['gst', 'pan', 'aadhar'] as $type) {
            $attr = "{$type}_image_path";
            $customer->setAttribute(
                $attr,
                $customer->getRawOriginal($attr) ? url("/api/customers/{$customer->id}/documents/{$type}") : null
            );
        }

        return $customer;
    }

    private function deleteDocument(string $path): void
    {
        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }
}
