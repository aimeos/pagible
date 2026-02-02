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

use Illuminate\Http\Request;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Resources\Container;
use LaravelJsonApi\Core\Resources\ConditionalIterator;
use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Encoder\Neomerx\Mapper;
use LogicException;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Contracts\Schema\LinkInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaInterface;
use UnexpectedValueException;
use function sprintf;

/**
 * Class Schema
 *
 * @internal
 */
final class Schema implements SchemaInterface
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
     * @var SchemaFields
     */
    private SchemaFields $fields;

    /**
     * @var string
     */
    private string $type;

    /**
     * @var Request|null
     */
    private $request;

    /**
     * Schema constructor.
     *
     * @param Container $container
     * @param Mapper $mapper
     * @param SchemaFields $fields
     * @param string $type
     * @param Request|null $request
     */
    public function __construct(
        Container $container,
        Mapper $mapper,
        SchemaFields $fields,
        string $type,
        $request
    ) {
        if (empty($type)) {
            throw new InvalidArgumentException('Expecting a non-empty resource type.');
        }

        $this->container = $container;
        $this->mapper = $mapper;
        $this->fields = $fields;
        $this->type = $type;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getId($resource): ?string
    {
        if ($resource instanceof JsonApiResource) {
            return $resource->id();
        }

        throw new UnexpectedValueException('Expecting a resource object.');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        if ($resource instanceof JsonApiResource) {
            return new ConditionalIterator($resource->attributes($this->request));
        }

        throw new UnexpectedValueException('Expecting a resource object.');
    }

    /**
     * @inheritDoc
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        return new Relationships(
            $this->container,
            $this->mapper,
            $resource,
            $this->fields,
            $context,
            $this->request,
        );
    }

    /**
     * @inheritDoc
     */
    public function getSelfLink($resource): LinkInterface
    {
        if (!$resource instanceof JsonApiResource) {
            throw new UnexpectedValueException('Expecting a resource object.');
        }

        if ($link = $resource->links($this->request)->get('self')) {
            return $this->mapper->link($link);
        }

        throw new LogicException(sprintf(
            'Resource object %s does not have a self link.',
            $resource->type()
        ));
    }

    /**
     * @inheritDoc
     */
    public function getLinks($resource): iterable
    {
        if ($resource instanceof JsonApiResource) {
            return $this->mapper->links($resource->links($this->request));
        }

        throw new UnexpectedValueException('Expecting a resource object.');
    }

    /**
     * @inheritDoc
     */
    public function getRelationshipSelfLink($resource, string $name): LinkInterface
    {
        if (!$resource instanceof JsonApiResource) {
            throw new UnexpectedValueException('Expecting a resource object.');
        }

        if ($link = $resource->relationship($name)->links()->get('self')) {
            return $this->mapper->link($link);
        }

        throw new LogicException(sprintf(
            'Relation %s on resource object %s does not have a self link.',
            $name,
            $resource->type()
        ));
    }

    /**
     * @inheritDoc
     */
    public function getRelationshipRelatedLink($resource, string $name): LinkInterface
    {
        if (!$resource instanceof JsonApiResource) {
            throw new UnexpectedValueException('Expecting a resource object.');
        }

        if ($link = $resource->relationship($name)->links()->get('related')) {
            return $this->mapper->link($link);
        }

        throw new LogicException(sprintf(
            'Relation %s on resource object %s does not have a related link.',
            $name,
            $resource->type()
        ));
    }

    /**
     * @inheritDoc
     */
    public function hasIdentifierMeta($resource): bool
    {
        throw new LogicException('Expecting parser to skip Schema::hasIdentifierMeta.');
    }

    /**
     * @inheritDoc
     */
    public function getIdentifierMeta($resource)
    {
        if ($resource instanceof JsonApiResource) {
            return $resource->identifier()->meta();
        }

        throw new UnexpectedValueException('Expecting a resource object.');
    }

    /**
     * @inheritDoc
     */
    public function hasResourceMeta($resource): bool
    {
        throw new LogicException('Expecting parser to skip Schema::hasResourceMeta.');
    }

    /**
     * @inheritDoc
     */
    public function getResourceMeta($resource)
    {
        if ($resource instanceof JsonApiResource) {
            return new ConditionalIterator($resource->meta($this->request));
        }

        throw new UnexpectedValueException('Expecting a resource object.');
    }

    /**
     * @inheritDoc
     */
    public function isAddSelfLinkInRelationshipByDefault(string $relationshipName): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isAddRelatedLinkInRelationshipByDefault(string $relationshipName): bool
    {
        return false;
    }

}
