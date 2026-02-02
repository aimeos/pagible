<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\QueryBuilder;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Core\Query\Custom\CountablePaths;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Eloquent\QueryBuilder\Aggregates\CountableLoader;
use LaravelJsonApi\Eloquent\QueryBuilder\EagerLoading\EagerLoader;
use LaravelJsonApi\Eloquent\Schema;

class ModelLoader
{

    /**
     * @var Container
     */
    private Container $schemas;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var Model|EloquentCollection
     */
    private $target;

    /**
     * ModelLoader constructor.
     *
     * @param Container $schemas
     * @param Schema $schema
     * @param EloquentCollection|Model $target
     */
    public function __construct(Container $schemas, Schema $schema, $target)
    {
        if (!$target instanceof Model && !$target instanceof EloquentCollection) {
            throw new InvalidArgumentException('Expecting a model or collection of models.');
        }

        $this->schemas = $schemas;
        $this->schema = $schema;
        $this->target = $target;
    }

    /**
     * Eager load relations using JSON:API include paths.
     *
     * @param $includePaths
     * @return $this
     */
    public function load($includePaths): self
    {
        $loader = new EagerLoader(
            $this->schemas,
            $this->schema,
            IncludePaths::cast($includePaths),
        );

        $this->target->load(
            $loader->getRelations()
        );

        foreach ($loader->getMorphs() as $relation => $map) {
            $this->target->loadMorph($relation, $map);
        }

        return $this;
    }

    /**
     * Eager load relations using JSON:API include paths, if they are not already loaded.
     *
     * @param $includePaths
     * @return $this
     */
    public function loadMissing($includePaths): self
    {
        $loader = new EagerLoader(
            $this->schemas,
            $this->schema,
            IncludePaths::cast($includePaths),
        );

        $this->target->loadMissing(
            $loader->getRelations()
        );

        foreach ($loader->getMorphs() as $relation => $map) {
            $this->target->loadMorph($relation, $map);
        }

        return $this;
    }

    /**
     * Eager load relation counts.
     *
     * @param $countable
     * @return $this
     */
    public function loadCount($countable): self
    {
        $paths = CountablePaths::cast($countable);

        if ($paths->isNotEmpty()) {
            $counter = new CountableLoader($this->schema, $paths);
            $this->target->loadCount($counter->getRelations());
        }

        return $this;
    }

}
