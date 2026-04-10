<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeStatementClearCacheRequest;
use App\Http\Requests\IncomeStatementDataRequest;
use App\Jobs\GenerateIncomeStatementSnapshot;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\IncomeStatementService;

class IncomeStatementController extends Controller
{
    public function __construct(private readonly IncomeStatementService $incomeStatementService)
    {
        $this->middleware('can:baca laba rugi');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::all();
        $users = User::all();

        return view('pages.income-statement.index', compact('warehouses', 'users'));
    }

    /**
     * Clear cache for income statement data
     */
    public function clearCache(IncomeStatementClearCacheRequest $request)
    {
        $context = $this->incomeStatementService->buildContext($request->validated(), $request->user());
        $this->incomeStatementService->clearSnapshot($context);

        return response()->json(['message' => 'Cache cleared successfully']);
    }

    public function data(IncomeStatementDataRequest $request)
    {
        $context = $this->incomeStatementService->buildContext($request->validated(), $request->user());
        $cachedResult = $this->incomeStatementService->getSnapshot($context);
        if ($cachedResult) {
            $statusCode = ($cachedResult['status'] ?? null) === 'failed' ? 500 : 200;

            return response()->json($cachedResult, $statusCode);
        }

        $this->incomeStatementService->storePending($context);
        GenerateIncomeStatementSnapshot::dispatch($context)->afterCommit();

        return response()->json([
            'status' => 'pending',
            'message' => 'Income statement sedang diproses. Silakan refresh beberapa saat lagi.',
        ], 202);
    }
}
