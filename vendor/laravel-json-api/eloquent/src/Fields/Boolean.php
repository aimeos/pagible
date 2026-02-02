<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields;

class Boolean extends Attribute
{

    /**
     * Create a boolean attribute.
     *
     * @param string $fieldName
     * @param string|null $column
     * @return Boolean
     */
    public static function make(string $fieldName, ?string $column = null): self
    {
        return new self($fieldName, $column);
    }

    /**
     * @inheritDoc
     */
    protected function assertValue($value): void
    {
        if (!is_null($value) && !is_bool($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Expecting the value of attribute %s to be a boolean.',
                $this->name()
            ));
        }
    }
}
