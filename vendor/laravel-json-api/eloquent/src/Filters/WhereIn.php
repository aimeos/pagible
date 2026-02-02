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

class WhereIn implements Filter
{

    use Concerns\DeserializesValue;
    use Concerns\HasColumn;
    use Concerns\HasDelimiter;

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
    public function isSingular(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        return $query->whereIn(
            $query->getModel()->qualifyColumn($this->column()),
            $this->deserialize($value)
        );
    }

    /**
     * Deserialize the fitler value.
     *
     * @param string|array $value
     * @return array
     */
    protected function deserialize($value): array
    {
        if ($this->deserializer) {
            return ($this->deserializer)($value);
        }

        return $this->toArray($value);
    }

    /**
     * @return string
     */
    private function guessColumn(): string
    {
        return Str::underscore(
            Str::singular($this->name)
        );
    }

}
