<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Responses\Internal;

use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Core\Resources\JsonApiResource;

class PaginatedRelatedResourceResponse extends RelatedResourceCollectionResponse
{

    /**
     * @var Page
     */
    private Page $page;

    /**
     * PaginatedIdentifierResponse constructor.
     *
     * @param JsonApiResource $resource
     * @param string $fieldName
     * @param Page $related
     */
    public function __construct(JsonApiResource $resource, string $fieldName, Page $related)
    {
        parent::__construct($resource, $fieldName, $related);
        $this->page = $related;
    }

    /**
     * @return Hash
     */
    public function meta(): Hash
    {
        return Hash::cast($this->page->meta())->merge(
            Hash::cast(parent::metaForRelationship())
        )->merge(
            parent::meta()
        );
    }

    /**
     * @return Links
     */
    public function links(): Links
    {
        return $this->page->links()->merge(
            parent::links()
        );
    }
}
