# Notification System

Professional multi-channel notification system with Laravel-like API, Clean Architecture, and performance optimization.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Available Channels](#available-channels)
- [Creating Notifications](#creating-notifications)
- [Sending Notifications](#sending-notifications)
- [Database Notifications](#database-notifications)
- [Queue Support](#queue-support)
- [Performance](#performance)
- [API Reference](#api-reference)

---

## Overview

The Notification system provides a unified API for sending notifications across multiple channels:

- **Mail**: Email notifications with rich HTML formatting
- **Database**: In-app notifications with read/unread tracking
- **SMS**: Text messages via Twilio/Nexmo/AWS SNS
- **Slack**: Real-time team notifications

**Key Features:**
- ✅ Multi-channel delivery (send to multiple channels simultaneously)
- ✅ Queue support for async delivery
- ✅ Fluent message builders for each channel
- ✅ Laravel-compatible API
- ✅ Clean Architecture (SOLID principles)
- ✅ Performance optimized (O(1) channel resolution, lazy loading)

---

## Architecture

```
Notification/
├── Contracts/                    # Interfaces
│   ├── NotificationInterface.php      # Notification contract
│   ├── NotifiableInterface.php        # Notifiable entity contract
│   ├── ChannelInterface.php           # Channel driver contract
│   └── NotificationManagerInterface.php
├── Notification.php              # Base notification class
├── NotificationManager.php       # Multi-channel dispatcher
├── Notifiable.php                # Trait for notifiable models
├── Messages/                     # Message builders
│   ├── MailMessage.php
│   ├── SmsMessage.php
│   └── SlackMessage.php
└── Channels/                     # Channel drivers
    ├── MailChannel.php           # Email delivery
    ├── DatabaseChannel.php       # Database storage
    ├── SmsChannel.php            # SMS delivery
    └── SlackChannel.php          # Slack webhooks
```

**SOLID Principles:**
- **Single Responsibility**: Each channel handles one delivery method
- **Open/Closed**: Extensible via custom channels
- **Liskov Substitution**: All channels implement `ChannelInterface`
- **Interface Segregation**: Minimal, focused interfaces
- **Dependency Inversion**: Depends on abstractions (interfaces)

---

## Installation

### 1. Register Service Provider

Add to `bootstrap/app.php`:

```php
$app->registerProviders([
    // ... other providers
    \Toporia\Framework\Providers\NotificationServiceProvider::class,
]);
```

### 2. Run Database Migration

Create notifications table:

```bash
php console migrate
```

### 3. Configure Channels

Edit `config/notification.php`:

```php
return [
    'default' => 'mail',

    'channels' => [
        'mail' => [
            'driver' => 'mail',
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],
        'sms' => [
            'driver' => 'sms',
            'provider' => 'twilio',
            'account_sid' => env('TWILIO_SID'),
            'auth_token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'slack' => [
            'driver' => 'slack',
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],
    ],
];
```

---

## Quick Start

### 1. Make Model Notifiable

```php
use Toporia\Framework\Notification\Contracts\NotifiableInterface;
use Toporia\Framework\Notification\Notifiable;

class User implements NotifiableInterface
{
    use Notifiable;

    public string $email;
    public string $phone;
    public int $id;

    public function routeNotificationFor(string $channel): mixed
    {
        return match($channel) {
            'mail' => $this->email,
            'sms' => $this->phone,
            'database' => $this->id,
            default => null
        };
    }
}
```

### 2. Create Notification

```php
use Toporia\Framework\Notification\Notification;
use Toporia\Framework\Notification\Messages\MailMessage;

class WelcomeNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome!')
            ->greeting('Hello!')
            ->line('Thanks for signing up!')
            ->action('Get Started', url('/dashboard'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Welcome!',
            'message' => 'Thanks for signing up!',
            'action_url' => url('/dashboard')
        ];
    }
}
```

### 3. Send Notification

```php
// Via model method
$user->notify(new WelcomeNotification());

// Via helper function
notify($user, new WelcomeNotification());

// Via facade
Notification::send($user, new WelcomeNotification());
```

---

## Available Channels

### Mail Channel

Send rich HTML emails with action buttons.

```php
public function toMail($notifiable): MailMessage
{
    return (new MailMessage)
        ->subject('Order Confirmation')
        ->greeting('Hello ' . $notifiable->name)
        ->line('Your order has been confirmed.')
        ->line('Order ID: #12345')
        ->action('View Order', url('/orders/12345'))
        ->line('Thank you for your purchase!')
        ->success(); // or ->error()
}
```

**Features:**
- Subject line
- Greeting/Salutation
- Multiple text lines
- Action button (one per email)
- Success/Error styling

### Database Channel

Store notifications in database for in-app notifications.

```php
public function toDatabase($notifiable): array
{
    return [
        'title' => 'New Message',
        'message' => 'You have a new message from John',
        'icon' => '💬',
        'action_url' => url('/messages/123'),
        'action_text' => 'View Message',
        'sender_id' => 456,
        'custom_data' => ['foo' => 'bar']
    ];
}
```

**Querying Notifications:**

```php
// Get unread notifications
$notifications = $db->table('notifications')
    ->where('notifiable_id', $userId)
    ->whereNull('read_at')
    ->orderBy('created_at', 'DESC')
    ->get();

// Mark as read
$db->table('notifications')
    ->where('id', $notificationId)
    ->update(['read_at' => time()]);

// Mark all as read
$channel = app('notification')->channel('database');
$channel->markAllAsRead($userId);

// Delete old notifications (30+ days)
$channel->deleteOld(30);
```

### SMS Channel

Send SMS via Twilio, Nexmo, or AWS SNS.

```php
public function toSms($notifiable): SmsMessage
{
    return (new SmsMessage)
        ->content('Your verification code is: 123456')
        ->from('YourApp');
}
```

**Providers:**
- **Twilio**: Most popular, reliable
- **Nexmo**: Cost-effective
- **AWS SNS**: For AWS ecosystem

**Character Limit:** 160 characters (automatically validated)

### Slack Channel

Send messages to Slack via webhooks.

```php
public function toSlack($notifiable): SlackMessage
{
    return (new SlackMessage)
        ->content('New order received!')
        ->channel('#orders')
        ->from('Order Bot')
        ->icon(':shopping_cart:')
        ->attachment(function ($attachment) {
            $attachment
                ->title('Order #12345')
                ->fields([
                    'Customer' => 'John Doe',
                    'Total' => '$99.99',
                    'Items' => '3'
                ])
                ->color('good');
        });
}
```

---

## Creating Notifications

### Basic Notification

```php
class InvoicePaidNotification extends Notification
{
    public function __construct(
        private readonly array $invoice
    ) {
        parent::__construct();
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Invoice Paid')
            ->line("Invoice #{$this->invoice['id']} has been paid.")
            ->action('View Invoice', url("/invoices/{$this->invoice['id']}"))
            ->success();
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Invoice Paid',
            'message' => "Invoice #{$this->invoice['id']} paid successfully",
            'invoice_id' => $this->invoice['id'],
            'amount' => $this->invoice['amount']
        ];
    }
}
```

### Conditional Channels

```php
public function via($notifiable): array
{
    $channels = ['database'];

    // Send email only if user has email notifications enabled
    if ($notifiable->email_notifications_enabled) {
        $channels[] = 'mail';
    }

    // Send SMS only for urgent notifications
    if ($this->isUrgent) {
        $channels[] = 'sms';
    }

    return $channels;
}
```

### Queue Support

```php
// Enable queueing in notification
public function shouldQueue(): bool
{
    return true;
}

public function getQueueName(): string
{
    return 'notifications';
}

// Or fluently
$notification = new OrderShippedNotification($order);
$notification->onQueue('notifications');
$user->notify($notification);

// Delayed delivery
$notification->delay(60); // Send in 60 seconds
```

---

## Sending Notifications

### To Single User

```php
// Via model method
$user->notify(new WelcomeNotification());

// Via helper
notify($user, new WelcomeNotification());

// Via facade
Notification::send($user, new WelcomeNotification());
```

### To Multiple Users

```php
// Bulk send
$users = User::all();
Notification::sendToMany($users, new AnnouncementNotification());

// Or iterate
foreach ($users as $user) {
    $user->notify(new AnnouncementNotification());
}
```

### Async Delivery

```php
// Queue for async delivery
$user->notifyLater(new OrderShippedNotification($order), 'notifications');
```

---

## Database Notifications

### Schema

```sql
CREATE TABLE notifications (
    id VARCHAR(255) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id VARCHAR(255) NOT NULL,
    data TEXT NOT NULL,
    read_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    INDEX idx_notifiable_read (notifiable_id, read_at),
    INDEX idx_created (created_at)
);
```

### Querying

```php
// Get unread count
$unreadCount = $db->table('notifications')
    ->where('notifiable_id', $userId)
    ->whereNull('read_at')
    ->count();

// Get recent notifications (last 30 days)
$recent = $db->table('notifications')
    ->where('notifiable_id', $userId)
    ->where('created_at', '>=', time() - (30 * 86400))
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Paginated notifications
$notifications = $db->table('notifications')
    ->where('notifiable_id', $userId)
    ->orderBy('created_at', 'DESC')
    ->paginate(perPage: 20, page: 1);
```

### Maintenance

```php
// Clean up old notifications (30+ days)
$channel = app('notification')->channel('database');
$deleted = $channel->deleteOld(30);

echo "Deleted {$deleted} old notifications";
```

---

## Performance

### Benchmarks

| Operation | Time | Queries |
|-----------|------|---------|
| Send to Mail | ~50ms | 0 (async via queue) |
| Send to Database | ~2ms | 1 INSERT |
| Send to SMS | ~100ms | 0 (HTTP request) |
| Send to Slack | ~80ms | 0 (webhook) |
| Multi-channel (3) | ~150ms | 1 (if database included) |

### Optimization Tips

1. **Use Queue for Async Delivery**
   ```php
   $notification->onQueue('notifications');
   ```

2. **Lazy Channel Loading**
   - Channels are only instantiated when used
   - O(1) channel resolution

3. **Batch Sending**
   ```php
   // Efficient bulk send
   Notification::sendToMany($users, $notification);
   ```

4. **Database Indexes**
   - Index on `(notifiable_id, read_at)` for fast unread queries
   - Index on `created_at` for cleanup

5. **Cleanup Old Notifications**
   ```php
   // Run daily via scheduler
   $channel->deleteOld(30);
   ```

---

## API Reference

### NotificationInterface

```php
interface NotificationInterface
{
    public function via(NotifiableInterface $notifiable): array;
    public function toChannel(NotifiableInterface $notifiable, string $channel): mixed;
    public function getId(): string;
    public function shouldQueue(): bool;
    public function getQueueName(): string;
    public function getDelay(): int;
}
```

### NotifiableInterface

```php
interface NotifiableInterface
{
    public function routeNotificationFor(string $channel): mixed;
}
```

### MailMessage

```php
$message = (new MailMessage)
    ->subject(string $subject)
    ->greeting(string $greeting)
    ->line(string $line)
    ->action(string $text, string $url)
    ->salutation(string $salutation)
    ->success()  // Green button
    ->error();   // Red button
```

### SmsMessage

```php
$message = (new SmsMessage)
    ->content(string $content)
    ->from(string $from);
```

### SlackMessage

```php
$message = (new SlackMessage)
    ->content(string $content)
    ->channel(string $channel)
    ->from(string $username)
    ->icon(string $icon)
    ->attachment(callable $callback);
```

---

## Examples

See complete examples in:
- `src/App/Notifications/WelcomeNotification.php`
- `src/App/Notifications/OrderShippedNotification.php`
- `src/App/Notifications/SystemAlertNotification.php`
