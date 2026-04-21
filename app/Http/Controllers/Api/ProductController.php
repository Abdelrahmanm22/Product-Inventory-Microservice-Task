<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProductRequest;
use App\Http\Requests\Api\UpdateProductRequest;
use App\Http\Resources\Api\ProductResource;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    use ApiResponseTrait;
    public function __construct(protected ProductRepositoryInterface $repository)
    {
    }
    public function index(Request $request) : JsonResponse
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $cacheKey = 'products:list' . ':' . md5("search={$search}&per_page={$perPage}&page={$request->input('page', 1)}");
            $products = Cache::tags(['products'])->remember(
                $cacheKey,
                300,
                fn () => $this->repository->getAll(['search' => $search, 'per_page' => $perPage])
            );
            return $this->paginatedResponse($products,ProductResource::collection($products),"Products retrieved successfully");
        }catch (\Exception $exception){
            Log::error('Failed to list products', ['error' => $exception->getMessage()]);
            return $this->errorResponse("Failed to retrieve products",500);
        }
    }
    public function show(string $id) : JsonResponse
    {
        try {
            $product = $this->repository->findById($id);
            if (!$product) {
                return $this->notFoundResponse('Product');
            }
            return $this->successResponse(new ProductResource($product), "Product retrieved successfully");
        }catch (\Exception $exception){
            Log::error('Failed to show product', ['id' => $id, 'error' => $exception->getMessage()]);
            return $this->errorResponse("Failed to show product",500);
        }
    }
    public function store(StoreProductRequest $request) : JsonResponse
    {
        try{
            $product = $this->repository->create($request->validated());
            $this->invalidateCache();
            return $this->createdResponse(new ProductResource($product), "Product created successfully");
        }catch (\Exception $exception){
            Log::error('Failed to create product', ['error' => $exception->getMessage()]);
            return $this->errorResponse("Failed to create product",500);
        }
    }
    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        try {
            $product = $this->repository->findById($id);
            if (!$product) {
                return $this->notFoundResponse('Product');
            }
            $updated = $this->repository->update($id, $request->validated());
            $this->invalidateCache();
            return $this->successResponse(new ProductResource($updated), 'Product updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to update product', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to update product.', 500);
        }
    }
    public function destroy(string $id): JsonResponse
    {
        try {
            $product = $this->repository->findById($id);
            if (!$product) {
                return $this->notFoundResponse('Product');
            }
            $this->repository->softDelete($id);
            $this->invalidateCache();
            return $this->successResponse(null, 'Product deleted successfully.');
        } catch (\Throwable $e) {
            Log::error('Failed to delete product', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Failed to delete product.', 500);
        }
    }
    private function invalidateCache(): void
    {
        try {
            Cache::tags(['products'])->flush();
        } catch (\Throwable $e) {
            Log::warning('Cache tag flush failed, falling back to full flush.', ['error' => $e->getMessage()]);
            Cache::flush();
        }
    }
}
