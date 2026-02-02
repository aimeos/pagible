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
use LaravelJsonApi\Core\Query\RelationshipPath;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Schema;
use LogicException;
use Traversable;

/**
 * Class EagerLoadPathList
 *
 * @internal
 */
class EagerLoadPathList implements IteratorAggregate
{

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var RelationshipPath
     */
    private RelationshipPath $path;

    /**
     * @var array|null
     */
    private ?array $paths = null;

    /**
     * EagerLoadPathList constructor.
     *
     * @param Schema $schema
     * @param RelationshipPath $path
     */
    public function __construct(Schema $schema, RelationshipPath $path)
    {
        $this->schema = $schema;
        $this->path = $path;
    }

    /**
     * Get the default eager load paths.
     *
     * @return iterable
     */
    public function defaults(): iterable
    {
        foreach ($this->cachedPaths() as $path) {
            foreach ($path->defaults() as $default) {
                yield $default;
            }
        }
    }

    /**
     * Get the eager load paths for the relationship path.
     *
     * @return array
     */
    public function paths(): iterable
    {
        foreach ($this->cachedPaths() as $path) {
            yield $path->toString();
        }
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        foreach ($this->defaults() as $default) {
            yield $default;
        }

        foreach ($this->paths() as $path) {
            yield $path;
        }
    }

    /**
     * Get the first relationship.
     *
     * @return Relation
     */
    private function relation(): Relation
    {
        $relation = $this->schema->relationship(
            $this->path->first()
        );

        if ($relation instanceof Relation) {
            return $relation;
        }

        throw new LogicException('Expecting an Eloquent relationship.');
    }

    /**
     * @return EagerLoadPath[]
     */
    private function cachedPaths(): array
    {
        if (is_array($this->paths)) {
            return $this->paths;
        }

        return $this->paths = $this->compute();
    }

    /**
     * Calculate the eager load paths for the provided relationship path.
     *
     * Due to polymorphic to-many relationships, one JSON:API include path
     * can be mapped to one or many Eloquent eager load paths.
     *
     * @return array
     */
    private function compute(): array
    {
        $paths = EagerLoadPath::make($this->relation());
        $terminated = [];

        if ($path = $this->path->skip(1)) {
            foreach ($path as $idx => $name) {
                $retain = [];
                foreach ($paths as $path) {
                    if (is_array($next = $path->next($name))) {
                        $retain = array_merge($retain, $next);
                        continue;
                    }

                    $terminated[] = $path;
                }

                $paths = $retain;
            }
        }

        return array_merge($paths, $terminated);
    }

}
