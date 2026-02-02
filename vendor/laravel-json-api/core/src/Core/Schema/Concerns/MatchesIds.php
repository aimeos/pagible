<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema\Concerns;

trait MatchesIds
{
    /**
     * @var string
     */
    private string $pattern = '[0-9]+';

    /**
     * @var string
     */
    private string $flags = 'iD';

    /**
     * Get the regex pattern for the ID field.
     *
     * @return string
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Mark the ID field as a UUID.
     *
     * @return $this
     */
    public function uuid(): static
    {
        return $this->matchAs('[\da-f]{8}-[\da-f]{4}-[\da-f]{4}-[\da-f]{4}-[\da-f]{12}');
    }

    /**
     * Mark the ID field as a ULID.
     *
     * @return $this
     */
    public function ulid(): static
    {
        return $this->matchAs('[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}');
    }

    /**
     * Set the pattern for the ID field.
     *
     * @param string $pattern
     * @return $this
     */
    public function matchAs(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    /**
     * Mark the ID as case-sensitive.
     *
     * @return $this
     */
    public function matchCase(): static
    {
        $this->flags = 'D';

        return $this;
    }

    /**
     * Does the value match the ID's pattern?
     *
     * @param string $resourceId
     * @return bool
     */
    public function match(string $resourceId): bool
    {
        return 1 === preg_match("/^{$this->pattern}$/{$this->flags}", $resourceId);
    }
}
