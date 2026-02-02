<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Contracts;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Resources\Creatable;

interface Proxy extends Creatable, UrlRoutable
{

    /**
     * Create a new proxy for the supplied model.
     *
     * @param Model $model
     * @return Proxy
     */
    public static function proxyFor(Model $model): Proxy;

    /**
     * Create a nullable proxy for the supplied model.
     *
     * @param Model|null $model
     * @return Proxy|null
     */
    public static function nullable(?Model $model): ?Proxy;

    /**
     * Create an iterator of proxies.
     *
     * @param mixed $models
     * @return iterable
     */
    public static function iterator($models): iterable;

    /**
     * Wrap the provided model or models.
     *
     * @param Model|mixed|null $modelOrModels
     * @return mixed
     */
    public static function wrap($modelOrModels);

    /**
     * Get the model the proxy is wrapping.
     *
     * @return Model
     */
    public function toBase(): Model;
}
