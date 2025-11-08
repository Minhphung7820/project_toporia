<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\User\User;
use App\Domain\User\UserRepository;

/**
 * In-Memory User Repository - For testing and development.
 *
 * Stores users in memory (not persistent across requests).
 * Useful for prototyping and testing.
 */
final class InMemoryUserRepository implements UserRepository
{
    /**
     * @var array<int, User> Users storage
     */
    private array $users = [];

    /**
     * @var int Next ID counter
     */
    private int $nextId = 1;

    public function __construct()
    {
        // Seed with a demo user
        // Password: "password" hashed with PASSWORD_DEFAULT
        $demoUser = new User(
            id: 1,
            email: 'admin@example.com',
            password: password_hash('password', PASSWORD_DEFAULT),
            name: 'Admin User',
            createdAt: new \DateTimeImmutable()
        );

        $this->users[1] = $demoUser;
        $this->nextId = 2;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function findByToken(int $id, string $token): ?User
    {
        $user = $this->findById($id);

        if ($user === null || $user->rememberToken !== $token) {
            return null;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function save(User $user): User
    {
        if ($user->id === null) {
            // Create new user
            $newUser = $user->withId($this->nextId);
            $this->users[$this->nextId] = $newUser;
            $this->nextId++;
            return $newUser;
        }

        // Update existing user
        $this->users[$user->id] = $user;
        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(User $user, ?string $token): User
    {
        if ($user->id === null) {
            throw new \LogicException('Cannot update remember token for user without ID');
        }

        $updatedUser = $user->withRememberToken($token);
        $this->users[$user->id] = $updatedUser;

        return $updatedUser;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(User $user): bool
    {
        if ($user->id === null) {
            return false;
        }

        if (!isset($this->users[$user->id])) {
            return false;
        }

        unset($this->users[$user->id]);
        return true;
    }
}
