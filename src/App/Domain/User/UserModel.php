<?php

declare(strict_types=1);

namespace App\Domain\User;

use Toporia\Framework\Database\ORM\Model;

/**
 * User ORM Model - Demo of Auto Table Name & Auto-Fillable.
 *
 * This model demonstrates convention over configuration:
 * - NO $table property -> auto-inferred as "users" (from UserModel)
 * - NO $fillable property -> ALL fields are fillable
 * - NO $guarded property -> inherits empty array (auto-fillable)
 *
 * Benefits:
 * - Less boilerplate code
 * - Follows Laravel conventions
 * - Easy to override when needed
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $remember_token
 * @property string $created_at
 * @property string $updated_at
 */
class UserModel extends Model
{
    // =========================================================================
    // NO CONFIGURATION NEEDED!
    // =========================================================================

    // Table name auto-inferred: UserModel -> users
    // protected static string $table = 'users'; // <- Not needed!

    // Auto-fillable: All fields are fillable by default
    // protected static array $fillable = []; // <- Not needed!

    // No guarded fields by default
    // protected static array $guarded = []; // <- Already inherited!

    /**
     * {@inheritdoc}
     */
    protected static array $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Hide sensitive attributes from JSON output.
     *
     * Security best practice: Always hide passwords and tokens!
     */
    protected static array $hidden = ['password', 'remember_token'];

    /**
     * Check if user's email is verified.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return !empty($this->email_verified_at);
    }
}
