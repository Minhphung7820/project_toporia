<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Guards;

use Toporia\Framework\Auth\Authenticatable;
use Toporia\Framework\Auth\GuardInterface;
use Toporia\Framework\Auth\UserProviderInterface;

/**
 * Session Guard - Session-based authentication.
 *
 * Stores user ID in PHP session for stateful authentication.
 * Following Single Responsibility Principle - only handles session auth.
 */
final class SessionGuard implements GuardInterface
{
    private ?Authenticatable $user = null;
    private bool $userResolved = false;

    /**
     * @param UserProviderInterface $provider User provider for retrieving users.
     * @param string $name Guard name (for session key).
     */
    public function __construct(
        private UserProviderInterface $provider,
        private string $name = 'default'
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * {@inheritdoc}
     */
    public function user(): ?Authenticatable
    {
        if ($this->userResolved) {
            return $this->user;
        }

        $this->userResolved = true;

        // Try to get user from session
        $id = $this->getSessionId();
        if ($id !== null) {
            $this->user = $this->provider->retrieveById($id);
        }

        // Try remember token if no session
        if ($this->user === null) {
            $this->user = $this->getUserByRememberToken();
        }

        return $this->user;
    }

    /**
     * {@inheritdoc}
     */
    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * {@inheritdoc}
     */
    public function attempt(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->login($user);

        // Handle "remember me"
        if ($credentials['remember'] ?? false) {
            $this->setRememberToken($user);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function login(Authenticatable $user): void
    {
        $this->updateSession($user->getAuthIdentifier());
        $this->user = $user;
        $this->userResolved = true;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void
    {
        $this->clearUserData();
        $this->user = null;
        $this->userResolved = false;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        return $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Get user ID from session.
     *
     * @return int|string|null
     */
    private function getSessionId(): int|string|null
    {
        return $_SESSION[$this->getName()] ?? null;
    }

    /**
     * Update session with user ID.
     *
     * @param int|string $id User ID.
     * @return void
     */
    private function updateSession(int|string $id): void
    {
        $_SESSION[$this->getName()] = $id;
    }

    /**
     * Get session key name.
     *
     * @return string
     */
    private function getName(): string
    {
        return 'auth_' . $this->name;
    }

    /**
     * Get remember token cookie name.
     *
     * @return string
     */
    private function getRememberTokenName(): string
    {
        return 'remember_' . $this->name;
    }

    /**
     * Set remember token cookie.
     *
     * @param Authenticatable $user
     * @return void
     */
    private function setRememberToken(Authenticatable $user): void
    {
        $token = bin2hex(random_bytes(32));
        $this->provider->updateRememberToken($user, $token);

        // Set cookie for 30 days
        setcookie(
            $this->getRememberTokenName(),
            $user->getAuthIdentifier() . '|' . $token,
            time() + (86400 * 30),
            '/',
            '',
            false,
            true // httponly
        );
    }

    /**
     * Get user by remember token from cookie.
     *
     * @return Authenticatable|null
     */
    private function getUserByRememberToken(): ?Authenticatable
    {
        $cookie = $_COOKIE[$this->getRememberTokenName()] ?? null;

        if ($cookie === null) {
            return null;
        }

        [$id, $token] = explode('|', $cookie, 2);

        if (empty($id) || empty($token)) {
            return null;
        }

        $user = $this->provider->retrieveByToken($id, $token);

        if ($user !== null) {
            // Re-login via session
            $this->login($user);
        }

        return $user;
    }

    /**
     * Clear user data from session and cookies.
     *
     * @return void
     */
    private function clearUserData(): void
    {
        unset($_SESSION[$this->getName()]);

        // Clear remember cookie
        if (isset($_COOKIE[$this->getRememberTokenName()])) {
            setcookie(
                $this->getRememberTokenName(),
                '',
                time() - 3600,
                '/'
            );
        }
    }
}
