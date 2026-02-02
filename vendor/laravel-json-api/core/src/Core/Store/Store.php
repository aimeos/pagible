<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Store;

use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Contracts\Store\CreatesResources;
use LaravelJsonApi\Contracts\Store\DeletesResources;
use LaravelJsonApi\Contracts\Store\ModifiesToMany;
use LaravelJsonApi\Contracts\Store\ModifiesToOne;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\QueriesOne;
use LaravelJsonApi\Contracts\Store\QueriesToMany;
use LaravelJsonApi\Contracts\Store\QueriesToOne;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\Contracts\Store\QueryOneBuilder;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Contracts\Store\ResourceBuilder;
use LaravelJsonApi\Contracts\Store\Store as StoreContract;
use LaravelJsonApi\Contracts\Store\ToManyBuilder;
use LaravelJsonApi\Contracts\Store\ToOneBuilder;
use LaravelJsonApi\Contracts\Store\UpdatesResources;
use LogicException;
use RuntimeException;
use function sprintf;

class Store implements StoreContract
{

    /**
     * @var Container
     */
    private Container $schemas;

    /**
     * Store constructor.
     *
     * @param Container $schemas
     */
    public function __construct(Container $schemas)
    {
        $this->schemas = $schemas;
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceType, string $resourceId): ?object
    {
        if ($repository = $this->resources($resourceType)) {
            return $repository->find($resourceId);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(string $resourceType, string $resourceId): object
    {
        if ($repository = $this->resources($resourceType)) {
            return $repository->findOrFail($resourceId);
        }

        throw new RuntimeException(sprintf(
            'Resource type %s with id %s does not exist.',
            $resourceType,
            $resourceId,
        ));
    }

    /**
     * @inheritDoc
     */
    public function findMany(array $identifiers): iterable
    {
        return collect($identifiers)
            ->groupBy('type')
            ->flatMap(fn($ids, $type) => $this->findManyByType($type, $ids));
    }

    /**
     * @inheritDoc
     */
    public function exists(string $resourceType, string $resourceId): bool
    {
        if ($repository = $this->resources($resourceType)) {
            return $repository->exists($resourceId);
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function queryAll(string $resourceType): QueryManyBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof QueriesAll) {
            return new QueryAllHandler($repository->queryAll());
        }

        throw new LogicException("Querying all {$resourceType} resources is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function queryOne(string $resourceType, $modelOrResourceId): QueryOneBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof QueriesOne) {
            return $repository->queryOne($modelOrResourceId);
        }

        throw new LogicException("Querying one {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function queryToOne(string $resourceType, $modelOrResourceId, string $fieldName): QueryOneBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof QueriesToOne) {
            return $repository->queryToOne($modelOrResourceId, $fieldName);
        }

        throw new LogicException("Querying to-one relationships on a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function queryToMany(string $resourceType, $modelOrResourceId, string $fieldName): QueryManyBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof QueriesToMany) {
            return new QueryManyHandler(
                $repository->queryToMany($modelOrResourceId, $fieldName)
            );
        }

        throw new LogicException("Querying to-many relationships on a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function create(string $resourceType): ResourceBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof CreatesResources) {
            return $repository->create();
        }

        throw new LogicException("Creating a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function update(string $resourceType, $modelOrResourceId): ResourceBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof UpdatesResources) {
            return $repository->update($modelOrResourceId);
        }

        throw new LogicException("Updating a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function delete(string $resourceType, $modelOrResourceId): void
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof DeletesResources) {
            $repository->delete($modelOrResourceId);
            return;
        }

        throw new LogicException("Deleting a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function modifyToOne(string $resourceType, $modelOrResourceId, string $fieldName): ToOneBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof ModifiesToOne) {
            return $repository->modifyToOne($modelOrResourceId, $fieldName);
        }

        throw new LogicException("Modifying to-one relationships on a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function modifyToMany(string $resourceType, $modelOrResourceId, string $fieldName): ToManyBuilder
    {
        $repository = $this->resources($resourceType);

        if ($repository instanceof ModifiesToMany) {
            return $repository->modifyToMany($modelOrResourceId, $fieldName);
        }

        throw new LogicException("Modifying to-one relationships on a {$resourceType} resource is not supported.");
    }

    /**
     * @inheritDoc
     */
    public function resources(string $resourceType): ?Repository
    {
        return $this->schemas
            ->schemaFor($resourceType)
            ->repository();
    }

    /**
     * Find many resources by a resource type.
     *
     * @param string $resourceType
     * @param Collection $ids
     * @return Collection
     */
    private function findManyByType(string $resourceType, Collection $ids): Collection
    {
        if ($repository = $this->resources($resourceType)) {
            return collect($repository->findMany(
                $ids->pluck('id')->unique()->all()
            ));
        }

        return collect();
    }
}
