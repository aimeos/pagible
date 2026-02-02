<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx\Schema;

use LaravelJsonApi\Contracts\Resources\Container;
use LaravelJsonApi\Contracts\Resources\JsonApiRelation;
use LaravelJsonApi\Core\Document\ResourceIdentifier;
use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Encoder\Neomerx\Mapper;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\IdentifierInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaInterface;
use function is_null;

/**
 * Class Relation
 *
 * @internal
 */
final class Relation
{

    /**
     * @var Container
     */
    private Container $container;

    /**
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * @var JsonApiRelation
     */
    private JsonApiRelation $relation;

    /**
     * @var SchemaFields
     */
    private SchemaFields $fields;

    /**
     * @var ContextInterface
     */
    private ContextInterface $context;

    /**
     * Relation constructor.
     *
     * @param Container $container
     * @param Mapper $mapper
     * @param JsonApiRelation $object
     * @param SchemaFields $fields
     * @param ContextInterface $context
     */
    public function __construct(
        Container $container,
        Mapper $mapper,
        JsonApiRelation $object,
        SchemaFields $fields,
        ContextInterface $context
    ) {
        $this->container = $container;
        $this->mapper = $mapper;
        $this->relation = $object;
        $this->fields = $fields;
        $this->context = $context;
    }

    /**
     * @return JsonApiResource|IdentifierInterface|iterable|null
     */
    public function data()
    {
        $data = $this->relation->data();

        if ($data instanceof JsonApiResource || is_null($data)) {
            return $data;
        }

        if ($data instanceof ResourceIdentifier) {
            return $this->mapper->identifier($data);
        }

        return $this->container->resolve($data);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $relation = [];
        $links = $this->relation->links();
        $meta = $this->relation->meta();

        if ($this->willShowData()) {
            $relation[SchemaInterface::RELATIONSHIP_DATA] = $this->data();
        }

        if ($links->isNotEmpty()) {
            $relation[SchemaInterface::RELATIONSHIP_LINKS] = $this->mapper->allLinks(
                $this->relation->links()
            );
        }

        if (!empty($meta)) {
            $relation[SchemaInterface::RELATIONSHIP_META] = $meta;
        }

        return $relation;
    }

    /**
     * @return bool
     */
    private function willShowData(): bool
    {
        if ($this->relation->showData()) {
            return true;
        }

        return $this->fields->isRelationshipRequested(
            $this->context->getPosition()->getPath(),
            $this->relation->fieldName(),
        );
    }

}
