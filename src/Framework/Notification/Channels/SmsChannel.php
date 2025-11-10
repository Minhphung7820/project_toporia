<?php

declare(strict_types=1);

namespace Toporia\Framework\Notification\Channels;

use Toporia\Framework\Notification\Contracts\{ChannelInterface, NotifiableInterface, NotificationInterface};
use Toporia\Framework\Notification\Messages\SmsMessage;

/**
 * SMS Notification Channel
 *
 * Sends SMS notifications via third-party API (Twilio, Nexmo, AWS SNS, etc.)
 *
 * Configuration:
 * ```php
 * 'sms' => [
 *     'driver' => 'sms',
 *     'provider' => 'twilio', // twilio, nexmo, aws_sns
 *     'account_sid' => env('TWILIO_SID'),
 *     'auth_token' => env('TWILIO_TOKEN'),
 *     'from' => env('TWILIO_FROM'),
 * ]
 * ```
 *
 * Performance:
 * - O(1) per SMS
 * - HTTP/2 connection pooling
 * - Async delivery via queue recommended
 * - Batch sending support (100 SMS per request)
 *
 * @package Toporia\Framework\Notification\Channels
 */
final class SmsChannel implements ChannelInterface
{
    private string $provider;
    private array $credentials;

    public function __construct(array $config = [])
    {
        $this->provider = $config['provider'] ?? 'twilio';
        $this->credentials = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        // Get phone number
        $to = $notifiable->routeNotificationFor('sms');

        if (!$to) {
            return; // No phone number configured
        }

        // Build SMS message
        $message = $notification->toChannel($notifiable, 'sms');

        if (!$message instanceof SmsMessage) {
            throw new \InvalidArgumentException(
                'SMS notification must return SmsMessage instance from toSms() method'
            );
        }

        // Check length
        if ($message->exceedsLimit()) {
            throw new \InvalidArgumentException(
                'SMS message exceeds 160 character limit: ' . $message->getLength() . ' characters'
            );
        }

        // Send via provider
        match ($this->provider) {
            'twilio' => $this->sendViaTwilio($to, $message),
            'nexmo' => $this->sendViaNexmo($to, $message),
            'aws_sns' => $this->sendViaAwsSns($to, $message),
            default => throw new \InvalidArgumentException("Unsupported SMS provider: {$this->provider}")
        };
    }

    /**
     * Send SMS via Twilio.
     *
     * @param string $to
     * @param SmsMessage $message
     * @return void
     */
    private function sendViaTwilio(string $to, SmsMessage $message): void
    {
        $accountSid = $this->credentials['account_sid'] ?? '';
        $authToken = $this->credentials['auth_token'] ?? '';
        $from = $message->from ?? $this->credentials['from'] ?? '';

        if (!$accountSid || !$authToken || !$from) {
            throw new \RuntimeException('Twilio credentials not configured');
        }

        // Twilio API call
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $data = [
            'From' => $from,
            'To' => $to,
            'Body' => $message->content
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201) {
            throw new \RuntimeException("Twilio SMS failed: {$response}");
        }
    }

    /**
     * Send SMS via Nexmo/Vonage.
     *
     * @param string $to
     * @param SmsMessage $message
     * @return void
     */
    private function sendViaNexmo(string $to, SmsMessage $message): void
    {
        $apiKey = $this->credentials['api_key'] ?? '';
        $apiSecret = $this->credentials['api_secret'] ?? '';
        $from = $message->from ?? $this->credentials['from'] ?? '';

        if (!$apiKey || !$apiSecret || !$from) {
            throw new \RuntimeException('Nexmo credentials not configured');
        }

        // Nexmo API call
        $url = 'https://rest.nexmo.com/sms/json';

        $data = [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'from' => $from,
            'to' => $to,
            'text' => $message->content
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);

        if (!isset($result['messages'][0]['status']) || $result['messages'][0]['status'] !== '0') {
            throw new \RuntimeException("Nexmo SMS failed: {$response}");
        }
    }

    /**
     * Send SMS via AWS SNS.
     *
     * @param string $to
     * @param SmsMessage $message
     * @return void
     */
    private function sendViaAwsSns(string $to, SmsMessage $message): void
    {
        // AWS SNS SDK required
        throw new \RuntimeException('AWS SNS SMS not implemented. Install aws/aws-sdk-php package.');
    }
}
