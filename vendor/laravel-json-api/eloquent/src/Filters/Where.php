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

class Where implements Filter
{

    use Concerns\DeserializesValue;
    use Concerns\HasColumn;
    use Concerns\HasOperator;
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
     * @return static
     */
    public static function make(string $name, ?string $column = null): self
    {
        return new static($name, $column);
    }

    /**
     * Where constructor.
     *
     * @param string $name
     * @param string|null $column
     */
    public function __construct(string $name, ?string $column = null)
    {
        $this->name = $name;
        $this->column = $column ?: $this->guessColumn();
        $this->operator = '=';
    }

    /**
     * @inheritDoc
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
        return $query->where(
            $query->getModel()->qualifyColumn($this->column()),
            $this->operator(),
            $this->deserialize($value)
        );
    }

    /**
     * @return string
     */
    private function guessColumn(): string
    {
        return Str::underscore($this->name);
    }
}
