<?php

namespace App\Console\Commands;

use App\Models\SendStock;
use Illuminate\Console\Command;

class BackfillSendStockNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sendstock:backfill-numbers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill send_stock_number for all existing SendStock records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting backfill of send stock numbers...');

        // Get all SendStock records that don't have a send_stock_number
        $sendStocks = SendStock::whereNull('send_stock_number')
            ->orderBy('id')
            ->get();

        if ($sendStocks->isEmpty()) {
            $this->info('No records to backfill.');
            return 0;
        }

        $this->info("Found {$sendStocks->count()} records to backfill.");

        $progressBar = $this->output->createProgressBar($sendStocks->count());
        $progressBar->start();

        $updated = 0;

        foreach ($sendStocks as $sendStock) {
            // Generate send stock number using the same format as print function
            // Format: PS-{Ymd}-{id_padded}
            $date = $sendStock->created_at->format('Ymd');
            $sendStockNumber = "PS-{$date}-" . str_pad($sendStock->id, 4, '0', STR_PAD_LEFT);

            // Update the record
            $sendStock->send_stock_number = $sendStockNumber;
            $sendStock->save();

            $updated++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Successfully backfilled {$updated} send stock numbers.");

        return 0;
    }
}
