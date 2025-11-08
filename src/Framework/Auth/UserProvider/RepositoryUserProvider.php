<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\UserProvider;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Toporia\Framework\Auth\Authenticatable;
use Toporia\Framework\Auth\UserProviderInterface;

/**
 * Repository User Provider - Retrieves users via UserRepository.
 *
 * Bridges the authentication system with the domain layer.
 * Following Adapter pattern and Dependency Inversion Principle.
 */
final class RepositoryUserProvider implements UserProviderInterface
{
    /**
     * @param UserRepository $repository User repository.
     */
    public function __construct(
        private UserRepository $repository
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById(int|string $identifier): ?Authenticatable
    {
        return $this->repository->findById((int)$identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Typically find by email or username
        $email = $credentials['email'] ?? $credentials['username'] ?? null;

        if ($email === null) {
            return null;
        }

        return $this->repository->findByEmail($email);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $password = $credentials['password'] ?? '';

        if (empty($password)) {
            return false;
        }

        return $user->verifyPassword($password);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken(int|string $identifier, string $token): ?Authenticatable
    {
        return $this->repository->findByToken((int)$identifier, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, string $token): void
    {
        if (!$user instanceof User) {
            return;
        }

        $this->repository->updateRememberToken($user, $token);
    }
}
