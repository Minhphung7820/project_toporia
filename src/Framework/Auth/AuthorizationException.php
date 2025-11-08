<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth;

use RuntimeException;

/**
 * Authorization Exception
 *
 * Thrown when a user is not authorized to perform an action.
 */
final class AuthorizationException extends RuntimeException
{
    public function __construct(
        string $message = 'This action is unauthorized.',
        int $code = 403
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Create exception for a specific ability
     *
     * @param string $ability
     * @return self
     */
    public static function forAbility(string $ability): self
    {
        return new self("You are not authorized to perform the '{$ability}' action.");
    }
}
