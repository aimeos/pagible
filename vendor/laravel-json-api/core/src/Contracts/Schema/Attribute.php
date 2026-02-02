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

interface Attribute extends Field
{

    /**
     * Is the attribute sortable?
     *
     * @return bool
     */
    public function isSortable(): bool;
}
