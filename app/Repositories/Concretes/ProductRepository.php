<?php

namespace App\Repositories\Concretes;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
class ProductRepository implements ProductRepositoryInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->where('status', '!=', ProductStatus::Discontinued->value);
        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%')
                ->orWhere('sku', 'like', '%' . $filters['search'] . '%');
        }
        return $query->paginate($filters['per_page'] ?? 15);
    }
    public function findById(string $id): ?Product
    {
        return Product::find($id);
    }
    public function create(array $data): Product
    {
        return Product::create($data);
    }
    public function update(string $id, array $data): Product
    {
        $product = $this->findById($id);
        $product->update($data);
        return $product;
    }
    public function softDelete(string $id): bool
    {
        $product = Product::findOrFail($id);
        return $product->delete();
    }
}
