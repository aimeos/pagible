<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

use InvalidArgumentException;

final class JsonApiSpec
{

    /**
     * The namespace for translations.
     *
     * @var string
     */
    public static string $translationNamespace = 'jsonapi-spec';

    /**
     * The specification class to use.
     *
     * @var string
     */
    public static string $specification = ServerSpecification::class;

    /**
     * Set the translation namespace.
     *
     * @param string $namespace
     * @return static
     */
    public static function useTranslationNamespace(string $namespace): self
    {
        if (empty($namespace)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        self::$translationNamespace = $namespace;

        return new self();
    }

    /**
     * Set the specification class to use.
     *
     * @param string $fqn
     * @return static
     */
    public static function useSpecification(string $fqn): self
    {
        if (empty($fqn)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        self::$specification = $fqn;

        return new self();
    }
}
