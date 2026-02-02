<?php

namespace Orchestra\Canvas;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class Canvas
{
    /**
     * Make Preset from configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public static function preset(array $config, string $basePath): Presets\Preset
    {
        /** @var array<string, mixed> $configuration */
        $configuration = Arr::except($config, 'preset');

        /** @var string|class-string<\Orchestra\Canvas\Presets\Preset> $preset */
        $preset = $config['preset'] ?? 'laravel';

        /** @phpstan-ignore return.type */
        return match (true) {
            $preset === 'package' => new Presets\Package($configuration, $basePath),
            $preset === 'laravel' => new Presets\Laravel($configuration, $basePath),
            class_exists($preset) => new $preset($configuration, $basePath),
            default => throw new InvalidArgumentException(\sprintf('Unable to resolve %s preset', $preset)),
        };
    }
}
