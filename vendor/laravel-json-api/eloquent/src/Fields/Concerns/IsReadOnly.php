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

trait IsReadOnly
{

    /**
     * Whether the field is read-only.
     *
     * @var Closure|bool
     */
    private $readOnly = false;

    /**
     * Mark the field as read-only.
     *
     * @param Closure|bool $callback
     * @return $this
     */
    public function readOnly($callback = true): self
    {
        if (!is_bool($callback) && !$callback instanceof Closure) {
            throw new InvalidArgumentException('Expecting a boolean or closure.');
        }

        $this->readOnly = $callback;

        return $this;
    }

    /**
     * Mark the field as read only when the resource is being created.
     *
     * @return $this
     */
    public function readOnlyOnCreate(): self
    {
        $this->readOnly(static fn($request) => $request && $request->isMethod('POST'));

        return $this;
    }

    /**
     * Mark the field as read only when the resource is being updated.
     *
     * @return $this
     */
    public function readOnlyOnUpdate(): self
    {
        $this->readOnly(static fn($request) => $request && $request->isMethod('PATCH'));

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isReadOnly(?Request $request): bool
    {
        if ($this->readOnly instanceof Closure) {
            return true === ($this->readOnly)($request);
        }

        return true === $this->readOnly;
    }

    /**
     * @inheritDoc
     */
    public function isNotReadOnly(?Request $request): bool
    {
        return !$this->isReadOnly($request);
    }

}
