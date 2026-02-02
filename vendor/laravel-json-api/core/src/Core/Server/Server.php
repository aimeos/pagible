<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Server;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Encoder\Encoder;
use LaravelJsonApi\Contracts\Encoder\Factory as EncoderFactory;
use LaravelJsonApi\Contracts\Resources\Container as ResourceContainerContract;
use LaravelJsonApi\Contracts\Schema\Container as SchemaContainerContract;
use LaravelJsonApi\Contracts\Server\Server as ServerContract;
use LaravelJsonApi\Contracts\Store\Store as StoreContract;
use LaravelJsonApi\Core\Document\JsonApi;
use LaravelJsonApi\Core\Resources\Container as ResourceContainer;
use LaravelJsonApi\Core\Resources\Factory as ResourceFactory;
use LaravelJsonApi\Core\Schema\Container as SchemaContainer;
use LaravelJsonApi\Core\Store\Store;
use LaravelJsonApi\Core\Support\AppResolver;
use LogicException;

abstract class Server implements ServerContract
{
    /**
     * The base URI for the server.
     *
     * @var string
     */
    protected string $baseUri = '';

    /**
     * @var AppResolver
     */
    private AppResolver $app;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var SchemaContainerContract|null
     */
    private ?SchemaContainerContract $schemas = null;

    /**
     * @var ResourceContainerContract|null
     */
    private ?ResourceContainerContract $resources = null;

    /**
     * Get the server's list of schemas.
     *
     * @return array
     */
    abstract protected function allSchemas(): array;

    /**
     * Server constructor.
     *
     * @param AppResolver $app
     * @param string $name
     */
    public function __construct(AppResolver $app, string $name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->app = $app;
        $this->name = $name;
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function jsonApi(): JsonApi
    {
        return new JsonApi('1.0');
    }

    /**
     * @inheritDoc
     */
    public function schemas(): SchemaContainerContract
    {
        if ($this->schemas) {
            return $this->schemas;
        }

        return $this->schemas = new SchemaContainer(
            $this->app->container(),
            $this,
            $this->allSchemas(),
        );
    }

    /**
     * @inheritDoc
     */
    public function resources(): ResourceContainerContract
    {
        if ($this->resources) {
            return $this->resources;
        }

        return $this->resources = new ResourceContainer(
            new ResourceFactory($this->schemas()),
        );
    }

    /**
     * @inheritDoc
     */
    public function store(): StoreContract
    {
        return new Store($this->schemas());
    }

    /**
     * @inheritDoc
     */
    public function encoder(): Encoder
    {
        /** @var EncoderFactory $factory */
        $factory = $this->app()->make(EncoderFactory::class);

        return $factory->build($this);
    }

    /**
     * @inheritDoc
     */
    public function authorizable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function url($extra = [], ?bool $secure = null): string
    {
        $tail = Collection::make(Arr::wrap($extra))
            ->map(fn($value) => ($value instanceof UrlRoutable) ? $value->getRouteKey() : $value)
            ->map(fn($value) => rawurlencode($value))
            ->implode('/');

        $path = $this->baseUri();

        if ($tail) {
            $path = rtrim($path, '/') . '/' . $tail;
        }

        return $this->app()->make(UrlGenerator::class)->to($path, [], $secure);
    }

    /**
     * The base URI namespace for this server.
     *
     * @return string
     */
    protected function baseUri(): string
    {
        if (!empty($this->baseUri)) {
            return $this->baseUri;
        }

        throw new LogicException('No base URI set on server.');
    }

    /**
     * Get the application instance.
     *
     * @return Application
     */
    protected function app(): Application
    {
        return $this->app->instance();
    }
}
