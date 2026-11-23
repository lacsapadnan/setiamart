<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductReport;
use App\Models\Sell;
use App\Models\SellCart;
use App\Models\SellCartDraft;
use App\Models\SellDetail;
use App\Support\SalePaymentMethodResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SellService
{
    public function __construct(
        protected CashflowService $cashflowService
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: true, type: string, sell_id?: int}|array{ok: false, error: string, with_input?: bool}
     */
    public function store(array $input): array
    {
        $sellCart = SellCart::with(['product', 'unit'])->where('cashier_id', auth()->id())->get();

        // Validate cart has items if not a draft
        if ($sellCart->isEmpty() && ($input['status'] ?? null) !== 'draft') {
            return ['ok' => false, 'error' => 'Keranjang penjualan kosong', 'with_input' => true];
        }

        $transfer = (int) str_replace(',', '', $input['transfer'] ?? 0);
        $cash = (int) str_replace(',', '', $input['cash'] ?? 0);
        $grandtotal = (int) preg_replace('/[,.]/', '', $input['grand_total'] ?? 0);

        // Field yang disembunyikan bisa masih terisi sisa input sebelumnya; abaikan yang tidak sesuai metode.
        if (($input['payment_method'] ?? null) === 'transfer') {
            $cash = 0;
        } elseif (($input['payment_method'] ?? null) === 'cash') {
            $transfer = 0;
        }
        $pay = $transfer + $cash;

        if (($input['status'] ?? null) === 'draft') {
            $status = 'draft';
        } elseif ($pay < $grandtotal) {
            $status = 'piutang';
        } else {
            $status = 'lunas';
        }

        if ($status === 'piutang') {
            $authorizedAt = session('piutang_authorization');
            if (! $authorizedAt || now()->timestamp - $authorizedAt > 300) {
                return [
                    'ok' => false,
                    'error' => 'Transaksi piutang memerlukan otorisasi password user master.',
                    'with_input' => true,
                ];
            }
            session()->forget(['piutang_authorization', 'piutang_authorized_by']);
        }

        $grandTotalValid = 0;
        foreach ($sellCart as $sc) {
            $grandTotalValid += (int) preg_replace('/[,.]/', '', ($sc->price * $sc->quantity) - $sc->diskon);
        }

        if ($grandtotal !== $grandTotalValid) {
            return ['ok' => false, 'error' => 'Terjadi Kesalahan Kalkulasi Total', 'with_input' => true];
        }

        try {
            DB::beginTransaction();

            $rawPaymentMethod = $input['payment_method'] ?? null;
            $paymentMethod = SalePaymentMethodResolver::resolve(
                is_string($rawPaymentMethod) ? $rawPaymentMethod : null,
                $cash,
                $transfer
            );

            if (($input['status'] ?? null) !== 'draft' && $pay > 0 && $paymentMethod === null) {
                throw new InvalidArgumentException('Metode pembayaran tidak dapat ditentukan untuk jurnal kas.');
            }

            $sell = Sell::create([
                'cashier_id' => auth()->id(),
                'warehouse_id' => auth()->user()->warehouse_id,
                'order_number' => $input['order_number'] ?? null,
                'customer_id' => $input['customer'] ?? null,
                'subtotal' => preg_replace('/[,.]/', '', $input['subtotal'] ?? 0),
                'grand_total' => preg_replace('/[,.]/', '', $input['grand_total'] ?? 0),
                'cash' => preg_replace('/[,.]/', '', $cash),
                'transfer' => preg_replace('/[,.]/', '', $transfer),
                'pay' => preg_replace('/[,.]/', '', $pay),
                'change' => preg_replace('/[,.]/', '', $input['change'] ?? 0),
                'transaction_date' => Carbon::createFromFormat('d/m/Y', $input['transaction_date'])->format('Y-m-d'),
                'payment_method' => $paymentMethod,
                'status' => $status,
            ]);

            $customer = Customer::find($input['customer'] ?? null);

            if (($input['status'] ?? null) == 'draft') {
                $draftData = [];
                foreach ($sellCart as $sc) {
                    $draftData[] = [
                        'sell_id' => $sell->id,
                        'cashier_id' => auth()->id(),
                        'product_id' => $sc->product_id,
                        'unit_id' => $sc->unit_id,
                        'quantity' => $sc->quantity,
                        'price' => $sc->price,
                        'diskon' => $sc->diskon,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                SellCartDraft::insert($draftData);

                // Clear the cart after creating draft
                SellCart::where('cashier_id', auth()->id())->delete();
            } else {
                // Prepare bulk insert data for sell details and product reports
                $sellDetailsData = [];
                $productReportsData = [];
                $now = now();

                foreach ($sellCart as $sc) {
                    // Prepare sell details data
                    $sellDetailsData[] = [
                        'sell_id' => $sell->id,
                        'product_id' => $sc->product_id,
                        'unit_id' => $sc->unit_id,
                        'quantity' => $sc->quantity,
                        'price' => $sc->price,
                        'diskon' => $sc->diskon,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Use already loaded relationships from eager loading
                    $unit = $sc->unit;
                    $product = $sc->product;

                    if ($sc->unit_id == $product->unit_dus) {
                        $unitType = 'DUS';
                    } elseif ($sc->unit_id == $product->unit_pak) {
                        $unitType = 'PAK';
                    } elseif ($sc->unit_id == $product->unit_eceran) {
                        $unitType = 'ECERAN';
                    }

                    // Prepare product reports data
                    $productReportsData[] = [
                        'product_id' => $sc->product_id,
                        'warehouse_id' => auth()->user()->warehouse_id,
                        'user_id' => auth()->id(),
                        'customer_id' => $input['customer'] ?? null,
                        'unit' => $unit->name,
                        'unit_type' => $unitType ?? null,
                        'qty' => $sc->quantity,
                        'price' => ($sc->price * $sc->quantity) - $sc->diskon,
                        'for' => 'KELUAR',
                        'type' => 'PENJUALAN',
                        'description' => 'Penjualan '.$sell->order_number,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Bulk insert sell details
                SellDetail::insert($sellDetailsData);

                // Bulk insert product reports
                ProductReport::insert($productReportsData);

                if ($pay > 0) {
                    $this->cashflowService->handleSalePayment(
                        warehouseId: auth()->user()->warehouse_id,
                        orderNumber: $sell->order_number,
                        customerName: $customer->name,
                        paymentMethod: $paymentMethod,
                        cash: $cash,
                        transfer: $transfer,
                        change: $sell->change
                    );
                }

                // Move cart deletion to end of transaction
                SellCart::where('cashier_id', auth()->id())->delete();
            }

            DB::commit();

            // Verify sell details were created if not a draft
            if (($input['status'] ?? null) !== 'draft') {
                $detailCount = SellDetail::where('sell_id', $sell->id)->count();
                if ($detailCount === 0) {
                    return [
                        'ok' => false,
                        'error' => 'Terjadi kesalahan: Detail penjualan tidak tersimpan',
                        'with_input' => true,
                    ];
                }
            }

            if (($input['status'] ?? null) != 'draft') {
                return ['ok' => true, 'type' => 'print', 'sell_id' => $sell->id];
            }

            return ['ok' => true, 'type' => 'draft'];
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return [
                'ok' => false,
                'error' => 'Terjadi kesalahan saat menyimpan transaksi.',
                'with_input' => true,
            ];
        }
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, message: string, status: int}
     */
    public function destroy(int|string $id): array
    {
        $sell = Sell::find($id);

        if (! $sell) {
            return [
                'ok' => false,
                'message' => 'Data penjualan tidak ditemukan',
                'status' => 404,
            ];
        }

        // Delete the sell record
        $sell->delete();

        return [
            'ok' => true,
            'message' => 'Data penjualan berhasil dihapus',
        ];
    }
}
