<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema;

use LaravelJsonApi\Core\Support\Str;
use function class_basename;

final class TypeResolver
{

    /**
     * @var array
     */
    private static array $cache = [];

    /**
     * Manually register the resource type to use for a schema class.
     *
     * @param string $schemaClass
     * @param string $resourceType
     * @return void
     */
    public static function register(string $schemaClass, string $resourceType): void
    {
        self::$cache[$schemaClass] = $resourceType;
    }

    /**
     * Resolve the JSON:API resource type from the fully-qualified schema class.
     *
     * @param string $schemaClass
     * @return string
     */
    public function __invoke(string $schemaClass): string
    {
        if (isset(self::$cache[$schemaClass])) {
            return self::$cache[$schemaClass];
        }

        return self::$cache[$schemaClass] = Str::plural(Str::dasherize(
            Str::replaceLast('Schema', '', class_basename($schemaClass))
        ));
    }
}
