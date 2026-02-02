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
use LaravelJsonApi\Eloquent\Contracts\Parser;
use LaravelJsonApi\Eloquent\Contracts\Proxy as ProxyContract;
use LaravelJsonApi\Eloquent\Parsers\ProxyParser;

abstract class ProxySchema extends Schema
{

    /**
     * @return Parser
     */
    public function parser(): Parser
    {
        if ($this->parser) {
            return $this->parser;
        }

        return $this->parser = new ProxyParser($this->newProxy());
    }

    /**
     * @inheritDoc
     */
    public function newInstance(): Model
    {
        return $this->newProxy()->toBase();
    }

    /**
     * @inheritDoc
     */
    public function isModel($model): bool
    {
        $expected = get_class($this->newInstance());

        return ($model instanceof $expected) || $expected === $model;
    }

    /**
     * Create a new proxy.
     *
     * @param Model|null $model
     * @return ProxyContract
     */
    public function newProxy(?Model $model = null): ProxyContract
    {
        $proxyClass = $this->model();

        return new $proxyClass($model);
    }
}
