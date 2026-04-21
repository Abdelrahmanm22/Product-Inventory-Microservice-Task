<?php

namespace App\Listeners;

use App\Events\StockThresholdReached;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
class SendLowStockAlert
{
    //Uses Redis by default when configured.
    public string $connection = 'redis';
    public string $queue      = 'alerts';
    public int    $tries      = 3;

    /**
     * Handle the event.
     */
    public function handle(StockThresholdReached $event): void
    {
        $product = $event->product;
        Log::warning('Low stock alert triggered', [
            'product_id'          => $product->id,
            'sku'                 => $product->sku,
            'name'                => $product->name,
            'stock_quantity'      => $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
        ]);
        //for example, you could send an email or a notification to the admin here.
    }
    public function failed(StockThresholdReached $event, \Throwable $exception): void
    {
        Log::error('Failed to send low stock alert', [
            'product_id' => $event->product->id,
            'error'      => $exception->getMessage(),
        ]);
    }
}
