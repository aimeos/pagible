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

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use InvalidArgumentException;
use LaravelJsonApi\Eloquent\Contracts\FillableToMany;
use LaravelJsonApi\Eloquent\Fields\Concerns\IsReadOnly;
use function sprintf;

class BelongsToMany extends ToMany implements FillableToMany
{

    use IsReadOnly;

    /**
     * Create a belongs-to-many relation.
     *
     * @param string $fieldName
     * @param string|null $relation
     * @return BelongsToMany
     */
    public static function make(string $fieldName, ?string $relation = null): BelongsToMany
    {
        return new self($fieldName, $relation);
    }

    /**
     * @var callable|array|null
     */
    private $pivot;

    /**
     * Set the values or callback to use for pivot attributes.
     *
     * @param callable|array|null $pivot
     * @return $this
     */
    public function fields($pivot): self
    {
        if (!is_array($pivot) && !is_callable($pivot)) {
            throw new InvalidArgumentException('Expecting an array or a callable value.');
        }

        $this->pivot = $pivot;

        return $this;
    }

    /**
     * @return iterable
     */
    public function filters(): iterable
    {
        foreach (parent::filters() as $filter) {
            yield $filter;
        }

        if (is_object($this->pivot) && method_exists($this->pivot, 'filters')) {
            foreach ($this->pivot->filters() as $filter) {
                yield $filter;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, array $identifiers): void
    {
        $this->sync($model, $identifiers);
    }

    /**
     * @inheritDoc
     */
    public function sync(Model $model, array $identifiers): iterable
    {
        $related = $this->findMany($identifiers);
        $relation = $this->getRelation($model);

        $relation->sync($this->formatAttachRecords(
            $model,
            $relation,
            $related
        ));

        $model->setRelation($this->relationName(), $related);

        return $related;
    }

    /**
     * @inheritDoc
     */
    public function attach(Model $model, array $identifiers): iterable
    {
        $related = $this->findMany($identifiers);
        $relation = $this->getRelation($model);

        /**
         * Note that the spec says that duplicates MUST NOT be added. The default Laravel
         * behaviour for `saveMany` is to add duplicates, therefore we need to do some
         * work to ensure that we only add the records that are not already in the relationship.
         */
        $existing = $relation
            ->whereKey($related->modelKeys())
            ->get();

        $relation->attach($this->formatAttachRecords(
            $model,
            $relation,
            $related->diff($existing)
        ));

        $model->unsetRelation($this->relationName());

        return $related;
    }

    /**
     * @inheritDoc
     */
    public function detach(Model $model, array $identifiers): iterable
    {
        $related = $this->findMany($identifiers);

        $this->getRelation($model)->detach($related);
        $model->unsetRelation($this->relationName());

        return $related;
    }

    /**
     * @param Model $model
     * @return EloquentBelongsToMany
     */
    private function getRelation(Model $model): EloquentBelongsToMany
    {
        $name = $this->relationName();

        assert(method_exists($model, $name) || $model->relationResolver($model::class, $name), sprintf(
            'Expecting method %s to exist on model %s.',
            $name,
            $model::class,
        ));

        $relation = $model->{$name}();

        assert($relation instanceof EloquentBelongsToMany, sprintf(
            'Expecting method %s on model %s to return a belongs-to-many relation.',
            $name,
            $model::class,
        ));

        return $relation;
    }

    /**
     * @param Model $parent
     * @param EloquentBelongsToMany $relation
     * @param EloquentCollection $models
     * @return array
     */
    private function formatAttachRecords(
        Model $parent,
        EloquentBelongsToMany $relation,
        EloquentCollection $models
    ): array
    {
        return $models
            ->keyBy(static fn($related) => $related->{$relation->getRelatedKeyName()})
            ->map(fn($related) => $this->getPivotAttributes($parent, $related))
            ->all();
    }

    /**
     * @param Model $parent
     * @param Model $related
     * @return array
     */
    private function getPivotAttributes(Model $parent, Model $related): array
    {
        if (is_array($this->pivot)) {
            return $this->pivot;
        }

        if (is_callable($this->pivot)) {
            return ($this->pivot)($parent, $related);
        }

        return [];
    }
}
