<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\QueryBuilder\EagerLoading;

use IteratorAggregate;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphTo;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Schema;
use LogicException;
use Traversable;

/**
 * Class EagerLoadPath
 *
 * @internal
 */
class EagerLoadPath implements IteratorAggregate
{

    /**
     * @var Relation[]
     */
    private array $relations;

    /**
     * @var bool
     */
    private bool $skipMissing = false;

    /**
     * Make new paths for the relation.
     *
     * @param Relation $relation
     * @return EagerLoadPath[]
     */
    public static function make(Relation $relation): array
    {
        if ($relation instanceof MorphToMany) {
            return self::makeMorphs($relation);
        }

        return [new self($relation)];
    }

    /**
     * Make polymorphic paths.
     *
     * @param MorphToMany $relation
     * @return EagerLoadPath[]
     */
    public static function makeMorphs(MorphToMany $relation): array
    {
        return array_map(function (Relation $relation) {
            $path = new self($relation);
            $path->skipMissing = true;
            return $path;
        }, iterator_to_array($relation));
    }

    /**
     * Path constructor.
     *
     * @param Relation ...$relations
     */
    public function __construct(Relation ...$relations)
    {
        $this->relations = $relations;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return collect($this->relations)
            ->map(fn(Relation $relation) => $relation->relationName())
            ->implode('.');
    }

    /**
     * Get the next path or paths.
     *
     * @param string $name
     * @return EagerLoadPath[]|null
     */
    public function next(string $name): ?array
    {
        if ($this->mustTerminate()) {
            return null;
        }

        $schema = $this->last()->schema();

        if ($this->skipMissing && !$schema->isRelationship($name)) {
            return null;
        }

        $relation = $schema->relationship($name);

        if (!$relation->isIncludePath()) {
            throw new LogicException(sprintf(
                'Unsupported include field %s at path %s.',
                $name,
                $this->toString()
            ));
        }

        if ($relation instanceof MorphToMany) {
            return $this->morphs($relation);
        }

        return [$this->push($relation)];
    }

    /**
     * @param MorphToMany $relation
     * @return array
     */
    public function morphs(MorphToMany $relation): array
    {
        return array_map(function (Relation $relation) {
            $path = $this->push($relation);
            $path->skipMissing = true;
            return $path;
        }, iterator_to_array($relation));
    }

    /**
     * @param Relation $relation
     * @return $this
     */
    public function push(Relation $relation): self
    {
        if ($relation instanceof MorphToMany) {
            throw new LogicException('Cannot push a morph-to-many relation.');
        }

        $copy = clone $this;
        $copy->relations[] = $relation;

        return $copy;
    }

    /**
     * Must the path be the end of the eager load path?
     *
     * We do not support eager loading beyond a MorphTo relation - this is because
     * a morph map needs to be used instead.
     *
     * @return bool
     */
    public function mustTerminate(): bool
    {
        return $this->last() instanceof MorphTo;
    }

    /**
     * @return Relation
     */
    public function last(): Relation
    {
        if (!empty($this->relations)) {
            return $this->relations[count($this->relations) - 1];
        }

        throw new LogicException('No relations.');
    }

    /**
     * Get default eager load paths.
     *
     * To work out the default eager load paths, we work our way down
     * the list of relations that make up this eager load path, yielding
     * the default eager loading settings for each schema in the path.
     *
     * When doing this, we ignore MorphTo relations as default eager loading
     * needs to be handled via the morph map.
     *
     * @return iterable
     */
    public function defaults(): iterable
    {
        $names = [];

        /** @var Relation $relation */
        foreach ($this as $relation) {
            /** Morph to relations must be dealt with via a morph map. */
            if ($relation instanceof MorphTo) {
                break;
            }

            $names[] = $relation->relationName();
            $schema = $relation->schema();

            if ($schema instanceof Schema) {
                foreach ($schema->with() as $default) {
                    yield implode('.', array_merge($names, [$default]));
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->relations;
    }

}
