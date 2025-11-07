<?php

declare(strict_types=1);

namespace Framework\Application\UseCase;

/**
 * Handler interface for Commands and Queries.
 *
 * Handlers contain the business logic for executing use cases.
 * They orchestrate domain objects and infrastructure services.
 */
interface HandlerInterface
{
    /**
     * Execute the use case.
     *
     * @param object $message Command or Query object.
     * @return mixed Result of the operation.
     */
    public function __invoke(object $message): mixed;
}
