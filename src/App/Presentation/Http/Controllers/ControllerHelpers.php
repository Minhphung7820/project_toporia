<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

/**
 * Controller Helper Trait
 *
 * Provides convenient helper methods for controllers.
 * Use this trait in controllers that need view rendering or other utilities.
 *
 * Clean Architecture Benefits:
 * - No forced inheritance (composition over inheritance)
 * - Lightweight - only adds methods when needed
 * - Compatible with both MVC and ADR patterns
 *
 * Usage:
 * ```php
 * final class MyController
 * {
 *     use ControllerHelpers;
 *
 *     public function index(Request $request, Response $response)
 *     {
 *         return $this->view('products/index', ['products' => $products]);
 *     }
 * }
 * ```
 */
trait ControllerHelpers
{
    /**
     * Render a view template.
     *
     * Delegates to global view() helper for consistency.
     * DRY Principle: Don't Repeat Yourself - reuse existing helper.
     *
     * @param string $path View path relative to Views directory (without .php extension)
     * @param array $data Data to extract into view scope
     * @return string Rendered HTML content
     */
    protected function view(string $path, array $data = []): string
    {
        return view($path, $data);
    }

    /**
     * Get the current request instance.
     *
     * @return \Toporia\Framework\Http\Request
     */
    protected function request(): \Toporia\Framework\Http\Request
    {
        return request();
    }

    /**
     * Get the current response instance.
     *
     * @return \Toporia\Framework\Http\Response
     */
    protected function response(): \Toporia\Framework\Http\Response
    {
        return response();
    }

    /**
     * Return JSON response.
     *
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @return void
     */
    protected function json(mixed $data, int $status = 200): void
    {
        response()->json($data, $status);
    }

    /**
     * Return HTML response.
     *
     * @param string $content HTML content
     * @param int $status HTTP status code
     * @return void
     */
    protected function html(string $content, int $status = 200): void
    {
        response()->html($content, $status);
    }

    /**
     * Redirect to a path.
     *
     * @param string $path Redirect path
     * @param int $status HTTP status code (default 302)
     * @return void
     */
    protected function redirect(string $path, int $status = 302): void
    {
        response()->redirect($path, $status);
    }

    /**
     * Validate request input.
     *
     * Simple validation helper - throws exception if validation fails.
     * For complex validation, use dedicated Validator class.
     *
     * @param array $rules Validation rules ['field' => 'required|email']
     * @return array Validated data
     * @throws \RuntimeException if validation fails
     */
    protected function validate(array $rules): array
    {
        $request = request();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $request->input($field);
            $ruleList = explode('|', $rule);

            foreach ($ruleList as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$field][] = "The {$field} field is required.";
                }
                if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email.";
                }
            }
        }

        if (!empty($errors)) {
            throw new \RuntimeException('Validation failed: ' . json_encode($errors));
        }

        return $request->only(array_keys($rules));
    }
}
