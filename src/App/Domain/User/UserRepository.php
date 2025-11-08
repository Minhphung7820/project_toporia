<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * User Repository Interface - Domain persistence contract.
 *
 * Following Repository pattern and Dependency Inversion Principle.
 * Domain defines the contract, Infrastructure provides implementation.
 */
interface UserRepository
{
    /**
     * Find a user by ID.
     *
     * @param int $id User ID.
     * @return User|null User or null if not found.
     */
    public function findById(int $id): ?User;

    /**
     * Find a user by email.
     *
     * @param string $email Email address.
     * @return User|null User or null if not found.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find a user by remember token.
     *
     * @param int $id User ID.
     * @param string $token Remember token.
     * @return User|null User or null if not found.
     */
    public function findByToken(int $id, string $token): ?User;

    /**
     * Save a user (create or update).
     *
     * @param User $user User to save.
     * @return User Saved user with ID.
     */
    public function save(User $user): User;

    /**
     * Update remember token for a user.
     *
     * @param User $user User to update.
     * @param string|null $token New remember token.
     * @return User Updated user.
     */
    public function updateRememberToken(User $user, ?string $token): User;

    /**
     * Delete a user.
     *
     * @param User $user User to delete.
     * @return bool True if deleted.
     */
    public function delete(User $user): bool;
}
