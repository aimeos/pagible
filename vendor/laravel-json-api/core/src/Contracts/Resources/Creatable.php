<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Resources;

interface Creatable
{

    /**
     * Was the resource created in the current HTTP request?
     *
     * @return bool
     */
    public function wasCreated(): bool;
}
