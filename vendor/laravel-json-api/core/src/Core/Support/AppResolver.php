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
use Illuminate\Contracts\Foundation\Application;

class AppResolver
{
    /**
     * @var Closure
     */
    private Closure $resolver;

    /**
     * AppResolver constructor.
     *
     * @param Closure $resolver
     */
    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get the application instance.
     *
     * @return Application
     */
    public function instance(): Application
    {
        return ($this->resolver)();
    }

    /**
     * Get a container resolver.
     *
     * @return ContainerResolver
     */
    public function container(): ContainerResolver
    {
        return new ContainerResolver($this->resolver);
    }
}
