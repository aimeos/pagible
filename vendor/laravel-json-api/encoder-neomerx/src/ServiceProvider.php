<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use LaravelJsonApi\Contracts\Encoder\Factory as EncoderFactoryContract;
use LaravelJsonApi\Encoder\Neomerx\Factory as EncoderFactory;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;

class ServiceProvider extends BaseServiceProvider implements DeferrableProvider
{

    /**
     * @inheritDoc
     */
    public function provides(): array
    {
        return [
            EncoderFactoryContract::class,
            FactoryInterface::class,
        ];
    }

    /**
     * Bind package services into the service container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(EncoderFactoryContract::class, EncoderFactory::class);
        $this->app->bind(FactoryInterface::class, Factory\Factory::class);
    }
}
