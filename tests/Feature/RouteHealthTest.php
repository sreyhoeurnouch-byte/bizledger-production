<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_authenticated_application_pages_render_without_server_errors(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);

        foreach (['/dashboard', '/vendors', '/customers', '/items', '/purchasing', '/sales', '/payables', '/stock', '/accounting', '/reports/stock-valuation', '/reports/receivables-aging', '/reports/payables-aging'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }
}
