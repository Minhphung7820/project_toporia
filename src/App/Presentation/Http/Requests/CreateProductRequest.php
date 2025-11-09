<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Toporia\Framework\Http\FormRequest;

/**
 * Create Product Request
 *
 * Validates data for creating a new product.
 *
 * Example usage in controller:
 * ```php
 * public function store(CreateProductRequest $request)
 * {
 *     $validated = $request->validated();
 *     $product = Product::create($validated);
 *     return response()->json($product, 201);
 * }
 * ```
 */
final class CreateProductRequest extends FormRequest
{
    /**
     * Get validation rules.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'sku' => 'string|max:100',
            'description' => 'string',
            'price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Product title is required.',
            'title.max' => 'Product title is too long (max 255 characters).',
            'price.required' => 'Product price is required.',
            'price.numeric' => 'Product price must be a number.',
            'price.min' => 'Product price cannot be negative.',
        ];
    }

    /**
     * Determine if user is authorized to create products.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Example: Check if user is authenticated
        // return auth()->check();

        return true; // Allow for now
    }
}
