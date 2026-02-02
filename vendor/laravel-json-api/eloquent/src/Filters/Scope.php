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

class Scope implements Filter
{

    use Concerns\DeserializesValue;
    use Concerns\IsSingular;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $scope;

    /**
     * Create a new scope filter.
     *
     * @param string $name
     * @param string|null $scope
     * @return static
     */
    public static function make(string $name, ?string $scope = null)
    {
        return new static($name, $scope);
    }

    /**
     * Scope constructor.
     *
     * @param string $name
     * @param string|null $scope
     */
    public function __construct(string $name, ?string $scope = null)
    {
        $this->name = $name;
        $this->scope = $scope ?: $this->guessScope();
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
        return $query->{$this->scope}(
            $this->deserialize($value)
        );
    }

    /**
     * @return string
     */
    private function guessScope(): string
    {
        return Str::camel($this->name);
    }
}
