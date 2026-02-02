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

use LogicException;

class ResourceBuilder extends Builder
{

    /**
     * @var string|null
     */
    private ?string $expectedType = null;

    /**
     * @var string|null
     */
    private ?string $expectedId = null;

    /**
     * Expect the document to be the provided resource type and id.
     *
     * @param string $resourceType
     * @param string|null $resourceId
     * @return $this
     */
    public function expects(string $resourceType, ?string $resourceId): self
    {
        $this->expectedType = $resourceType;
        $this->expectedId = $resourceId;

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function create(object $json): Document
    {
        if ($this->expectedType) {
            return new ResourceDocument(
                $json,
                $this->expectedType,
                $this->expectedId
            );
        }

        throw new LogicException('No expected resource type set.');
    }

    /**
     * @inheritDoc
     */
    protected function pipes(): array
    {
        return [
            Validators\DataValidator::class,
            Validators\TypeValidator::class,
            Validators\ClientIdValidator::class,
            Validators\IdValidator::class,
            Validators\FieldsValidator::class,
            Validators\AttributesValidator::class,
            Validators\RelationshipsValidator::class,
        ];
    }

}
