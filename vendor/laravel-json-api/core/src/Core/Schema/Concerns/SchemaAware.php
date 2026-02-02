<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema\Concerns;

use LaravelJsonApi\Contracts\Schema\Container;
use LogicException;

trait SchemaAware
{

    /**
     * @var Container|null
     */
    private ?Container $schemas = null;

    /**
     * Set the container to use when looking up other schemas.
     *
     * @param Container $container
     * @return void
     */
    public function withSchemas(Container $container): void
    {
        if (!$this->schemas) {
            $this->schemas = $container;
            return;
        }

        throw new LogicException('Not expecting schema container to be changed.');
    }

    /**
     * @return Container
     */
    protected function schemas(): Container
    {
        if ($this->schemas) {
            return $this->schemas;
        }

        throw new LogicException('Expecting schemas to have access to their schema container.');
    }
}
