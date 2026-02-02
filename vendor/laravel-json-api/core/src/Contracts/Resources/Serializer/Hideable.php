<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Resources\Serializer;

use Illuminate\Http\Request;

interface Hideable
{

    /**
     * Is the field hidden?
     *
     * @param Request|null $request
     * @return bool
     */
    public function isHidden(?Request $request): bool;

    /**
     * Is the field not hidden?
     *
     * @param Request|null $request
     * @return bool
     */
    public function isNotHidden(?Request $request): bool;
}
