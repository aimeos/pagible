<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Facades;

use Illuminate\Support\Facades\Facade;
use LaravelJsonApi\Contracts\Routing\Route;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Core\JsonApiService;

/**
 * Class JsonApi
 *
 * @method static Route route()
 * @method static Server server(string $name = null)
 * @method static Server|null serverIfExists()
 */
class JsonApi extends Facade
{

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return JsonApiService::class;
    }
}
