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

use Illuminate\Contracts\Pagination\Paginator;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Pagination\Page as PageContract;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Core\Pagination\Page;
use LaravelJsonApi\Core\Resources\ResourceCollection;

class PaginatedResourceResponse extends ResourceCollectionResponse
{

    /**
     * @var PageContract
     */
    private PageContract $page;

    /**
     * PaginatedResourceResponse constructor.
     *
     * @param PageContract|Paginator|ResourceCollection $resources
     */
    public function __construct($resources)
    {
        if ($resources instanceof PageContract) {
            $this->page = $resources;
            $resources = new ResourceCollection($resources);
        } else if ($resources instanceof Paginator) {
            $this->page = new Page($resources);
            $resources = new ResourceCollection($resources);
        } else if ($resources instanceof ResourceCollection && $resources->resources instanceof PageContract) {
            $this->page = $resources->resources;
        } else {
            throw new InvalidArgumentException('Expecting a page or a resource collection that contains a page.');
        }

        parent::__construct($resources);
    }

    /**
     * @return Hash
     */
    public function meta(): Hash
    {
        return Hash::cast($this->page->meta())->merge(
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
