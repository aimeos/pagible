<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation\Rules;

use Illuminate\Contracts\Validation\Rule;
use LaravelJsonApi\Contracts\Schema\PolymorphicRelation;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Validation\JsonApiValidation;

class HasOne implements Rule
{

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var array|null
     */
    private ?array $types;

    /**
     * HasOne constructor.
     *
     * @param Schema $schema
     */
    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        if (!is_null($value) && !is_array($value)) {
            return false;
        }

        $relation = $this->schema->relationship($attribute);

        if ($relation instanceof PolymorphicRelation) {
            $this->types = $relation->inverseTypes();
        } else {
            $this->types = [$relation->inverse()];
        }

        return $this->accept($value);
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        return trans(JsonApiValidation::translationKeyForRule($this), [
            'types' => collect($this->types)->implode(', '),
        ]);
    }

    /**
     * Accept the data value.
     *
     * @param array|null $data
     * @return bool
     */
    protected function accept(?array $data): bool
    {
        if (is_null($data)) {
            return true;
        }

        return $this->acceptType($data);
    }

    /**
     * @param $data
     * @return bool
     */
    protected function acceptType($data): bool
    {
        return is_array($data) && collect($this->types)->containsStrict(
            $data['type'] ?? null
        );
    }

}
