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

class RelationBuilder extends Builder
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
     * @param string $relation
     * @return $this
     */
    public function expects(string $resourceType, string $relation): self
    {
        $this->expectedType = $resourceType;
        $this->expectedId = $relation;

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function create(object $json): Document
    {
        if ($this->expectedType) {
            return new RelationDocument(
                $json,
                $this->expectedType,
                $this->expectedId
            );
        }

        throw new LogicException('No expected resource type and relation name set.');
    }

    /**
     * @inheritDoc
     */
    protected function pipes(): array
    {
        return [
            Validators\RelationValidator::class,
        ];
    }
}
