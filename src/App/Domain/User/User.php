<?php

declare(strict_types=1);

namespace App\Domain\User;

use Toporia\Framework\Auth\Authenticatable;

/**
 * User Entity - Domain model for users.
 *
 * Implements Authenticatable for authentication system integration.
 * Immutable entity following Clean Architecture principles.
 */
final class User implements Authenticatable
{
    /**
     * @param int|null $id User ID
     * @param string $email Email address
     * @param string $password Hashed password
     * @param string $name Full name
     * @param string|null $rememberToken Remember me token
     * @param \DateTimeImmutable|null $createdAt Creation timestamp
     * @param \DateTimeImmutable|null $updatedAt Last update timestamp
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $email,
        public readonly string $password,
        public readonly string $name,
        public readonly ?string $rememberToken = null,
        public readonly ?\DateTimeImmutable $createdAt = null,
        public readonly ?\DateTimeImmutable $updatedAt = null
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->id ?? throw new \LogicException('User ID not set');
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    /**
     * {@inheritdoc}
     */
    public function setRememberToken(string $token): void
    {
        // Since User is immutable, this would need to be handled by the UserProvider
        // when persisting. For in-memory use, we'd create a new instance.
        throw new \BadMethodCallException(
            'User entity is immutable. Update remember token via UserRepository.'
        );
    }

    /**
     * Create a new User with updated remember token.
     *
     * @param string|null $token Remember token.
     * @return self New User instance.
     */
    public function withRememberToken(?string $token): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->password,
            $this->name,
            $token,
            $this->createdAt,
            $this->updatedAt
        );
    }

    /**
     * Create a new User with ID (after persistence).
     *
     * @param int $id User ID.
     * @return self New User instance.
     */
    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->email,
            $this->password,
            $this->name,
            $this->rememberToken,
            $this->createdAt ?? new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Verify a password against this user's password.
     *
     * @param string $password Plain text password.
     * @return bool True if password matches.
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }
}
