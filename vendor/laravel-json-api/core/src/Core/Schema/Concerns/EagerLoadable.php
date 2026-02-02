<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema\Concerns;

use Closure;
use InvalidArgumentException;

trait EagerLoadable
{

    /**
     * @var Closure|bool
     */
    private $includePath = true;

    /**
     * Set whether the relation can be eager loaded (via include paths).
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function canEagerLoad($callback = true): self
    {
        if (!is_bool($callback) && !$callback instanceof Closure) {
            throw new InvalidArgumentException('Expecting a boolean or closure.');
        }

        $this->includePath = $callback;

        return $this;
    }

    /**
     * Mark the relation as not eager-loadable (i.e. not an include path).
     *
     * @return $this
     */
    public function cannotEagerLoad(): self
    {
        $this->includePath = false;

        return $this;
    }

    /**
     * Is the relation allowed as an include path?
     *
     * @return bool
     */
    public function isIncludePath(): bool
    {
        if ($this->includePath instanceof Closure) {
            $this->includePath = ($this->includePath)();
        }

        return $this->includePath;
    }
}
