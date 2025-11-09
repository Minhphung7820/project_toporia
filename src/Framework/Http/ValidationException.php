<?php

declare(strict_types=1);

namespace Toporia\Framework\Http;

use RuntimeException;

/**
 * Validation Exception
 *
 * Thrown when validation fails.
 * Contains validation errors for all fields.
 *
 * @package Toporia\Framework\Http
 */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, array<string>> $errors Validation errors
     * @param int $code HTTP status code (default: 422 Unprocessable Entity)
     */
    public function __construct(
        private array $errors,
        int $code = 422
    ) {
        $message = 'The given data was invalid.';
        parent::__construct($message, $code);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message.
     *
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }

        return null;
    }

    /**
     * Convert to array (for JSON responses).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors
        ];
    }
}
