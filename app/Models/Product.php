<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes,HasUuids;
    protected $fillable = [
        'sku', 'name', 'description', 'price',
        'stock_quantity', 'low_stock_threshold', 'status'
    ];
    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'status' => ProductStatus::class,
    ];
    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<', 'low_stock_threshold')
            ->where('status', ProductStatus::Active->value);
    }
}
