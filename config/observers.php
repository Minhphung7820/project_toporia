<?php

declare(strict_types=1);

/**
 * Observer Configuration
 *
 * Register observers for observable classes.
 *
 * Format:
 * - Simple: 'ObservableClass' => 'ObserverClass'
 * - Advanced: 'ObservableClass' => [
 *     'ObserverClass',
 *     ['class' => 'ObserverClass', 'event' => 'created', 'priority' => 10],
 *   ]
 *
 * Performance:
 * - Observers are lazy-loaded (only instantiated when needed)
 * - Observer instances are cached (singleton pattern)
 * - Event-specific observers are indexed for fast lookup
 */

return [
    // Product Model Observers
    \App\Domain\Product\ProductModel::class => [
        \App\Observers\ProductObserver::class,
    ],

    // Add more observers here
    // \App\Domain\User\UserModel::class => [
    //     \App\Observers\UserObserver::class,
    // ],
];

