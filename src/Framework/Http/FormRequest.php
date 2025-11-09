<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use Toporia\Framework\Validation\Validator;
use Toporia\Framework\Validation\ValidatorInterface;

/**
 * Form Request
 *
 * Base class for form validation requests (Laravel-style).
 *
 * Features:
 * - Auto-validation before controller method
 * - Custom validation rules per request
 * - Custom error messages
 * - Authorization support
 * - Validated data access
 *
 * Performance: O(N*R) where N = fields, R = rules
 *
 * Clean Architecture:
 * - Single Responsibility: Only validates request data
 * - Open/Closed: Extend for custom validation
 * - Liskov Substitution: All FormRequests are interchangeable
 *
 * Usage:
 * ```php
 * final class CreateProductRequest extends FormRequest
 * {
 *     public function rules(): array
 *     {
 *         return [
 *             'title' => 'required|string|max:255',
 *             'price' => 'required|numeric|min:0',
 *             'email' => 'required|email',
 *         ];
 *     }
 * }
 *
 * // In controller:
 * public function store(CreateProductRequest $request)
 * {
 *     $validated = $request->validated(); // Only validated data
 *     // Validation already passed!
 * }
 * ```
 *
 * @package Toporia\Framework\Http
 */
abstract class FormRequest
{
    /**
     * @var Request The HTTP request
     */
    protected Request $request;

    /**
     * @var ValidatorInterface The validator instance
     */
    protected ValidatorInterface $validator;

    /**
     * @var array Validated data
     */
    protected array $validatedData = [];

    /**
     * Create a new form request instance.
     *
     * @param Request $request HTTP request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->validator = new Validator();
    }

    /**
     * Get validation rules.
     *
     * Override this method to define validation rules.
     *
     * @return array<string, string|array>
     */
    abstract public function rules(): array;

    /**
     * Get custom error messages.
     *
     * Override to customize error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * Override to add authorization logic.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the request.
     *
     * @return void
     * @throws ValidationException if validation fails
     */
    public function validate(): void
    {
        // Check authorization first
        if (!$this->authorize()) {
            throw new \RuntimeException('This action is unauthorized.', 403);
        }

        // Get all input data
        $data = $this->request->all();

        // Validate
        $passes = $this->validator->validate($data, $this->rules(), $this->messages());

        if (!$passes) {
            throw new ValidationException($this->validator->errors());
        }

        // Store validated data
        $this->validatedData = $this->validator->validated();
    }

    /**
     * Get validated data.
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Get a specific validated field.
     *
     * @param string $key Field name
     * @param mixed $default Default value
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->validatedData[$key] ?? $default;
    }

    /**
     * Get only specific validated fields.
     *
     * @param array $keys Field names
     * @return array
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->validatedData, array_flip($keys));
    }

    /**
     * Get all validated fields except specific ones.
     *
     * @param array $keys Fields to exclude
     * @return array
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->validatedData, array_flip($keys));
    }

    /**
     * Check if a field exists in validated data.
     *
     * @param string $key Field name
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->validatedData);
    }

    /**
     * Get the underlying request instance.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }
}
