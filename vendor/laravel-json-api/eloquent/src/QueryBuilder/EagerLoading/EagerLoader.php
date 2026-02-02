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

use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Core\Query\RelationshipPath;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphTo;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Class EagerLoader
 *
 * @internal
 */
class EagerLoader
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
     * @var IncludePaths
     */
    private IncludePaths $paths;

    /**
     * EagerLoader constructor.
     *
     * @param Container $schemas
     * @param Schema $schema
     * @param IncludePaths|null $paths
     */
    public function __construct(Container $schemas, Schema $schema, ?IncludePaths $paths)
    {
        $this->schemas = $schemas;
        $this->schema = $schema;
        $this->paths = $paths ?? new IncludePaths();
    }

    /**
     * Get the eager load relationship paths.
     *
     * @return array
     */
    public function getRelations(): array
    {
        return EagerLoadIterator::make($this->schema, $this->paths)->all();
    }

    /**
     * Get the morph-to eager load paths.
     *
     * @return array
     */
    public function getMorphs(): array
    {
        return $this->paths
            ->collect()
            ->filter(fn($path) => $this->isMorph($path))
            ->groupBy(fn(RelationshipPath $path) => $path->first())
            ->map(fn($paths, $name) => $this->morphs($name, $paths)->all())
            ->all();
    }

    /**
     * Does the relationship path need to be treated as a morph map?
     *
     * We create morph maps for any path where the first item in the path
     * is a morph-to relation, and:
     *
     * 1. there is more than one relation in the path and it is an include
     * path; OR
     * 2. at least one of the inverse resource types has default eager load
     * paths.
     *
     * @param RelationshipPath $path
     * @return bool
     */
    private function isMorph(RelationshipPath $path): bool
    {
        if (!$this->schema->isRelationship($path->first())) {
            return false;
        }

        $relation = $this->schema->relationship($path->first());

        if (!$relation instanceof MorphTo) {
            return false;
        }

        if (1 < $path->count() && $relation->isIncludePath()) {
            return true;
        }

        /** @var Schema $schema */
        foreach ($relation->allSchemas() as $schema) {
            if (!empty($schema->with())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $fieldName
     * @param $paths
     * @return EagerLoadMorphs
     */
    private function morphs(string $fieldName, $paths): EagerLoadMorphs
    {
        /** @var MorphTo $relation */
        $relation = $this->schema->relationship($fieldName);

        return new EagerLoadMorphs(
            $this->schemas,
            $relation,
            IncludePaths::cast($paths)->skip(1)
        );
    }
}
