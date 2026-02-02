<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Parsers;

use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Eloquent\Contracts\Parser;
use LaravelJsonApi\Eloquent\Contracts\Proxy;
use LaravelJsonApi\Eloquent\Pagination\ProxyPage;

class ProxyParser implements Parser
{

    /**
     * @var Proxy
     */
    private Proxy $proxy;

    /**
     * ProxyParser constructor.
     *
     * @param Proxy $proxy
     */
    public function __construct(Proxy $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * @inheritDoc
     */
    public function parseOne(Model $model): object
    {
        return $this->proxy->proxyFor($model);
    }

    /**
     * @inheritDoc
     */
    public function parseNullable(?Model $model): ?object
    {
        return $model ? $this->parseOne($model) : null;
    }

    /**
     * @inheritDoc
     */
    public function parseMany($models): iterable
    {
        return $this->proxy->iterator($models);
    }

    /**
     * @inheritDoc
     */
    public function parsePage(Page $page): Page
    {
        return new ProxyPage($page, $this->proxy);
    }

}
