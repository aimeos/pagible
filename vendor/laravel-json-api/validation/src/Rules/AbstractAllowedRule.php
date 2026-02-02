<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;
use LaravelJsonApi\Core\Support\Arr;
use LaravelJsonApi\Validation\JsonApiValidation;

abstract class AbstractAllowedRule implements Rule
{

    /**
     * @var Collection
     */
    private Collection $allowed;

    /**
     * The last value that was validated.
     *
     * @var mixed|null
     */
    private $value;

    /**
     * Extract parameters from the value.
     *
     * @param mixed $value
     * @return Collection
     */
    abstract protected function extract($value): Collection;

    /**
     * AllowedFilterParameters constructor.
     *
     * @param iterable $allowed
     */
    public function __construct(iterable $allowed = [])
    {
        $values = collect($allowed)->unique()->values();
        $this->allowed = $values->combine($values);
    }

    /**
     * Add allowed parameters.
     *
     * @param string ...$params
     * @return $this
     */
    public function allow(string ...$params): self
    {
        foreach ($params as $param) {
            $this->allowed->put($param, $param);
        }

        return $this;
    }

    /**
     * Forget an allowed parameter.
     *
     * @param string ...$params
     * @return $this
     */
    public function forget(string ...$params): self
    {
        $this->allowed->forget($params);

        return $this;
    }

    /**
     * Forget allowed parameters if the provided check is true.
     *
     * @param bool $check
     * @param string|string[] $paths
     * @return $this
     */
    public function forgetIf(bool $check, $paths): self
    {
        if (true === $check) {
            $this->forget(...Arr::wrap($paths));
        }

        return $this;
    }

    /**
     * Forget allowed parameters, if the provided check is not true.
     *
     * @param bool $check
     * @param string|string[] $paths
     * @return $this
     */
    public function forgetUnless(bool $check, $paths): self
    {
        return $this->forgetIf(false === $check, $paths);
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        $this->value = $value;

        return $this->extract($value)->every(function ($key) {
            return $this->allowed($key);
        });
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        $invalid = $this->invalid();

        if ($invalid->isEmpty()) {
            $key = 'default';
        } else {
            $key = (1 === $invalid->count()) ? 'singular' : 'plural';
        }

        return trans(JsonApiValidation::translationKeyForRule($this, $key), [
            'values' => $params = $invalid->implode(', '),
        ]);
    }

    /**
     * Is the parameter allowed?
     *
     * @param string $param
     * @return bool
     */
    protected function allowed(string $param): bool
    {
        return $this->allowed->has($param);
    }

    /**
     * @return Collection
     */
    protected function invalid(): Collection
    {
        return $this
            ->extract($this->value)
            ->reject(fn($value) => $this->allowed($value))
            ->sort();
    }

}
