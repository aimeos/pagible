<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Query;

use LaravelJsonApi\Core\Query\FieldSets;
use LaravelJsonApi\Core\Query\FilterParameters;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Core\Query\SortFields;

interface QueryParameters
{

    /**
     * Get the JSON:API include paths.
     *
     * @return IncludePaths|null
     */
    public function includePaths(): ?IncludePaths;

    /**
     * Get the JSON:API sparse field sets.
     *
     * @return FieldSets|null
     */
    public function sparseFieldSets(): ?FieldSets;

    /**
     * Get the JSON:API sort fields.
     *
     * @return SortFields|null
     */
    public function sortFields(): ?SortFields;

    /**
     * Get the JSON:API page parameters.
     *
     * @return array|null
     */
    public function page(): ?array;

    /**
     * Get the JSON:API filter parameters.
     *
     * @return FilterParameters|null
     */
    public function filter(): ?FilterParameters;

    /**
     * Get query parameters that are not defined in the JSON:API specification.
     *
     * @return array
     */
    public function unrecognisedParameters(): array;

}
