<?php

namespace App\Http\Controllers;

use App\Http\Requests\SellReturAddCartRequest;
use App\Http\Requests\SellReturUpdateCartQuantityRequest;
use App\Models\Sell;
use App\Models\SellRetur;
use App\Models\SellReturCart;
use App\Models\SellReturDetail;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SellReturCartService;
use App\Services\SellReturQueryService;
use App\Services\SellReturService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SellReturController extends Controller
{
    public function __construct(
        protected SellReturService $sellReturService,
        protected SellReturCartService $cartService,
        protected SellReturQueryService $queryService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $masters = User::role('master')->get();
        $warehouses = Warehouse::all();
        $users = User::all();

        return view('pages.retur.index', compact('masters', 'warehouses', 'users'));
    }

    public function data(Request $request)
    {
        $user = auth()->user();
        $isMaster = $user->getRoleNames()[0] == 'master';

        return response()->json($this->queryService->data(
            [
                'user_id' => $request->input('user_id'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'warehouse' => $request->input('warehouse'),
            ],
            $isMaster,
            $user->id,
            $user->warehouse_id,
        ));
    }

    public function dataBySaleId(int|string $saleId)
    {
        $user = auth()->user();
        $isMaster = $user->getRoleNames()[0] == 'master';

        return response()->json($this->queryService->dataBySaleId(
            $saleId,
            $isMaster,
            $user->warehouse_id,
        ));
    }

    public function dataDetail(int|string $id)
    {
        return response()->json($this->queryService->dataDetail($id));
    }

    public function dataSell(Request $request)
    {
        $user = auth()->user();
        $isMaster = $user->getRoleNames()[0] == 'master';

        return $this->queryService->dataSell(
            [
                'cashier_id' => $request->input('cashier_id'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'warehouse' => $request->input('warehouse'),
            ],
            $isMaster,
            $user->warehouse_id,
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $masters = User::role('master')->get();
        $warehouses = Warehouse::all();
        $role = auth()->user()->getRoleNames();
        if ($role[0] == 'master') {
            $users = User::all();
        } else {
            $users = User::where('warehouse_id', auth()->user()->warehouse_id)->get();
        }

        return view('pages.retur.list-penjualan', compact('masters', 'warehouses', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $result = $this->sellReturService->store($request->sell_id);

        if (! $result['ok']) {
            return redirect()->back()->with('error', $result['error']);
        }

        return redirect()->route('penjualan-retur.index')->with('success', 'Retur berhasil disimpan');
    }

    public function konfirmReturn(Request $request)
    {
        $result = $this->sellReturService->konfirmReturn(
            $request->sell_id,
            $request->input('selectedIds', [])
        );

        if (! $result['ok']) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json(['message' => 'Return confirmed successfully']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $sellId = $id;
        $penjualan = Sell::with('customer', 'warehouse', 'details.product', 'details.unit')->findOrFail($id);
        $cart = SellReturCart::with('product', 'unit')
            ->where('user_id', auth()->id())
            ->where('sell_id', $id)
            ->get();
        $subtotal = 0;
        foreach ($cart as $c) {
            $subtotal += $c->price * $c->quantity;
        }

        return view('pages.retur.create', compact('penjualan', 'cart', 'subtotal', 'sellId'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $result = $this->sellReturService->destroy($id);

        if (! $result['ok']) {
            return redirect()->back()->with('error', $result['error']);
        }

        if ($result['forceDelete']) {
            return redirect()->route('penjualan-retur.index')->with('success', 'Retur berhasil dihapus secara paksa. Data penjualan terkait tidak ditemukan, namun stok dan laporan produk telah diperbaiki.');
        }

        return redirect()->route('penjualan-retur.index')->with('success', 'Retur berhasil dihapus dan stok telah dikembalikan ke kondisi semula');
    }

    /**
     * Get available quantities for return in all units
     */
    public function getAvailableReturnQuantities(int|string $sellId, int|string $productId)
    {
        $result = $this->cartService->getAvailableReturnQuantities($sellId, $productId);

        if (! $result['ok']) {
            return response()->json(['error' => $result['error']], $result['status']);
        }

        return response()->json($result['data']);
    }

    public function addCart(SellReturAddCartRequest $request)
    {
        $result = $this->cartService->addCart($request->input('input_requests', []));

        if (! $result['ok']) {
            return response()->json([
                'errors' => $result['errors'],
            ], $result['status']);
        }

        return redirect()->back()->with('success', 'Berhasil menambahkan retur ke keranjang');
    }

    public function destroyCart(int|string $id)
    {
        $this->cartService->destroyCart($id);

        return redirect()->back();
    }

    public function updateCartQuantity(SellReturUpdateCartQuantityRequest $request, int|string $id)
    {
        $result = $this->cartService->updateCartQuantity($id, $request->quantity);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Quantity updated successfully',
            'data' => $result['data'],
        ]);
    }

    public function print(int|string $id)
    {
        $sellRetur = SellRetur::with('sell.customer', 'sell.details', 'warehouse', 'user')->where('id', $id)->first();
        $sellReturDetail = SellReturDetail::with('sellRetur.sell.details', 'product', 'unit')->where('sell_retur_id', $id)->get();
        $returNumber = 'PJR-'.now()->format('Ymd').'-'.str_pad($sellRetur->id, 4, '0', STR_PAD_LEFT);

        $totalQuantity = $sellReturDetail->count();
        $totalPrice = 0;

        foreach ($sellReturDetail as $prd) {
            $totalPrice += $prd->qty * $prd->price;
        }

        $pdf = Pdf::loadView('pages.retur.print-retur', compact('sellRetur', 'sellReturDetail', 'totalQuantity', 'returNumber', 'totalPrice'));

        return response()->stream(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Retur-Penjualan-'.$sellRetur->sell->order_number.'.pdf"',
        ]);
    }

    public function viewReturnPenjualan()
    {
        return view('pages.retur.view_return');
    }
}
