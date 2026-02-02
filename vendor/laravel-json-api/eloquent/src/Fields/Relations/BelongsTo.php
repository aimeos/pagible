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
use LaravelJsonApi\Eloquent\Contracts\FillableToOne;
use LaravelJsonApi\Eloquent\Fields\Concerns\IsReadOnly;

class BelongsTo extends ToOne implements FillableToOne
{
    use IsReadOnly;

    /**
     * Create a belongs-to relation.
     *
     * @param string $fieldName
     * @param string|null $relation
     * @return static
     */
    public static function make(string $fieldName, ?string $relation = null): BelongsTo
    {
        return new static($fieldName, $relation);
    }

    /**
     * BelongsTo constructor.
     *
     * @param string $fieldName
     * @param string|null $relation
     */
    public function __construct(string $fieldName, ?string $relation = null)
    {
        parent::__construct($fieldName, $relation);
        $this->mustValidate();
    }

    /**
     * @inheritDoc
     */
    public function mustExist(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, ?array $identifier): void
    {
        $name = $this->relationName();

        assert(method_exists($model, $name)  || $model->relationResolver($model::class, $name), sprintf(
            'Expecting method %s to exist on model %s.',
            $name,
            $model::class,
        ));

        $relation = $model->{$name}();

        if ($related = $this->find($identifier)) {
            assert(method_exists($relation, 'associate'), sprintf(
                'Expecting relation class %s to have an "associate" method.',
                $relation::class,
            ));
            $relation->associate($related);
            return;
        }

        assert(method_exists($relation, 'disassociate'), sprintf(
            'Expecting relation class %s to have a "disassociate" method.',
            $relation::class,
        ));

        $relation->disassociate();
    }

    /**
     * @inheritDoc
     */
    public function associate(Model $model, ?array $identifier): ?Model
    {
        $this->fill($model, $identifier);
        $model->save();

        return $model->getRelation($this->relationName());
    }
}
