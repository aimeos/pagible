<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation;

use Illuminate\Support\Arr;
use LaravelJsonApi\Core\Facades\JsonApi;
use LaravelJsonApi\Validation\Rules\AllowedCountableFields;
use LaravelJsonApi\Validation\Rules\AllowedFieldSets;
use LaravelJsonApi\Validation\Rules\AllowedFilterParameters;
use LaravelJsonApi\Validation\Rules\AllowedIncludePaths;
use LaravelJsonApi\Validation\Rules\AllowedPageParameters;
use LaravelJsonApi\Validation\Rules\AllowedSortParameters;
use LaravelJsonApi\Validation\Rules\ClientId;
use LaravelJsonApi\Validation\Rules\DateTimeIso8601;
use LaravelJsonApi\Validation\Rules\HasMany;
use LaravelJsonApi\Validation\Rules\HasOne;
use LaravelJsonApi\Validation\Rules\JsonArray;
use LaravelJsonApi\Validation\Rules\JsonBoolean;
use LaravelJsonApi\Validation\Rules\JsonNumber;
use LaravelJsonApi\Validation\Rules\JsonObject;
use LaravelJsonApi\Validation\Rules\ParameterNotSupported;
use function is_null;

class Rule
{
    /**
     * Get a validation rule for an array.
     *
     * In JSON an array is always zero-indexed, which means it should decode
     * to an array list in PHP. This rule ensures the value is always an empty
     * array or a zero-indexed array list.
     *
     * @return JsonArray
     */
    public static function arr(): JsonArray
    {
        return new JsonArray();
    }

    /**
     * Get a validation rule for a boolean.
     *
     * This rule validates that the value is a boolean, which is what we expect a boolean
     * in JSON to be. This is different from the Laravel boolean rule, which is looser i.e.
     * it accepts strings and integers in addition to "real" booleans.
     *
     * This rule can also be used to validate booleans from query parameters, which are
     * always strings. Just call the `asString()` method on the rule to configure it to
     * validate a boolean string. This is better than the Laravel boolean rule, as that
     * does not accept the strings "true" and "false", whereas this rule does accept
     * those.
     *
     * @return JsonBoolean
     */
    public static function boolean(): JsonBoolean
    {
        return new JsonBoolean();
    }

    /**
     * Get a validation rule for a client-generated ID.
     *
     * @return ClientId
     */
    public static function clientId(): ClientId
    {
        return new ClientId(
            JsonApi::route()->schema()
        );
    }

    /**
     * Get a countable fields constraint builder instance.
     *
     * @param array|string|null $allowed
     * @return AllowedCountableFields
     */
    public static function countable($allowed = null): AllowedCountableFields
    {
        if (!is_null($allowed)) {
            return new AllowedCountableFields(
                Arr::wrap($allowed)
            );
        }

        $route = JsonApi::route();

        return AllowedCountableFields::make(
            $route->hasRelation() ? $route->inverse() : $route->schema()
        );
    }

    /**
     * Get a countable fields constraint builder instance for a morph-many relation.
     *
     * @return AllowedIncludePaths
     */
    public static function countableForPolymorph(): AllowedIncludePaths
    {
        return AllowedIncludePaths::morphMany(
            JsonApi::server()->schemas(),
            JsonApi::route()->relation(),
        );
    }

    /**
     * Get a date time ISO8601 validation rule instance.
     *
     * @return DateTimeIso8601
     */
    public static function dateTime(): DateTimeIso8601
    {
        return new DateTimeIso8601();
    }

    /**
     * Get a sparse field sets constraint builder instance.
     *
     * @param array|null $allowed
     * @return AllowedFieldSets
     */
    public static function fieldSets(?array $allowed = null): AllowedFieldSets
    {
        if (is_null($allowed)) {
            return AllowedFieldSets::make(
                JsonApi::server()->schemas()
            );
        }

        return new AllowedFieldSets($allowed);
    }

    /**
     * Get a filter parameter constraint builder instance.
     *
     * @param string|string[]|null $allowed
     * @return AllowedFilterParameters
     */
    public static function filter($allowed = null): AllowedFilterParameters
    {
        if (!is_null($allowed)) {
            return new AllowedFilterParameters(
                Arr::wrap($allowed)
            );
        }

        $route = JsonApi::route();

        if ($route->hasRelation()) {
            return AllowedFilterParameters::forFilters(
                ...$route->inverse()->filters(),
                ...$route->relation()->filters()
            );
        }

        return AllowedFilterParameters::make(
            $route->schema()
        );
    }

    /**
     * Get an include paths constraint builder instance.
     *
     * @param string|string[]|null $allowed
     * @return AllowedIncludePaths
     */
    public static function includePaths($allowed = null): AllowedIncludePaths
    {
        if (!is_null($allowed)) {
            return new AllowedIncludePaths(
                Arr::wrap($allowed)
            );
        }

        $route = JsonApi::route();

        return AllowedIncludePaths::make(
            $route->hasRelation() ? $route->inverse() : $route->schema()
        );
    }

    /**
     * Get an include paths constraint builder instance for a morph-many relation.
     *
     * @return AllowedIncludePaths
     */
    public static function includePathsForPolymorph(): AllowedIncludePaths
    {
        return AllowedIncludePaths::morphMany(
            JsonApi::server()->schemas(),
            JsonApi::route()->relation(),
        );
    }

    /**
     * Get a rule to validate integers in JSON.
     *
     * @return JsonNumber
     */
    public static function integer(): JsonNumber
    {
        return (new JsonNumber())->onlyIntegers();
    }

    /**
     * Get a not supported parameter rule instance.
     *
     * @param string|null $name
     * @return ParameterNotSupported
     */
    public static function notSupported(?string $name = null): ParameterNotSupported
    {
        return new ParameterNotSupported($name);
    }

    /**
     * Get a JSON number rule.
     *
     * This rule validates that the value is a number in JSON, i.e. an integer or a float.
     *
     * @return JsonNumber
     */
    public static function number(): JsonNumber
    {
        return new JsonNumber();
    }

    /**
     * Get a JSON object rule.
     *
     * @return JsonObject
     */
    public static function obj(): JsonObject
    {
        return new JsonObject();
    }

    /**
     * Get a page parameter constraint builder instance.
     *
     * @param string|string[]|null $allowed
     * @return AllowedPageParameters
     */
    public static function page($allowed = null): AllowedPageParameters
    {
        if (!is_null($allowed)) {
            return new AllowedPageParameters(
                Arr::wrap($allowed)
            );
        }

        $route = JsonApi::route();

        return AllowedPageParameters::make(
            $route->hasRelation() ? $route->inverse() : $route->schema()
        );
    }

    /**
     * Get a sort parameter constraint builder instance.
     *
     * @param string|string[]|null $allowed
     * @return AllowedSortParameters
     */
    public static function sort($allowed = null): AllowedSortParameters
    {
        if (!is_null($allowed)) {
            return new AllowedSortParameters(
                Arr::wrap($allowed)
            );
        }

        $route = JsonApi::route();

        return AllowedSortParameters::make(
            $route->hasRelation() ? $route->inverse() : $route->schema()
        );
    }

    /**
     * Get a validation rule instance for a to-many relation.
     *
     * @return HasMany
     */
    public static function toMany(): HasMany
    {
        return new HasMany(
            JsonApi::route()->schema()
        );
    }

    /**
     * Get a validation rule instance for a to-one relation.
     *
     * @return HasOne
     */
    public static function toOne(): HasOne
    {
        return new HasOne(
            JsonApi::route()->schema()
        );
    }
}
