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

use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Contracts\Schema\PolymorphicRelation;
use LaravelJsonApi\Eloquent\Contracts\Countable;
use LaravelJsonApi\Eloquent\Contracts\FillableToMany;
use LaravelJsonApi\Eloquent\Fields\Concerns\IsReadOnly;
use LaravelJsonApi\Eloquent\Polymorphism\MorphMany;
use LaravelJsonApi\Eloquent\Polymorphism\MorphValue;
use LogicException;
use Traversable;
use UnexpectedValueException;

class MorphToMany extends ToMany implements PolymorphicRelation, IteratorAggregate, FillableToMany
{
    use Polymorphic;
    use IsReadOnly;

    /**
     * @var array
     */
    private array $relations;

    /**
     * @param string $fieldName
     * @param array $relations
     * @return static
     */
    public static function make(string $fieldName, array $relations): self
    {
        return new self($fieldName, $relations);
    }

    /**
     * MorphMany constructor.
     *
     * @param string $fieldName
     * @param array $relations
     */
    public function __construct(string $fieldName, array $relations)
    {
        if (2 > count($relations)) {
            throw new InvalidArgumentException('Expecting morph-many to have more than one relation.');
        }

        parent::__construct($fieldName);
        $this->relations = $relations;
    }

    /**
     * @inheritDoc
     */
    public function inverseTypes(): array
    {
        return Collection::make($this->relations)
            ->map(fn(ToMany $relation) => $relation->inverse())
            ->all();
    }

    /**
     * @inheritDoc
     */
    public function withSchemas(Container $container): void
    {
        parent::withSchemas($container);

        foreach ($this as $relation) {
            $relation->withSchemas($container);
        }
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        foreach ($this->relations as $relation) {
            if ($relation instanceof self) {
                throw new UnexpectedValueException(
                    'Cannot have a JSON:API morph-to-many relation within a morph-to-many relation.'
                );
            }

            /** The countable setting on the child relationship must match the parent. */
            if ($relation instanceof Countable) {
                $relation->countable($this->isCountable());
            }

            if ($relation instanceof Relation) {
                yield $relation;
                continue;
            }

            throw new UnexpectedValueException(
                'JSON:API morph-to-many relation expects to receive JSON:API relation objects.'
            );
        }
    }

    /**
     * Get the value of the relationship from the supplied model.
     *
     * @param object $model
     * @return MorphMany
     */
    public function value(object $model): MorphMany
    {
        $values = new MorphMany();

        /** @var Relation $relation */
        foreach ($this as $relation) {
            $values->push(new MorphValue(
                $relation,
                $model->{$relation->relationName()}
            ));
        }

        return $values;
    }

    /**
     * @param object $model
     * @return int|null
     */
    public function count(object $model): ?int
    {
        $hasValue = false;
        $count = 0;

        foreach ($this as $relation) {
            if ($relation instanceof Countable) {
                $value = $model->{$relation->keyForCount()};

                if (null !== $value) {
                    $hasValue = true;
                    $count += intval($value);
                }
            }
        }

        return $hasValue ? $count : null;
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, array $identifiers): void
    {
        /** @var Relation|FillableToMany $relation */
        foreach ($this->fillable() as $relation) {
            $relation->fill($model, $this->identifiersFor($relation, $identifiers));
        }
    }

    /**
     * @inheritDoc
     */
    public function sync(Model $model, array $identifiers): iterable
    {
        $values = new MorphMany();

        /** @var Relation|FillableToMany $relation */
        foreach ($this->fillable() as $relation) {
            $synced = $relation->sync($model, $this->identifiersFor($relation, $identifiers));
            $values->push(new MorphValue($relation, $synced));
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    public function attach(Model $model, array $identifiers): iterable
    {
        $values = new MorphMany();

        /** @var Relation|FillableToMany $relation */
        foreach ($this->fillable() as $relation) {
            $attached = $relation->attach($model, $this->identifiersFor($relation, $identifiers));
            $values->push(new MorphValue($relation, $attached));
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    public function detach(Model $model, array $identifiers): iterable
    {
        $values = new MorphMany();

        /** @var Relation|FillableToMany $relation */
        foreach ($this->fillable() as $relation) {
            $detached = $relation->detach($model, $this->identifiersFor($relation, $identifiers));
            $values->push(new MorphValue($relation, $detached));
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    public function parse($models): iterable
    {
        /**
         * The MorphMany value object already takes care of parsing the models
         * it contains, so we can just return that. It is used as it is more efficient
         * at parsing because internally it holds the models with the relationship
         * that generated them (so it can use that relationship to parse the models
         * when it is iterating).
         *
         * If we didn't use it, we would have to traverse the models and work out which
         * relation to use to parse the models. Although this would be possible, it
         * is much more efficient to just store the values with the relationship that
         * generated them in the MorphMany value object.
         */
        if ($models instanceof MorphMany) {
            return $models;
        }

        throw new LogicException('Expecting model value to already be a morph many value.');
    }

    /**
     * Get the identifiers that are valid for the supplied relation.
     *
     * @param Relation $relation
     * @param array $identifiers
     * @return array
     */
    private function identifiersFor(Relation $relation, array $identifiers): array
    {
        $inverse = $relation->allInverse();

        return collect($identifiers)
            ->filter(fn(array $identifier) => in_array($identifier['type'], $inverse))
            ->all();
    }

    /**
     * @return Generator
     */
    private function fillable(): Generator
    {
        foreach ($this as $relation) {
            if ($relation instanceof FillableToMany) {
                yield $relation;
                continue;
            }

            throw new LogicException(sprintf(
                'Cannot modify morph-to-many relation %s because it contains relation %s that is not fillable.',
                $this->name(),
                $relation->relationName(),
            ));
        }
    }

}
