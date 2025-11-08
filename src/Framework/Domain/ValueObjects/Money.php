<?php

declare(strict_types=1);

namespace Toporia\Framework\Domain\ValueObjects;

use Toporia\Framework\Domain\ValueObject;

/**
 * Money Value Object.
 *
 * Represents a monetary amount with currency.
 */
final class Money extends ValueObject
{
    /**
     * @param float $amount Amount.
     * @param string $currency Currency code (ISO 4217).
     * @throws \InvalidArgumentException If amount is negative or currency is invalid.
     */
    public function __construct(
        private readonly float $amount,
        private readonly string $currency = 'USD'
    ) {
        $this->validate();
    }

    /**
     * {@inheritdoc}
     */
    protected function validate(): void
    {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if (strlen($this->currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be 3-letter ISO code');
        }
    }

    /**
     * Get the amount.
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Get the currency.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Add another money object.
     *
     * @param Money $other
     * @return Money
     * @throws \InvalidArgumentException If currencies don't match.
     */
    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot add different currencies');
        }

        return new Money($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract another money object.
     *
     * @param Money $other
     * @return Money
     * @throws \InvalidArgumentException If currencies don't match.
     */
    public function subtract(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot subtract different currencies');
        }

        return new Money($this->amount - $other->amount, $this->currency);
    }

    /**
     * Multiply by a factor.
     *
     * @param float $multiplier
     * @return Money
     */
    public function multiply(float $multiplier): Money
    {
        return new Money($this->amount * $multiplier, $this->currency);
    }

    /**
     * Check if greater than another money object.
     *
     * @param Money $other
     * @return bool
     */
    public function greaterThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount > $other->amount;
    }

    /**
     * Check if less than another money object.
     *
     * @param Money $other
     * @return bool
     */
    public function lessThan(Money $other): bool
    {
        $this->assertSameCurrency($other);
        return $this->amount < $other->amount;
    }

    /**
     * Assert same currency.
     *
     * @param Money $other
     * @return void
     * @throws \InvalidArgumentException
     */
    private function assertSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot compare different currencies');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency
        ];
    }
}
