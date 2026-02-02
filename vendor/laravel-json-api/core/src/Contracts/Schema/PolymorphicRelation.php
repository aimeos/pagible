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

interface PolymorphicRelation extends Relation
{

    /**
     * Get the inverse resource types for a polymorphic relation.
     *
     * @return string[]
     */
    public function inverseTypes(): array;
}
