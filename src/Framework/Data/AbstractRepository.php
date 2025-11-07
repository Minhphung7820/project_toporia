<?php

declare(strict_types=1);

namespace Framework\Data;

/**
 * Base Repository class with common utilities.
 *
 * Provides helper methods for data transformation and mapping
 * that are useful across different repository implementations.
 *
 * This class focuses on data mapping and doesn't implement
 * RepositoryInterface to allow maximum flexibility in child classes.
 */
abstract class AbstractRepository
{
    /**
     * Map database snake_case columns to camelCase properties.
     *
     * Converts: ['user_name' => 'John'] to ['userName' => 'John']
     *
     * @param array<string, mixed> $row Database row.
     * @return array<string, mixed> Mapped array.
     */
    protected function mapSnakeToCamel(array $row): array
    {
        $result = [];

        foreach ($row as $key => $value) {
            $camelKey = preg_replace_callback(
                '/_([a-z])/',
                fn($matches) => strtoupper($matches[1]),
                $key
            );
            $result[$camelKey] = $value;
        }

        return $result;
    }

    /**
     * Map camelCase properties to database snake_case columns.
     *
     * Converts: ['userName' => 'John'] to ['user_name' => 'John']
     *
     * @param array<string, mixed> $data Data array.
     * @return array<string, mixed> Mapped array.
     */
    protected function mapCamelToSnake(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $snakeKey = strtolower(preg_replace(
                '/([a-z])([A-Z])/',
                '$1_$2',
                $key
            ));
            $result[$snakeKey] = $value;
        }

        return $result;
    }

    /**
     * Filter null values from array.
     *
     * Useful for UPDATE queries where you only want to update non-null fields.
     *
     * @param array<string, mixed> $data Data array.
     * @return array<string, mixed> Filtered array.
     */
    protected function filterNulls(array $data): array
    {
        return array_filter($data, fn($value) => $value !== null);
    }

    /**
     * Extract only specific keys from array.
     *
     * @param array<string, mixed> $data Source data.
     * @param array<string> $keys Keys to extract.
     * @return array<string, mixed> Extracted data.
     */
    protected function only(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Exclude specific keys from array.
     *
     * @param array<string, mixed> $data Source data.
     * @param array<string> $keys Keys to exclude.
     * @return array<string, mixed> Filtered data.
     */
    protected function except(array $data, array $keys): array
    {
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * Hydrate a domain object from data array.
     *
     * Override this method to define how to convert database data
     * into domain objects.
     *
     * @param array<string, mixed> $data Data from data source.
     * @return object Domain object.
     */
    abstract protected function hydrate(array $data): object;

    /**
     * Extract data from domain object for persistence.
     *
     * Override this method to define how to convert domain objects
     * into data arrays for storage.
     *
     * @param object $entity Domain object.
     * @return array<string, mixed> Data for persistence.
     */
    abstract protected function extract(object $entity): array;
}
