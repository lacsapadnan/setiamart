<?php

namespace App\Jobs;

use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Milon\Barcode\DNS1D;

class GenerateBarcodePDF implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $maxExceptions = 2;

    protected array $products;
    protected string $filename;
    protected int $chunkIndex;
    protected int $totalChunks;

    public function __construct(array $products, string $filename, int $chunkIndex = 0, int $totalChunks = 1)
    {
        $this->products = $products;
        $this->filename = $filename;
        $this->chunkIndex = $chunkIndex;
        $this->totalChunks = $totalChunks;
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $productIds = array_column($this->products, 'id');
            
            // Select all barcode columns from your products table
            $productsData = Product::whereIn('id', $productIds)
                ->select('id', 'name', 'barcode_dus', 'barcode_pak', 'barcode_eceran')
                ->get()
                ->keyBy('id');

            // Initialize barcode generator
            $dns1d = new DNS1D();

            $barcodeData = [];
            foreach ($this->products as $product) {
                $productModel = $productsData->get($product['id']);
                
                if (!$productModel) {
                    continue;
                }

                // Generate the barcode value based on available columns
                $barcodeValue = $this->generateBarcodeValue($productModel);
                
                // Generate PNG barcode as base64
                // Optimized: smaller scale but still readable, faster generation
                $barcodeImage = $dns1d->getBarcodePNG($barcodeValue, 'C128', 2, 60);

                for ($i = 0; $i < $product['quantity']; $i++) {
                    $barcodeData[] = [
                        'name' => $productModel->name,
                        'barcode' => $barcodeValue,
                        'barcode_image' => $barcodeImage,
                    ];
                }
            }

            if (empty($barcodeData)) {
                Log::warning('No barcode data to generate', ['chunk' => $this->chunkIndex]);
                return;
            }

            $chunkFilename = $this->totalChunks > 1 
                ? str_replace('.pdf', "_part{$this->chunkIndex}.pdf", $this->filename)
                : $this->filename;

            $pdf = Pdf::loadView('pdf.barcode-labels', ['products' => $barcodeData])
                ->setPaper('a4')
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('isPhpEnabled', true)
                ->setOption('defaultFont', 'sans-serif');

            $path = 'barcodes/' . $chunkFilename;
            Storage::disk('public')->put($path, $pdf->output());

        } catch (\Exception $e) {
            Log::error('Barcode generation failed', [
                'chunk' => $this->chunkIndex,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate barcode value based on available product attributes
     * Priority: barcode_eceran > barcode_dus > barcode_pak > product ID
     */
    protected function generateBarcodeValue($product): string
    {
        // Check each barcode field in priority order
        if (!empty($product->barcode_eceran)) {
            return $product->barcode_eceran;
        }
        
        if (!empty($product->barcode_dus)) {
            return $product->barcode_dus;
        }
        
        if (!empty($product->barcode_pak)) {
            return $product->barcode_pak;
        }
        
        // Fallback: Use ID with prefix (pad to 8 digits)
        return 'PRD' . str_pad($product->id, 8, '0', STR_PAD_LEFT);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Barcode generation job failed permanently', [
            'chunk' => $this->chunkIndex,
            'filename' => $this->filename,
            'error' => $exception->getMessage()
        ]);
    }
}
