<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields\Concerns;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;

trait Hideable
{

    /**
     * @var Closure|bool
     */
    private $hidden = false;

    /**
     * Mark the field as hidden.
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function hidden($callback = true): self
    {
        if (!is_bool($callback) && !$callback instanceof Closure) {
            throw new InvalidArgumentException('Expecting a boolean or closure.');
        }

        $this->hidden = $callback;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isHidden(?Request $request): bool
    {
        if (is_callable($this->hidden)) {
            return true === ($this->hidden)($request);
        }

        return true === $this->hidden;
    }

    /**
     * @inheritDoc
     */
    public function isNotHidden(?Request $request): bool
    {
        return !$this->isHidden($request);
    }
}
