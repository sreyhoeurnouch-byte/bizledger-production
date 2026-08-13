<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Models\VendorBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_payment_cannot_exceed_a_bill_open_balance(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'operator']);
        $vendor = Contact::create(['company_id' => $company->id, 'kind' => 'vendor', 'code' => 'V-100', 'name' => 'Vendor', 'opening_balance' => 0, 'active' => true]);

        $this->actingAs($user)->post(route('payables.store'), [
            'number' => 'BILL-100', 'vendor_id' => $vendor->id, 'bill_date' => now()->toDateString(),
            'lines' => [['description' => 'Freight', 'quantity' => 2, 'unit_cost' => 15]],
        ])->assertRedirect(route('payables.index'));
        $bill = VendorBill::where('number', 'BILL-100')->firstOrFail();
        $this->assertDatabaseHas('vendor_bills', ['id' => $bill->id, 'total' => 30, 'status' => 'open']);

        $this->actingAs($user)->post(route('payables.payment'), [
            'number' => 'PAY-100', 'vendor_id' => $vendor->id, 'payment_date' => now()->toDateString(), 'amount' => 31,
            'allocations' => [['vendor_bill_id' => $bill->id, 'amount' => 31]],
        ])->assertSessionHasErrors('allocations');
        $this->assertDatabaseMissing('vendor_payments', ['number' => 'PAY-100']);

        $this->actingAs($user)->post(route('payables.payment'), [
            'number' => 'PAY-101', 'vendor_id' => $vendor->id, 'payment_date' => now()->toDateString(), 'amount' => 30,
            'allocations' => [['vendor_bill_id' => $bill->id, 'amount' => 30]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendor_bills', ['id' => $bill->id, 'status' => 'paid']);
        $this->assertDatabaseHas('vendor_payment_allocations', ['vendor_bill_id' => $bill->id, 'amount' => 30]);
    }
}
