<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Store;

interface ToManyBuilder extends Builder
{

    /**
     * Completely replace every member of the relationship with the specified members.
     *
     * @param array $identifiers
     * @return iterable
     *      the related models that were used to replace the relationship.
     */
    public function sync(array $identifiers): iterable;

    /**
     * Add the specified members to the relationship unless they are already present.
     *
     * @param array $identifiers
     * @return iterable
     *      the related models that were added to the relationship.
     */
    public function attach(array $identifiers): iterable;

    /**
     * Delete the specified members from the relationship.
     *
     * @param array $identifiers
     * @return iterable
     *      the related models that were removed from the relationship.
     */
    public function detach(array $identifiers): iterable;
}
