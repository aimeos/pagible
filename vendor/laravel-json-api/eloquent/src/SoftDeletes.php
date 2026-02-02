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

use LaravelJsonApi\Eloquent\Contracts\Driver;
use LaravelJsonApi\Eloquent\Drivers\SoftDeleteDriver;

trait SoftDeletes
{

    /**
     * @return Driver
     */
    protected function driver(): Driver
    {
        return new SoftDeleteDriver($this->newInstance());
    }
}
