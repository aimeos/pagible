<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

class RelationDocument extends Document
{

    /**
     * @var string
     */
    private string $resourceType;

    /**
     * @var string|null
     */
    private string $relation;

    /**
     * RelationDocument constructor.
     *
     * @param object $document
     * @param string $resourceType
     * @param string $relation
     */
    public function __construct(object $document, string $resourceType, string $relation)
    {
        parent::__construct($document);
        $this->resourceType = $resourceType;
        $this->relation = $relation;
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return $this->resourceType;
    }

    /**
     * @return string
     */
    public function relation(): string
    {
        return $this->relation;
    }

}
