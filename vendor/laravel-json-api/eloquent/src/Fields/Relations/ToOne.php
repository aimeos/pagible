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
use LaravelJsonApi\Core\Document\ResourceIdentifier;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Contracts\Proxy;

abstract class ToOne extends Relation
{

    /**
     * @inheritDoc
     */
    public function toOne(): bool
    {
        return true;
    }

    /**
     * Parse a model for the relationship.
     *
     * @param Model|null $model
     * @return object|null
     */
    public function parse(?Model $model): ?object
    {
        if ($model) {
            return $this->schema()->parser()->parseOne($model);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    protected function guessInverse(): string
    {
        return Str::dasherize(
            Str::plural($this->relationName())
        );
    }

    /**
     * @param array|null $value
     * @return Model|null
     */
    protected function find(?array $value): ?Model
    {
        if (is_null($value)) {
            return null;
        }

        $identifier = ResourceIdentifier::fromArray($value);

        $this->assertInverseType($identifier->type());

        $model = $this
            ->schemas()
            ->schemaFor($identifier->type())
            ->repository()
            ->findOrFail($identifier->id());

        if ($model instanceof Proxy) {
            return $model->toBase();
        }

        return $model;
    }
}
