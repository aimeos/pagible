<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation;

use Closure;
use LaravelJsonApi\Core\Document\Error;
use function str_replace;

class ValidatorErrorIterator extends ErrorIterator
{

    /**
     * A callback to convert validation keys to JSON pointers.
     *
     * @var Closure|null
     */
    private ?Closure $pointer = null;

    /**
     * Use the prefix when converting keys.
     *
     * @param string $prefix
     * @return $this
     */
    public function withSourcePrefix(string $prefix): self
    {
        $prefix = trim($prefix, '/');

        $this->withPointers(
            fn($key) => sprintf('/%s%s', $prefix, $this->convertKey($key))
        );
        
        return $this;
    }

    /**
     * @param Closure $pointer
     * @return $this
     */
    public function withPointers(Closure $pointer): self
    {
        $this->pointer = $pointer;

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function createError(string $key, string $message, array $failed): Error
    {
        return $this->translator->invalid(
            $this->pointerFor($key),
            $message,
            $failed
        );
    }

    /**
     * Convert a validation key to a JSON pointer.
     *
     * @param string $key
     * @return string
     */
    protected function pointerFor(string $key): string
    {
        if ($this->pointer) {
            return ($this->pointer)($key);
        }

        return $this->convertKey($key);
    }

    /**
     * @param string $key
     * @return string
     */
    protected function convertKey(string $key): string
    {
        return '/' . str_replace('.', '/', $key);
    }

}
