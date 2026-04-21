<?php

namespace App\Actions;

use App\Events\StockThresholdReached;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
class AdjustStockAction
{
    public function __construct(protected ProductRepositoryInterface $repository)
    {
    }
    //Adjust(increment or decrement) stock quantity for a product and Fires StockThresholdReached event when stock falls below threshold
    public function execute(string $productId, int $quantity): Product
    {
        return DB::transaction(function () use ($productId, $quantity){
            $product = Product::lockForUpdate()->findOrFail($productId);
            $newQuantity = $product->stock_quantity + $quantity;
            if ($newQuantity < 0) {
                throw new \RuntimeException(
                    "Insufficient stock. Current: {$product->stock_quantity}, Requested decrement: " . abs($quantity)
                );
            }
            $product->update(['stock_quantity' => $newQuantity]);
            $product->refresh();
            //then fire event if stock falls below threshold
            if ($product->isLowStock()) {
                event(new StockThresholdReached($product));
            }
            return $product;
        });
    }
}
