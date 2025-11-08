<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Auth\GateInterface;
use Toporia\Framework\Auth\Authenticatable;

/**
 * Gate Service Accessor
 *
 * Provides static-like access to the authorization gate.
 *
 * @method static void define(string $ability, callable $callback) Define ability
 * @method static bool allows(string $ability, mixed ...$arguments) Check if ability is allowed
 * @method static bool denies(string $ability, mixed ...$arguments) Check if ability is denied
 * @method static void authorize(string $ability, mixed ...$arguments) Authorize or throw exception
 * @method static bool any(array $abilities, mixed ...$arguments) Check if any ability is allowed
 * @method static bool all(array $abilities, mixed ...$arguments) Check if all abilities are allowed
 * @method static GateInterface forUser(Authenticatable $user) Get gate for specific user
 *
 * @see GateInterface
 *
 * @example
 * // Define ability
 * Gate::define('edit-post', function($user, $post) {
 *     return $user->id === $post->user_id;
 * });
 *
 * // Check ability
 * if (Gate::allows('edit-post', $post)) {
 *     // User can edit
 * }
 *
 * // Authorize (throws exception if denied)
 * Gate::authorize('edit-post', $post);
 *
 * // Check for specific user
 * if (Gate::forUser($admin)->allows('delete-user', $user)) {
 *     // Admin can delete user
 * }
 */
final class Gate extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'gate';
    }
}
