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

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Core\Resources\ResourceCollection;
use LaravelJsonApi\Core\Responses\Concerns\HasEncodingParameters;
use LaravelJsonApi\Core\Responses\Concerns\IsResponsable;

class ResourceCollectionResponse implements Responsable
{

    use HasEncodingParameters;
    use IsResponsable;

    /**
     * @var ResourceCollection
     */
    private ResourceCollection $resources;

    /**
     * ResourceCollectionResponse constructor.
     *
     * @param ResourceCollection $resources
     */
    public function __construct(ResourceCollection $resources)
    {
        $this->resources = $resources;
    }

    /**
     * @return Links
     */
    public function links(): Links
    {
        return $this->resources->links()->merge(
            $this->links ?: new Links()
        );
    }

    /**
     * @return Hash
     */
    public function meta(): Hash
    {
        return Hash::cast($this->resources->meta())->merge(
            $this->meta ?: []
        );
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function toResponse($request)
    {
        $encoder = $this->server()->encoder();

        $document = $encoder
            ->withRequest($request)
            ->withIncludePaths($this->includePaths($request))
            ->withFieldSets($this->sparseFieldSets($request))
            ->withResources($this->resources)
            ->withJsonApi($this->jsonApi())
            ->withMeta($this->meta())
            ->withLinks($this->links())
            ->toJson($this->encodeOptions);

        return new Response(
            $document,
            Response::HTTP_OK,
            $this->headers()
        );
    }

}
