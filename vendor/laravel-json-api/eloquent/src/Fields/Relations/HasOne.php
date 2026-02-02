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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne as EloquentMorphOne;
use LaravelJsonApi\Eloquent\Contracts\FillableToOne;
use LaravelJsonApi\Eloquent\Fields\Concerns\IsReadOnly;

class HasOne extends ToOne implements FillableToOne
{
    /** @var int */
    private const KEEP_DETACHED_MODEL = 0;

    /** @var int */
    private const DELETE_DETACHED_MODEL = 1;

    /** @var int */
    private const FORCE_DELETE_DETACHED_MODEL = 2;

    use IsReadOnly;

    /**
     * Flag for how to detach a model from the relationship.
     *
     * @var int
     */
    private int $detachMode = self::KEEP_DETACHED_MODEL;

    /**
     * Create a has-one relation.
     *
     * @param string $fieldName
     * @param string|null $relation
     * @return HasOne
     */
    public static function make(string $fieldName, ?string $relation = null): HasOne
    {
        return new self($fieldName, $relation);
    }

    /**
     * Keep a detached model by setting the inverse relationship column(s) to `null`.
     *
     * @return $this
     */
    public function keepDetachedModel(): self
    {
        $this->detachMode = self::KEEP_DETACHED_MODEL;

        return $this;
    }

    /**
     * Delete a detached model using the `Model::delete()` method.
     *
     * @return $this
     */
    public function deleteDetachedModel(): self
    {
        $this->detachMode = self::DELETE_DETACHED_MODEL;

        return $this;
    }

    /**
     * Force delete a detached model using the `Model::forceDelete()` method.
     *
     * @return $this
     */
    public function forceDeleteDetachedModel(): self
    {
        $this->detachMode = self::FORCE_DELETE_DETACHED_MODEL;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function mustExist(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, ?array $identifier): void
    {
        $name = $this->relationName();

        assert(method_exists($model, $name) || $model->relationResolver($model::class, $name), sprintf(
            'Expecting method %s to exist on model %s.',
            $name,
            $model::class,
        ));

        $relation = $model->{$name}();

        assert($relation instanceof EloquentHasOne || $relation instanceof EloquentMorphOne, sprintf(
            'Expecting method %s on model %s to return a belongs-to-many relation.',
            $name,
            $model::class,
        ));

        /** @var Model|null $current */
        $current = $model->{$name};
        $related = $this->find($identifier);

        if ($this->willChange($current, $related)) {
            if ($current) $this->disassociate($relation, $current);
            if ($related) $relation->save($related);
            $model->setRelation($this->relationName(), $related);
        }
    }

    /**
     * @inheritDoc
     */
    public function associate(Model $model, ?array $identifier): ?Model
    {
        $this->fill($model, $identifier);

        return $model->getRelation($this->relationName());
    }

    /**
     * @param Model|null $current
     * @param Model|null $new
     * @return bool
     */
    private function willChange(?Model $current, ?Model $new): bool
    {
        if ($current) {
            return $current->isNot($new);
        }

        return !!$new;
    }

    /**
     * Disassociate the model from the relationship.
     *
     * @param EloquentMorphOne|EloquentHasOne $relation
     * @param Model $current
     */
    private function disassociate(EloquentMorphOne|EloquentHasOne $relation, Model $current): void
    {
        if (self::KEEP_DETACHED_MODEL === $this->detachMode) {
            $this->setInverseToNull($relation, $current);
            return;
        }

        $this->deleteRelatedModel($current);
    }

    /**
     * Disassociate the related model by setting the relationship column(s) to `null`.
     *
     * @param EloquentMorphOne|EloquentHasOne $relation
     * @param Model $current
     * @return void
     */
    private function setInverseToNull(EloquentMorphOne|EloquentHasOne $relation, Model $current): void
    {
        if ($relation instanceof EloquentMorphOne) {
            $current->setAttribute($relation->getMorphType(), null);
        }

        $current->setAttribute($relation->getForeignKeyName(), null)->save();
    }

    /**
     * Disassociate the related model by deleting it.
     *
     * @param Model $related
     * @return void
     */
    private function deleteRelatedModel(Model $related): void
    {
        if (self::FORCE_DELETE_DETACHED_MODEL === $this->detachMode) {
            $related->forceDelete();
            return;
        }

        $related->delete();
    }
}
