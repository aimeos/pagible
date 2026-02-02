<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Support;

use Closure;
use Illuminate\Contracts\Container\Container;

class ContainerResolver
{
    /**
     * @var Closure
     */
    private Closure $resolver;

    /**
     * ContainerResolver constructor.
     *
     * @param Closure $resolver
     */
    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get the container instance.
     *
     * @return Container
     */
    public function instance(): Container
    {
        return ($this->resolver)();
    }
}
