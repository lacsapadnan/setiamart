<?php

namespace Tests\Feature;

use App\Http\Controllers\IncomeStatementController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Requests\IncomeStatementDataRequest;
use App\Http\Requests\InventoryUpdateRequest;
use App\Http\Requests\ProductImportRequest;
use App\Jobs\GenerateIncomeStatementSnapshot;
use App\Models\User;
use App\Services\IncomeStatementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class PhaseTwoStructureScaleTest extends TestCase
{
    public function test_income_statement_routes_have_throttle_middleware(): void
    {
        $apiRoute = Route::getRoutes()->getByName('api.income-statement');
        $pageRoute = Route::getRoutes()->getByName('income-statement.data');

        $this->assertContains('throttle:30,1', $apiRoute?->gatherMiddleware() ?? []);
        $this->assertContains('throttle:30,1', $pageRoute?->gatherMiddleware() ?? []);
    }

    public function test_income_statement_routes_have_auth_and_permission_middleware(): void
    {
        $apiRoute = Route::getRoutes()->getByName('api.income-statement');
        $pageRoute = Route::getRoutes()->getByName('income-statement.data');

        $apiMiddleware = $apiRoute?->gatherMiddleware() ?? [];
        $pageMiddleware = $pageRoute?->gatherMiddleware() ?? [];

        $this->assertContains('auth', $apiMiddleware);
        $this->assertContains('auth', $pageMiddleware);
        $this->assertContains('can:baca laba rugi', $apiMiddleware);
        $this->assertContains('can:baca laba rugi', $pageMiddleware);
    }

    public function test_inventory_api_routes_are_guarded_by_auth_middleware(): void
    {
        $inventoryDataRoute = Route::getRoutes()->getByName('api.inventori');
        $inventoryExportRoute = Route::getRoutes()->getByName('api.inventori.export');

        $this->assertContains('auth', $inventoryDataRoute?->gatherMiddleware() ?? []);
        $this->assertContains('auth', $inventoryExportRoute?->gatherMiddleware() ?? []);
    }

    public function test_inventory_controller_keeps_role_based_warehouse_filter_logic(): void
    {
        $controllerSource = file_get_contents(app_path('Http/Controllers/InventoryController.php'));

        $this->assertNotFalse($controllerSource);
        $this->assertStringContainsString("\$userRoles[0] != 'master'", $controllerSource);
        $this->assertStringContainsString("\$query->where('warehouse_id', Auth::user()->warehouse_id);", $controllerSource);
    }

    public function test_inventory_controller_update_uses_inventory_update_form_request(): void
    {
        $method = new ReflectionMethod(InventoryController::class, 'update');
        $parameters = $method->getParameters();

        $this->assertSame(InventoryUpdateRequest::class, $parameters[0]->getType()?->getName());
    }

    public function test_product_controller_import_uses_product_import_form_request(): void
    {
        $method = new ReflectionMethod(ProductController::class, 'import');
        $parameters = $method->getParameters();

        $this->assertSame(ProductImportRequest::class, $parameters[0]->getType()?->getName());
    }

    public function test_income_statement_controller_data_uses_form_request(): void
    {
        $method = new ReflectionMethod(IncomeStatementController::class, 'data');
        $parameters = $method->getParameters();

        $this->assertSame(IncomeStatementDataRequest::class, $parameters[0]->getType()?->getName());
    }

    public function test_generate_income_statement_snapshot_job_is_queueable(): void
    {
        $job = new GenerateIncomeStatementSnapshot([
            'user_id' => 1,
            'from_date' => now()->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
            'warehouse_id' => null,
            'all_branches' => false,
            'auth_user' => 1,
        ]);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(3, $job->tries);
    }

    public function test_income_statement_data_returns_ready_snapshot_contract(): void
    {
        $context = ['auth_user' => 1, 'from_date' => now()->format('Y-m-d')];
        $readyPayload = [
            'status' => 'ready',
            'sales_data' => ['total_revenue' => 1000],
            'cogs_data' => ['total_cogs' => -500, 'cogs_by_product' => [], 'is_out_of_town' => false],
            'stock_burden' => ['total_stock_burden' => 10],
            'operating_expenses' => ['total_operating_expenses' => 100],
            'other_income' => ['total_other_income' => 0],
            'gross_profit' => 500,
            'net_income' => 390,
            'period' => ['from_date' => now()->format('Y-m-d'), 'to_date' => now()->format('Y-m-d'), 'warehouse' => 'Semua Gudang'],
            'cache_generated_at' => now()->toISOString(),
        ];

        $serviceMock = Mockery::mock(IncomeStatementService::class);
        $serviceMock->shouldReceive('buildContext')->once()->andReturn($context);
        $serviceMock->shouldReceive('getSnapshot')->once()->with($context)->andReturn($readyPayload);
        $this->app->instance(IncomeStatementService::class, $serviceMock);

        $controller = $this->app->make(IncomeStatementController::class);
        $request = $this->makeIncomeStatementRequest();

        $response = $controller->data($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertSame('ready', $responseData['status']);
        $this->assertArrayHasKey('sales_data', $responseData);
        $this->assertArrayHasKey('net_income', $responseData);
    }

    public function test_income_statement_data_returns_failed_snapshot_contract(): void
    {
        $context = ['auth_user' => 1, 'from_date' => now()->format('Y-m-d')];
        $failedPayload = [
            'status' => 'failed',
            'error' => 'Failed to generate income statement snapshot.',
            'cache_generated_at' => now()->toISOString(),
        ];

        $serviceMock = Mockery::mock(IncomeStatementService::class);
        $serviceMock->shouldReceive('buildContext')->once()->andReturn($context);
        $serviceMock->shouldReceive('getSnapshot')->once()->with($context)->andReturn($failedPayload);
        $this->app->instance(IncomeStatementService::class, $serviceMock);

        $controller = $this->app->make(IncomeStatementController::class);
        $request = $this->makeIncomeStatementRequest();

        $response = $controller->data($request);

        $this->assertSame(500, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertSame('failed', $responseData['status']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function test_income_statement_data_returns_pending_and_dispatches_job_when_snapshot_missing(): void
    {
        Queue::fake();

        $context = ['auth_user' => 1, 'from_date' => now()->format('Y-m-d')];

        $serviceMock = Mockery::mock(IncomeStatementService::class);
        $serviceMock->shouldReceive('buildContext')->once()->andReturn($context);
        $serviceMock->shouldReceive('getSnapshot')->once()->with($context)->andReturnNull();
        $serviceMock->shouldReceive('storePending')->once()->with($context);
        $this->app->instance(IncomeStatementService::class, $serviceMock);

        $controller = $this->app->make(IncomeStatementController::class);
        $request = $this->makeIncomeStatementRequest();

        $response = $controller->data($request);

        $this->assertSame(202, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertSame('pending', $responseData['status']);
        $this->assertArrayHasKey('message', $responseData);

        Queue::assertPushed(GenerateIncomeStatementSnapshot::class);
    }

    protected function makeIncomeStatementRequest(): IncomeStatementDataRequest
    {
        return new class extends IncomeStatementDataRequest
        {
            public function validated($key = null, $default = null): array
            {
                return [];
            }

            public function user($guard = null): User
            {
                return new User;
            }
        };
    }
}
