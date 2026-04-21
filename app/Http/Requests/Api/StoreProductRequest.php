<?php

namespace App\Http\Requests\Api;

use App\Enums\ProductStatus;
use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
class StoreProductRequest extends FormRequest
{
    use ApiResponseTrait;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price'=> ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'in:' . implode(',', ProductStatus::values())],
        ];
    }
    public function messages(): array
    {
        return [
            'sku.required' => 'A SKU (Stock Keeping Unit) is required.',
            'sku.unique' => 'This SKU is already in use. Each product must have a unique SKU.',
            'sku.max' => 'SKU must not exceed 100 characters.',
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name must not exceed 255 characters.',
            'description.max' => 'Description must not exceed 5000 characters.',
            'price.required'=> 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min'  => 'Price cannot be negative.',
            'price.max' => 'Price exceeds the maximum allowed value.',
            'stock_quantity.required' => 'Stock quantity is required.',
            'stock_quantity.integer' => 'Stock quantity must be a whole number.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'low_stock_threshold.required' => 'Low stock threshold is required.',
            'low_stock_threshold.integer' => 'Low stock threshold must be a whole number.',
            'low_stock_threshold.min' => 'Low stock threshold cannot be negative.',
            'status.required' => 'Product status is required.',
            'status.in' => 'Invalid status value. Allowed values are: ' . implode(', ', ProductStatus::values()) . '.',
        ];
    }
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->errorResponse(
                message: 'Validation failed. Please check the provided data.',
                status: 422,
                errors: $validator->errors()
            )
        );
    }
}
