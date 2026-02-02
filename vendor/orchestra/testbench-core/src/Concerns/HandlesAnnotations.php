<?php

namespace Orchestra\Testbench\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Orchestra\Testbench\Attributes;

/**
 * @internal
 *
 * @deprecated
 *
 * @codeCoverageIgnore
 */
trait HandlesAnnotations
{
    /**
     * Parse test method annotations.
     *
     * @internal
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @param  string  $name
     */
    protected function parseTestMethodAnnotations($app, string $name, ?Closure $callback = null): void
    {
        /** @phpstan-ignore match.unhandled */
        $attribute = match ($name) {
            'environment-setup' => Attributes\DefineEnvironment::class,
            'define-env' => Attributes\DefineEnvironment::class,
            'define-db' => Attributes\DefineDatabase::class,
            'define-route' => Attributes\DefineRoute::class,
        };

        $this->resolvePhpUnitAnnotations()
            ->lazy()
            ->filter(static fn ($actions, string $key) => $key === $name && ! empty($actions))
            ->flatten()
            ->filter(fn ($method) => \is_string($method) && method_exists($this, $method))
            ->each($callback ?? function ($method) use ($app, $name, $attribute) {
                trigger_deprecation('orchestra/testbench-core', '9.12.0', 'Use #[%s] attribute instead of deprecated @%s annotation', $attribute, $name);

                $this->{$method}($app);
            });
    }

    /**
     * Resolve PHPUnit method annotations.
     *
     * @phpunit-overrides
     *
     * @return \Illuminate\Support\Collection<string, mixed>
     */
    abstract protected function resolvePhpUnitAnnotations(): Collection;
}
