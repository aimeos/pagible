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

use LaravelJsonApi\Spec\Values\Identifier;

class ResourceDocument extends Document
{

    /**
     * @var string
     */
    private string $resourceType;

    /**
     * @var string|null
     */
    private ?string $resourceId;

    /**
     * Document constructor.
     *
     * @param object $document
     * @param string $resourceType
     * @param string|null $resourceId
     */
    public function __construct(object $document, string $resourceType, ?string $resourceId)
    {
        parent::__construct($document);
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
    }


    /**
     * Get the document's expected resource type.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->resourceType;
    }

    /**
     * Get the document's expected resource id.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->resourceId;
    }

    /**
     * @return bool
     */
    public function expectsId(): bool
    {
        return !Identifier::idIsEmpty($this->resourceId);
    }

}
