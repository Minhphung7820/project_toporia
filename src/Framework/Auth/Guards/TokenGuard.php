<?php

declare(strict_types=1);

namespace Toporia\Framework\Auth\Guards;

use Toporia\Framework\Auth\Authenticatable;
use Toporia\Framework\Auth\GuardInterface;
use Toporia\Framework\Auth\UserProviderInterface;
use Toporia\Framework\Http\Request;

/**
 * Token Guard - Token-based authentication (JWT/Bearer).
 *
 * Authenticates users via Bearer tokens from Authorization header.
 * Stateless authentication suitable for APIs.
 *
 * Following Single Responsibility Principle - only handles token auth.
 */
final class TokenGuard implements GuardInterface
{
    private ?Authenticatable $user = null;
    private bool $userResolved = false;

    /**
     * @param UserProviderInterface $provider User provider for retrieving users.
     * @param Request $request HTTP request for extracting token.
     * @param string $name Guard name.
     */
    public function __construct(
        private UserProviderInterface $provider,
        private Request $request,
        private string $name = 'api'
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

        // Extract token from request
        $token = $this->getTokenFromRequest();

        if ($token === null) {
            return null;
        }

        // Decode and validate token
        $userId = $this->validateToken($token);

        if ($userId === null) {
            return null;
        }

        // Retrieve user by ID
        $this->user = $this->provider->retrieveById($userId);

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

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function login(Authenticatable $user): void
    {
        $this->user = $user;
        $this->userResolved = true;
    }

    /**
     * {@inheritdoc}
     */
    public function logout(): void
    {
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
     * Generate a token for authenticated user.
     *
     * @param Authenticatable $user User to generate token for.
     * @param int $expiresIn Token expiration in seconds (default: 1 hour).
     * @return string JWT token.
     */
    public function generateToken(Authenticatable $user, int $expiresIn = 3600): string
    {
        $payload = [
            'sub' => $user->getAuthIdentifier(),
            'iat' => time(),
            'exp' => time() + $expiresIn,
            'guard' => $this->name,
        ];

        return $this->encodeJWT($payload);
    }

    /**
     * Get token from request Authorization header.
     *
     * @return string|null Token or null if not found.
     */
    private function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('authorization');

        if ($header === null) {
            return null;
        }

        // Extract Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate JWT token and return user ID.
     *
     * @param string $token JWT token.
     * @return int|string|null User ID or null if invalid.
     */
    private function validateToken(string $token): int|string|null
    {
        try {
            $payload = $this->decodeJWT($token);

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null; // Token expired
            }

            // Check guard
            if (isset($payload['guard']) && $payload['guard'] !== $this->name) {
                return null; // Wrong guard
            }

            // Return user ID
            return $payload['sub'] ?? null;
        } catch (\Throwable $e) {
            return null; // Invalid token
        }
    }

    /**
     * Encode payload to JWT.
     *
     * Simple JWT implementation without external dependencies.
     * For production, consider using firebase/php-jwt library.
     *
     * @param array<string, mixed> $payload Payload data.
     * @return string JWT token.
     */
    private function encodeJWT(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            $this->getSecret(),
            true
        );

        $signatureEncoded = $this->base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Decode JWT to payload.
     *
     * @param string $token JWT token.
     * @return array<string, mixed> Payload data.
     * @throws \RuntimeException If token is invalid.
     */
    private function decodeJWT(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid JWT format');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            $this->getSecret(),
            true
        );

        $expectedSignatureEncoded = $this->base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedSignatureEncoded, $signatureEncoded)) {
            throw new \RuntimeException('Invalid JWT signature');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid JWT payload');
        }

        return $payload;
    }

    /**
     * Base64 URL encode.
     *
     * @param string $data Data to encode.
     * @return string Encoded string.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode.
     *
     * @param string $data Data to decode.
     * @return string Decoded string.
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get JWT secret key.
     *
     * @return string Secret key.
     */
    private function getSecret(): string
    {
        // TODO: Move to config
        return $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
    }
}
