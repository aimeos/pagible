<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

interface Serializable extends Arrayable, JsonSerializable, Jsonable
{

    /**
     * Serialize to a human-readable (pretty-printed) JSON string.
     *
     * @return string
     */
    public function toString(): string;
}
