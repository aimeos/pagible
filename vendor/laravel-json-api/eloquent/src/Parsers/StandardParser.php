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
use Illuminate\Support\LazyCollection;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Eloquent\Contracts\Parser;

class StandardParser implements Parser
{

    /**
     * @inheritDoc
     */
    public function parseOne(Model $model): object
    {
        return $model;
    }

    /**
     * @inheritDoc
     */
    public function parseNullable(?Model $model): ?object
    {
        return $model;
    }

    /**
     * @inheritDoc
     */
    public function parseMany($models): iterable
    {
        if (is_iterable($models)) {
            return $models;
        }

        return new LazyCollection($models);
    }

    /**
     * @inheritDoc
     */
    public function parsePage(Page $page): Page
    {
        return $page;
    }

}
