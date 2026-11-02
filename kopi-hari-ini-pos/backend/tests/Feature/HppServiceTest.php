<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\HppService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppServiceTest extends TestCase
{
    use RefreshDatabase;

    private HppService $hpp;
    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hpp   = new HppService();
        $this->stock = new StockService($this->hpp);

        Setting::create(['key' => 'hpp_method', 'value' => 'weighted_average']);
        Setting::create(['key' => 'tax_percent', 'value' => '10']);
    }

    private function makeProduct(array $attrs = []): Product
    {
        $category = Category::factory()->create();
        $product  = Product::create(array_merge([
            'category_id'   => $category->id,
            'sku'           => 'TEST-' . rand(1000, 9999),
            'name'          => 'Test Product',
            'unit'          => 'pcs',
            'selling_price' => 30000,
        ], $attrs));

        Product::where('id', $product->id)->update([
            'current_cost' => $attrs['current_cost'] ?? 0,
            'stock_qty'    => $attrs['stock_qty'] ?? 0,
        ]);

        return $product->fresh();
    }

    // FR-08: HPP rata-rata tertimbang dari stok nol

    #[\PHPUnit\Framework\Attributes\Test]
    public function weighted_average_from_zero_stock_equals_incoming_cost(): void
    {
        $result = $this->hpp->calculateWeightedAverage(0, 14800, 20, 14200);
        $this->assertEquals(14200, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weighted_average_with_existing_stock_uses_formula(): void
    {
        // ((10 x 15400) + (20 x 14200)) / 30 = 14600
        $result = $this->hpp->calculateWeightedAverage(10, 15400, 20, 14200);
        $this->assertEquals(14600, $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function weighted_average_rounds_half_up(): void
    {
        // (10000 + 10001) / 2 = 10000.5 => 10001
        $result = $this->hpp->calculateWeightedAverage(1, 10000, 1, 10001);
        $this->assertEquals(10001, $result);
    }

    // FR-04: Restock mencatat HPP baru

    #[\PHPUnit\Framework\Attributes\Test]
    public function restock_updates_stock_and_cost(): void
    {
        $user    = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(['current_cost' => 15400, 'stock_qty' => 10]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $user) {
            $product = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->stock->recordRestock($product, 20, 14200, null, null, $user->id);
        });

        $product->refresh();
        $this->assertEquals(30, $product->stock_qty);
        $this->assertEquals(14600, $product->current_cost);
    }

    // FR-13: Stok terpotong setelah checkout

    #[\PHPUnit\Framework\Attributes\Test]
    public function checkout_deducts_stock_and_creates_movement(): void
    {
        $user    = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct(['current_cost' => 11400, 'stock_qty' => 48, 'selling_price' => 22000]);

        $tx = \App\Models\Transaction::create([
            'invoice_no'     => 'TRX-TEST-0001',
            'user_id'        => $user->id,
            'subtotal'       => 0,
            'tax_amount'     => 0,
            'total'          => 0,
            'payment_method' => 'tunai',
            'paid_amount'    => 0,
            'change_amount'  => 0,
            'total_cogs'     => 0,
            'gross_profit'   => 0,
            'occurred_at'    => now(),
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $user, $tx) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->stock->deductForSale($lockedProduct, 2, $user->id, $tx);
        });

        $product->refresh();
        $this->assertEquals(46, $product->stock_qty);

        $movement = \App\Models\StockMovement::where('product_id', $product->id)
            ->where('type', 'sale')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-2, $movement->qty_change);
        $this->assertEquals(46, $movement->qty_after);
    }

    // FR-08: Penolakan stok kurang

    #[\PHPUnit\Framework\Attributes\Test]
    public function checkout_throws_when_stock_insufficient(): void
    {
        $user    = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct(['current_cost' => 7200, 'stock_qty' => 8, 'selling_price' => 15000]);

        $tx = \App\Models\Transaction::create([
            'invoice_no'     => 'TRX-TEST-0002',
            'user_id'        => $user->id,
            'subtotal'       => 0,
            'tax_amount'     => 0,
            'total'          => 0,
            'payment_method' => 'tunai',
            'paid_amount'    => 0,
            'change_amount'  => 0,
            'total_cogs'     => 0,
            'gross_profit'   => 0,
            'occurred_at'    => now(),
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        \Illuminate\Support\Facades\DB::transaction(function () use ($product, $user, $tx) {
            $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->first();
            $this->stock->deductForSale($lockedProduct, 10, $user->id, $tx);
        });
    }

    // FR-16: Idempotensi client_uuid

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_client_uuid_returns_existing_transaction(): void
    {
        $kasir   = User::factory()->create(['role' => 'kasir']);
        $product = $this->makeProduct(['current_cost' => 11400, 'stock_qty' => 48, 'selling_price' => 22000]);
        $uuid    = \Illuminate\Support\Str::uuid()->toString();

        $payload = [
            'client_uuid'    => $uuid,
            'payment_method' => 'tunai',
            'paid_amount'    => 50000,
            'items'          => [['product_id' => $product->id, 'qty' => 1]],
        ];

        $r1 = $this->actingAs($kasir, 'sanctum')->postJson('/api/transactions', $payload);
        $r1->assertStatus(201);

        $stockAfterFirst = Product::find($product->id)->stock_qty;

        $r2 = $this->actingAs($kasir, 'sanctum')->postJson('/api/transactions', $payload);
        $r2->assertStatus(200);
        $r2->assertJson(['duplicate' => true]);

        $this->assertEquals($stockAfterFirst, Product::find($product->id)->stock_qty);
    }

    // FR-02: RBAC

    #[\PHPUnit\Framework\Attributes\Test]
    public function kasir_cannot_access_hpp_endpoint(): void
    {
        $kasir    = User::factory()->create(['role' => 'kasir']);
        $response = $this->actingAs($kasir, 'sanctum')->getJson('/api/hpp');
        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manajer_cannot_access_checkout(): void
    {
        $manajer  = User::factory()->create(['role' => 'manajer']);
        $response = $this->actingAs($manajer, 'sanctum')->postJson('/api/transactions', []);
        $response->assertStatus(403);
    }

    // FR-06: Opname menyesuaikan stok

    #[\PHPUnit\Framework\Attributes\Test]
    public function opname_lock_adjusts_stock_and_creates_movement(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = $this->makeProduct(['current_cost' => 11400, 'stock_qty' => 48, 'selling_price' => 22000]);

        $sessionResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/opname', ['session_date' => today()->toDateString()]);
        $sessionResponse->assertStatus(201);
        $sessionId = $sessionResponse->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/opname/{$sessionId}/items", [
                'items' => [['product_id' => $product->id, 'physical_qty' => 45, 'note' => 'Hitung ulang']],
            ])->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/opname/{$sessionId}/lock")
            ->assertStatus(200);

        $product->refresh();
        $this->assertEquals(45.0, $product->stock_qty);

        $movement = \App\Models\StockMovement::where('product_id', $product->id)
            ->where('type', 'opname')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-3.0, $movement->qty_change);
    }

    // FR-01: Login

    #[\PHPUnit\Framework\Attributes\Test]
    public function login_returns_token_and_role(): void
    {
        User::factory()->create([
            'username' => 'testuser',
            'password' => \Illuminate\Support\Facades\Hash::make('password123'),
            'role'     => 'kasir',
        ]);

        $this->postJson('/api/auth/login', ['username' => 'testuser', 'password' => 'password123'])
            ->assertStatus(200)
            ->assertJsonPath('data.user.role', 'kasir')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'testuser2',
            'password' => bcrypt('correct'),
            'role'     => 'admin',
        ]);
        $this->postJson('/api/auth/login', ['username' => 'testuser2', 'password' => 'wrong'])
            ->assertStatus(422);
    }
}
