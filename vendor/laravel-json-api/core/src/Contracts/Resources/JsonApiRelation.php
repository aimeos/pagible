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

use LaravelJsonApi\Core\Document\Links;

interface JsonApiRelation
{

    /**
     * Get the relation's JSON:API field name.
     *
     * @return string
     */
    public function fieldName(): string;

    /**
     * Get the value of the links member.
     *
     * @return Links
     */
    public function links(): Links;

    /**
     * Get the value of the meta member.
     *
     * @return array|null
     */
    public function meta(): ?array;

    /**
     * Get the value of the data member.
     *
     * @return mixed
     */
    public function data();

    /**
     * Should data always be shown?
     *
     * If this method returns `false`, the relationship's data will only be shown
     * if the client has requested it (via include paths). Returning `true` means
     * the relationship data will always be shown, regardless of what the client
     * has requested.
     *
     * @return bool
     */
    public function showData(): bool;
}
