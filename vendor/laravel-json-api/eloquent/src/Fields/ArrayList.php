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

use Illuminate\Support\Arr;
use function is_null;
use function sort;

class ArrayList extends Attribute
{

    /**
     * @var bool
     */
    private bool $sorted = false;

    /**
     * Create an array attribute.
     *
     * @param string $fieldName
     * @param string|null $column
     * @return ArrayList
     */
    public static function make(string $fieldName, ?string $column = null): self
    {
        return new self($fieldName, $column);
    }

    /**
     * Sort values when deserializing the array.
     *
     * @return $this
     */
    public function sorted(): self
    {
        $this->sorted = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function serialize(object $model)
    {
        $value = parent::serialize($model);

        if ($value && $this->sorted) {
            sort($value);
        }

        return $value ? array_values($value) : $value;
    }

    /**
     * @inheritDoc
     */
    protected function deserialize($value)
    {
        $value = parent::deserialize($value);

        if ($value && $this->sorted) {
            sort($value);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    protected function assertValue($value): void
    {
        if ((!is_null($value) && !is_array($value)) || (!empty($value) && Arr::isAssoc($value))) {
            throw new \UnexpectedValueException(sprintf(
                'Expecting the value of attribute %s to be an array list.',
                $this->name()
            ));
        }
    }

}
