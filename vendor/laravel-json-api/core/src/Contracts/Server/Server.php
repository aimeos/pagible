<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Contracts\Server;

use LaravelJsonApi\Contracts\Encoder\Encoder;
use LaravelJsonApi\Contracts\Resources\Container as ResourceContainer;
use LaravelJsonApi\Contracts\Schema\Container as SchemaContainer;
use LaravelJsonApi\Contracts\Store\Store;
use LaravelJsonApi\Core\Document\JsonApi;

interface Server
{

    /**
     * Get the server's name.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Get the server's JSON:API object.
     *
     * @return JsonApi
     */
    public function jsonApi(): JsonApi;

    /**
     * Get the server's schemas.
     *
     * @return SchemaContainer
     */
    public function schemas(): SchemaContainer;

    /**
     * Get the server's resources.
     *
     * @return ResourceContainer
     */
    public function resources(): ResourceContainer;

    /**
     * Get the server's store.
     *
     * @return Store
     */
    public function store(): Store;

    /**
     * Get the server's encoder.
     *
     * @return Encoder
     */
    public function encoder(): Encoder;

    /**
     * Determine if the server is authorizable.
     *
     * @return bool
     */
    public function authorizable(): bool;

    /**
     * Get a URL for the server.
     *
     * @param mixed|array $extra
     * @param bool|null $secure
     * @return string
     */
    public function url($extra = [], ?bool $secure = null): string;
}
