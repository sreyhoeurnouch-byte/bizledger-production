<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Contact;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        abort_if(app()->isProduction(), 403, 'Demo data must never be seeded in production.');
        $company = Company::firstOrCreate(['name' => 'Northstar Trading'], ['currency' => 'USD', 'timezone' => 'Asia/Phnom_Penh']);
        User::updateOrCreate(['email' => 'admin@bizledger.local'], ['company_id' => $company->id, 'name' => 'Demo Administrator', 'password' => 'ChangeMe123!', 'role' => 'owner', 'active' => true]);
        $warehouse = Warehouse::firstOrCreate(['company_id' => $company->id, 'code' => 'MAIN'], ['name' => 'Main Warehouse', 'active' => true]);
        $group = ItemGroup::firstOrCreate(['company_id' => $company->id, 'code' => 'GENERAL'], ['name' => 'General Goods']);
        Contact::firstOrCreate(['company_id' => $company->id, 'code' => 'V-0001'], ['kind' => 'vendor', 'name' => 'Harbor Supply Co.', 'email' => 'orders@harborsupply.example', 'opening_balance' => 8245]);
        Contact::firstOrCreate(['company_id' => $company->id, 'code' => 'C-0001'], ['kind' => 'customer', 'name' => 'Saffron Market', 'email' => 'finance@saffron.example', 'opening_balance' => 6420]);
        foreach ([['BL-RICE-05', 'Jasmine Rice 5kg', 8.25, 11.50, 20, 8], ['BL-OIL-01', 'Cooking Oil 1L', 2.10, 3.25, 36, 21], ['BL-SUGAR-01', 'Cane Sugar 1kg', .90, 1.45, 30, 84]] as [$sku,$name,$cost,$sale,$reorder,$quantity]) {
            $item = Item::firstOrCreate(['company_id' => $company->id, 'sku' => $sku], ['group_id' => $group->id, 'name' => $name, 'unit' => 'Each', 'cost' => $cost, 'sale_price' => $sale, 'reorder_level' => $reorder, 'quantity' => $quantity]);
            InventoryBalance::firstOrCreate(['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id], ['quantity' => $item->quantity, 'average_cost' => $item->cost]);
        }
        foreach ([['1000', 'Cash and equivalents', 'asset', 48320], ['1100', 'Accounts receivable', 'asset', 6420], ['1200', 'Inventory', 'asset', 126845.50], ['2000', 'Accounts payable', 'liability', 8245], ['4000', 'Sales revenue', 'revenue', 0], ['5000', 'Cost of goods sold', 'expense', 0]] as [$code,$name,$category,$balance]) {
            Account::firstOrCreate(['company_id' => $company->id, 'code' => $code], compact('name', 'category', 'balance'));
        }
        $this->call(MvpDemoSeeder::class);
    }
}
