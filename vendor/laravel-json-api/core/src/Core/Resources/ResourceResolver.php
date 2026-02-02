<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Resources;

use InvalidArgumentException;
use LaravelJsonApi\Core\Support\Str;
use function class_exists;

final class ResourceResolver
{

    /**
     * @var string
     */
    private static string $defaultResource = JsonApiResource::class;

    /**
     * @var array
     */
    private static array $cache = [];

    /**
     * Manually register the resource class to use for a resource class.
     *
     * @param string $schemaClass
     * @param string $resourceClass
     * @return void
     */
    public static function register(string $schemaClass, string $resourceClass): void
    {
        self::$cache[$schemaClass] = $resourceClass;
    }

    /**
     * Set the default resource class.
     *
     * @param string $resourceClass
     * @return void
     */
    public static function useDefault(string $resourceClass): void
    {
        if (class_exists($resourceClass)) {
            self::$defaultResource = $resourceClass;
            return;
        }

        throw new InvalidArgumentException('Expecting a default resource class that exists.');
    }

    /**
     * Get the default resource class.
     *
     * @return string
     */
    public static function defaultResource(): string
    {
        return self::$defaultResource;
    }

    /**
     * Resolve the fully-qualified resource class from the fully-qualified schema class.
     *
     * @param string $schemaClass
     * @return string
     */
    public function __invoke(string $schemaClass): string
    {
        if (isset(self::$cache[$schemaClass])) {
            return self::$cache[$schemaClass];
        }

        $guess = Str::replaceLast('Schema', 'Resource', $schemaClass);

        if (class_exists($guess)) {
            return self::$cache[$schemaClass] = $guess;
        }

        return self::$cache[$schemaClass] = self::$defaultResource;
    }
}
