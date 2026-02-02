<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Core\Resources;

use ArrayAccess;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Resources\Creatable;
use LaravelJsonApi\Contracts\Resources\JsonApiRelation;
use LaravelJsonApi\Contracts\Resources\Serializer\Attribute as SerializableAttribute;
use LaravelJsonApi\Contracts\Resources\Serializer\Relation as SerializableRelation;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Document\ResourceIdentifier;
use LaravelJsonApi\Core\Resources\Concerns\ConditionallyLoadsFields;
use LaravelJsonApi\Core\Resources\Concerns\DelegatesToResource;
use LaravelJsonApi\Core\Responses\Internal\ResourceResponse;
use LaravelJsonApi\Core\Schema\IdParser;
use LogicException;
use function sprintf;

class JsonApiResource implements ArrayAccess, Responsable
{

    use ConditionallyLoadsFields;
    use DelegatesToResource;

    /**
     * The model that the resource represents.
     *
     * @var object
     */
    public object $resource;

    /**
     * @var Schema
     */
    protected Schema $schema;

    /**
     * The resource type.
     *
     * @var string
     */
    protected string $type = '';

    /**
     * The resource id.
     *
     * @var string|null
     */
    protected ?string $id = null;

    /**
     * The resource's self URL.
     *
     * @var string|null
     */
    protected ?string $selfUri = null;

    /**
     * @var array
     */
    private static array $types = [];

    /**
     * JsonApiResource constructor.
     *
     * @param Schema $schema
     * @param object $resource
     */
    public function __construct(Schema $schema, object $resource)
    {
        $this->schema = $schema;
        $this->resource = $resource;
    }

    /**
     * Get the resource's `self` link URL.
     *
     * @return string|null
     */
    public function selfUrl(): ?string
    {
        if ($this->selfUri) {
            return $this->selfUri;
        }

        if (false === $this->schema->hasSelfLink()) {
            return null;
        }

        return $this->selfUri = $this->schema->url(
            $this->id(),
        );
    }

    /**
     * Get the `self` link for the resource.
     *
     * @return Link|null
     */
    public function selfLink(): ?Link
    {
        if ($url = $this->selfUrl()) {
            return new Link(
                'self',
                $url,
                $this->selfMeta()
            );
        }

        return null;
    }

    /**
     * Get the resource's type.
     *
     * @return string
     */
    public function type(): string
    {
        if (!empty($this->type)) {
            return $this->type;
        }

        return $this->type = $this->schema->type();
    }

    /**
     * Get the resource's id.
     *
     * @return string
     */
    public function id(): string
    {
        if ($this->id) {
            return $this->id;
        }

        return $this->id = IdParser::encoder($this->schema->id())
            ->encode($this->modelKey());
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     * @return iterable
     */
    public function attributes($request): iterable
    {
        foreach ($this->schema->attributes() as $attr) {
            if ($attr instanceof SerializableAttribute && $attr->isNotHidden($request)) {
                yield $attr->serializedFieldName() => $attr->serialize($this->resource);
            }
        }
    }

    /**
     * Get the resource's relationships.
     *
     * @param Request|null $request
     * @return iterable
     */
    public function relationships($request): iterable
    {
        foreach ($this->schema->relationships() as $relation) {
            if ($relation instanceof SerializableRelation && $relation->isNotHidden($request)) {
                yield $relation->serializedFieldName() => $this->serializeRelation($relation);
            }
        }
    }

    /**
     * Was the resource created in the current HTTP request?
     *
     * @return bool
     */
    public function wasCreated(): bool
    {
        if ($this->resource instanceof Creatable) {
            return $this->resource->wasCreated();
        }

        if (property_exists($this->resource, 'wasRecentlyCreated')) {
            return (bool) $this->resource->wasRecentlyCreated;
        }

        return false;
    }

    /**
     * Get the resource identifier for this resource.
     *
     * @return ResourceIdentifier
     */
    public function identifier(): ResourceIdentifier
    {
        return ResourceIdentifier::make($this->type(), $this->id())
            ->setMeta($this->identifierMeta());
    }

    /**
     * Get the resource's meta.
     *
     * @param Request|null $request
     * @return iterable
     */
    public function meta($request): iterable
    {
        return [];
    }

    /**
     * Get the resource's links.
     *
     * @param Request|null $request
     * @return Links
     */
    public function links($request): Links
    {
        $links = new Links();

        if ($self = $this->selfLink()) {
            $links->push($self);
        }

        return $links;
    }

    /**
     * Get a resource relation by name.
     *
     * When searching for a relationship by name, all relations will be checked
     * regardless of whether they are conditional fields.
     *
     * @param string $name
     * @return JsonApiRelation
     */
    public function relationship(string $name): JsonApiRelation
    {
        /** @var JsonApiRelation $relation */
        foreach (new RelationIterator($this) as $field => $relation) {
            if ($field === $name) {
                return $relation;
            }
        }

        throw new LogicException(sprintf(
            'Unknown relationship %s on resource %s: relationship does not exist or is hidden.',
            $name,
            $this->type()
        ));
    }

    /**
     * Prepare the resource to become an HTTP response.
     *
     * @param Request $request
     * @return ResourceResponse
     */
    public function prepareResponse($request): ResourceResponse
    {
        return new ResourceResponse($this);
    }

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this->prepareResponse($request)->toResponse($request);
    }

    /**
     * Get meta for the `self` link.
     *
     * @return array
     */
    protected function selfMeta(): array
    {
        return [];
    }

    /**
     * Get meta for the resource's identifier.
     *
     * @return array
     */
    protected function identifierMeta(): array
    {
        return [];
    }

    /**
     * Create a new resource relation.
     *
     * @param string $fieldName
     * @param string|null $keyName
     * @return Relation
     */
    protected function relation(string $fieldName, ?string $keyName = null): Relation
    {
        $field = $this->schema->isRelationship($fieldName) ? $this->schema->relationship($fieldName) : null;

        return new Relation(
            $this->resource,
            $this->selfUrl(),
            $fieldName,
            $keyName,
            $field ? $field->uriName() : null,
        );
    }

    /**
     * Serialize a relation.
     *
     * Child classes can overload this method to further customise the serialization of
     * the relationship.
     *
     * @param SerializableRelation $relation
     * @return JsonApiRelation
     */
    protected function serializeRelation(SerializableRelation $relation): JsonApiRelation
    {
        return $relation->serialize($this->resource, $this->selfUrl());
    }

    /**
     * Get the model key.
     *
     * @return string|int
     */
    private function modelKey()
    {
        if ($key = $this->schema->idKeyName()) {
            return $this->resource->{$key};
        }

        if ($this->resource instanceof UrlRoutable) {
            return $this->resource->getRouteKey();
        }

        throw new LogicException('Resource is not URL routable: you must implement the id method yourself.');
    }

}
