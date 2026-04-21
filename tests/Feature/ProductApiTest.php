<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Events\StockThresholdReached;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'sku'                 => 'SKU-TEST-001',
            'name'                => 'Test Product',
            'description'         => 'A test product description.',
            'price'               => 49.99,
            'stock_quantity'      => 100,
            'low_stock_threshold' => 10,
            'status'              => ProductStatus::Active->value,
        ], $overrides);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create($this->productPayload($overrides));
    }
    #[Test]
    public function it_returns_a_paginated_list_of_products(): void
    {
        $this->createProduct(['sku' => 'SKU-001', 'status' => ProductStatus::Active->value]);
        $this->createProduct(['sku' => 'SKU-002', 'status' => ProductStatus::Inactive->value]);
        $this->createProduct(['sku' => 'SKU-003', 'status' => ProductStatus::Discontinued->value]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'sku', 'name', 'price', 'stock_quantity', 'status'],
                ],
                'meta' => [
                    'pagination' => [
                        'total', 'per_page', 'current_page', 'last_page', 'from', 'to',
                    ],
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.total', 2);
    }
    #[Test]
    public function it_returns_a_single_product(): void
    {
        $product = $this->createProduct();

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku)
            ->assertJsonPath('data.name', $product->name);
    }

    #[Test]
    public function it_returns_404_for_non_existent_product(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->getJson("/api/products/{$fakeId}");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Product not found');
    }
    #[Test]
    public function it_creates_a_product_with_valid_data(): void
    {
        $payload = $this->productPayload();

        $response = $this->postJson('/api/products', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'SKU-TEST-001')
            ->assertJsonPath('data.name', 'Test Product')
            ->assertJsonPath('data.stock_quantity', 100);

        $this->assertDatabaseHas('products', [
            'sku'  => 'SKU-TEST-001',
            'name' => 'Test Product',
        ]);
    }

    #[Test]
    public function it_rejects_duplicate_sku_on_create(): void
    {
        $this->createProduct(['sku' => 'DUPE-SKU']);

        $response = $this->postJson('/api/products', $this->productPayload(['sku' => 'DUPE-SKU']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['sku']]);
    }

    #[Test]
    public function it_rejects_create_when_required_fields_are_missing(): void
    {
        $response = $this->postJson('/api/products', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'errors' => ['sku', 'name', 'price', 'stock_quantity', 'low_stock_threshold', 'status'],
            ]);
    }

    #[Test]
    public function it_rejects_negative_price_on_create(): void
    {
        $response = $this->postJson('/api/products', $this->productPayload(['price' => -5]));

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['price']]);
    }

    #[Test]
    public function it_rejects_invalid_status_on_create(): void
    {
        $response = $this->postJson('/api/products', $this->productPayload(['status' => 'unknown_status']));

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    #[Test]
    public function it_rejects_negative_stock_quantity_on_create(): void
    {
        $response = $this->postJson('/api/products', $this->productPayload(['stock_quantity' => -1]));

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['stock_quantity']]);
    }
    #[Test]
    public function it_updates_a_product_with_valid_data(): void
    {
        $product = $this->createProduct();

        $response = $this->putJson("/api/products/{$product->id}", [
            'name'  => 'Updated Name',
            'price' => 99.99,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.price', '99.99');

        $this->assertDatabaseHas('products', [
            'id'   => $product->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function it_returns_404_when_updating_non_existent_product(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $response = $this->putJson("/api/products/{$fakeId}", ['name' => 'Ghost']);

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function it_rejects_duplicate_sku_on_update_for_another_product(): void
    {
        $productA = $this->createProduct(['sku' => 'SKU-A']);
        $productB = $this->createProduct(['sku' => 'SKU-B']);

        $response = $this->putJson("/api/products/{$productB->id}", ['sku' => 'SKU-A']);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['sku']]);
    }
    #[Test]
    public function it_soft_deletes_a_product(): void
    {
        $product = $this->createProduct();

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
    #[Test]
    public function it_increments_stock_quantity(): void
    {
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->postJson("/api/products/{$product->id}/stock", ['quantity' => 20]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stock_quantity', 70);
    }

    #[Test]
    public function it_decrements_stock_quantity(): void
    {
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->postJson("/api/products/{$product->id}/stock", ['quantity' => -10]);

        $response->assertOk()
            ->assertJsonPath('data.stock_quantity', 40);
    }

    #[Test]
    public function it_rejects_stock_adjustment_that_would_result_in_negative_stock(): void
    {
        $product = $this->createProduct(['stock_quantity' => 5]);

        $response = $this->postJson("/api/products/{$product->id}/stock", ['quantity' => -10]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('products', [
            'id'             => $product->id,
            'stock_quantity' => 5,
        ]);
    }

    #[Test]
    public function it_rejects_zero_stock_adjustment(): void
    {
        $product = $this->createProduct(['stock_quantity' => 50]);

        $response = $this->postJson("/api/products/{$product->id}/stock", ['quantity' => 0]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_stock_adjustment_when_quantity_is_missing(): void
    {
        $product = $this->createProduct();

        $response = $this->postJson("/api/products/{$product->id}/stock", []);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_fires_stock_threshold_reached_event_when_stock_falls_below_threshold(): void
    {
        Event::fake([StockThresholdReached::class]);

        $product = $this->createProduct([
            'stock_quantity'      => 15,
            'low_stock_threshold' => 10,
        ]);

        $this->postJson("/api/products/{$product->id}/stock", ['quantity' => -8]);

        Event::assertDispatched(StockThresholdReached::class, function ($event) use ($product) {
            return $event->product->id === $product->id;
        });
    }
    #[Test]
    public function it_returns_only_low_stock_active_products(): void
    {
        $this->createProduct([
            'sku'                 => 'LOW-1',
            'stock_quantity'      => 3,
            'low_stock_threshold' => 10,
            'status'              => ProductStatus::Active->value,
        ]);

        $this->createProduct([
            'sku'                 => 'LOW-2',
            'stock_quantity'      => 3,
            'low_stock_threshold' => 10,
            'status'              => ProductStatus::Inactive->value,
        ]);

        $this->createProduct([
            'sku'                 => 'OK-1',
            'stock_quantity'      => 50,
            'low_stock_threshold' => 10,
            'status'              => ProductStatus::Active->value,
        ]);

        $response = $this->getJson('/api/products/low-stock');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'LOW-1');
    }
}
