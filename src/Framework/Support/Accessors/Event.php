<?php

declare(strict_types=1);

namespace Toporia\Framework\Support\Accessors;

use Toporia\Framework\Foundation\ServiceAccessor;
use Toporia\Framework\Events\Contracts\EventDispatcherInterface;
use Toporia\Framework\Events\Contracts\EventInterface;

/**
 * Event Service Accessor
 *
 * Provides static-like access to the event dispatcher.
 *
 * @method static void dispatch(EventInterface|string $event, array $payload = []) Dispatch event
 * @method static void listen(string $eventName, callable $listener, int $priority = 0) Register listener
 * @method static void subscribe(array $events) Subscribe multiple listeners
 * @method static array getListeners(string $eventName) Get listeners for event
 * @method static bool hasListeners(string $eventName) Check if event has listeners
 *
 * @see EventDispatcherInterface
 *
 * @example
 * // Dispatch event
 * Event::dispatch('user.created', ['user' => $user]);
 *
 * // Listen to event
 * Event::listen('user.created', function($event) {
 *     Mail::send($event->getData()['user']);
 * });
 *
 * // With priority
 * Event::listen('user.created', $listener, priority: 100);
 */
final class Event extends ServiceAccessor
{
    protected static function getServiceName(): string
    {
        return 'events';
    }
}
