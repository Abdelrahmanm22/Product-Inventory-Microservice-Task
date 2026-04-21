<?php

namespace App\Repositories\Interfaces;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function getAll(array $filters = []):LengthAwarePaginator;
    public function findById(string $id):?Product;
    public function create(array $data):Product;
    public function update(string $id, array $data):Product;
    public function softDelete(string $id):bool;

}
