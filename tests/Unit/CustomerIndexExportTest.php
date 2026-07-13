<?php

namespace Tests\Unit;

use Tests\TestCase;

class CustomerIndexExportTest extends TestCase
{
    public function test_customer_index_initializes_datatable_export_buttons(): void
    {
        $viewPath = resource_path('views/pages/customer/index.blade.php');
        $this->assertFileExists($viewPath);

        $contents = file_get_contents($viewPath);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-kt-export="excel"', $contents);
        $this->assertMatchesRegularExpression(
            '/init:\s*function\s*\(\)\s*\{[\s\S]*exportButtons\(\);/',
            $contents
        );
    }
}
