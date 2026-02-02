<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\ForwardsCalls;
use LaravelJsonApi\Eloquent\Contracts\Proxy as ProxyContract;

/**
 * Class Proxy
 *
 * @mixin Model
 */
abstract class Proxy implements ProxyContract
{

    use ForwardsCalls;

    /**
     * @var Model
     */
    protected Model $model;

    /**
     * @inheritDoc
     */
    public static function proxyFor(Model $model): self
    {
        return new static($model);
    }

    /**
     * @inheritDoc
     */
    public static function nullable(?Model $model): ?self
    {
        if ($model) {
            return static::proxyFor($model);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public static function iterator($models): iterable
    {
        if (!$models instanceof Enumerable) {
            $models = new LazyCollection($models);
        }

        return $models->map(static fn($model) => static::proxyFor($model));
    }

    /**
     * @inheritDoc
     */
    public static function wrap($modelOrModels)
    {
        if ($modelOrModels instanceof Model) {
            return static::proxyFor($modelOrModels);
        }

        if (is_null($modelOrModels)) {
            return null;
        }

        return static::iterator($modelOrModels);
    }

    /**
     * Proxy constructor.
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->model->{$key};
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set($key, $value)
    {
        $this->model->{$key} = $value;
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        $result = $this->forwardCallTo($this->model, $name, $arguments);

        if ($result === $this->model) {
            return $this;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getRouteKey()
    {
        return $this->model->getRouteKey();
    }

    /**
     * @inheritDoc
     */
    public function getRouteKeyName()
    {
        return $this->model->getRouteKeyName();
    }

    /**
     * @inheritDoc
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->model->resolveRouteBinding($value, $field);
    }

    /**
     * @inheritDoc
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        return $this->model->resolveChildRouteBinding($childType, $value, $field);
    }

    /**
     * @inheritDoc
     */
    public function wasCreated(): bool
    {
        return (bool) $this->model->wasRecentlyCreated;
    }

    /**
     * @inheritDoc
     */
    public function toBase(): Model
    {
        return $this->model;
    }

}
