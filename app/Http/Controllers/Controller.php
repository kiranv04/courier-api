<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Manifest;
use App\Models\Shipment;

abstract class Controller
{
    /** Log the exception; only expose its message to clients when APP_DEBUG is on. */
    protected function errorDetail(\Throwable $e): string
    {
        report($e);

        return config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.';
    }

    /** Validate the common list/report query filters (422 instead of silently ignoring bad input). */
    protected function validateFilters(\Illuminate\Http\Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'branch_id'     => 'nullable|integer|min:1',
            'customer_id'   => 'nullable|integer|min:1',
            'customer_type' => 'nullable|in:cash,corporate',
            'page'          => 'nullable|integer|min:1',
        ], $extra));
    }

    protected function isHeadOffice(): bool
    {
        return auth()->user()->hasAnyRole(['super-admin', 'admin']);
    }

    /** 403 unless the signed-in user may see/act on this shipment. */
    protected function authorizeShipment(Shipment $shipment): void
    {
        abort_unless($shipment->isVisibleTo(auth()->user()), 403, 'You do not have access to this shipment.');
    }

    /** 403 unless the customer belongs to the user's branch (head office: any). */
    protected function authorizeCustomer(Customer $customer): void
    {
        $user = auth()->user();
        abort_unless(
            $this->isHeadOffice()
                || ($user->hasAnyRole(['branch-admin', 'branch-employee'])
                    && $customer->branch_id !== null
                    && (int) $customer->branch_id === (int) $user->owner_id),
            403,
            'You do not have access to this customer.'
        );
    }

    /** 403 unless the invoice belongs to the user's branch (head office: any). */
    protected function authorizeInvoice(Invoice $invoice): void
    {
        $user = auth()->user();
        abort_unless(
            $this->isHeadOffice()
                || ($user->hasAnyRole(['branch-admin', 'branch-employee'])
                    && (int) $invoice->branch_id === (int) $user->owner_id),
            403,
            'You do not have access to this invoice.'
        );
    }

    /** 403 unless the manifest was created by, or is destined for, the user's location. */
    protected function authorizeManifest(Manifest $manifest, bool $originOnly = false): void
    {
        if ($this->isHeadOffice()) {
            return;
        }
        $user = auth()->user();
        $type = $user->hasAnyRole(['warehouse-admin', 'warehouse-employee'])
            ? \App\Models\Warehouse::class
            : \App\Models\Branch::class;

        $isOrigin = $manifest->origin_type === $type && (int) $manifest->origin_id === (int) $user->owner_id;
        $isDest   = !$originOnly && $manifest->destination_type === $type
            && (int) $manifest->destination_id === (int) $user->owner_id;

        abort_unless($isOrigin || $isDest, 403, 'You do not have access to this manifest.');
    }
}
