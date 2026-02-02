<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Resources;

use LaravelJsonApi\Contracts\Resources\Factory as FactoryContract;
use LaravelJsonApi\Contracts\Schema\Container as SchemaContainer;
use LaravelJsonApi\Contracts\Schema\Schema;
use LogicException;
use Throwable;
use function get_class;
use function sprintf;

class Factory implements FactoryContract
{

    /**
     * @var SchemaContainer
     *
     */
    protected SchemaContainer $schemas;

    /**
     * Factory constructor.
     *
     * @param SchemaContainer $schemas
     */
    public function __construct(SchemaContainer $schemas)
    {
        $this->schemas = $schemas;
    }

    /**
     * @inheritDoc
     */
    public function canCreate(object $model): bool
    {
        return $this->schemas->existsForModel($model);
    }

    /**
     * @inheritDoc
     */
    public function createResource(object $model): JsonApiResource
    {
        try {
            return $this->build(
                $this->schemas->schemaForModel($model),
                $model,
            );
        } catch (Throwable $ex) {
            throw new LogicException(sprintf(
                'Failed to build a JSON:API resource for model %s.',
                get_class($model),
            ), 0, $ex);
        }
    }

    /**
     * Build a new resource object instance.
     *
     * @param Schema $schema
     * @param object $model
     * @return JsonApiResource
     */
    protected function build(Schema $schema, object $model): JsonApiResource
    {
        $fqn = $schema->resource();

        return new $fqn($schema, $model);
    }

}
