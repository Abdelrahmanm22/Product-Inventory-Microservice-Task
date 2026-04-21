<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::post('products/{id}/stock', [ProductController::class, 'adjustStock']);
Route::get('products/low-stock', [ProductController::class, 'lowStock']);
Route::apiResource('products', ProductController::class);

