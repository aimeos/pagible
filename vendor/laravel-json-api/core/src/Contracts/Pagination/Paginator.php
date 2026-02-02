<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Pagination;

interface Paginator
{

    /**
     * Get the keys expected in the `page` query parameter for this paginator.
     *
     * @return array
     */
    public function keys(): array;
}
