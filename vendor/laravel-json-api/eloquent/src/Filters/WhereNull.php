<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Filters;

use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Contracts\Filter;

class WhereNull implements Filter
{
    use Concerns\HasColumn;
    use Concerns\IsSingular;

    /**
     * @var string
     */
    private string $name;

    /**
     * Create a new filter.
     *
     * @param string $name
     * @param string|null $column
     *
     * @return static
     */
    public static function make(string $name, ?string $column = null): self
    {
        return new static($name, $column);
    }

    /**
     * WhereNull constructor.
     *
     * @param string|null $column
     */
    public function __construct(string $name, ?string $column = null)
    {
        $this->name = $name;
        $this->column = $column ?: $this->guessColumn();
    }

    /**
     * @return string
     */
    public function key(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        $value = $this->deserialize($value);
        $column = $query->getModel()->qualifyColumn($this->column());

        if ($this->isWhereNull($value)) {
            return $query->whereNull($column);
        }

        return $query->whereNotNull($column);
    }

    /**
     * Should a "where null" query be used?
     *
     * @param bool $value
     * @return bool
     */
    protected function isWhereNull(bool $value): bool
    {
        return $value === true;
    }

    /**
     * Deserialize the value.
     *
     * @param mixed $value
     * @return bool
     */
    private function deserialize($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * @return string
     */
    private function guessColumn(): string
    {
        return Str::underscore($this->name);
    }
}
