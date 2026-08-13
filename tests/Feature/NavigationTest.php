<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    public function test_the_main_navigation_links_every_primary_application_module(): void
    {
        $navigation = File::get(resource_path('views/layouts/app.blade.php'));

        foreach ([
            'dashboard', 'vendors.index', 'customers.index', 'items.index', 'stock.index',
            'purchasing.index', 'sales.index', 'payables.index', 'accounting.index',
            'reports.stock', 'reports.receivables', 'reports.payables',
        ] as $route) {
            $this->assertStringContainsString("route('{$route}')", $navigation);
        }
    }
}
