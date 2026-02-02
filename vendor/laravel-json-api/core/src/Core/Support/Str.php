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

use Illuminate\Support\Str as IlluminateStr;
use function call_user_func_array;
use function str_replace;

/**
 * Class Str
 *
 * @mixin IlluminateStr
 */
final class Str
{

    /**
     * @var array
     */
    private static array $dasherized = [];

    /**
     * @var array
     */
    private static array $underscored = [];

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(IlluminateStr::class . '::' . $name, $arguments);
    }

    /**
     * Gets the lower camel case form of a string.
     *
     * @param string $value
     * @return string
     * @deprecated 1.0 use `Str::camel`.
     */
    public static function camelize(string $value): string
    {
        return IlluminateStr::camel($value);
    }

    /**
     * Replaces underscores or camel case with dashes.
     *
     * @param string $value
     * @return string
     */
    public static function dasherize(string $value): string
    {
        if (isset(self::$dasherized[$value])) {
            return self::$dasherized[$value];
        }

        return self::$dasherized[$value] = str_replace('_', '-', self::snake($value));
    }

    /**
     * Converts a camel case or dasherized string into a lower cased and underscored string.
     *
     * This differs from `Str::snake()` in that it will convert both camel-cased and
     * dasherized strings to snake case with a `_` delimiter.
     *
     * @param $value
     * @return string
     */
    public static function underscore(string $value): string
    {
        if (isset(self::$underscored[$value])) {
            return self::$underscored[$value];
        }

        return self::$underscored[$value] = str_replace('-', '_', self::snake($value));
    }

    /**
     * Gets the upper camel case form of a string.
     *
     * @param string $value
     * @return string
     */
    public static function classify(string $value): string
    {
        return IlluminateStr::studly($value);
    }
}
