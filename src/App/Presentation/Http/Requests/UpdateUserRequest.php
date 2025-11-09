<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use Toporia\Framework\Http\FormRequest;

/**
 * Update User Request
 *
 * Validates data for updating an existing user.
 * Demonstrates unique validation with ignore support.
 *
 * Example usage in controller:
 * ```php
 * public function update(UpdateUserRequest $request, int $userId)
 * {
 *     $validated = $request->validated();
 *     $user = User::find($userId);
 *     $user->update($validated);
 *     return response()->json($user);
 * }
 * ```
 */
final class UpdateUserRequest extends FormRequest
{
    /**
     * User ID to ignore in unique validation.
     */
    private int $userId;

    /**
     * Constructor.
     *
     * @param \Toporia\Framework\Http\Request $request
     * @param int $userId User ID from route parameter
     */
    public function __construct(
        \Toporia\Framework\Http\Request $request,
        int $userId
    ) {
        parent::__construct($request);
        $this->userId = $userId;
    }

    /**
     * Get validation rules.
     *
     * Uses unique validation with ignore to allow user to keep their own email/username.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'string|min:3|max:255',
            // Ignore current user's email in uniqueness check
            'email' => "email|unique:users,email,{$this->userId},id",
            // Ignore current user's username in uniqueness check
            'username' => "string|alpha_dash|unique:users,username,{$this->userId},id",
            'password' => 'string|min:8', // Optional password update
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already taken by another user.',
            'username.unique' => 'This username is already taken by another user.',
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes and underscores.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }

    /**
     * Determine if user is authorized to update users.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Example: Check if user is authenticated and is the owner or admin
        // return auth()->check() && (auth()->id() === $this->userId || auth()->user()->isAdmin());

        return true; // Allow for now
    }
}
