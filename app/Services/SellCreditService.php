<?php

namespace App\Services;

use App\Models\Sell;

class SellCreditService
{
    public function __construct(
        protected CashflowService $cashflowService
    ) {}

    /**
     * @param  array{sell_id: int, payment: string, potongan?: mixed, pay?: mixed, pay_credit_cash?: mixed, pay_credit_transfer?: mixed, keterangan?: mixed}  $input
     * @return array{ok: true, message: string}|array{ok: false, message: string}
     */
    public function payCredit(array $input): array
    {
        $sell = Sell::with('customer')->find($input['sell_id']);

        if (! $sell) {
            return [
                'ok' => false,
                'message' => 'Sale not found',
            ];
        }

        $grandTotal = (int) preg_replace('/[,.]/', '', $sell->grand_total);
        $currentPay = (int) preg_replace('/[,.]/', '', $sell->pay);
        $paymentMethod = $input['payment'];

        // Initialize payment variables
        $payment = 0;
        $paymentCash = 0;
        $paymentTransfer = 0;
        $potongan = (int) preg_replace('/[,.]/', '', $input['potongan'] ?? 0);

        if ($paymentMethod === 'split') {
            $paymentCash = (int) preg_replace('/[,.]/', '', $input['pay_credit_cash'] ?? 0);
            $paymentTransfer = (int) preg_replace('/[,.]/', '', $input['pay_credit_transfer'] ?? 0);
            $payment = $paymentCash + $paymentTransfer;

            // Validate split payment
            if ($paymentCash < 0 || $paymentTransfer < 0) {
                return [
                    'ok' => false,
                    'message' => 'Pembayaran tidak boleh kurang dari 0',
                ];
            }
        } else {
            $payment = (int) preg_replace('/[,.]/', '', $input['pay'] ?? 0);
            if ($payment <= 0) {
                return [
                    'ok' => false,
                    'message' => 'Pembayaran tidak boleh 0 atau kurang',
                ];
            }
        }

        $sisaHutang = $grandTotal - $currentPay;

        // Validate total payment (payment + potongan) against remaining debt
        if ($payment > $sisaHutang) {
            return [
                'ok' => false,
                'message' => 'Total pembayaran tidak boleh lebih dari sisa piutang',
            ];
        }

        $sell->pay = $currentPay + $payment + $potongan;

        if ($sell->pay >= $grandTotal) {
            $sell->status = 'lunas';
        }

        $sell->save();

        // Handle cashflow using service
        $this->cashflowService->handleCreditPayment(
            warehouseId: $sell->warehouse_id,
            orderNumber: $sell->order_number,
            customerName: $sell->customer->name,
            paymentMethod: $paymentMethod,
            payment: $payment,
            paymentCash: $paymentCash,
            paymentTransfer: $paymentTransfer,
            potongan: $potongan,
            keterangan: $input['keterangan'] ?? ''
        );

        return [
            'ok' => true,
            'message' => 'Pembayaran piutang berhasil',
        ];
    }
}
