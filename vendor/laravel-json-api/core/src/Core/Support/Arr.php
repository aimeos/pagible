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

use Illuminate\Support\Arr as IlluminateArr;
use function call_user_func_array;
use function collect;
use function is_iterable;
use function is_string;

/**
 * Class Arr
 *
 * @mixin IlluminateArr
 */
final class Arr
{

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(IlluminateArr::class . '::' . $name, $arguments);
    }

    /**
     * Recursively camel-case all keys in the provided array.
     *
     * @param iterable|null $data
     * @return array
     */
    public static function camelize(?iterable $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) {
            if (is_string($key)) {
                $key = Str::camelize($key);
            }

            if (is_iterable($value)) {
                return [$key => static::camelize($value)];
            }

            return [$key => $value];
        })->all();
    }

    /**
     * Recursively dash-case all keys in the provided array.
     *
     * @param iterable|null $data
     * @return array
     */
    public static function dasherize(?iterable $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) {
            if (is_string($key)) {
                $key = Str::dasherize($key);
            }

            if (is_iterable($value)) {
                return [$key => static::dasherize($value)];
            }

            return [$key => $value];
        })->all();
    }

    /**
     * Recursively convert camel-case keys to snake case.
     *
     * @param iterable|null $data
     * @param string $delimiter
     * @return array
     */
    public static function decamelize(?iterable $data, string $delimiter = '_'): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) use ($delimiter) {
            if (is_string($key)) {
                $key = Str::snake($key, $delimiter);
            }

            if (is_iterable($value)) {
                return [$key => static::decamelize($value)];
            }

            return [$key => $value];
        })->all();
    }

    /**
     * Recursively convert camel-case keys to underscore case.
     *
     * Alias for calling `Arr::decamelize` without a delimiter arguments.
     *
     * @param iterable|null $data
     * @return array
     */
    public static function underscore(?iterable $data): array
    {
        return collect($data)->mapWithKeys(function ($value, $key) {
            if (is_string($key)) {
                $key = Str::underscore($key);
            }

            if (is_iterable($value)) {
                return [$key => static::underscore($value)];
            }

            return [$key => $value];
        })->all();
    }
}
