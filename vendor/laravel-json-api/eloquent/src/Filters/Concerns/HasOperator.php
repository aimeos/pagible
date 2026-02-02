<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Filters\Concerns;

trait HasOperator
{

    /**
     * @var string
     */
    private string $operator;

    /**
     * @return $this
     */
    public function eq(): self
    {
        return $this->using('=');
    }

    /**
     * @return $this
     */
    public function gt(): self
    {
        return $this->using('>');
    }

    /**
     * @return $this
     */
    public function gte(): self
    {
        return $this->using('>=');
    }

    /**
     * @return $this
     */
    public function lt(): self
    {
        return $this->using('<');
    }

    /**
     * @return $this
     */
    public function lte(): self
    {
        return $this->using('<=');
    }

    /**
     * Use the provided operator for the filter.
     *
     * @param string $operator
     * @return $this
     */
    public function using(string $operator): self
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * @return string
     */
    public function operator(): string
    {
        return $this->operator;
    }
}
