<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Models;

use Toporia\Framework\Database\ORM\Model;
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Notifiable;

/**
 * User ORM Model (Infrastructure Layer).
 *
 * This is the Active Record implementation for database persistence.
 * Located in Infrastructure layer as it depends on framework components.
 *
 * Clean Architecture:
 * - This class belongs to Infrastructure layer
 * - Should NOT be used directly by controllers
 * - Should be accessed through Repository implementations
 *
 * This model demonstrates convention over configuration:
 * - NO $table property -> auto-inferred as "users" (from UserModel)
 * - NO $fillable property -> ALL fields are fillable
 * - NO $guarded property -> inherits empty array (auto-fillable)
 *
 * Features:
 * - Notifiable: Can send/receive notifications via email, database, etc.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $remember_token
 * @property string $created_at
 * @property string $updated_at
 */
class UserModel extends Model implements NotifiableInterface
{
    use Notifiable;
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

    /**
     * Route notification to specific channel.
     *
     * @param string $channel Notification channel (mail, sms, slack, database)
     * @return mixed Channel-specific routing data
     */
    public function routeNotificationFor(string $channel): mixed
    {
        return match ($channel) {
            'mail' => $this->email,
            'database' => $this->id,
            default => null
        };
    }
}
