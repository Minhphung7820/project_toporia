<?php

declare(strict_types=1);

namespace Framework\Domain\ValueObjects;

use Framework\Domain\ValueObject;

/**
 * Email Value Object.
 *
 * Represents a validated email address.
 */
final class Email extends ValueObject
{
    /**
     * @param string $value Email address.
     * @throws \InvalidArgumentException If email is invalid.
     */
    public function __construct(
        private readonly string $value
    ) {
        $this->validate();
    }

    /**
     * {@inheritdoc}
     */
    protected function validate(): void
    {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address: {$this->value}");
        }
    }

    /**
     * Get the email value.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the domain part of the email.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }

    /**
     * Get the local part of the email.
     *
     * @return string
     */
    public function getLocalPart(): string
    {
        return substr($this->value, 0, strpos($this->value, '@'));
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return ['email' => $this->value];
    }
}
