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

use Illuminate\Support\Str;

class JsonApiValidation
{

    /**
     * The namespace for translations.
     *
     * @var string
     */
    public static string $translationNamespace = 'jsonapi-validation';

    /**
     * Indicates if Laravel validator failed data is added to JSON API error objects.
     *
     * @var bool
     */
    public static bool $validationFailures = false;

    /**
     * Set the translation namespace.
     *
     * @param string $namespace
     * @return static
     */
    public static function useTranslationNamespace(string $namespace): self
    {
        if (empty($namespace)) {
            throw new \InvalidArgumentException('Expecting a non-empty string.');
        }

        self::$translationNamespace = $namespace;

        return new self();
    }

    /**
     * Set JSON API errors for validators to show failed data in their meta member.
     *
     * @return static
     */
    public static function showValidatorFailures(): self
    {
        self::$validationFailures = true;

        return new self();
    }

    /**
     * Get the translation key for the supplied rule.
     *
     * @param string|object $rule
     * @param string|null $path
     * @return string
     */
    public static function translationKeyForRule($rule, ?string $path = null): string
    {
        $name = Str::snake(class_basename($rule));

        return self::qualifyTranslationKey($name, $path);
    }

    /**
     * Turn a package translation key into a fully qualified translation key.
     *
     * @param string $key
     * @param string|null $path
     * @return string
     */
    public static function qualifyTranslationKey(string $key, ?string $path = null): string
    {
        $namespace = self::$translationNamespace;
        $qualified = "{$namespace}::validation.{$key}";

        if ($path) {
            return "{$qualified}.$path";
        }

        return $qualified;
    }
}
