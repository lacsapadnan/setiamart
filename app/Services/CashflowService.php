<?php

namespace App\Services;

use App\Models\Cashflow;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class CashflowService
{
    /**
     * Create a single cashflow entry
     */
    public function createCashflow(array $data): Cashflow
    {
        $defaultData = [
            'user_id' => Auth::id(),
            'payment_method' => 'cash',
        ];

        return Cashflow::create(array_merge($defaultData, $data));
    }

    /**
     * Handle sale payments (transfer, cash, or split)
     */
    public function handleSalePayment(
        int $warehouseId,
        string $orderNumber,
        string $customerName,
        string $paymentMethod,
        float $cash = 0,
        float $transfer = 0,
        float $change = 0
    ): void {
        $description = "Penjualan {$orderNumber} Customer {$customerName}";

        $method = strtolower(trim($paymentMethod));
        $hasPaymentAmount = $cash > 0 || $transfer > 0;

        switch ($method) {
            case 'transfer':
                if ($transfer > 0) {
                    $this->createTransferPaymentFlow($warehouseId, $description, $transfer - $change);
                }
                break;

            case 'cash':
                if ($cash > 0) {
                    $this->createCashflow([
                        'warehouse_id' => $warehouseId,
                        'for' => 'Penjualan',
                        'description' => $description,
                        'in' => $cash - $change,
                        'out' => 0,
                        'payment_method' => 'cash',
                    ]);
                }
                break;

            case 'split':
                if ($cash > 0 && $transfer > 0) {
                    $this->handleSplitPayment($warehouseId, $orderNumber, $customerName, $cash, $transfer, $change);
                }
                break;

            default:
                if ($hasPaymentAmount) {
                    throw new InvalidArgumentException(
                        "Metode pembayaran penjualan tidak dikenal: \"{$paymentMethod}\" (order {$orderNumber})."
                    );
                }
                break;
        }
    }

    /**
     * Handle split payment for sales
     */
    private function handleSplitPayment(
        int $warehouseId,
        string $orderNumber,
        string $customerName,
        float $cash,
        float $transfer,
        float $change
    ): void {
        $cashFinal = $cash - $change;
        $transferFormat = number_format($transfer, 0, ',', '.');
        $cashFormat = number_format($cashFinal, 0, ',', '.');

        $description = "Penjualan {$orderNumber} transfer sebesar {$transferFormat} dan tunai sebesar {$cashFormat} Customer {$customerName}";

        // Transfer out
        $this->createCashflow([
            'warehouse_id' => $warehouseId,
            'for' => 'Penjualan',
            'description' => $description,
            'in' => 0,
            'out' => $transfer,
            'payment_method' => 'split payment',
        ]);

        // Transfer in
        $this->createCashflow([
            'warehouse_id' => $warehouseId,
            'for' => 'Penjualan',
            'description' => $description,
            'in' => $transfer,
            'out' => 0,
            'payment_method' => 'split payment',
        ]);

        // Cash in (Note: this should be the total, not just cash portion)
        $this->createCashflow([
            'warehouse_id' => $warehouseId,
            'for' => 'Penjualan',
            'description' => $description,
            'in' => $cashFinal,
            'out' => 0,
            'payment_method' => 'split payment',
        ]);
    }

    /**
     * Handle credit payment for sales
     */
    public function handleCreditPayment(
        int $warehouseId,
        string $orderNumber,
        string $customerName,
        string $paymentMethod,
        float $payment = 0,
        float $paymentCash = 0,
        float $paymentTransfer = 0,
        float $potongan = 0,
        string $keterangan = ''
    ): void {
        $description = "Bayar piutang {$orderNumber} Customer {$customerName} {$keterangan}";

        switch ($paymentMethod) {
            case 'split':
                if ($paymentCash > 0) {
                    $this->createCashflow([
                        'warehouse_id' => $warehouseId,
                        'for' => 'Bayar piutang',
                        'description' => $description,
                        'in' => $paymentCash,
                        'out' => 0,
                        'payment_method' => 'cash',
                    ]);
                }

                if ($paymentTransfer > 0) {
                    $this->createTransferPaymentFlow($warehouseId, $description, $paymentTransfer, 'Bayar piutang');
                }
                break;

            case 'transfer':
                if ($payment > 0) {
                    $this->createTransferPaymentFlow($warehouseId, $description, $payment, 'Bayar piutang');
                }
                break;

            case 'cash':
                if ($payment > 0) {
                    $this->createCashflow([
                        'warehouse_id' => $warehouseId,
                        'for' => 'Bayar piutang',
                        'description' => $description,
                        'in' => $payment,
                        'out' => 0,
                        'payment_method' => 'cash',
                    ]);
                }
                break;
        }

        // Handle potongan (discount)
        if ($potongan > 0) {
            $this->createCashflow([
                'warehouse_id' => $warehouseId,
                'for' => 'Bayar piutang',
                'description' => "Potongan bayar piutang {$orderNumber}",
                'in' => 0,
                'out' => $potongan,
                'payment_method' => 'cash',
            ]);
        }
    }

    /**
     * Handle purchase payments
     */
    public function handlePurchasePayment(
        int $warehouseId,
        string $orderNumber,
        string $supplierName,
        string $paymentMethod,
        float $cash = 0,
        float $transfer = 0,
        float $grandTotal = 0
    ): void {

        $description = "Pembelian {$orderNumber} Supplier {$supplierName}";

        switch ($paymentMethod) {
            case 'transfer':
                if ($grandTotal > 0) {
                    // Use grand total for transfer, create two entries (out and in)
                    $this->createTransferPaymentFlow($warehouseId, $description, $grandTotal, 'Pembelian');
                }
                break;

            case 'cash':
                if ($grandTotal > 0) {
                    // Use grand total for cash payment
                    $this->createCashflow([
                        'warehouse_id' => $warehouseId,
                        'for' => 'Pembelian',
                        'description' => $description,
                        'in' => 0,
                        'out' => $grandTotal,
                        'payment_method' => 'cash',
                    ]);
                }
                break;

            case 'split':
                if ($cash > 0 || $transfer > 0) {
                    $this->handlePurchaseSplitPayment($warehouseId, $orderNumber, $supplierName, $cash, $transfer, $grandTotal);
                }
                break;

            default:
                break;
        }
    }

    /**
     * Handle split payment for purchases (creates 3 entries like sales)
     */
    private function handlePurchaseSplitPayment(
        int $warehouseId,
        string $orderNumber,
        string $supplierName,
        float $cash,
        float $transfer,
        float $grandTotal
    ): void {
        $transferFormat = number_format($transfer, 0, ',', '.');
        $cashFormat = number_format($cash, 0, ',', '.');

        $description = "Pembelian {$orderNumber} transfer sebesar {$transferFormat} dan tunai sebesar {$cashFormat} Supplier {$supplierName}";

        // For purchases with split payment, create 3 entries to match sales pattern:

        // 1. Transfer out (money going out to transfer account)
        if ($transfer > 0) {
            $this->createCashflow([
                'warehouse_id' => $warehouseId,
                'for' => 'Pembelian',
                'description' => $description,
                'in' => 0,
                'out' => $transfer,
                'payment_method' => 'split payment',
            ]);
        }

        // 2. Transfer in (money coming back from transfer account)
        if ($transfer > 0) {
            $this->createCashflow([
                'warehouse_id' => $warehouseId,
                'for' => 'Pembelian',
                'description' => $description,
                'in' => $transfer,
                'out' => 0,
                'payment_method' => 'split payment',
            ]);
        }

        // 3. Cash out (money going out as cash payment)
        if ($cash > 0) {
            $this->createCashflow([
                'warehouse_id' => $warehouseId,
                'for' => 'Pembelian',
                'description' => $description,
                'in' => 0,
                'out' => $cash,
                'payment_method' => 'split payment',
            ]);
        }
    }

    /**
     * Handle debt payments for purchases
     */
    public function handleDebtPayment(
        int $warehouseId,
        string $orderNumber,
        string $supplierName,
        float $bayarHutang,
        float $potongan,
        string $paymentMethod
    ): void {
        $description = "Bayar hutang {$orderNumber} Supplier {$supplierName}";

        // Handle payment based on method
        switch ($paymentMethod) {
            case 'transfer':
                if ($bayarHutang > 0) {
                    $this->createTransferPaymentFlow($warehouseId, $description, $bayarHutang, 'Bayar hutang');
                }
                break;

            default:
                // For cash, kas kecil, kas besar, etc.
                if ($bayarHutang > 0) {
                    $this->createCashflow([
                        'warehouse_id' => $warehouseId,
                        'for' => 'Bayar hutang',
                        'description' => $description,
                        'in' => 0,
                        'out' => $bayarHutang,
                        'payment_method' => $paymentMethod,
                    ]);
                }
                break;
        }

        // Create cash flow for potongan if applicable - discount is money saved (in)
        if ($potongan > 0) {
            $this->createCashflow([
                'warehouse_id' => $warehouseId,
                'for' => 'Bayar hutang',
                'description' => "Potongan diskon {$description}",
                'in' => $potongan,
                'out' => 0,
                'payment_method' => $paymentMethod,
            ]);
        }
    }

    /**
     * Handle kas (cash register) transactions
     */
    public function handleKasTransaction(
        int $warehouseId,
        string $type,
        string $description,
        float $amount
    ): void {
        $kasFor = $type === 'Kas Masuk' ? 'Kas Masuk' : 'Kas Keluar';

        $this->createCashflow([
            'warehouse_id' => $warehouseId,
            'for' => $kasFor,
            'description' => $description,
            'in' => $type === 'Kas Masuk' ? $amount : 0,
            'out' => $type === 'Kas Keluar' ? $amount : 0,
            'payment_method' => null,
        ]);
    }

    /**
     * Handle treasury mutations
     */
    public function handleTreasuryMutation(
        int $fromWarehouseId,
        string $description,
        float $amount,
        int $outputCashier
    ): void {
        $this->createCashflow([
            'user_id' => $outputCashier,
            'warehouse_id' => $fromWarehouseId,
            'for' => 'Mutasi Kas',
            'description' => $description,
            'out' => $amount,
            'in' => 0,
            'payment_method' => null,
        ]);
    }

    /**
     * Handle settlement transactions
     */
    public function handleSettlement(
        int $warehouseId,
        string $description,
        float $totalReceived,
        int $outputCashier
    ): void {
        $this->createCashflow([
            'user_id' => $outputCashier,
            'warehouse_id' => $warehouseId,
            'for' => 'Settlement',
            'description' => $description,
            'out' => 0,
            'in' => $totalReceived,
            'payment_method' => null,
        ]);
    }

    /**
     * Create transfer payment flow (in and out entries)
     */
    private function createTransferPaymentFlow(
        int $warehouseId,
        string $description,
        float $amount,
        string $for = 'Penjualan'
    ): void {
        // Transfer out
        $this->createCashflow([
            'warehouse_id' => $warehouseId,
            'for' => $for,
            'description' => $description,
            'in' => 0,
            'out' => $amount,
            'payment_method' => 'transfer',
        ]);

        // Transfer in
        $this->createCashflow([
            'warehouse_id' => $warehouseId,
            'for' => $for,
            'description' => $description,
            'in' => $amount,
            'out' => 0,
            'payment_method' => 'transfer',
        ]);
    }

    /**
     * Batch create multiple cashflows
     */
    public function createMultipleCashflows(array $cashflows): void
    {
        foreach ($cashflows as $cashflowData) {
            $this->createCashflow($cashflowData);
        }
    }

    /**
     * Delete a cashflow entry
     */
    public function deleteCashflow(int $id): bool
    {
        $cashflow = Cashflow::find($id);

        if ($cashflow) {
            return $cashflow->delete();
        }

        return false;
    }

    /**
     * Delete cashflows related to a sale order
     */
    public function deleteSaleCashflows(string $orderNumber): int
    {
        return Cashflow::where('description', 'like', "%{$orderNumber}%")
            ->where('for', 'Penjualan')
            ->delete();
    }

    /**
     * Delete cashflows related to credit payment for a sale order
     */
    public function deleteSaleCreditCashflows(string $orderNumber): int
    {
        return Cashflow::where('description', 'like', "%{$orderNumber}%")
            ->where('for', 'Bayar piutang')
            ->delete();
    }

    /**
     * Delete all cashflows related to a sale (both sale and credit payments)
     */
    public function deleteAllSaleCashflows(string $orderNumber): int
    {
        $deletedSale = $this->deleteSaleCashflows($orderNumber);
        $deletedCredit = $this->deleteSaleCreditCashflows($orderNumber);

        return $deletedSale + $deletedCredit;
    }

    /**
     * Delete cashflows related to a purchase order
     */
    public function deletePurchaseCashflows(string $orderNumber): int
    {
        return Cashflow::where('description', 'like', "%{$orderNumber}%")
            ->where('for', 'Pembelian')
            ->delete();
    }

    /**
     * Delete cashflows related to debt payment for a purchase order
     */
    public function deletePurchaseDebtCashflows(string $orderNumber): int
    {
        return Cashflow::where('description', 'like', "%{$orderNumber}%")
            ->where('for', 'Bayar hutang')
            ->delete();
    }

    /**
     * Delete all cashflows related to a purchase (both purchase and debt payments)
     */
    public function deleteAllPurchaseCashflows(string $orderNumber): int
    {
        $deletedPurchase = $this->deletePurchaseCashflows($orderNumber);
        $deletedDebt = $this->deletePurchaseDebtCashflows($orderNumber);

        return $deletedPurchase + $deletedDebt;
    }

    /**
     * Delete cashflows related to treasury mutation
     */
    public function deleteTreasuryMutationCashflows(string $description, int $outputCashier, int $fromWarehouseId): int
    {
        // More flexible deletion - match by key criteria
        $query = Cashflow::where('for', 'Mutasi Kas')
            ->where('user_id', $outputCashier)
            ->where('warehouse_id', $fromWarehouseId);

        // Handle description matching (including null descriptions)
        if ($description) {
            $query->where('description', $description);
        } else {
            $query->whereNull('description');
        }

        return $query->delete();
    }

    /**
     * Delete cashflows by treasury mutation (alternative method using LIKE)
     */
    public function deleteTreasuryMutationCashflowsLike(string $description, int $outputCashier, int $fromWarehouseId): int
    {
        return Cashflow::where('for', 'Mutasi Kas')
            ->where('user_id', $outputCashier)
            ->where('warehouse_id', $fromWarehouseId)
            ->where('description', 'like', "%{$description}%")
            ->delete();
    }

    /**
     * Handle return transactions (retur penjualan)
     */
    public function handleReturnTransaction(
        int $warehouseId,
        string $orderNumber,
        string $customerName,
        float $totalReturnAmount,
        string $sellStatus,
        float $paidAmount = 0,
        bool $isPartialPayment = false
    ): void {
        if ($sellStatus === 'lunas') {
            // For fully paid sales, refund the full return amount
            $this->createCashflow([
                'warehouse_id' => $warehouseId,
                'for' => 'Retur penjualan',
                'description' => "Retur Penjualan {$orderNumber} - {$customerName}",
                'out' => $totalReturnAmount,
                'in' => 0,
                'payment_method' => null,
            ]);
        } elseif ($sellStatus === 'piutang' && $paidAmount > 0) {
            // For debt sales with partial payment, only refund up to the amount that was paid
            $refundAmount = min($totalReturnAmount, $paidAmount);

            if ($refundAmount > 0) {
                $description = $isPartialPayment
                    ? "Retur Penjualan {$orderNumber} - {$customerName} (Sebagian terbayar)"
                    : "Retur Penjualan {$orderNumber} - {$customerName}";

                $this->createCashflow([
                    'warehouse_id' => $warehouseId,
                    'for' => 'Retur penjualan',
                    'description' => $description,
                    'out' => $refundAmount,
                    'in' => 0,
                    'payment_method' => null,
                ]);
            }
        }
        // For piutang with no payment ($paidAmount = 0), no cashflow entry is created
    }

    /**
     * Delete cashflows related to return transactions
     */
    public function deleteReturnCashflows(string $orderNumber): int
    {
        return Cashflow::where('description', 'like', "%Retur Penjualan {$orderNumber}%")
            ->where('for', 'Retur penjualan')
            ->delete();
    }

    /**
     * Record purchase expense in cashflow
     */
    public function recordPurchaseExpense(
        int $warehouseId,
        string $orderNumber,
        string $supplierName,
        string $paymentMethod,
        float $amount
    ): void {

        $description = "Pembelian {$orderNumber} Supplier {$supplierName}";

        // Handle transfer payments differently - they need two entries (out and in)
        if ($paymentMethod === 'transfer') {
            $this->createTransferPaymentFlow($warehouseId, $description, $amount, 'Pembelian');
        } else {
            // For other payment methods (cash, split), create single entry
            $cashflowData = [
                'warehouse_id' => $warehouseId,
                'for' => 'Pembelian',
                'description' => $description,
                'in' => 0,
                'out' => $amount, // Grand total as expense
                'payment_method' => $paymentMethod,
            ];

            $cashflow = $this->createCashflow($cashflowData);
        }
    }
}
