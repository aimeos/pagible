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
use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Core\Responses\Concerns\HasEncodingParameters;
use LaravelJsonApi\Core\Responses\Concerns\HasRelationship;
use LaravelJsonApi\Core\Responses\Concerns\IsResponsable;

class ResourceIdentifierCollectionResponse implements Responsable
{
    use HasEncodingParameters;
    use HasRelationship;
    use IsResponsable;

    /**
     * @var iterable
     */
    private iterable $related;

    /**
     * ResourceIdentifierResponse constructor.
     *
     * @param JsonApiResource $resource
     * @param string $fieldName
     * @param iterable $related
     */
    public function __construct(JsonApiResource $resource, string $fieldName, iterable $related)
    {
        $this->resource = $resource;
        $this->fieldName = $fieldName;
        $this->related = $related;
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
            ->withToMany($this->resource, $this->fieldName, $this->related)
            ->withJsonApi($this->jsonApi())
            ->withMeta($this->allMeta())
            ->withLinks($this->allLinks())
            ->toJson($this->encodeOptions);

        return new Response(
            $document,
            Response::HTTP_OK,
            $this->headers()
        );
    }
}
