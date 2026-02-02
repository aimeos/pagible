<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx\Schema;

use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Resources\Container;
use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Encoder\Neomerx\Mapper;
use LogicException;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaInterface;

/**
 * Class SchemaContainer
 *
 * @internal
 */
final class SchemaContainer implements SchemaContainerInterface
{

    /**
     * @var Container
     */
    private Container $container;

    /**
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * @var SchemaFields
     */
    private SchemaFields $fields;

    /**
     * @var Request|null
     */
    private $request;

    /**
     * @var array
     */
    private array $schemas;

    /**
     * SchemaContainer constructor.
     *
     * @param Container $container
     * @param Mapper $mapper
     * @param SchemaFields $fields
     * @param Request|null $request
     */
    public function __construct(
        Container $container,
        Mapper $mapper,
        SchemaFields $fields,
        $request
    ) {
        $this->container = $container;
        $this->mapper = $mapper;
        $this->fields = $fields;
        $this->request = $request;
        $this->schemas = [];
    }

    /**
     * @param JsonApiResource $resourceObject
     * @return SchemaInterface
     */
    public function getSchema($resourceObject): SchemaInterface
    {
        if (!$resourceObject instanceof JsonApiResource) {
            throw new LogicException('Expecting a resource object.');
        }

        $type = $resourceObject->type();

        if (isset($this->schemas[$type])) {
            return $this->schemas[$type];
        }

        return $this->schemas[$type] = $this->createSchema($type);
    }

    /**
     * @param mixed $resourceObject
     * @return bool
     */
    public function hasSchema($resourceObject): bool
    {
        return $resourceObject instanceof JsonApiResource;
    }

    /**
     * @param string $type
     * @return SchemaInterface
     */
    private function createSchema(string $type): SchemaInterface
    {
        return new Schema(
            $this->container,
            $this->mapper,
            $this->fields,
            $type,
            $this->request,
        );
    }

}
