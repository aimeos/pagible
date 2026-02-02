<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields\Relations;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Schema\PolymorphicRelation;
use LogicException;
use function sprintf;

class MorphTo extends BelongsTo implements PolymorphicRelation
{

    use Polymorphic;

    /**
     * @var string[]
     */
    private array $types = [];

    /**
     * Set the inverse resource types.
     *
     * @param string ...$types
     * @return $this
     */
    public function types(string ...$types): self
    {
        if (2 > count($types)) {
            throw new InvalidArgumentException('Expecting at least two resource types.');
        }

        $this->types = $types;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function inverseTypes(): array
    {
        if (empty($this->types)) {
            throw new LogicException(sprintf(
                'No inverse resource types have been set on morph-to relation %s.',
                $this->name()
            ));
        }

        return $this->types;
    }

    /**
     * @inheritDoc
     */
    public function parse(?Model $model): ?object
    {
        if ($model) {
            return $this->schemaFor($model)->parser()->parseOne(
                $model
            );
        }

        return null;
    }

}
