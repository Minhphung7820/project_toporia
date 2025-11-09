<?php

declare(strict_types=1);

namespace Toporia\Framework\Validation;

/**
 * Validator
 *
 * Powerful validation engine inspired by Laravel Validator.
 *
 * Features:
 * - 20+ built-in validation rules
 * - Custom error messages
 * - Nested validation (dot notation)
 * - Custom rules support (extend())
 * - Database validation (unique, exists)
 * - Conditional validation
 *
 * Performance: O(N*R) where N = fields, R = rules per field
 *
 * Clean Architecture:
 * - Single Responsibility: Only validates data
 * - Open/Closed: Extensible via custom rules
 * - Dependency Inversion: Implements ValidatorInterface
 *
 * @package Toporia\Framework\Validation
 */
final class Validator implements ValidatorInterface
{
    /**
     * @var array<string, array<string>> Validation errors
     */
    private array $errors = [];

    /**
     * @var array Validated data
     */
    private array $validatedData = [];

    /**
     * @var bool Validation status
     */
    private bool $passes = false;

    /**
     * @var array<string, callable> Custom validation rules
     */
    private static array $customRules = [];

    /**
     * @var array<string, string> Custom rule messages
     */
    private static array $customMessages = [];

    /**
     * @var object|null Database connection for unique/exists rules
     */
    private static ?object $connection = null;

    /**
     * @var callable|null Connection resolver callback
     */
    private static $connectionResolver = null;

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $rules, array $messages = []): bool
    {
        $this->errors = [];
        $this->validatedData = [];
        $this->passes = true;

        foreach ($rules as $field => $fieldRules) {
            $value = $this->getValue($data, $field);
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            foreach ($ruleList as $rule) {
                $this->validateRule($field, $value, $rule, $data, $messages);
            }

            // Store validated data (only if field passed validation)
            if (!isset($this->errors[$field])) {
                $this->validatedData[$field] = $value;
            }
        }

        $this->passes = empty($this->errors);

        return $this->passes;
    }

    /**
     * {@inheritdoc}
     */
    public function fails(): bool
    {
        return !$this->passes;
    }

    /**
     * {@inheritdoc}
     */
    public function passes(): bool
    {
        return $this->passes;
    }

    /**
     * {@inheritdoc}
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * {@inheritdoc}
     */
    public function extend(string $name, callable $callback, ?string $message = null): void
    {
        self::$customRules[$name] = $callback;

        if ($message !== null) {
            self::$customMessages[$name] = $message;
        }
    }

    /**
     * Set database connection for unique/exists validation.
     *
     * @param object|string $connection Database connection (PDO/QueryBuilder) or connection name
     * @return void
     *
     * @example
     * ```php
     * // Option 1: Pass connection object directly
     * Validator::setConnection($connection);
     *
     * // Option 2: Pass connection name (resolves from DatabaseManager)
     * Validator::setConnection('mysql');     // Use mysql connection
     * Validator::setConnection('pgsql');     // Use postgres connection
     * Validator::setConnection('default');   // Use default connection
     * ```
     */
    public static function setConnection(object|string $connection): void
    {
        if (is_string($connection)) {
            // Connection name - will be resolved lazily
            self::$connectionResolver = function() use ($connection) {
                // Try to get DatabaseManager from container
                if (function_exists('app') && app()->has('db.manager')) {
                    return app('db.manager')->connection($connection);
                }

                // Fallback: Try to get connection directly from container
                if (function_exists('app') && app()->has("db.{$connection}")) {
                    return app("db.{$connection}");
                }

                throw new \RuntimeException("Database connection '{$connection}' not found in container.");
            };
        } else {
            // Direct object
            self::$connection = $connection;
        }
    }

    /**
     * Set database connection resolver callback.
     *
     * The resolver will be called lazily when database connection is needed.
     * This allows auto-resolving from container without manual setup.
     *
     * Example:
     * ```php
     * Validator::setConnectionResolver(fn() => app('db'));
     * Validator::setConnectionResolver(fn() => app('db.manager')->connection('mysql'));
     * ```
     *
     * @param callable $resolver Callback that returns database connection
     * @return void
     */
    public static function setConnectionResolver(callable $resolver): void
    {
        self::$connectionResolver = $resolver;
    }

    /**
     * Get database connection (lazy loading).
     *
     * Tries in order:
     * 1. Static $connection if already set
     * 2. Resolver callback if configured
     * 3. Auto-resolve from global app() container
     * 4. Throw exception if none available
     *
     * Performance: O(1) after first call (cached in static $connection)
     *
     * @return object Database connection (PDO or QueryBuilder)
     * @throws \RuntimeException If no database available
     */
    private static function getConnection(): object
    {
        // Already set - return immediately (O(1))
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Try resolver callback
        if (self::$connectionResolver !== null) {
            self::$connection = (self::$connectionResolver)();
            return self::$connection;
        }

        // Try auto-resolve from container
        if (function_exists('app')) {
            try {
                self::$connection = app('db');
                return self::$connection;
            } catch (\Throwable $e) {
                // Container doesn't have 'db' - continue to error
            }
        }

        throw new \RuntimeException(
            'Database connection not available. ' .
            'Please call Validator::setConnection($db) or Validator::setConnectionResolver(fn() => app(\'db\')) first.'
        );
    }

    /**
     * Set database connection (deprecated - use setConnection).
     *
     * @deprecated Use setConnection() instead
     * @param object|string $db Database connection or name
     * @return void
     */
    public static function setDatabase(object|string $db): void
    {
        self::setConnection($db);
    }

    /**
     * Set database resolver (deprecated - use setConnectionResolver).
     *
     * @deprecated Use setConnectionResolver() instead
     * @param callable $resolver Callback
     * @return void
     */
    public static function setDatabaseResolver(callable $resolver): void
    {
        self::setConnectionResolver($resolver);
    }

    /**
     * Validate a single rule.
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Rule name with optional parameters
     * @param array $data All data (for dependent rules)
     * @param array $messages Custom messages
     * @return void
     */
    private function validateRule(string $field, mixed $value, string $rule, array $data, array $messages): void
    {
        // Parse rule and parameters (e.g., "max:255" => ['max', '255'])
        [$ruleName, $parameters] = $this->parseRule($rule);

        // Check if custom rule exists first (Open/Closed Principle)
        if (isset(self::$customRules[$ruleName])) {
            $passes = self::$customRules[$ruleName]($value, $parameters, $data);

            if (!$passes) {
                $this->addError($field, $ruleName, $parameters, $messages);
            }

            return;
        }

        // Check if built-in rule method exists
        $method = 'validate' . str_replace('_', '', ucwords($ruleName, '_'));

        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("Validation rule '{$ruleName}' does not exist");
        }

        // Execute validation
        $passes = $this->{$method}($value, $parameters, $data);

        if (!$passes) {
            $this->addError($field, $ruleName, $parameters, $messages);
        }
    }

    /**
     * Parse rule string into name and parameters.
     *
     * @param string $rule Rule string (e.g., "max:255")
     * @return array [ruleName, parameters]
     */
    private function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, []];
        }

        [$ruleName, $params] = explode(':', $rule, 2);
        return [$ruleName, explode(',', $params)];
    }

    /**
     * Get value from data using dot notation.
     *
     * @param array $data Data array
     * @param string $key Key (supports dot notation)
     * @return mixed
     */
    private function getValue(array $data, string $key): mixed
    {
        if (isset($data[$key])) {
            return $data[$key];
        }

        // Support dot notation (e.g., "user.email")
        $segments = explode('.', $key);
        $value = $data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Add validation error.
     *
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $parameters Rule parameters
     * @param array $customMessages Custom messages
     * @return void
     */
    private function addError(string $field, string $rule, array $parameters, array $customMessages): void
    {
        $message = $customMessages["{$field}.{$rule}"]
            ?? $customMessages[$rule]
            ?? $this->getDefaultMessage($field, $rule, $parameters);

        $this->errors[$field][] = $message;
    }

    /**
     * Get default error message for a rule.
     *
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $parameters Rule parameters
     * @return string
     */
    private function getDefaultMessage(string $field, string $rule, array $parameters): string
    {
        $fieldName = str_replace('_', ' ', $field);

        // Check custom rule messages first
        if (isset(self::$customMessages[$rule])) {
            return str_replace(':field', $fieldName, self::$customMessages[$rule]);
        }

        return match ($rule) {
            'required' => "The {$fieldName} field is required.",
            'email' => "The {$fieldName} must be a valid email address.",
            'min' => "The {$fieldName} must be at least {$parameters[0]} characters.",
            'max' => "The {$fieldName} must not exceed {$parameters[0]} characters.",
            'numeric' => "The {$fieldName} must be a number.",
            'integer' => "The {$fieldName} must be an integer.",
            'string' => "The {$fieldName} must be a string.",
            'array' => "The {$fieldName} must be an array.",
            'boolean' => "The {$fieldName} must be true or false.",
            'url' => "The {$fieldName} must be a valid URL.",
            'ip' => "The {$fieldName} must be a valid IP address.",
            'alpha' => "The {$fieldName} may only contain letters.",
            'alpha_num' => "The {$fieldName} may only contain letters and numbers.",
            'alpha_dash' => "The {$fieldName} may only contain letters, numbers, dashes and underscores.",
            'in' => "The selected {$fieldName} is invalid.",
            'not_in' => "The selected {$fieldName} is invalid.",
            'same' => "The {$fieldName} and {$parameters[0]} must match.",
            'different' => "The {$fieldName} and {$parameters[0]} must be different.",
            'confirmed' => "The {$fieldName} confirmation does not match.",
            'unique' => "The {$fieldName} has already been taken.",
            'exists' => "The selected {$fieldName} is invalid.",
            default => "The {$fieldName} is invalid."
        };
    }

    // =========================================================================
    // Validation Rules
    // =========================================================================

    /**
     * Validate required field.
     */
    private function validateRequired(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Validate email address.
     */
    private function validateEmail(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true; // Not required by default
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate minimum length/value.
     */
    private function validateMin(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        $min = (int) $parameters[0];

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * Validate maximum length/value.
     */
    private function validateMax(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        $max = (int) $parameters[0];

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * Validate numeric value.
     */
    private function validateNumeric(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return is_numeric($value);
    }

    /**
     * Validate integer value.
     */
    private function validateInteger(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate string value.
     */
    private function validateString(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value)) {
            return true;
        }

        return is_string($value);
    }

    /**
     * Validate array value.
     */
    private function validateArray(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value)) {
            return true;
        }

        return is_array($value);
    }

    /**
     * Validate boolean value.
     */
    private function validateBoolean(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    /**
     * Validate URL.
     */
    private function validateUrl(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate IP address.
     */
    private function validateIp(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate alpha (letters only).
     */
    private function validateAlpha(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    /**
     * Validate alpha-numeric.
     */
    private function validateAlphaNum(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }

    /**
     * Validate alpha-dash (letters, numbers, dashes, underscores).
     */
    private function validateAlphaDash(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    /**
     * Validate value is in list.
     */
    private function validateIn(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return in_array($value, $parameters, true);
    }

    /**
     * Validate value is not in list.
     */
    private function validateNotIn(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return !in_array($value, $parameters, true);
    }

    /**
     * Validate field matches another field.
     */
    private function validateSame(mixed $value, array $parameters, array $data): bool
    {
        $other = $this->getValue($data, $parameters[0]);
        return $value === $other;
    }

    /**
     * Validate field is different from another field.
     */
    private function validateDifferent(mixed $value, array $parameters, array $data): bool
    {
        $other = $this->getValue($data, $parameters[0]);
        return $value !== $other;
    }

    /**
     * Validate confirmed field (e.g., password_confirmation).
     */
    private function validateConfirmed(mixed $value, array $parameters, array $data): bool
    {
        $field = $parameters[0] ?? null;

        if (!$field) {
            return false;
        }

        $confirmation = $this->getValue($data, "{$field}_confirmation");
        return $value === $confirmation;
    }

    /**
     * Validate regex pattern.
     */
    private function validateRegex(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        return preg_match($parameters[0], $value) === 1;
    }

    // =========================================================================
    // Database Validation Rules
    // =========================================================================

    /**
     * Validate unique value in database.
     *
     * Usage:
     * - unique:table,column
     * - unique:table,column,ignoreValue,ignoreColumn
     *
     * Examples:
     * - 'email' => 'unique:users,email'
     * - 'email' => 'unique:users,email,' . $userId . ',id'
     *
     * Performance: O(1) - Single indexed query with prepared statement
     *
     * @param mixed $value Value to validate
     * @param array $parameters [table, column, ignoreValue, ignoreColumn]
     * @param array $data All form data
     * @return bool
     * @throws \RuntimeException If database not available
     * @throws \InvalidArgumentException If parameters invalid
     */
    private function validateUnique(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        // Lazy load database connection (auto-resolve from container)
        $db = self::getConnection();

        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? null;
        $ignoreValue = $parameters[2] ?? null;
        $ignoreColumn = $parameters[3] ?? 'id';

        if (!$table || !$column) {
            throw new \InvalidArgumentException('unique rule requires table and column parameters');
        }

        // Build query based on database type
        if (method_exists($db, 'table')) {
            // QueryBuilder
            $query = $db->table($table)->where($column, $value);

            if ($ignoreValue !== null) {
                $query->where($ignoreColumn, '!=', $ignoreValue);
            }

            return !$query->exists();
        }

        if ($db instanceof \PDO) {
            // PDO
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $params = [$value];

            if ($ignoreValue !== null) {
                $sql .= " AND {$ignoreColumn} != ?";
                $params[] = $ignoreValue;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn() === 0;
        }

        throw new \RuntimeException('Database connection must be PDO or QueryBuilder instance');
    }

    /**
     * Validate value exists in database.
     *
     * Usage:
     * - exists:table,column
     *
     * Example:
     * - 'category_id' => 'exists:categories,id'
     *
     * Performance: O(1) - Single indexed query with prepared statement
     *
     * @param mixed $value Value to validate
     * @param array $parameters [table, column]
     * @param array $data All form data
     * @return bool
     * @throws \RuntimeException If database not available
     * @throws \InvalidArgumentException If parameters invalid
     */
    private function validateExists(mixed $value, array $parameters, array $data): bool
    {
        if (is_null($value) || $value === '') {
            return true;
        }

        // Lazy load database connection (auto-resolve from container)
        $db = self::getConnection();

        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? 'id';

        if (!$table) {
            throw new \InvalidArgumentException('exists rule requires table parameter');
        }

        // Build query based on database type
        if (method_exists($db, 'table')) {
            // QueryBuilder
            return $db->table($table)->where($column, $value)->exists();
        }

        if ($db instanceof \PDO) {
            // PDO
            $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$value]);

            return (int) $stmt->fetchColumn() > 0;
        }

        throw new \RuntimeException('Database connection must be PDO or QueryBuilder instance');
    }
}
