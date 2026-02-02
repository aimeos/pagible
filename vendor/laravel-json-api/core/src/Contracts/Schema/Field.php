<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Schema;

interface Field
{

    /**
     * The JSON:API field name.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Can the field be listed in sparse field sets?
     *
     * @return bool
     */
    public function isSparseField(): bool;
}
