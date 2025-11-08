<?php

declare(strict_types=1);

namespace Toporia\Framework\Database\Schema;

/**
 * Column definition helper.
 *
 * Provides fluent interface for column modifiers.
 */
class ColumnDefinition
{
    /**
     * @param array $column Reference to column definition array.
     */
    public function __construct(
        private array &$column
    ) {
    }

    /**
     * Make column nullable.
     *
     * @return self
     */
    public function nullable(): self
    {
        $this->column['nullable'] = true;
        return $this;
    }

    /**
     * Set default value.
     *
     * @param mixed $value Default value.
     * @return self
     */
    public function default(mixed $value): self
    {
        $this->column['default'] = $value;
        return $this;
    }

    /**
     * Make column unsigned (integers).
     *
     * @return self
     */
    public function unsigned(): self
    {
        $this->column['unsigned'] = true;
        return $this;
    }

    /**
     * Make column unique.
     *
     * @return self
     */
    public function unique(): self
    {
        $this->column['unique'] = true;
        return $this;
    }

    /**
     * Add comment to column.
     *
     * @param string $comment Column comment.
     * @return self
     */
    public function comment(string $comment): self
    {
        $this->column['comment'] = $comment;
        return $this;
    }
}
