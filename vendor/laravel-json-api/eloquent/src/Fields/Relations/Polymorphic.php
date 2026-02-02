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
use LaravelJsonApi\Eloquent\Schema;
use LogicException;
use UnexpectedValueException;
use function get_class;
use function sprintf;

trait Polymorphic
{

    /**
     * Get the inverse schema for the provided model.
     *
     * @param Model $model
     * @return Schema
     */
    public function schemaFor(Model $model): Schema
    {
        $class = get_class($model);

        /** @var Schema $schema */
        foreach ($this->allSchemas() as $schema) {
            if ($schema->isModel($model)) {
                return $schema;
            }
        }

        throw new UnexpectedValueException(sprintf(
            'Model %s is not valid for polymorphic relation %s.',
            $class,
            $this->name()
        ));
    }

    /**
     * @return Generator
     */
    public function allSchemas(): Generator
    {
        foreach ($this->inverseTypes() as $type) {
            $schema = $this->schemas()->schemaFor($type);

            if ($schema instanceof Schema) {
                yield $type => $schema;
                continue;
            }

            throw new LogicException(sprintf(
                'Expecting schema for resource type %s to be an Eloquent schema.',
                $type
            ));
        }
    }
}
