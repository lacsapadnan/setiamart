<?php

namespace Tests\Feature;

use App\Models\SendStock;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendStockNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_send_stock_number_starts_at_one_for_the_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00'));

        $number = SendStock::generateSendStockNumber();

        $this->assertSame('PS-20260805-0001', $number);

        Carbon::setTestNow();
    }

    public function test_generate_send_stock_number_skips_existing_high_sequence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00'));

        [$user, $warehouse] = $this->createUserAndWarehouse();

        // Simulate legacy count()-based number that already exists (the production bug case).
        SendStock::create([
            'user_id' => $user->id,
            'from_warehouse' => $warehouse->id,
            'to_warehouse' => $warehouse->id,
            'status' => 'completed',
            'completed_at' => now(),
            'send_stock_number' => 'PS-20260805-11618',
        ]);

        $number = SendStock::generateSendStockNumber();

        $this->assertSame('PS-20260805-11619', $number);

        Carbon::setTestNow();
    }

    public function test_creating_send_stock_auto_assigns_unique_number(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00'));

        [$user, $warehouse] = $this->createUserAndWarehouse();

        SendStock::create([
            'user_id' => $user->id,
            'from_warehouse' => $warehouse->id,
            'to_warehouse' => $warehouse->id,
            'status' => 'completed',
            'completed_at' => now(),
            'send_stock_number' => 'PS-20260805-11618',
        ]);

        $sendStock = SendStock::create([
            'user_id' => $user->id,
            'from_warehouse' => $warehouse->id,
            'to_warehouse' => $warehouse->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->assertSame('PS-20260805-11619', $sendStock->send_stock_number);
        $this->assertDatabaseHas('send_stocks', [
            'id' => $sendStock->id,
            'send_stock_number' => 'PS-20260805-11619',
        ]);

        Carbon::setTestNow();
    }

    public function test_generate_send_stock_number_is_independent_of_total_row_count(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 12:00:00'));

        [$user, $warehouse] = $this->createUserAndWarehouse();

        // Older-day records inflate total count but must not affect today's sequence.
        SendStock::create([
            'user_id' => $user->id,
            'from_warehouse' => $warehouse->id,
            'to_warehouse' => $warehouse->id,
            'status' => 'completed',
            'completed_at' => Carbon::parse('2026-08-01'),
            'send_stock_number' => 'PS-20260801-9999',
            'created_at' => Carbon::parse('2026-08-01'),
            'updated_at' => Carbon::parse('2026-08-01'),
        ]);

        $number = SendStock::generateSendStockNumber();

        $this->assertSame('PS-20260805-0001', $number);

        Carbon::setTestNow();
    }

    /**
     * @return array{0: User, 1: Warehouse}
     */
    private function createUserAndWarehouse(): array
    {
        $warehouse = Warehouse::create([
            'name' => 'Gudang Tes',
            'address' => 'Jl. Test',
            'phone' => '08123456789',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        return [$user, $warehouse];
    }
}
