<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PhaseOneRiskReductionTest extends TestCase
{
    public function test_backup_database_route_uses_post_method(): void
    {
        $route = Route::getRoutes()->getByName('backup.database');
        $methods = $route?->methods() ?? [];
        $this->assertContains('POST', $methods);
        $this->assertNotContains('GET', $methods);
    }

    public function test_backup_database_route_has_explicit_permission_middleware(): void
    {
        $route = Route::getRoutes()->getByName('backup.database');
        $middleware = $route?->gatherMiddleware() ?? [];
        $this->assertContains('permission:backup database', $middleware);
    }

    public function test_purchase_retur_api_data_route_has_single_named_registration(): void
    {
        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => $name === 'api.purchaseRetur');
        $this->assertCount(1, $routes);
        $this->assertSame('pembelian-retur/api/data', $routes->first()->uri());
    }

    public function test_transaction_routes_are_guarded_by_auth_middleware(): void
    {
        $sellRoute = Route::getRoutes()->getByName('penjualan.index');
        $purchaseRoute = Route::getRoutes()->getByName('pembelian.index');
        $returRoute = Route::getRoutes()->getByName('pembelian-retur.index');

        $this->assertContains('auth', $sellRoute?->gatherMiddleware() ?? []);
        $this->assertContains('auth', $purchaseRoute?->gatherMiddleware() ?? []);
        $this->assertContains('auth', $returRoute?->gatherMiddleware() ?? []);
    }
}
