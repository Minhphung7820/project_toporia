<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Toporia\Framework\Auth\AuthManagerInterface;
use Toporia\Framework\Auth\Guards\TokenGuard;
use Toporia\Framework\Http\Request;
use Toporia\Framework\Http\Response;

/**
 * Authentication Controller
 *
 * Handles user authentication for both web and API requests.
 * Supports session-based (web) and token-based (API) authentication.
 *
 * Following Clean Architecture and SOLID principles.
 */
final class AuthController extends BaseController
{
    /**
     * @param Request $request
     * @param Response $response
     * @param AuthManagerInterface $auth Auth manager for multi-guard support
     * @param UserRepository $userRepository User repository for registration
     */
    public function __construct(
        Request $request,
        Response $response,
        private AuthManagerInterface $auth,
        private UserRepository $userRepository
    ) {
        parent::__construct($request, $response);
    }

    /**
     * Show login form (web only).
     *
     * @return void
     */
    public function showLoginForm(): void
    {
        $this->view('auth/login', [
            'title' => 'Login',
        ]);
    }

    /**
     * Handle login request.
     *
     * Supports both web (session) and API (token) authentication.
     *
     * @return void
     */
    public function login(): void
    {
        // Validate input
        $credentials = $this->request->only(['email', 'password']);
        $remember = (bool) ($this->request->input('remember') ?? false);

        if (empty($credentials['email']) || empty($credentials['password'])) {
            $this->handleLoginError('Email and password are required');
            return;
        }

        // Determine guard based on request type
        $guard = $this->request->expectsJson() ? 'api' : 'web';

        // Add remember to credentials for session guard
        $credentials['remember'] = $remember;

        // Attempt authentication
        if (!$this->auth->guard($guard)->attempt($credentials)) {
            $this->handleLoginError('Invalid credentials');
            return;
        }

        // Success response based on guard type
        if ($guard === 'api') {
            $this->handleApiLoginSuccess();
        } else {
            $this->handleWebLoginSuccess();
        }
    }

    /**
     * Show registration form (web only).
     *
     * @return void
     */
    public function showRegisterForm(): void
    {
        $this->view('auth/register', [
            'title' => 'Register',
        ]);
    }

    /**
     * Handle user registration.
     *
     * Supports both web and API registration.
     *
     * @return void
     */
    public function register(): void
    {
        // Validate input
        $data = $this->request->only(['name', 'email', 'password', 'password_confirmation']);

        $errors = $this->validateRegistration($data);

        if (!empty($errors)) {
            $this->handleRegistrationError($errors);
            return;
        }

        // Create user
        $user = new User(
            id: null,
            email: $data['email'],
            password: password_hash($data['password'], PASSWORD_DEFAULT),
            name: $data['name'],
            createdAt: new \DateTimeImmutable()
        );

        try {
            $savedUser = $this->userRepository->save($user);

            // Auto-login after registration
            $guard = $this->request->expectsJson() ? 'api' : 'web';
            $this->auth->guard($guard)->login($savedUser);

            // Success response
            if ($guard === 'api') {
                $this->handleApiLoginSuccess();
            } else {
                $this->response->redirect('/dashboard');
            }
        } catch (\Throwable $e) {
            $this->handleRegistrationError(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle logout request.
     *
     * Supports both web and API logout.
     *
     * @return void
     */
    public function logout(): void
    {
        $guard = $this->request->expectsJson() ? 'api' : 'web';

        $this->auth->guard($guard)->logout();

        if ($guard === 'api') {
            $this->response->json(['message' => 'Logged out successfully']);
        } else {
            $this->response->redirect('/login');
        }
    }

    /**
     * Get authenticated user profile.
     *
     * API endpoint to retrieve current user data.
     *
     * @return void
     */
    public function me(): void
    {
        $user = $this->auth->guard('api')->user();

        if ($user === null) {
            $this->response->json(['error' => 'Unauthenticated'], 401);
            return;
        }

        if (!$user instanceof User) {
            $this->response->json(['error' => 'Invalid user type'], 500);
            return;
        }

        $this->response->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->createdAt?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Validate registration data.
     *
     * @param array<string, mixed> $data Registration data
     * @return array<string, string> Validation errors
     */
    private function validateRegistration(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } elseif ($this->userRepository->findByEmail($data['email']) !== null) {
            $errors['email'] = 'Email already exists';
        }

        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }

        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = 'Password confirmation is required';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        return $errors;
    }

    /**
     * Handle login error response.
     *
     * @param string $message Error message
     * @return void
     */
    private function handleLoginError(string $message): void
    {
        if ($this->request->expectsJson()) {
            $this->response->json(['error' => $message], 401);
        } else {
            // For web, redirect back with error
            // TODO: Implement flash session messages
            $this->response->redirect('/login');
        }
    }

    /**
     * Handle API login success response.
     *
     * Returns JWT token for API authentication.
     *
     * @return void
     */
    private function handleApiLoginSuccess(): void
    {
        $guard = $this->auth->guard('api');

        if (!$guard instanceof TokenGuard) {
            $this->response->json(['error' => 'Invalid guard type'], 500);
            return;
        }

        $user = $guard->user();

        if ($user === null) {
            $this->response->json(['error' => 'Authentication failed'], 500);
            return;
        }

        // Generate JWT token
        $token = $guard->generateToken($user);

        $this->response->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'email' => $user instanceof User ? $user->email : null,
                'name' => $user instanceof User ? $user->name : null,
            ],
        ]);
    }

    /**
     * Handle web login success response.
     *
     * Redirects to dashboard.
     *
     * @return void
     */
    private function handleWebLoginSuccess(): void
    {
        $this->response->redirect('/dashboard');
    }

    /**
     * Handle registration error response.
     *
     * @param array<string, string> $errors Validation errors
     * @return void
     */
    private function handleRegistrationError(array $errors): void
    {
        if ($this->request->expectsJson()) {
            $this->response->json(['errors' => $errors], 422);
        } else {
            // For web, redirect back with errors
            // TODO: Implement flash session messages
            $this->response->redirect('/register');
        }
    }
}
