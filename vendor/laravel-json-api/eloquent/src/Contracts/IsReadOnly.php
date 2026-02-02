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

use Illuminate\Http\Request;

interface IsReadOnly
{
    /**
     * Is the field read-only?
     *
     * @param Request|null $request
     * @return bool
     */
    public function isReadOnly(?Request $request): bool;

    /**
     * Is the field not read-only.
     *
     * @param Request|null $request
     * @return bool
     */
    public function isNotReadOnly(?Request $request): bool;
}
