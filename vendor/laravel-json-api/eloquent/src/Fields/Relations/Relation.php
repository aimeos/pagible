<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields\Relations;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Resources\JsonApiRelation;
use LaravelJsonApi\Contracts\Resources\Serializer\Relation as SerializableContract;
use LaravelJsonApi\Contracts\Schema\PolymorphicRelation;
use LaravelJsonApi\Contracts\Schema\Relation as RelationContract;
use LaravelJsonApi\Contracts\Schema\SchemaAware as SchemaAwareContract;
use LaravelJsonApi\Core\Schema\Concerns\EagerLoadable;
use LaravelJsonApi\Core\Schema\Concerns\Filterable;
use LaravelJsonApi\Core\Schema\Concerns\RequiredForValidation;
use LaravelJsonApi\Core\Schema\Concerns\SchemaAware;
use LaravelJsonApi\Core\Schema\Concerns\SparseField;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Fields\Concerns\Hideable;
use LaravelJsonApi\Eloquent\QueryBuilder\JsonApiBuilder;
use LaravelJsonApi\Eloquent\Resources\Relation as ResourceRelation;
use LaravelJsonApi\Eloquent\Schema;
use LogicException;
use function sprintf;

abstract class Relation implements RelationContract, SchemaAwareContract, SerializableContract
{

    use EagerLoadable;
    use Filterable;
    use Hideable;
    use RequiredForValidation;
    use SchemaAware;
    use SparseField;

    /**
     * The JSON API field name.
     *
     * @var string
     */
    private string $name;

    /**
     * The name of the relation on the model.
     *
     * @var string|null
     */
    private ?string $relation;

    /**
     * The inverse JSON API resource type.
     *
     * @var string|null
     */
    private ?string $inverse = null;

    /**
     * The name of the field as it appears in a URI.
     *
     * @var string|null
     */
    private ?string $uriName = null;

    /**
     * @var Closure|null
     */
    private ?Closure $serializer = null;

    /**
     * Guess the inverse resource type.
     *
     * @return string
     */
    abstract protected function guessInverse(): string;

    /**
     * Relation constructor.
     *
     * @param string $fieldName
     * @param string|null $relation
     */
    public function __construct(string $fieldName, ?string $relation = null)
    {
        $this->name = $fieldName;
        $this->relation = $relation;
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function serializedFieldName(): string
    {
        return $this->name();
    }

    /**
     * Get the name of the relation on the model.
     *
     * @return string
     */
    public function relationName(): string
    {
        if ($this->relation) {
            return $this->relation;
        }

        return $this->relation = $this->guessRelationName();
    }

    /**
     * Set the inverse resource type.
     *
     * @param string $resourceType
     * @return $this
     */
    public function type(string $resourceType): self
    {
        if (empty($resourceType)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->inverse = $resourceType;

        return $this;
    }

    /**
     * Set the inverse resource type.
     *
     * @param string $resourceType
     * @return $this
     * @deprecated 1.0-stable use `type()` instead.
     */
    public function inverseType(string $resourceType): self
    {
        return $this->type($resourceType);
    }

    /**
     * @inheritDoc
     */
    public function inverse(): string
    {
        if ($this->inverse) {
            return $this->inverse;
        }

        return $this->inverse = $this->guessInverse();
    }

    /**
     * @inheritDoc
     */
    public function allInverse(): array
    {
        if ($this instanceof PolymorphicRelation) {
            return $this->inverseTypes();
        }

        return [$this->inverse()];
    }

    /**
     * @inheritDoc
     */
    public function uriName(): string
    {
        if ($this->uriName) {
            return $this->uriName;
        }

        return $this->uriName = $this->guessUriName();
    }

    /**
     * Use the field-name as-is for relationship URLs.
     *
     * @return $this
     */
    public function retainFieldName(): self
    {
        $this->uriName = $this->name();

        return $this;
    }

    /**
     * Use the provided string as the URI fragment for the field name.
     *
     * @param string $uri
     * @return $this
     */
    public function withUriFieldName(string $uri): self
    {
        if (!empty($uri)) {
            $this->uriName = $uri;
            return $this;
        }

        throw new InvalidArgumentException('Expecting a non-empty string URI fragment.');
    }

    /**
     * Get the schema for the inverse resource type.
     *
     * @return Schema
     */
    public function schema(): Schema
    {
        $schema = $this->schemas()->schemaFor(
            $this->inverse()
        );

        if ($schema instanceof Schema) {
            return $schema;
        }

        throw new LogicException(sprintf(
            'Expecting inverse schema for resource type %s to be an Eloquent schema.',
            $this->inverse()
        ));
    }

    /**
     * @inheritDoc
     */
    public function toMany(): bool
    {
        return !$this->toOne();
    }

    /**
     * @param Closure $serializer
     * @return $this
     */
    public function serializeUsing(Closure $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function serialize(object $model, ?string $baseUri): JsonApiRelation
    {
        $relation = new ResourceRelation(
            $model,
            $baseUri,
            $this,
        );

        if ($this->serializer) {
            ($this->serializer)($relation);
        }

        return $relation;
    }

    /**
     * @param Builder|EloquentRelation $relation
     * @return JsonApiBuilder
     */
    public function newQuery($relation): JsonApiBuilder
    {
        return new JsonApiBuilder(
            $this->schemas(),
            $this->schema(),
            $relation,
            $this,
        );
    }

    /**
     * @param string $type
     * @return void
     */
    protected function assertInverseType(string $type): void
    {
        $expected = $this->allInverse();

        if (!in_array($type, $expected, true)) {
            throw new LogicException(sprintf(
                'Resource type %s is not a valid inverse resource type for relation %s: expecting %s.',
                $type,
                $this->name(),
                implode(', ', $expected),
            ));
        }
    }

    /**
     * Guess the relation name on the model.
     *
     * @return string
     */
    private function guessRelationName(): string
    {
        return Str::camel($this->name());
    }

    /**
     * Guess the field name as it appears in a URI.
     *
     * @return string
     */
    private function guessUriName(): string
    {
        return Str::dasherize($this->name());
    }
}
